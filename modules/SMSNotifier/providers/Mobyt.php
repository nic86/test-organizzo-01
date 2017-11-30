<?php
/* +**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * ********************************************************************************** */

class SMSNotifier_Mobyt_Provider implements SMSNotifier_ISMSProvider_Model
{
	private $userName;
	private $password;
	private $parameters = array();

 	const SERVICE_URI = 'http://smsin.simplesolutions.it';
 	
 	
	private static $REQUIRED_PARAMETERS = array('qty','operation');

	/**
	 * Function to get provider name
	 * @return <String> provider name
	 */
	public function getName()
	{
		return 'Mobyt';
	}

	/**
	 * Function to get required parameters other than (userName, password)
	 * @return <array> required parameters list
	 */
	public function getRequiredParams()
	{
		return self::$REQUIRED_PARAMETERS;
	}

	/**
	 * Function to get service URL to use for a given type
	 * @param <String> $type like SEND, PING, QUERY
	 */
	public function getServiceURL($type = false)
	{
		if ($type) {
			switch (strtoupper($type)) {
				case self::SERVICE_AUTH: return self::SERVICE_URI . '/http/auth';
				case self::SERVICE_SEND: return self::SERVICE_URI . '/sms/send.php';
				case self::SERVICE_QUERY: return self::SERVICE_URI . '/sms/batch-status.php';
			}
		}
		return false;
	}

	/**
	 * Function to set authentication parameters
	 * @param <String> $userName
	 * @param <String> $password
	 */
	public function setAuthParameters($userName, $password)
	{
		$this->userName = $userName;
		$this->password = $password;
	}

	/**
	 * Function to set non-auth parameter.
	 * @param <String> $key
	 * @param <String> $value
	 */
	public function setParameter($key, $value)
	{
		$this->parameters[$key] = $value;
	}

	/**
	 * Function to get parameter value
	 * @param <String> $key
	 * @param <String> $defaultValue
	 * @return <String> value/$default value
	 */
	public function getParameter($key, $defaultValue = false)
	{
		if (isset($this->parameters[$key])) {
			return $this->parameters[$key];
		}
		return $defaultValue;
	}

	/**
	 * Function to prepare parameters
	 * @return <Array> parameters
	 */
	protected function prepareParameters()
	{
		$params = array('user' => $this->userName, 'pass' => $this->password);
		foreach (self::$REQUIRED_PARAMETERS as $key) {
			$params[$key] = $this->getParameter($key);
		}
		return $params;
	}

	/**
	 * Function to handle SMS Send operation
	 * @param <String> $message
	 * @param <Mixed> $toNumbers One or Array of numbers
	 */
	public function send($message, $toNumbers)
	{
		if (empty($toNumbers)) {
			return;
		}

		$newNumbers = array();
		foreach($toNumbers as $number)
		{
			if(!$this->startsWith($number,'0039') && !$this->startsWith($number,'+39'))
 			{
				$newNumbers[]="+39{$number}";
			}
			else
			{
				$newNumbers[]=$number;
			}
		}

		$toNumbersString = implode(",",$newNumbers);
		$params = $this->prepareParameters();
		$params['data'] = $message;
        $params['rcpt'] = $toNumbersString;
        $params['sender']='SENDER';
        $params['return_id']='1';

		$serviceURL = $this->getServiceURL(self::SERVICE_SEND);
		$patch = $serviceURL . '?'. http_build_query($params);
		$responseLines = Requests::post($patch);

		//$httpClient = new Vtiger_Net_Client($serviceURL);
		//$response = $httpClient->doPost($params);
		$responseLines = explode("\n", $responseLines->raw);

		$results = array();
		$i=0;
		foreach ($responseLines as $responseLine) {
			$responseLine = trim($responseLine);
			if (empty($responseLine))
				continue;
			$result = [];
			if (preg_match("/KO (.*)/", $responseLine, $matches)) {
				$result['error'] = true;
				$result['to'] = $toNumbers[$i++];
				$result['statusmessage'] = $matches[1]; // Complete error message
			} else if (preg_match("/OK HTTP(.*)/", $responseLine, $matches)) {
				$id=trim($matches[1]);
				$id=ltrim($id, '0');
				$result['id'] = $id;
				$result['to'] = $toNumbers[$i++];
				$result['status'] = self::MSG_STATUS_PROCESSING;
			}
			if(count($result)) {
				$results[] = $result;
			}
		}
		return $results;
	}

	public function startsWith($haystack, $needle) {
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
   }

	/**
	 * Function to get query for status using messgae id
	 * @param <Number> $messageId
	 */
	public function query($messageId)
	{
		$params = $this->prepareParameters();
		$params['id'] = $messageId;
		$params['type'] = 'queue';
		$params['schema'] = '1';
		
		$serviceURL = $this->getServiceURL(self::SERVICE_QUERY);
		$httpClient = new Vtiger_Net_Client($serviceURL);
		$response = $httpClient->doPost($params);
		$response = trim($response);

		$result = array('error' => false, 'needlookup' => 1);

		if (preg_match("/KO (.*)/", $response, $matches)) {
			$result['error'] = true;
			$result['needlookup'] = 0;
			$result['statusmessage'] = $matches[1];
		} else if (preg_match("/OK HTTP(.*)/", $response, $matches)) {
			$result['id'] = trim($matches[1]);
			$status = trim($matches[2]);
			
			// Capture the status code as message by default.
			$result['statusmessage'] = "CODE: $status";
			if ($status === '1') {
				$result['status'] = self::MSG_STATUS_PROCESSING;
			} else if ($status === '2') {
				$result['status'] = self::MSG_STATUS_DISPATCHED;
				$result['needlookup'] = 0;
			}
		}
		return $result;
	}
}

?>
