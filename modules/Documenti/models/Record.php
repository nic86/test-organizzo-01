<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Documenti_Record_Model extends Vtiger_Record_Model
{

	/**
	 * Function to get Image Details
	 * @return <array> Image Details List
	 */
	public function getDocDetails()
	{
		$db = App\Db::getInstance();
		$docDetails = [];
		$recordId = $this->getId();

		if ($recordId) {
			$docId = $this->getId();
			$docPath = $this->get('docpath');
			$docName = $this->get('docfilename');

			//decode_html - added to handle UTF-8 characters in file names
			$docOriginalName = decode_html($docName);

			if (!empty($docName)) {
				$docDetails = [
					'id' => $docId,
					'orgname' => $docOriginalName,
					'path' => $docPath . DIRECTORY_SEPARATOR . $docName,
					'name' => $docName,
					'type' => $this->get('doctype')
				];
			}
		}
		return $docDetails;
	}

	/**
	 * The function decide about mandatory save record
	 * @return type
	 */
	public function isMandatorySave()
	{
		return $_FILES ? true : false;
	}

	/**
	 * Function to save data to database
	 */
	public function saveToDb()
	{
		parent::saveToDb();
		$this->insertAttachment();
	}

	/**
	 * This function is used to add the vtiger_attachments. This will call the function uploadAndSaveFile which will upload the attachment into the server and save that attachment information in the database.
	 */
	public function insertAttachment()
	{
		$id = $this->getId();
		$moduleName = $this->getModuleName();

		//Verifico se esiste gia` un documento allegato
		$fileName = (new App\Db\Query())
				->select(['vtiger_documenti.docfilename'])->from('vtiger_documenti')
				->where(['vtiger_documenti.documentiid' => $id])->scalar();

		if (isset($_FILES['docfilename'])) {
			$file = $_FILES['docfilename'];

			if (empty($file['tmp_name'])) {
				return false;
			}
			$fileInstance = \App\Fields\File::loadFromRequest($file);
			if (!$fileInstance->validate()) {
				return false;
			}

			$commessa = !empty($this->get('doccommessa')) ? $this->get('doccommessa') : 'Condivisa';
			$configDocPath = !empty(AppConfig::module($moduleName, 'DOC_PATH')) ? AppConfig::module($moduleName, 'DOC_PATH') : 'storage/Documenti';
			$uploadFilePath = ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $configDocPath. DIRECTORY_SEPARATOR . $commessa;
			if (!is_dir($uploadFilePath)) { //create new folder
				if(!mkdir($uploadFilePath, 0744, true)) {
					return false;
				}
			}

			if (empty($fileName)) {
				$fileName           = trim(App\Purifier::purify($fileInstance->name));
				$fileNameWithoutExt = trim(App\Purifier::purify($fileInstance->getNameWithoutExtension()));
				$extension          = pathinfo($fileName, PATHINFO_EXTENSION);
				$count = 0;
				while (file_exists($uploadFilePath . DIRECTORY_SEPARATOR. $fileName)) {
				    $count              = $count + 1;
				    $fileName           = "{$fileNameWithoutExt}_{$count}.{$extension}";
				}
				if ($fileInstance->moveFile($uploadFilePath . DIRECTORY_SEPARATOR. $fileName)) {
					$db = \App\Db::getInstance();
					$db->createCommand()->update('vtiger_documenti', [
						'docnome' => $fileNameWithoutExt,
						'docfilename' => $fileName,
						'doctype' => $file['type'],
						'docpath' => $uploadFilePath,
						'docsize' => $file['size'],
						'docinuso' => 0
					], ['documentiid' => $id])->execute();
				} else {
					\App\Log::error('Error on the save attachment process.');
					return false;
				}
			} else {
				if ($fileInstance->moveFile($uploadFilePath . DIRECTORY_SEPARATOR. $fileName)) {
					$db = \App\Db::getInstance();
					$db->createCommand()->update('vtiger_documenti', [
						'docsize' => $file['size'],
						'doctype' => $file['type'],
						'docinuso' => 0
					], ['documentiid' => $id])->execute();

				} else {
					\App\Log::error('Error on the save attachment process.');
					return false;
				}
			}
			return true;
		}
	}

	public function validateFileName($uploadedFilename)
	{
		$version = $this->get('version');
		$filename = $this->get('filename');
		$id = $this->getId();
		$inUso = $this->get('docinuso');
		/* list($file['version'],$file['id_record'],$file['original_name']) = explode('_',$fileName,3); */

		$validFilename = "{$id}_{$filename}_{$version}";
		if($uploadedFilename === $validFilename && $inUso) {
			return true;
		}
		return false;
	}
}
