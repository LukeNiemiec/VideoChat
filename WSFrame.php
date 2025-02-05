<?php


class WSdeframe {
	public function __construct($socket) {

		$this->socket = $socket;

		$this->fin = null;
		$this->rsv2 = 0;
		$this->rsv3 = 0;
		$this->rsv4 = 0;
		$this->opcode = null;

		$this->mask = null;
		$this->length = 0;

		$this->lengthOffset = 0;

		$this->maskKeys = array();

		$this->content = "";
		$this->data = "";

		$this->deframe();

	}


	public function deframe() {
		$this->getFinAndOpcode();

		$this->getMaskAndLength();

		$this->getMaskKeys();

		$this->getContent();

		$this->unmask();
	}

	protected function getFinAndOpcode() {
		$byte = socket_read($this->socket, 1);

		if(ord($byte) >= 128) {

			$this->fin = 1;
			$this->opcode = ord($byte) - 128;
		} else {

			$this->fin = 0;
			$this->opcode = ord($byte);
		}
	}

	protected function getMaskAndLength() {
		$byte = socket_read($this->socket, 1);

		if(ord($byte) > 128) {

			$this->length = ord($byte) - 128;
			$this->mask = 1;
		} else {

			$this->length = ord($byte);
			$this->mask = 0;
		}

		if($this->length <= 125) {

			$this->lengthOffset = 1;
		} elseif($this->length == 126) {
			$len = socket_read($this->socket, 2);

			$len = unpack("n", $len);

			$this->length = $len[1];
			$this->lengthOffset = 3;
		} else {

			$len = socket_read($this->socket, 8);

			$len = unpack("J", $len);

			$this->length =	$len[1];
			$this->lengthOffset = 9;
		}
	}

	protected function getMaskKeys() {
		$mask = socket_read($this->socket, 4);

		$this->maskKeys = array(
			ord($mask[0]),
			ord($mask[1]),
			ord($mask[2]),
			ord($mask[3])
		);
	}

	protected function getContent() {
		do {
			$readCount = $this->length - strlen($this->content);

			$this->content .= socket_read($this->socket, $readCount);

		} while(strlen($this->content) != $this->length);

		$this->content = str_split($this->content);
	}

	protected function unmask() {

		foreach($this->content as $i => $character) {

			$character = ord($character) ^ $this->maskKeys[$i % 4];

			$this->data .= chr($character);
		}
	}
}





class WSframe {

	public function __construct($message, $length, $fin = "1", $opcode = 1, $mask = "0") {

		$this->payload = null;
		$this->message = $message;

		$this->fin = $fin;
		$this->rsv2 = 0;
		$this->rsv3 = 0;
		$this->rsv4 = 0;
		$this->opcode = str_pad(decbin($opcode), 4, "0", STR_PAD_LEFT);

		$this->mask = $mask;
		$this->length = $length;

		$this->lengthBytes = 7;

		$this->maskKeys = "";
		$this->content = "";
	}


	public function frame() {

		$this->setLength();

		$this->payload = chr(bindec($this->fin . $this->rsv2 . $this->rsv3 . $this->rsv4 . $this->opcode));
		$this->payload .= $this->length;

		if($this->mask) {

			$this->setMaskKeys();
			$this->payload .= $this->maskKeys;
		}

		$this->mask();

		$this->payload .= $this->content;

		return $this->payload;
	}

	protected function setLength() {
		if($this->length <= 125) {

			$this->length = chr(bindec($this->mask . str_pad(decbin($this->length), $this->lengthBytes, "0", STR_PAD_LEFT)));
			//$this->length = str_pad($this->length, 2, "0", STR_PAD_LEFT);
		} else {
			if($this->length <= 65535) {

				$this->lengthBytes = 16;
				$prefix = chr(bindec($this->mask.'1111110'));
			} else {

				$this->lengthBytes = 64;
				$prefix = chr(bindec($this->mask.'1111111'));
			}

			$len = str_pad(decbin($this->length), $this->lengthBytes, "0", STR_PAD_LEFT);
			preg_match_all('~.{8}~', $len, $bytes);

			$this->length = $prefix;

			foreach($bytes[0] as $byte) {

				$this->length .= chr(bindec($byte));
			}
		}
	}

	protected function setMaskKeys() {
		$num = decbin(rand(0, 4294967295));
		$num = str_pad($num, 32, "0", STR_PAD_LEFT);
		preg_match_all('~.{8}~', $num, $keys);

		foreach($keys[0] as $key) {
			$this->maskKeys .= chr(bindec($key));
		}
	}

	protected function mask() {
		for($i = 0; $i < strlen($this->message); $i++) {
			if($this->mask) {
				$this->content .= chr(bindec(ord($this->message[$i]) ^ ord($this->maskKeys[$i % 4])));
			} else {

				$this->content .= $this->message[$i];
			}
		}
	}
}

?>
