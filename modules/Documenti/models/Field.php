<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Documenti_Field_Model extends Vtiger_Field_Model
{

	/**
	 * Function to retieve display value for a value
	 * @param string $value - value which need to be converted to display value
	 * @return string - converted display value
	 */
	public function getDisplayValue($value, $record = false, $recordInstance = false, $rawText = false)
	{
		$fieldName = $this->getName();

		if ($fieldName == 'docsize' && $recordInstance) {
			$filesize = $value;
			if ($filesize < 1024) {
				$value = $filesize . ' B';
			} elseif ($filesize > 1024 && $filesize < 1048576) {
				$value = round($filesize / 1024, 2) . ' KB';
			} else if ($filesize > 1048576) {
				$value = round($filesize / (1024 * 1024), 2) . ' MB';
			}

			return $value;
		}

		if ($fieldName == 'docpath' && $recordInstance) {
			$value = str_replace(ROOT_DIRECTORY, '',  $value);
			$value = str_replace(AppConfig::module($this->getModuleName(), 'DOC_PATH'), '',  $value);
			$value = '\\\\' . $_SERVER['SERVER_ADDR'] . '\\' . ltrim($value, '/') . '\\' . $recordInstance->get('docfilename');
		}

		return parent::getDisplayValue($value, $record, $recordInstance, $rawText);
	}
}
