<?php

/**
 * Uitype Model
 * @package YetiForce.Github
 * @license licenses/License.html
 * @author Tomasz Kur <t.kur@yetiforce.com>
 */
class Notification_Text_UIType extends Vtiger_Text_UIType
{

	/**
	 * Function to get the Display Value, for the current field type with given DB Insert Value
	 * @param string $value
	 * @param int $record id record
	 * @param\Vtiger_Record_Model $recordInstance 
	 * @param mixed $rawText
	 * @return string
	 */
	public function getDisplayValue($value, $record = false, $recordInstance = false, $rawText = false)
	{
		$value = $recordInstance->getParseField($this->get('field')->getName());
		return parent::getDisplayValue($value, $record, $recordInstance, $rawText);
	}
}
