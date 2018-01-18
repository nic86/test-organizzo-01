<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Vtiger_Documento_UIType extends Vtiger_Base_UIType
{
	private static $allowedPreviewFormats = ['txt','pdf','jpeg', 'png', 'jpg', 'pjpeg', 'x-png', 'gif', 'bmp', 'x-ms-bmp'];
	/**
	 * Function to get the Template name for the current UI Type object
	 * @return string - Template Name
	 */
	public function getTemplateName()
	{
		return 'uitypes/Documento.tpl';
	}

	/**
	 * Function to get the Template name for the current UI Type object
	 * @return string - Template Name
	 */
	public function getDetailViewTemplateName()
	{
		return 'uitypes/DocumentoDetailView.tpl';
	}

	public function getDisplayValue($value, $record = false, $recordInstance = false, $rawText = false)
	{
		if ($recordInstance && !empty($value)) {
			$moduleName = $recordInstance->getModuleName();
			$filename = basename($value);
			$fieldname = $this->get('field')->name;
			$value ="<a class='btn btn-default btn-sm popoverTooltip' href='file.php?module=" . $moduleName .'&action=DownloadFile&record=' . $record . '&fieldname=' . $fieldname . "' data-content='Apri Documento'><span class='glyphicon glyphicon-open-file' aria-hidden='true'></span></a>&nbsp;&nbsp;";

			$ext = explode('.', $filename);
			$ext = strtolower(array_pop($ext));
			if (in_array($ext, self::$allowedPreviewFormats)) {
				$value .="<a class='btn btn-default btn-sm popoverTooltip' target='_blank' href='file.php?module=" . $moduleName .'&action=DownloadFile&record=' . $record . '&fieldname=' . $fieldname ."&show=true' data-content='Anteprima Documento'><span class='glyphicon glyphicon-search' aria-hidden='true'></span></a>&nbsp;&nbsp;";
			}
			$value .= $filename;
		}
		return $value;
	}

	public function getListViewDisplayValue($value, $record = false, $recordInstance = false, $rawText = false)
    {
        return $this->getDisplayValue($value, $record, $recordInstance, $rawText);
	}

}
