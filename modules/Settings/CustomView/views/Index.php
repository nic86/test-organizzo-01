<?php

/**
 * CustomView config view class
 * @package YetiForce.View
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 * @author Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 */
class Settings_CustomView_Index_View extends Settings_Vtiger_Index_View
{

	/**
	 * Main process
	 * @param \App\Request $request
	 */
	public function process(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$supportedModule = $request->get('sourceModule');
		if(empty($supportedModule)) {
			$supportedModules = Settings_CustomView_Module_Model::getSupportedModules();
			$supportedModule = reset($supportedModules);
		}
		$qualifiedModuleName = $request->getModule(false);
		$moduleModel = Settings_Vtiger_Module_Model::getInstance($qualifiedModuleName);
		$viewer = $this->getViewer($request);
		$viewer->assign('SOURCE_MODULE', $supportedModule);
		$viewer->assign('SOURCE_MODULE_ID', App\Module::getModuleId($supportedModule));
		$viewer->assign('MODULE_MODEL', $moduleModel);
		$viewer->assign('QUALIFIED_MODULE', $qualifiedModuleName);
		$viewer->assign('MODULE', $moduleName);
		if ($request->isAjax()) {
			$viewer->view('IndexContents.tpl', $qualifiedModuleName);
		} else {
			if(!isset($supportedModules)) {
				$supportedModules = Settings_CustomView_Module_Model::getSupportedModules();
			}
			$viewer->assign('SUPPORTED_MODULE_MODELS', $supportedModules);
			$viewer->view('Index.tpl', $qualifiedModuleName);
		}
	}

	/**
	 * Function to get the list of Script models to be included
	 * @param Vtiger_Request $request
	 * @return <Array> - List of Vtiger_JsScript_Model instances
	 */
	public function getFooterScripts(Vtiger_Request $request)
	{
		$jsFileNames = [
			'~libraries/jquery/colorpicker/js/colorpicker.js',
			'modules.CustomView.resources.CustomView'
		];
		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		return array_merge(parent::getFooterScripts($request), $jsScriptInstances);
	}

	/**
	 * Retrieves css styles that need to loaded in the page
	 * @param Vtiger_Request $request - request model
	 * @return <array> - array of Vtiger_CssScript_Model
	 */
	public function getHeaderCss(Vtiger_Request $request)
	{
		$headerCssInstances = parent::getHeaderCss($request);
		$cssFileNames = [
			'~libraries/jquery/colorpicker/css/colorpicker.css'
		];
		$cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
		return array_merge($headerCssInstances, $cssInstances);
	}
}
