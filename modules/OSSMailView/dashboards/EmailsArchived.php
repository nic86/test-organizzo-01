<?php

/**
 * Wdiget to show email not related
 *  * @package YetiForce.Dashboard
 * @license licenses/License.html
 * @author Tomasz Kur <t.kur@yetiforce.com>
 */
class OSSMailView_EmailsArchived_Dashboard extends Vtiger_IndexAjax_View
{

	private function getEmailNotArchived($moduleName, $user, $pagingModel)
	{
		$query = (new \App\Db\Query())->select(['vtiger_crmentity.crmid'])
			->from('vtiger_ossmailview')
			->innerJoin('vtiger_crmentity', 'vtiger_ossmailview.ossmailviewid=vtiger_crmentity.crmid')
			->where(['vtiger_crmentity.setype' => $moduleName])
			->andWhere(['vtiger_crmentity.deleted' => '0'])
			->andWhere(['vtiger_ossmailview.emailarchived' => '0']);

		if (is_array($user)) {
			$query->andWhere(['in','vtiger_crmentity.smownerid', $user]);
		} else {
			$query->andWhere(['vtiger_crmentity.smownerid' => $user]);
		}
		\App\PrivilegeQuery::getConditions($query, $moduleName);
		if($pagingModel->get('sortorder') === 'desc') {
			$query->orderBy(['vtiger_crmentity.createdtime' => SORT_DESC]);
		} else {
			$query->orderBy(['vtiger_crmentity.createdtime' => SORT_ASC]);
		}
		$query->limit($pagingModel->getPageLimit());
		$query->offset($pagingModel->getStartIndex());

		$rows = $query->all();
		$emails = [];
		foreach ($rows as $row) {
			$recordModel = Vtiger_Record_Model::getInstanceById($row['crmid']);
			$emails[$row['crmid']] = $recordModel;
		}
		return $emails;
	}

	public function process(Vtiger_Request $request)
	{
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$moduleName = $request->getModule();
		$linkId = $request->get('linkid');
		$user = $request->get('owner');
		$sortOrder = $request->get('sortorder');

		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$allRelation = Vtiger_Relation_Model::getAllRelations($moduleModel);
		$links = [];
		foreach ($allRelation as $relation) {
			if (strpos($relation->get('actions'), 'SELECT') !== FALSE) {
				$links[] = $relation;
			}
		}
		$widget = Vtiger_Widget_Model::getInstance($linkId, $currentUser->getId());
		if (empty($user)) {
			//Per attivare il filtraggio per utente modificare il file layout/basic/modules/FileManager/ArchivedFilesContents.tpl
			//e il file modules/Settings/WidgetManagment/models/Module.php la funzione getWidgetWithFilterUser
 			//$user = Settings_WidgetsManagement_Module_Model::getDefaultUserId($widget);
			$user = $currentUser->getId();

		}

		if (empty($sortOrder)) {
			$sortOrder= 'desc';
		}
		//$accessibleUsers = \App\Fields\Owner::getInstance($moduleName, $currentUser)->getAccessibleUsersForModule();
		//$accessibleGroups = \App\Fields\Owner::getInstance($moduleName, $currentUser)->getAccessibleGroupForModule();
		//if ($user == 'all') {
		//	$user = array_keys($accessibleUsers);
		//}
		$page = $request->get('page');
		if (empty($page)) {
			$page = 1;
		}
		$pagingModel = new Vtiger_Paging_Model();
		$pagingModel->set('page', $page);
		$pagingModel->set('limit', (int) $widget->get('limit'));
		$pagingModel->set('sortorder', $sortOrder);
		$emails = $this->getEmailNotArchived($moduleName, $user, $pagingModel);
		$viewer = $this->getViewer($request);
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('EMAILS', $emails);
		$viewer->assign('OWNER', $user);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('CURRENTUSER', $currentUser);
		$viewer->assign('LISTVIEWLINKS', true);
		$viewer->assign('SORTORDER', $sortOrder);
		$viewer->assign('LINKS', $links);
		//$viewer->assign('ACCESSIBLE_USERS', $accessibleUsers);
		//$viewer->assign('ACCESSIBLE_GROUPS', $accessibleGroups);
		$viewer->assign('PAGING_MODEL', $pagingModel);
		$content = $request->get('content');
		if (!empty($content)) {
			$viewer->view('dashboards/EmailsArchivedContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/EmailsArchived.tpl', $moduleName);
		}
	}
}
