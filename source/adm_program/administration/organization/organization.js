/******************************************************************************
 * Javascript-Klasse zur Darstellung der Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function organizationClass()
{
	// Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
	var ids = new Array();
	var ecard_CCRecipients = "";
	var ecard_textLength = "";
	var forum_Server = "";
	var forum_User = "";
	var forum_PW = "";
	var forum_DB = "";
	var text_Server = "";
	var text_User = "";
	var text_PW = "";
	var text_DB = "";

	this.init = function()
	{
		if(jQuery.isArray(this.ids) && this.ids.length > 0)
		{
			for(var i = 0;i<this.ids.length;i++)
			{
				$("#" + this.ids[i] + "_link").click(function() {
					organizationJS.toggleDiv($(this).attr("id").split("_",2)[0]);
				});
			}
		}		
		$("#accordion-common").accordion({
			active: false,
			autoHeight: false,
			collapsible: true,
			change: function(event, ui) {
				var selItem;
				var posNew = ui.newHeader.position();
				var posOld = ui.oldHeader.position();
				if( posNew != null ) {
					selItem = ui.newHeader;
				}
				else if( posOld != null ) {
					selItem = ui.oldHeader;
				}
				$.scrollTo(selItem,800,{axis:'y'});
			}
		});
		$("#accordion-modules").accordion({
			active: false,
			autoHeight: false,
			collapsible: true,
			change: function(event, ui) {
				var selItem;
				var posNew = ui.newHeader.position();
				var posOld = ui.oldHeader.position();
				if( posNew != null ) {
					selItem = ui.newHeader;
				}
				else if( posOld != null ) {
					selItem = ui.oldHeader;
				}
				$.scrollTo(selItem,800,{axis:'y'});
			}
		});
		$("#org_longname").focus();
		$("#tabs").tabs();
	}
	// Die eigentliche Funktion: Schaltet die Einstellungsdialoge durch
	this.toggleDiv = function(element_id)
	{
		for (var i=0;i<this.ids.length;i++)
		{
			// Erstmal alle DIVs aus unsichtbar setzen
			$("#" + this.ids[i]).css("display","none");
		}
		// Angeforderten Bereich anzeigen
		$("#" + element_id).css("display","block");
	}

	// Versteckt oder zeigt weitere Einstellungsmöglichkeiten
	this.showHideMoreSettings = function(LayerSetting,LayerSwith,LayerSettingName,Setting)
	{
		if($("#" + LayerSwith).val() == "1")
		{
			if(Setting == 0)
			{
				$("#" + LayerSetting).html('<input type="text" id="LayerSettingName" name="LayerSettingName" size="4" maxlength="4" value="' + this.ecard_CCRecipients + '" />');
			}
			else if(Setting == 1)
			{
				$("#" + LayerSetting).html('<input type="text" id="LayerSettingName" name="LayerSettingName" size="4" maxlength="4" value="' + this.ecard_textLength + '" />');
			}
		}
		else if($("#" + LayerSetting))
		{
				$("#" + LayerSetting).empty();
		}
	}
}