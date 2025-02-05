<?php

abstract class WSServer {

	public function __construct($host, $port, $logs = false) {

		//ip addr of server and port No.
		$this->host = $host;
		$this->port = $port;

		$this->ak = base64_encode(openssl_random_pseudo_bytes(5));

		//server socket
		$this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		//array of all the clients in the server
		$this->clients = array('server' => $this->server);

		$this->logs = $logs;
	}


	public function init() {

		//bind the socket to a host
		socket_bind($this->server, $this->host, $this->port);

		//allow the server to recieve multiple connections at once
		socket_set_nonblock($this->server);
	}


	//checks to see if a new client is an admin
	protected function checkADM($name) {

		$pattern = '~ADMIN K:'.$this->ak.'~';
		//checks to see if the admin key is matching the msg
		$check = preg_match($pattern, $name);

		if($check != false) {

			return true;
		} else {

			return false;
		}
	}


	//add data to the log file
	protected function addLog($text)  {

		if($this->logs){

			$message = file_get_contents('logs.txt').$text;
			file_put_contents('logs.txt', $message);
		}
	}


	protected function recvFrag($socket, $name, $deframe, $v = false) {

		$payload = $deframe->data;

		do {

			//gets each message and concatinates them together
			$frame = new WSdeframe($socket);
			$payload .= $frame->data;

			//verbose info
			if($v) {
				echo "continuos frame: \n";
				echo "fin: ".$frame->fin."\n";
				echo "opcode: ".$frame->opcode."\n";
				echo "length: ".$frame->length."\n";
				echo "length offset: ".$frame->lengthOffset."\n";
			}

			if($frame->fin == 1) {

				return $payload;
			}

		} while(true);
	}


	//recieves a message from a specified socket
	protected function recv($socket, $name, $v = false) {

		//gets and deframes the message from the client
		$deframe = new WSdeframe($socket);


		//verbose info
		if($v) {
			echo "recieved: \n";
			echo "fin: ".$deframe->fin."\n";
			echo "opcode: ".$deframe->opcode."\n";
			echo "length: ".$deframe->length."\n";
			echo "length offset: ".$deframe->lengthOffset."\n";
		}

		//recieves fragmented messages
		if($deframe->fin == 0 && $deframe->opcode == 1) {

			$message = $this->recvFrag($socket, $name, $deframe, $v);

			return $message;
		}


		//check the opcode that was sent
		switch($deframe->opcode) {

			case 1: //text opcode
				$this->removeConnection($socket, $name);

			case 2: //binary opcode
				echo "opcode: 2\n\n";
				return false;

			case 8: //close connection opcode
				return false;

			default: //opcode is unknown
				echo "opcode: ".$deframe->opcode."\n\n";
				return false;
		}
	}


	protected function send($msg, $sender) {

		//default false
		if($this->logs) {

			//writes the message data to the log file
			$this->addLog($msg);
		}

		$msg = $sender.$msg;

		//frames the message data
		$frame = new WSframe($msg, strlen($msg));
		$payload = $frame->frame();

		//sends the data to all connected clients besides the sender
		foreach($this->clients as $name => $socket) {
			if($name != 'server' && $name != $sender) {

				socket_write($socket, $payload, strlen($payload));
			}
		}
	}

	abstract protected function addConnection($connection);

	abstract protected function removeConnection($connection, $name);


	protected function handshake($request) {
		preg_match('~Sec-WebSocket-Key: ([\S]{24})~', $request, $match);

		if(isset($match[1])) {

			//creating the value of the accept header
			$accept = sha1(trim($match[1])."258EAFA5-E914-47DA-95CA-C5AB0DC85B11");

			$to = "";
			for($i = 0; $i < 20; $i++) {

				$to .= chr(hexdec(substr($accept, $i * 2, 2)));
			}

			$accept = base64_encode($to);

			$message = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Accept: ".$accept."\r\n\r\n";

			return $message;
		} else {

			return false;
		}
	}

	protected function sendLogs($connection)  {

		if($this->logs) {

			//get the content of the chat logs file
			$payload = file_get_contents('logs.txt');

			//frame the data
			$frame = new WSframe($payload, strlen($payload));
			$payload = $frame->frame();

			//send the chat logs to the new connection
			socket_write($connection, $payload, strlen($payload));
		}
	}

	abstract public function listen();
}

?>
