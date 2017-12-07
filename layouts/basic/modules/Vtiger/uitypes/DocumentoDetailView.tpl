{*<!--
/*********************************************************************************
  ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
   * ("License"); You may not use this file except in compliance with the License
   * The Original Code is:  vtiger CRM Open Source
   * The Initial Developer of the Original Code is vtiger.
   * Portions created by vtiger are Copyright (C) vtiger.
   * All Rights Reserved.
  *
 ********************************************************************************/
-->*}
{strip}
    <p>{$DOC_DETAILS.orgname}</p>
	{if !empty($DOC_DETAILS.path) && !empty($DOC_DETAILS.orgname) && (strpos($DOC_DETAILS.type, 'image') !== FALSE)}
		<img src="data:{$DOC_DETAILS.type};base64,{base64_encode(file_get_contents($DOC_DETAILS.path))}" width='100%'>
	{/if}
{/strip}
