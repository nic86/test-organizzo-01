<?php
namespace Api\Portal\BaseModule;

/**
 * Get fields class
 * @package YetiForce.WebserviceAction
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class AppFields extends \Api\Core\BaseAction
{

	/** @var string[] Allowed request methods */
	public $allowedMethod = ['GET'];

	/**
	 * Get method
	 * @return array
	 */
	public function get()
	{
		$moduleName = $this->controller->request->get('module');
		$module = \Vtiger_Module_Model::getInstance($moduleName);
		$fields = $blocks = [];
		foreach ($module->getFields() as &$field) {
			$block = $field->get('block');
			if (!isset($blocks[$block->id])) {
				$blockProperties = get_object_vars($block);
				$blocks[$block->id] = array_filter($blockProperties, function($v, $k) {
					return !is_object($v);
				}, ARRAY_FILTER_USE_BOTH);
				$blocks[$block->id]['name'] = \App\Language::translate($block->label, $moduleName);
			}
			$fieldInfo = $field->getFieldInfo();
			$fieldInfo['id'] = $field->getId();
			$fieldInfo['isNameField'] = $field->isNameField();
			if($fieldInfo['defaultvalue']) {
				$fieldInfo['defaultvalue'] = $field->getDefaultFieldValue();
			}
			$fieldInfo['isEditable'] = $field->isEditable();
			$fieldInfo['isViewable'] = $field->isViewable();
			$fieldInfo['isEditableReadOnly'] = $field->isEditableReadOnly();
			$fieldInfo['sequence'] = $field->get('sequence');
			$fieldInfo['fieldparams'] = $field->getFieldParams();
			$fieldInfo['blockId'] = $block->id;
			if ($field->isReferenceField()) {
				$fieldInfo['referenceList'] = $field->getReferenceList();
			}
			$fields[$field->getId()] = $fieldInfo;
		}
		$relations = $this->getRelatedModules($module);
		return ['fields' => $fields, 'blocks' => $blocks, 'relations' => $relations];
	}

	private function getRelatedModules($moduleModel)
	{
		$allRelationModuleName = \Vtiger_Relation_Model::getAllRelations($moduleModel);
		$relatedModules = [];
		foreach($allRelationModuleName as $relationModuleName) {
			if (!\App\Privilege::isPermitted($relationModuleName->getRelationModuleName())) {
				continue;
			}
			$moduleName = $relationModuleName->getRelationModuleName();
			$relatedModules[$moduleName] = [
				'moduleName'=> $moduleName,
				'moduleLabel'=> \App\Language::translate($moduleName, $moduleName),
				'records' => [],
				'field' => $relationModuleName->getRelationField()->name
			];
		}

		return $relatedModules;
	}

}
