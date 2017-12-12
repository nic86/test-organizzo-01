/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
Vtiger_Edit_Js("Documenti_Edit_Js",{},{

    /**
	 * Function to check for Portal User
	 */
	checkFilename : function(form){
		var thisInstance = this;
		var element = form.find('[name="docfilename"]',form);
        var file = element[0].files[0];
        var filename = false;
        var recordId = form.find('input[name="record"]').val();

        if (file){
            filename = file.name;
        }

        if (!filename || recordId == undefined || recordId == '') {
            return true;
        }
        var params = {
            'filename' : filename,
            'record'   : recordId,
        };

        thisInstance.validateFilename(params).then(
            function (data) {
                var result = data.result.success;
                if (result) {
                    return true;
                } else {
                    var params = {
                        title: 'Attenzione',
                        text: data.result.message
                    };
                    Vtiger_Helper_Js.showPnotify(params);
                    return false;
                }
            },
            function (error, err) {
                var params = {
                    title: 'Attenzione',
                    text: err
                };
                Vtiger_Helper_Js.showPnotify(params);
                return false;
            }
        );
		return false;
	},
     validateFilename: function (params) {
		var aDeferred = jQuery.Deferred();
		var url = "index.php?module=" + app.getModuleName() + "&action=ValidateFileName&record=" + params['record'] + "&filename=" + params['filename'];
		AppConnector.request(url).then(
            function (data) {
                if (data['success']) {
                    aDeferred.resolve(data);
                } else {
                    aDeferred.reject(data['message']);
                }
            },
            function (error) {
                aDeferred.reject();
            }
		)
		return aDeferred.promise();
	},
	/**
	 * Function to register recordpresave event
	 */
	registerRecordPreSaveEvent : function(form){
		var thisInstance = this;
		if(typeof form == 'undefined') {
			form = this.getForm();
		}

		form.on(Vtiger_Edit_Js.recordPreSave, function(e, data) {
			return thisInstance.checkFilename(form);
		});
	},

	registerBasicEvents : function(container){
		this._super(container);
		this.registerRecordPreSaveEvent(container);
	}
});
