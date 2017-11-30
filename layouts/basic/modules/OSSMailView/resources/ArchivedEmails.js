/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 *************************************************************************************/

var OSSMailView_Widget_Js = {
	setHasRelated: function (recordId) {
		var thisInstance = this;

		var url = 'index.php?module=OSSMailView&action=SetHasRelated';
        url += '&sourceRecord=' + recordId;
		var progress = jQuery.progressIndicator();
		thisInstance.callHasRelated(url).then(function (data) {
			progress.progressIndicator({
				'mode': 'hide'
			});
            var wContainer = jQuery('li[data-name="EmailsArchived"]');
            wContainer.find('a[name="drefresh"]').trigger('click');
		});
	},
    callHasRelated: function (url) {
		var thisInstance = this;
		var aDeferred = jQuery.Deferred();
		var requestParams;
		requestParams = url;
		AppConnector.request(requestParams).then(function (data) {
			aDeferred.resolve(data);
		});
		return aDeferred.promise();
	},
    addRecord: function(id) {
        var module = jQuery('select[name="tempSelect' + id + '"]').find('option:selected').val();
        this.showQuickCreateForm(module, id);
    },
    showQuickCreateForm: function(moduleName, record, params) {
        if (params == undefined) {
            var params = {};
        }
        var relatedParams = {};
        if (params['sourceModule']) {
            var sourceModule = params['sourceModule'];
        } else {
            var sourceModule = 'OSSMailView';
        }
        var postShown = function (data) {
            var index, queryParam, queryParamComponents;
            $('<input type="hidden" name="sourceModule" value="' + sourceModule + '" />').appendTo(data);
            $('<input type="hidden" name="sourceRecord" value="' + record + '" />').appendTo(data);
            $('<input type="hidden" name="relationOperation" value="true" />').appendTo(data);
        }

        // Aggiungere valori all'array relatedParams per mappare i valori in creazione su nuovo modulo
        // var subject = jQuery('div[name="rowsubject' + record + '"]').text();
        // relatedParams['arcmailoggetto'] = subject;

        var postQuickCreate = function (data) {
            var wContainer = jQuery('li[data-name="EmailsArchived"]');
            wContainer.find('a[name="drefresh"]').trigger('click');
        }

        relatedParams['sourceModule'] = sourceModule;
        relatedParams['sourceRecord'] = record;
        relatedParams['relationOperation'] = true;
        var quickCreateParams = {
            callbackFunction: postQuickCreate,
            callbackPostShown: postShown,
            data: relatedParams,
            noCache: true
        };
        var headerInstance = new Vtiger_Header_Js();
        headerInstance.quickCreateModule(moduleName, quickCreateParams);
    },
   selectRecord: function(id) {
        var sourceFieldElement = jQuery('input[name="tempField' + id  + '"]');
        var relParams = {
            mailId: id
        };
        var module = jQuery('select[name="tempSelect' + id + '"]').find('option:selected').val();
         
        var PopupParams = {
            module: module,
            src_module: module,
            src_field: 'tempField' + id,
            src_record: '',
            url: window.location.href + '?'
        };
        this.showPopup(PopupParams, sourceFieldElement, relParams);
    },
    showPopup: function (params, sourceFieldElement, actionsParams) {
        actionsParams['newModule'] = params['module'];
        var prePopupOpenEvent = jQuery.Event(Vtiger_Edit_Js.preReferencePopUpOpenEvent);
        sourceFieldElement.trigger(prePopupOpenEvent);
        var data = {};
        this.show(params, function (data) {
            var responseData = JSON.parse(data);
            for (var id in responseData) {
                var data = {
                    name: responseData[id].name,
                    id: id
                }
                sourceFieldElement.val(data.id);
            }
            actionsParams['newCrmId'] = data.id;
            var params = {}
            params.data = {
                module: 'OSSMail',
                action: 'executeActions',
                mode: 'addRelated',
                params: actionsParams
            }
            params.async = false;
            params.dataType = 'json';
            AppConnector.request(params).then(function (data) {
                var response = data['result'];
                if (response['success']) {
                    var notifyParams = {
                        text: response['data'],
                        type: 'info',
                        animation: 'show'
                    };
                } else {
                    var notifyParams = {
                        text: response['data'],
                        animation: 'show'
                    };
                }
                Vtiger_Helper_Js.showPnotify(notifyParams);
                var wContainer = jQuery('li[data-name="EmailsArchived"]');
                wContainer.find('a[name="drefresh"]').trigger('click');
            });
        });
    },
    show: function (urlOrParams, cb, windowName, eventName, onLoadCb) {
        var thisInstance = Vtiger_Popup_Js.getInstance();
        if (typeof urlOrParams == 'undefined') {
            urlOrParams = {};
        }
        if (typeof urlOrParams == 'object' && (typeof urlOrParams['view'] == "undefined")) {
            urlOrParams['view'] = 'Popup';
        }
        if (typeof eventName == 'undefined') {
            eventName = 'postSelection' + Math.floor(Math.random() * 10000);
        }
        if (typeof windowName == 'undefined') {
            windowName = 'test';
        }
        if (typeof urlOrParams == 'object') {
            urlOrParams['triggerEventName'] = eventName;
        } else {
            urlOrParams += '&triggerEventName=' + eventName;
        }
        var urlString = (typeof urlOrParams == 'string') ? urlOrParams : jQuery.param(urlOrParams);
        var url = urlOrParams['url'] + urlString;
        var popupWinRef = window.open(url, windowName, 'width=800,height=650,resizable=0,scrollbars=1');
        if (typeof thisInstance.destroy == 'function') {
            thisInstance.destroy();
        }
        jQuery.initWindowMsg();
        if (typeof cb != 'undefined') {
            thisInstance.retrieveSelectedRecords(cb, eventName);
        }
        if (typeof onLoadCb == 'function') {
           jQuery.windowMsg('Vtiger.OnPopupWindowLoad.Event', function (data) {
                onLoadCb(data);
            })
        }
        return popupWinRef;
    },
}

