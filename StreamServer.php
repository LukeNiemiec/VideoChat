<?php

include("WSServerTemp.php");
include("wsframe.php");

class WSStreamServer extends WSServer {


	protected function addConnection($connection) {

		$request = socket_read($connection, 1024);

		$response = $this->handshake($request);

		if($response != false) {

			socket_write($connection, $response, strlen($response));

			$name = base64_encode(random_bytes(4));

			$msg = implode(",", array_keys($this->clients));
			$msg = new WSframe($msg, strlen($msg));
			$msg = $msg->frame();

			socket_write($connection, $msg, strlen($msg));

			$this->clients[$name] = $connection;

			echo "client ".$name." connected to the server \n\n";

			echo strlen($name);

			$msg = "connect";
			$this->send($msg, $name);

			return true;
		} return false;
	}


	protected function removeConnection($connection, $name) {

		echo "client $name has disconnected\n\n";
		unset($this->clients[$name]);
		@socket_close($connection);

		//send the disconnection message to all othe users
		$msg = "disconnect";
		$this->send($msg, $name);
	}


	public function listen() {

		//initialize server
		$this->init();

		//server starts listening for incoming connections
		socket_listen($this->server);
		echo "listening on ".$this->host.":".$this->port."\n";

		echo "ADMIN key: ".$this->ak."\n\n";

		do {
			$queue = $this->clients;

			$w = $e = null;

			/*this line of code changes the queue to only have clients
			that have "changed", this can be an incoming connection or
			an incomming message*/
			if(socket_select($queue, $w, $e, 0) != false) {

				/*since the server isnt making messages to itself, when
				It is still in the queue after "socket_select", it means that
				a new client wants to establish a connection to the server*/
				if(isset($queue['server'])) {

					//accepts the socket's connection
					$connection = socket_accept($this->server);

					/*this method establishes a websocket connection for what
					otherwise would be a regular TCP connection by recieving headers
					and sending response headers*/
					if($this->addConnection($connection) == false) {

						echo "client couldnt connect to server \n\n";
					}

					unset($queue['server']);
				}

				//checks if any clients are sending messages to the server
				if(count($queue) != 0) {
					foreach($queue as $name => $client) {

						/*recieves message from the client, deframes the
						message and if they are valid messages that are framed
						correctly, they will be sent to other clients connected*/
						$message = $this->recv($client, $name, false);

						if($message !== false) {

								/*frames and sends the message to all of the clients except
								the server and the client that sent the message*/
								$this->send($message, $name);
						} else {

							$this->removeConnection($client, $name);
						}
					}
				}

			}
		} while (true);
	}


}


$server = new WSStreamServer('0.0.0.0', 12322);
$server->listen();


?>
