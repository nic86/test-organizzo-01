<?php
namespace Api\Portal\BaseModule;

/**
 * Get record list class
 * @package YetiForce.WebserviceAction
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class AppAllRecords extends \Api\Core\BaseAction
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
		$records = [];
		$queryGenerator = $this->getQuery();
		$fieldsModel = $queryGenerator->getListViewFields();
		$moduleModel = \Vtiger_Module_Model::getInstance($moduleName);
		$moduleBlockFields = \Vtiger_Field_Model::getAllForModule($moduleModel);
		$dataReader = $queryGenerator->createQuery()->all();
		foreach($dataReader as $row) {
			$record['id'] = $row['id'];
			$record['name']= trim(\App\Record::getLabel($row['id']));
			/* $record['modifiedtime'] = $fieldsModel['modifiedtime']->getDisplayValue($row['modifiedtime'], $row['id'], false, true); */
			$recordModel = \Vtiger_Record_Model::getInstanceById($row['id'], $moduleName);
			$displayData= [];
			$rawData = $recordModel->getData();
			foreach ($moduleBlockFields as $moduleFields) {
				foreach ($moduleFields as $moduleField) {
					$block = $moduleField->get('block');
					if (empty($block)) {
						continue;
					}
					$displayData[$moduleField->getName()] = $recordModel->getDisplayValue($moduleField->getName(), $row['id'], true);
					if ($moduleField->isReferenceField()) {
						$refereneModule = $moduleField->getUITypeModel()->getReferenceModule($recordModel->get($moduleField->getName()));
						$rawData[$moduleField->getName() . '_module'] = $refereneModule ? $refereneModule->getName() : null;
					}
				}
			}
			$relatedRecords = $this->getRelatedRecords($recordModel, $moduleModel, $moduleName);

			$record['data']           = $displayData;
			$record['moduleName']     = $moduleName;
			$record['rawData']        = $rawData;
			$record['relatedModules'] = $relatedRecords;
			array_push($records, $record);
		}

		return [
			'records' => $records
		];
	}

	/**
	 * Get query record list
	 * @return \App\QueryGenerator
	 * @throws \Api\Core\Exception
	 */
	public function getQuery()
	{
		$queryGenerator = new \App\QueryGenerator($this->controller->request->get('module'));
		$queryGenerator->setFields(['id', 'modifiedtime']);
		if ($requestLimit = $this->controller->request->getHeader('X-ROW-LIMIT')) {
			$limit = (int) $requestLimit;
			$queryGenerator->setLimit($limit);
		}
		if ($requestOffset = $this->controller->request->getHeader('X-ROW-OFFSET')) {
			$offset = (int) $requestOffset;
			$queryGenerator->setOffset($offset);
		}
		if ($requestFields = $this->controller->request->getHeader('X-FIELDS')) {
			$queryGenerator->setFields(\App\Json::decode($requestFields));
			$queryGenerator->setField('id');
		}
		if ($conditions = $this->controller->request->getHeader('X-CONDITION')) {
 			$conditions = \App\Json::decode($conditions);
 			if (isset($conditions['fieldName'])) {
 				$queryGenerator->addCondition($conditions['fieldName'], $conditions['value'], $conditions['operator']);
 			} else {
 				foreach ($conditions as $condition) {
 					$queryGenerator->addCondition($condition['fieldName'], $condition['value'], $condition['operator']);
 				}
 			}
 		}
		return $queryGenerator;
	}

	private function getRelatedRecords($recordModel, $moduleModel, $moduleName) {
		$allRelationModuleName = \Vtiger_Relation_Model::getAllRelations($moduleModel);
		$relatedRecords = [];
		foreach($allRelationModuleName as $relationModuleName) {
			$moduleName = $relationModuleName->getRelationModuleName();
			if (!\App\Privilege::isPermitted($moduleName)) {
				continue;
 			}

			try {
				$relatedRecords[$moduleName] = [ 
					'moduleName'=> $moduleName,
					'moduleLabel'=> \App\Language::translate($moduleName, $moduleName),
					'field' => $relationModuleName->getRelationField()->name,
					'records' => []
				];
				$relationListView  = \Vtiger_RelationListView_Model::getInstance($recordModel, $moduleName);
				$queryRel = $relationListView->getRelationQuery();
				$ids      = $queryRel->select(['vtiger_crmentity.crmid'])
				->distinct()
				->column();
			} catch (\Api\Core\Exception $e) {
				continue;
			} catch (Exception\NoPermittedToApi $e) {
				continue;
			} catch (Exception $e) {
				continue;
			} finally {
				if (!empty($ids)) {
					$relatedRecords[$moduleName]['records']= $ids; 
				} 
			}
		}

		return $relatedRecords;
	}

}
