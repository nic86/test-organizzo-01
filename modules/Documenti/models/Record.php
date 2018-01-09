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
	
	public function getNewReleaseURL()
	{
		return 'index.php?module=' . $this->getModuleName() . '&action=NewRelease&record=' . $this->getId();
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

	public function permissionNewRelease()
	{
		$stato = $this->get('doccommstato');
		if ($stato === 'Consegnato') {
			return true;
		}

		return false;
	}


	public function permissionDownload()
	{
		$stato = $this->get('doccommstato');
		if ($stato === 'Consegnato') {
			return true;
		}

		$share = $this->get('docshare');
		$permittedUserId = !empty($this->get('docutenteinuso')) ? $this->get('docutenteinuso') : false;
		$assignedUserId = !empty($this->get('assigned_user_id')) ? $this->get('assigned_user_id') : false;
		$currentUserId = \App\User::getCurrentUserId();

		/* if ($currentUserModel->isAdmin()) { */
		/* 	return true; */
		/* } */
		if(!$permittedUserId) {
			return true;
		} elseif ($permittedUserId == $currentUserId) {
			return true;
		} elseif ($assignedUserId == $currentUserId) {
			return true;
		} elseif ($permittedUserId && $share) {
			return true;
		}

		return false;
	}

	public function getDownloadFileName()
	{
		$codiceId  = $this->get('doccodicefile');
		$codice = '';
		if (!empty($codiceId)) {
			$moduleName  = \vtlib\Functions::getCRMRecordType($codiceId);
			$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
			$codice      = \vtlib\Functions::getSingleFieldValue($moduleModel->basetable, 'codfileelab', $moduleModel->basetableid, $codiceId);
			if (!empty($codice)) {
				$codice .= '-';
			}
		}

		$tipoId  = $this->get('doctipofileelab');
		$tipo = '';
		if (!empty($tipoId)) {
			$moduleName  = \vtlib\Functions::getCRMRecordType($tipoId);
			$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
			$tipo        = \vtlib\Functions::getSingleFieldValue($moduleModel->basetable, 'tipifileelabcodice', $moduleModel->basetableid, $tipoId);
			if (!empty($tipo)) {
				$tipo .= '-';
			}
		}

		$contatore = !empty($this->get('doccontatore')) ? $this->get('doccontatore').'-' : '';
		$release   = !empty($this->get('docrelease')) ? $this->get('docrelease').'-' : '';
		$desc      = !empty($this->get('docnome')) ? $this->get('docnome').'-' : '';

		$filename = $codice.$tipo.$contatore.$release.$desc;
		$filename = rtrim($filename, '-');
		$extension = pathinfo($this->get('docfilename'), PATHINFO_EXTENSION);

		if ($this->get('doccommstato') !== 'Consegnato') {
			$version = $this->get('docversione');
			$id = $this->getId();
			$md5Part = crc32("{$version}_{$id}");
			return "{$filename}_{$md5Part}.{$extension}";
		}
		return "{$filename}.{$extension}";
	}

	public function validateUploadedFileName($uploadedFileName)
	{
		$version = $this->get('docversione');
		$id = $this->getId();
		$explodeFileName = explode('_',$uploadedFileName);
		$lastValue = end($explodeFileName);
		$explodeExtension = explode('.', $lastValue);
		$md5File  = $explodeExtension[0];
		$md5Check = crc32("{$version}_{$id}");

		if($md5File == $md5Check ) {
			return true;
		}

		return false;
	}

	public function downloadFile()
	{
		if (!$this->checkFileIntegrity()) {
			throw new \Exception\NoPermitted('LBL_PERMISSION_DENIED');
		}

		if (!$this->permissionDownload()) {
			throw new \Exception\NoPermitted('LBL_PERMISSION_DENIED');
		}

		$filePath = $this->get('docpath');
		$fileName = $this->get('docfilename');
		$savedFile = $filePath . DIRECTORY_SEPARATOR . $fileName;
		$fileSize = filesize($savedFile);
		$fileSize = $fileSize + ($fileSize % 1024);

		$fileContent = fread(fopen($savedFile, "r"), $fileSize);
		if (!empty($fileContent)) {
			$currentUserId = \App\User::getCurrentUserId();
			$result = \App\Db::getInstance()->createCommand()->update(
				'vtiger_documenti', [
				'docutenteinuso' => $currentUserId
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

	public function isEditable()
	{
		if ($this->get('doccommstato') === 'Consegnato') {
			return false;
		}
		if (!isset($this->privileges['isEditable'])) {
			$moduleName = $this->getModuleName();
			$recordId = $this->getId();

			$isPermitted = Users_Privileges_Model::isPermitted($moduleName, 'EditView', $recordId);
			$checkLockEdit = Users_Privileges_Model::checkLockEdit($moduleName, $this);

			$this->privileges['isEditable'] = $isPermitted && $this->checkLockFields() && $checkLockEdit === false;
		}
		return $this->privileges['isEditable'];
	}

	/**
	 * Function to save data to database
	 */
	public function saveToDb()
	{
		$this->setDocument();
		$this->setContatore();
		parent::saveToDb();
	}

	public function isMandatorySave()
	{
		return $_FILES ? true : false;
	}


	public function setContatore() 
	{
		$commessa  = $this->get('doccommessa');
		$codice    = $this->get('doccodicefile');
		$tipo      = $this->get('doctipofileelab');

		if (empty($commessa)) {
			return;
		}
		if (empty($codice)) {
			return;
		}
		if (empty($tipo)) {
			return;
		}

		$contatore     = $this->get('doccontatore');
		$changedCodice = array_key_exists('doccodicefile', $this->changes);
		$changedTipo   = array_key_exists('doctipofileelab', $this->changes);

		if(empty($contatore) || $changedCodice || $changedTipo) {
			$where = "`doccommessa` = '{$commessa}'";
			$where .= "AND `doccodicefile` = '{$codice}'";
			$where .= "AND `doctipofileelab` = '{$tipo}'";
			$lastNumber  = $this->getLastNumber('doccontatore', $where);
			$lastNumber +=1;
			$this->set('doccontatore', $lastNumber);
		}
	}

	public function getLastNumber($fieldName,$search)
	{
		$moduleModel = $this->getModule();
		$maxSequence = (new \App\Db\Query())
			->select($fieldName)
			->from($moduleModel->basetable)
			->where($search)
			->max($fieldName);

		return (int) $maxSequence;
	}
	/**
	 * This function is used to add the vtiger_attachments. This will call the function uploadAndSaveFile which will upload the attachment into the server and save that attachment information in the database.
	 */
	public function setDocument()
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
					if(empty($this->get('docnome'))) {
						$this->set('docnome', $fileNameWithoutExt);
					}
					$this->set('docfilename', $fileName);
					$this->set('doctype', $file['type']);
					$this->set('docpath', $uploadFilePath);
					$this->set('docsize', $file['size']);
					$version = $this->get('docversione');
					$version +=1;
					$this->set('docversione',$version);
				} else {
					\App\Log::error('Error on the save attachment process.');
					unset($this->changes['docfilename']);
					return false;
				}
			} else {
				if(!$this->validateUploadedFileName($fileInstance->name)) {
					unset($this->changes['docfilename']);
					return false;
				}
				if ($fileInstance->moveFile($uploadFilePath . DIRECTORY_SEPARATOR. $fileName)) {
					$this->set('doctype', $file['type']);
					$this->set('docsize', $file['size']);
					$this->set('docutenteinuso', '');
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
