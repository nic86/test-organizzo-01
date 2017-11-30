<?php
/* {[The file is published on the basis of YetiForce Public License that can be found in the following directory: licenses/License.html]} */

class OSSMailView_SetHasRelated_Action extends Vtiger_Action_Controller
{
	public function checkPermission(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$currentUserPriviligesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!$currentUserPriviligesModel->hasModulePermission($moduleName)) {
			throw new \Exception\NoPermitted('LBL_PERMISSION_DENIED');
		}
	}

	public function process(Vtiger_Request $request)
	{
		$recordId = $request->get('sourceRecord');
		$moduleName = $request->get('module');

		if (!empty($recordId)) {
			$recordModel = Vtiger_Record_Model::getInstanceById($recordId, $moduleName);
			$recordModel->set('emailarchived', true);
			$recordId = $recordModel->save();
			$result = array('success' => true, 'data' => $data);
			$response = new Vtiger_Response();
			$response->setResult($result);
			$response->emit();

		} else {
			$response = new Vtiger_Response();
			$response->setResult(false);
			$response->emit();
		}
	}
}

