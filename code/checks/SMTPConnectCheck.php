<?php
/**
 * Checks if the SMTP connection configured through PHP.ini works as expected.
 * Only checks socket connection with HELO command, not actually sending the email.
 */
class SMTPConnectCheck implements EnvironmentCheck {

	/**
	 * @var String
	 */
	protected $host;

	/**
	 * @var Int
	 */
	protected $port;

	/**
	 * @var Int In seconds
	 */
	protected $timeout;

	/**
	 * @param String
	 * @param Int
	 */
	function __construct($host = null, $port = null, $timeout = 15) {
		$this->host = ($host) ? $host : ini_get('SMTP');
		if(!$this->host) $this->host = 'localhost';
		
		$this->port = ($port) ? $port : ini_get('smtp_port');
		if(!$this->port) $this->port = 25;

		$this->timeout = $timeout;
	}

	function check() {
		$f = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
		if(!$f) {
			return array(
				EnvironmentCheck::ERROR, 
				sprintf("Couldn't connect to SMTP on %s:%s (Error: %s %s)", $this->host, $this->port, $errno, $errstr)
			);
		}

		fwrite($f, "HELO its_me\r\n");
		$response = fread($f, 26);
		if(substr($response, 0, 3) != '220') {
			return array(
				EnvironmentCheck::ERROR,
				sprintf("Invalid mail server response: %s", $response)
			);
		}

		return array(EnvironmentCheck::OK, '');

	}
}