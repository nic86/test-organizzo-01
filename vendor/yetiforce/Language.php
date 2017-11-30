<?php
namespace App;

/**
 * Language basic class
 * @package YetiForce.App
 * @license licenses/License.html
 * @author Adrian Koń <a.kon@yetiforce.com>
 */
class Language
{

	/**
	 * Current language
	 * @var string 
	 */
	private static $language = false;
	/**
	 * Short current language
	 * @var string 
	 */
	private static $shortLanguage = false;
	/**
	 * Pluralize cache
	 * @var array 
	 */
	private static $pluralizeCache = [];
	/**
	 * Function that returns current language
	 * @return string -
	 */
	public static function getLanguage()
	{
		if (static::$language) {
			return static::$language;
		}
		if (vglobal('translated_language')) {
			$language = vglobal('translated_language');
		} elseif (\Vtiger_Session::get('language') !== '') {
			$language = \Vtiger_Session::get('language');
		} else {
			$language = User::getCurrentUserModel()->getDetail('language');
		}
		$language = empty($language) ? vglobal('default_language') : strtolower($language);
		return static::$language = $language;
	}
	/**
	 * Set current language
	 * @param string $language
	 */
	public static function setLanguage($language)
	{
		static::$language = $language;
	}


	/**
	 * Functions that gets translated string
	 * @param string $key - string which need to be translated
	 * @param string $moduleName - module scope in which the translation need to be check
	 * @return string - translated string
	 */
	public static function translate($key, $moduleName = 'Vtiger', $currentLanguage = false)
	{
		return \Vtiger_Language_Handler::getTranslatedString($key, $moduleName, $currentLanguage);
	}

	/**
	 * Functions that gets translated string by $args
	 * @param string $key - string which need to be translated
	 * @param string $moduleName - module scope in which the translation need to be check
	 * @return string - translated string
	 */
	public static function translateArgs($key, $moduleName = 'Vtiger')
	{
		$formattedString = static::translate($key, $moduleName);
		$args = func_get_args();
		array_shift($args);
		array_shift($args);
		if (is_array($args) && !empty($args)) {
			$formattedString = call_user_func_array('vsprintf', [$formattedString, $args]);
		}
		return $formattedString;
	}

	/**
	 * Get singular module name
	 * @param string $moduleName
	 * @return string
	 */
	public static function getSingularModuleName($moduleName)
	{
		return "SINGLE_$moduleName";
	}
}
