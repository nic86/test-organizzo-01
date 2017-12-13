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
	public function getDownloadFileURL()
	{
		return 'file.php?module=' . $this->getModuleName() . '&action=DownloadFile&record=' . $this->getId();
	}

	public function checkFileIntegrity()
	{
		$filePath = $this->get('docpath');
		$fileName = $this->get('docfilename');

		if (!empty($filePath) && !empty($fileName)) {
			$savedFile = $filePath . DIRECTORY_SEPARATOR . $fileName;
			if (is_readable($savedFile)) {
				return true;
			}
		}
		return false;
	}

	public function permissionDownload()
	{
		$inUso = $this->get('docinuso');
		$share = $this->get('docshare');
		$assignedUserId = $this->get('assigned_user_id');
		$currentUserId = \App\User::getCurrentUserId();

		/* if ($currentUserModel->isAdmin()) { */
		/* 	return true; */
		/* } */

		if($assignedUserId == $currentUserId) {
			return true;
		} elseif ($inUso && $share) {
			return true;
		} elseif (!$inUso) {
			return true;
		}

		return false;
	}

	public function getDownloadFileName()
	{
		$version = $this->get('docversione');
		$fileName = $this->get('docfilename');
		$id = $this->getId();
		$md5Part = crc32("{$version}_{$id}");
		return "{$md5Part}_{$fileName}";
	}

	public function validateUploadedFileName($uploadedFileName)
	{
		$version = $this->get('docversione');
		$id = $this->getId();
		list($file['md5'],$file['original_name']) = explode('_',$uploadedFileName,2);

		$md5Part = crc32("{$version}_{$id}");

		if($file['md5'] == $md5Part ) {
			return true;
		}

		return false;
	}

	public function downloadFile()
	{
		if (!$this->checkFileIntegrity()) {
			return false;
		}

		if (!$this->permissionDownload()) {
			return false;
		}

		$filePath = $this->get('docpath');
		$fileName = $this->get('docfilename');
		$savedFile = $filePath . DIRECTORY_SEPARATOR . $fileName;
		$fileSize = filesize($savedFile);
		$fileSize = $fileSize + ($fileSize % 1024);

		$fileContent = fread(fopen($savedFile, "r"), $fileSize);
		if (!empty($fileContent)) {
			$result = \App\Db::getInstance()->createCommand()->update(
				'vtiger_documenti', [
				'docinuso' => 1
				], ['documentiid' => $this->getId()]
			)->execute();

			$downloadFileName = $this->getDownloadFileName();
			header("Content-type: " . $this->get('type'));
			header("Pragma: public");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"$downloadFileName\"");
			header("Content-Description: PHP Generated Data");
			echo $fileContent;
		} else {
			return false;
		}
	}

	/**
	 * Function to get Image Details
	 * @return <array> Image Details List
	 */
	public function getDocDetails()
	{
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
		$this->addDocument();
		parent::saveToDb();
	}

	/**
	 * This function is used to add the vtiger_attachments. This will call the function uploadAndSaveFile which will upload the attachment into the server and save that attachment information in the database.
	 */
	public function addDocument()
	{
		$id = $this->getId();
		$moduleName = $this->getModuleName();

		if (!$this->isNew) {
			$commessaPrec = $this->getPreviousValue('doccommessa');
			if($commessaPrec !== false) {
				unset($this->changes['doccommessa']);
			}
		}
		if (isset($_FILES['docfilename'])) {
			$file = $_FILES['docfilename'];

			if (empty($file['tmp_name'])) {
				unset($this->changes['docfilename']);
				return false;
			}
			$fileInstance = \App\Fields\File::loadFromRequest($file);
			if (!$fileInstance->validate()) {
				unset($this->changes['docfilename']);
				return false;
			}

			$commessaId = $this->get('doccommessa');
			if (!empty($commessaId)) {
				$commessa = Vtiger_Record_Model::getInstanceById($commessaId, 'Commesse');
				$commessaPath = $commessa->get('nome');
			} else {
				$commessaPath = 'Condivisa';
			}
			$configDocPath = !empty(AppConfig::module($moduleName, 'DOC_PATH')) ? AppConfig::module($moduleName, 'DOC_PATH') : 'storage/Documenti';
			$uploadFilePath = ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $configDocPath. DIRECTORY_SEPARATOR . $commessaPath;
			if (!is_dir($uploadFilePath)) { //create new folder
				if(!mkdir($uploadFilePath, 0744, true)) {
					unset($this->changes['docfilename']);
					return false;
				}
			}

			//Verifico se esiste gia` un documento allegato
			$fileName = !empty($this->get('docfilename')) ? $this->get('docfilename') : false;

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
					$this->set('docnome', $fileNameWithoutExt);
					$this->set('docfilename', $fileName);
					$this->set('doctype', $file['type']);
					$this->set('docpath', $uploadFilePath);
					$this->set('docsize', $file['size']);
					$this->set('docinuso', 0);
					$version = $this->get('docversione');
					$version +=1;
					$this->set('docversione',$version);
				} else {
					\App\Log::error('Error on the save attachment process.');
					unset($this->changes['docfilename']);
					return false;
				}
			} else {
				if(!$this->validateUploadedFileName($fileName)) {
					unset($this->changes['docfilename']);
					return false;
				}
				if ($fileInstance->moveFile($uploadFilePath . DIRECTORY_SEPARATOR. $fileName)) {
					$this->set('doctype', $file['type']);
					$this->set('docsize', $file['size']);
					$this->set('docinuso', 0);
					$version = $this->get('docversione');
					$version +=1;
					$this->set('docversione',$version);
				} else {
					\App\Log::error('Error on the save attachment process.');
					unset($this->changes['docfilename']);
					return false;
				}
			}
			return true;
		} else {
			unset($this->changes['docfilename']);
			return false;
		}

	}

}
