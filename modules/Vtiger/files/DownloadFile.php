<?php
/**
 * DownloadFile class to handle files
 * @package YetiForce.Files
 * @copyright YetiForce Sp. z o.o.
 * @license YetiForce Public License 2.0 (licenses/License.html or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

/**
 * DownloadFile class to handle files
 */
class Vtiger_DownloadFile_File extends Vtiger_Basic_File
{

	/**
	 * Checking permission in get method
	 * @param \App\Request $request
	 * @return boolean
	 */
	public function getCheckPermission(\App\Request $request)
	{
		if (!\App\Privilege::isPermitted($request->getModule(), 'DetailView', $request->getInteger('record'))) {
			throw new \App\Exceptions\NoPermittedToRecord('LBL_NO_PERMISSIONS_FOR_THE_RECORD', 406);
		}
		return true;
	}

	/**
	 * Download file
	 * @param \App\Request $request
	 * @return string|boolean
	 */
	public function get(\App\Request $request)
	{
		$documentRecordModel = Vtiger_Record_Model::getInstanceById($request->getInteger('record'), $request->getModule());
		//Download the file
		$show = $request->getBoolean('show');
		$fieldName = $request->get('fieldname');
		$documentRecordModel->downloadFile($fieldName,$show);
		return false;
	}
}
