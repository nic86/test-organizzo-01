<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */
vimport('~modules/SMSNotifier/SMSNotifier.php');

class SMSNotifier_Record_Model extends Vtiger_Record_Model
{

	public static function SendSMS($message, $toNumbers, $currentUserId, $recordIds, $moduleName)
	{
		SMSNotifier::sendsms($message, $toNumbers, $currentUserId, $recordIds, $moduleName);
	}

	public function checkStatus()
	{
		$statusDetails = SMSNotifier::getSMSStatusInfo($this->get('id'));
		$statusColor = $this->getColorForStatus($statusDetails[0]['status']);

		$data = array_merge($statusDetails[0], ['statuscolor' => $statusColor]);
		$this->setData($data);

		return $this;
	}

	public function getCheckStatusUrl()
	{
		return "index.php?module=" . $this->getModuleName() . "&view=CheckStatus&record=" . $this->getId();
	}

	public function getColorForStatus($smsStatus)
	{
		if ($smsStatus == 'Processing') {
			$statusColor = '#FFFCDF';
		} elseif ($smsStatus == 'Dispatched') {
			$statusColor = '#E8FFCF';
		} elseif ($smsStatus == 'Failed') {
			$statusColor = '#FFE2AF';
		} else {
			$statusColor = '#FFFFFF';
		}
		return $statusColor;
	}

	public function processFireSendSMSResponse($responses)
	{

		if (empty($responses))
			return;

		$adb = PearDatabase::getInstance();

		foreach ($responses as $response) {
			$responseID = '';
			$responseStatus = '';
			$responseStatusMessage = '';

			$needlookup = 1;
			if ($response['error']) {
				$responseStatus = ISMSProvider::MSG_STATUS_FAILED;
				$needlookup = 0;
			} else {
				$responseID = $response['id'];
				$responseStatus = $response['status'];
			}

			if (isset($response['statusmessage'])) {
				$responseStatusMessage = $response['statusmessage'];
			}
			$adb->pquery("INSERT INTO vtiger_smsnotifier_status(smsnotifierid,tonumber,status,statusmessage,smsmessageid,needlookup) VALUES(?,?,?,?,?,?)", array($this->get('id'), $response['to'], $responseStatus, $responseStatusMessage, $responseID, $needlookup)
			);
		}
		return true;
	}
}
