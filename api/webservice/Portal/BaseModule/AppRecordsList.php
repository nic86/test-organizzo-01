<?php
namespace Api\Portal\BaseModule;

/**
 * Get record list class
 * @package YetiForce.WebserviceAction
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class AppRecordsList extends \Api\Core\BaseAction
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
		$limit = $queryGenerator->getLimit();
		$dataReader = $queryGenerator->createQuery()->createCommand()->query();
		while ($row = $dataReader->read()) {
			$records[] = $row['id'];
		}
		$rowsCount = count($records);
		return [
			'records' => $records,
			'count' => $rowsCount,
			'isMorePages' => $rowsCount === $limit,
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
		$queryGenerator->initForDefaultCustomView();
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

}
