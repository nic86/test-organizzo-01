<?php
namespace Api\Portal\BaseModule;

/**
 * Get Privileges class
 * @package YetiForce.WebserviceAction
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 * @author Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 */
class AppPrivileges extends \Api\Core\BaseAction
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
		$userId = $this->session->get('user_id');
		$privileges = [];
		if (\App\User::isExists($userId)) {
			$moduleId = \App\Module::getModuleId($moduleName);
			$actionPermissions = \App\User::getPrivilegesFile($userId);
			$isAdmin = $actionPermissions['is_admin'];
			$permission = isset($actionPermissions['profile_action_permission'][$moduleId]) ? $actionPermissions['profile_action_permission'][$moduleId] : false;
			if ($permission || $isAdmin) {
				foreach (\Vtiger_Action_Model::$standardActions as $key => $value) {
					$privileges[$value] = $isAdmin ? true : isset($permission[$key]) && $permission[$key] === \Settings_Profiles_Module_Model::IS_PERMITTED_VALUE;
				}
			}
		}
		return ['standardActions' => $privileges];
	}
}
