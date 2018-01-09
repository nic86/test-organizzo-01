<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Documenti_NewRelease_Action extends Vtiger_Action_Controller
{

	/**
	 * @var Vtiger_Record_Model 
	 */
	protected $record = false;

	public function checkPermission(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$record = $request->get('record');

		if (!empty($record)) {
			$recordModel = $this->record ? $this->record : Vtiger_Record_Model::getInstanceById($record, $moduleName);
			if (!$recordModel->permissionNewRelease()) {
				throw new \Exception\NoPermittedToRecord('LBL_PERMISSION_DENIED');
			}
		} else {
			$recordModel = Vtiger_Record_Model::getCleanInstance($moduleName);
			if (!$recordModel->isCreateable()) {
				throw new \Exception\NoPermittedToRecord('LBL_PERMISSION_DENIED');
			}
		}
	}

	public function process(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$record = $request->get('record');
		$recordModel = Vtiger_Record_Model::getInstanceById($record, $moduleName);
		$loadUrl = $recordModel->newRelease();
		header("Location: $loadUrl");
	}


	public function getDuplicate($record, $moduleName, $relationRecord = false, $relationModuleName =false)
	{
		$recordModel = Vtiger_Record_Model::getInstanceById($record, $moduleName);
		$duplicateRecordModel = Vtiger_Record_Model::getCleanInstance($moduleName);
		//While Duplicating record, If the related record is deleted then we are removing related record info in record model
		$fieldsModels = $recordModel->getModule()->getFields();
		foreach ($fieldsModels as $fieldModel) {
			if (!$fieldModel->isWritable()) {
				continue;
			}
			$fieldName = $fieldModel->get('name');

			$duplicateRecordModel->set($fieldName, $recordModel->get($fieldName));
			if ($fieldModel->isReferenceField()) {
				if(!empty($relationRecord)) {
					$referenceList = $fieldModel->getReferenceList();
					if (!empty($referenceList)) {
						if (in_array($relationModuleName, $referenceList)) {
							$duplicateRecordModel->set($fieldName, $relationRecord);
						}
					}
				}
				if (!\App\Record::isExists($recordModel->get($fieldName))) {
					$duplicateRecordModel->set($fieldName, '');
				}
			}
		}
		return $duplicateRecordModel;
	}

	public function validateRequest(Vtiger_Request $request)
	{
		return $request->validateWriteAccess(true);
	}
}
