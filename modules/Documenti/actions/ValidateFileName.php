<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Documenti_ValidateFileName_Action extends Vtiger_IndexAjax_View
{

	public function checkPermission(Vtiger_Request $request)
	{
		return true;
	}

	public function process(Vtiger_Request $request)
	{
		$filename = $request->get('filename');
		$id = $request->get('record');
		$moduleName = $request->get('module');

		$response = new Vtiger_Response();

		/* $permitted = Users_Privileges_Model::isPermitted($sourceModule, 'DetailView', $id); */
		/* if ($permitted $$ !empty($id)) { */
		if (!empty($id)) {
			$recordModel = Vtiger_Record_Model::getInstanceById($id, $moduleName);
			$result = $recordModel->validateUploadedFileName($filename);
			if ($result) {
				$response->setResult([
					'success' => true
				]);
			} else {
				$response->setResult([
					'success' => false, 
					'message' => "Il file non è valido. Grazie"
				]);
			}
		} else {
			$response->setResult([
				'success' => false,
				'message' => vtranslate('LBL_RECORD_NOT_FOUND')
			]);
		}
		$response->emit();
	}
}
