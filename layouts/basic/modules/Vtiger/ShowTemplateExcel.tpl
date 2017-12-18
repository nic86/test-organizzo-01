{*<!-- /********************************************************************************* ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
*
 ********************************************************************************/
-->*}
{strip}
<style>
.modal-content div{
    overflow: unset !important;
}
</style>
	<div id="templateExcelContainer" class='modelContainer modal fade' tabindex="-1">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header contentsBackground">
					<button data-dismiss="modal" class="close" title="{vtranslate('LBL_CLOSE')}">&times;</button>
					<h3 class="modal-title">Modelli di stampa:</h3>
				</div>
                {if $ERROR neq ''}
                <div class="modal-body tabbable">
                    <div class="alert alert-warning" role="alert">
						{$ERROR}
                    </div>
				</div>
                <div class="modal-footer">
                    <button class="btn btn-warning" type="reset" data-dismiss="modal"><strong>Chiudi</strong></button>
                </div>

			    {else}
				<form class="form-horizontal" id="printTemplate" method="post" action="index.php">
					<input type="hidden" name="module" value="{$MODULE}" />
					<input type="hidden" name="action" value="QuickExport" />
					<input type="hidden" name="mode" value="ExportToTemplate" />
					<input type="hidden" name="viewname" value="{$VIEWNAME}" />
					<input type="hidden" name="selected_ids" value={\App\Json::encode($SELECTED_IDS)}>
					<input type="hidden" name="excluded_ids" value={\App\Json::encode($EXCLUDED_IDS)}>
					<input type="hidden" name="search_key" value= "{$SEARCH_KEY}" />
					<input type="hidden" name="operator" value="{$OPERATOR}" />
					<input type="hidden" name="search_value" value="{$ALPHABET_VALUE}" />
					<input type="hidden" name="search_params" value='{\App\Json::encode($SEARCH_PARAMS)}' />

					<div class="modal-body">
                        <div class="form-group">
                            <div class="col-sm-4 control-label">
                                Lista modelli:
                            </div>
                            <div class="col-sm-6 controls">
                                <select name="selectedFiles[]" data-placeholder="seleziona uno o più modelli di stampa" multiple class="chzn-select form-control" required="required">
                                    <optgroup>
                                        {foreach item=MODELLO from=$MODELLI_EXCEL}
                                            <option value="{$MODELLO}">
                                                {$MODELLO}
                                            </option>
                                        {/foreach}
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
					<div class="modal-footer">
                        <button class="btn btn-success" type="submit" form="printTemplate" name="saveButton"><strong>Stampa</strong></button>
                        <button class="btn btn-warning" type="reset" data-dismiss="modal"><strong>Chiudi</strong></button>
                    </div>
				</form>
			    {/if}
			</div>
		</div>
	</div>
{/strip}
