<?php
namespace Api\Portal\Users;

/**
 * Users logout action class
 * @package YetiForce.WebserviceAction
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class AppRefresh extends \Api\Core\BaseAction
{

	/** @var string[] Allowed request methods */
	public $allowedMethod = ['GET'];

	/**
	 * Check permission to module
	 * @throws \Api\Core\Exception
	 */
	public function checkPermissionToModule()
	{
		return true;
	}

	/**
	 * Put method
	 * @return array
	 */
	public function GET()
	{
		return true;
	}
}
