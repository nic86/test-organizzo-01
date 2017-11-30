{*<!-- {[The file is published on the basis of YetiForce Public License that can be found in the following directory: licenses/License.html]} --!>*}
{strip}
	{if count($EMAILS) > 0}
	    <div class="row no-margin">
		<div class="col-sm-12">
		{if $PAGING_MODEL->getCurrentPage() eq 1}
			<div class="row">
			<div class="col-xs-2">
				<h6><b>{vtranslate('Subject' ,$MODULE_NAME)}</b></h6>
			</div>
			<div class="col-xs-2">
				<h6><b>{vtranslate('From' ,$MODULE_NAME)}</b></h6>
			</div>
			<div class="col-xs-2">
				<h6><b>{vtranslate('To' ,$MODULE_NAME)}</b></h6>
			</div>
            <div class="col-xs-2">
				<h6><b>{vtranslate('Date' ,$MODULE_NAME)}</b></h6>
			</div>
            <div class="col-xs-2">
				<h6><b>Azione</b></h6>
			</div>

			<div class="col-xs-12"><hr></div>
			{/if}
            </div>
			<div class="row">
			{foreach from=$EMAILS key=RECORD_ID item=EMAIL_MODEL}
				<div class="col-xs-12 paddingLRZero" >
					<div class="col-xs-2" name="rowsubject{$RECORD_ID}">
							{$EMAIL_MODEL->getDisplayValue('subject')}
					</div>
					<div class="col-xs-2">
						{$EMAIL_MODEL->getDisplayValue('from_email')}
                	</div>
                    <div class="col-xs-2">
						{$EMAIL_MODEL->getDisplayValue('to_email')}
                	</div>
					<div class="col-xs-2">
						<span title="{$EMAIL_MODEL->getDisplayValue('date')}">
							{Vtiger_Util_Helper::formatDateDiffInStrings($EMAIL_MODEL->getDisplayValue('date'))}
						</span>
					</div>
                    <div class="col-xs-2">
                            <input type="hidden" value="" id="tempField{$RECORD_ID}" name="tempField{$RECORD_ID}"/>
                            <select class="btn btn-xs btn-default" id="tempSelect{$RECORD_ID}" name="tempSelect{$RECORD_ID}">
                                {foreach item="ITEM" from=$LINKS}
                                    <option value="{$ITEM->get('modulename')}">
                                        {vtranslate($ITEM->get('modulename'), $ITEM->get('modulename'))}
                                    </option>
                                {/foreach}
                            </select>
                                <a class="btn btn-xs btn-default" style='vertical-align: middle;' onclick="OSSMailView_Widget_Js.addRecord('{$RECORD_ID}');"><span class='glyphicon glyphicon-plus'  style='vertical-align: middle;' border='0' title="Aggiungi" alt="Aggiungi"></span></a>
                                <a class="btn btn-xs btn-default" style='vertical-align: middle;' onclick="OSSMailView_Widget_Js.selectRecord('{$RECORD_ID}');"><span class='glyphicon glyphicon-search'  style='vertical-align: middle;' border='0' title="Relaziona" alt="Relaziona"></span></a>
                                <a class="btn btn-xs btn-default" style='vertical-align: middle;' target="_blank" href="index.php?module=OSSMailView&view=Detail&record={$RECORD_ID}"><span class='glyphicon glyphicon-link'  style='vertical-align: middle;' border='0' title="Apri" alt="Apri"></span></a>
                                <a class="btn btn-xs btn-default" style='vertical-align: middle;' onclick="OSSMailView_Widget_Js.setHasRelated('{$RECORD_ID}');"><span class='glyphicon glyphicon-ok'  style='vertical-align: middle;' border='0' title="Salta" alt="Salta"></span></a>
					</div>
				</div>
			{/foreach}
            </div>
		{if count($EMAILS) eq $PAGING_MODEL->getPageLimit()}
			<div class="pull-right padding5">
				<button type="button" class="btn btn-xs btn-primary showMoreHistory" data-url="{$WIDGET->getUrl()}&page={$PAGING_MODEL->getNextPage()}">{vtranslate('LBL_MORE', $MODULE_NAME)}</button>
			</div>
		{/if}
	{else}
		{if $PAGING_MODEL->getCurrentPage() eq 1}
			<span class="noDataMsg">
				{vtranslate('LBL_NO_RECORDS_MATCHED_THIS_CRITERIA')}
			</span>
		{/if}
	{/if}
    </div>
    </div>
{/strip}

