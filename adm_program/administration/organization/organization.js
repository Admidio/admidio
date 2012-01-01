/******************************************************************************
 * Javascript-Klasse zur Darstellung der Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
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
	this.drawForumAccessDataTable = function()
	{
		if($("#forum_access_data").length <= 0) return;
		if($("#forum_sqldata_from_admidio:checked").length > 0)
		{
			$("#" + "forum_access_data").hide("slow",function(){
				$("#" + "forum_access_data_text").hide("slow");
			});
		}
		else if ($("#forum_sqldata_from_admidio:checked").length <= 0)
		{
			var ElementsArray = Array("forum_srv","forum_usr","forum_pw","forum_db");
			var ValuesArray = Array();
			ValuesArray[0] = Array(this.text_Server,"TEXT","50", "200",this.forum_Server);
			ValuesArray[1] = Array(this.text_User,"TEXT","50", "200",this.forum_User);
			ValuesArray[2] = Array(this.text_PW,"PASSWORD","50", "200",this.forum_PW);
			ValuesArray[3] = Array(this.text_DB,"TEXT","50", "200",this.forum_DB);
			
			$("#" + "forum_access_data").empty();
			$("#" + "forum_access_data").css("display","none");
			//var li = "";
			var dl = "";
			var dt = "";
			var dd = "";
			var label = "";
			var input = "";
			for(var i = 0; i < ElementsArray.length;i++)
			{
					//li = document.createElement("LI");
					dl = document.createElement("DL");
					dt = document.createElement("DT");
					dd = document.createElement("DD");
					label = document.createElement("label");
					input = document.createElement("input");
					label.appendChild(document.createTextNode(ValuesArray[i][0]));
					input.type=ValuesArray[i][1];
					input.id = ElementsArray[i];
					input.name = ElementsArray[i];
					input.maxlength = ValuesArray[i][2];
					input.width = ValuesArray[i][3];
					input.value = ValuesArray[i][4];
					//li.appendChild(dl);
					dl.appendChild(dt);
					dl.appendChild(dd);
					dd.appendChild(input);
					dt.appendChild(label);
					$("#" + "forum_access_data").append(dl);
			}
			$("#" + "forum_access_data").show("slow",function(){
				$("#" + "forum_access_data_text").show("slow");
			});
		}
	}
}