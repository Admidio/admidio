/******************************************************************************
 * Common JavaScript functions that are used in multiple Admidio scripts.
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

/** The function can be used to show or hide a element. Therefore a small
 *  caret is used that will change his orientation if the element is hidden.
 *  @param elementId This is the id of the element you must click to show or hide
 *                   another element. The elements have the same id but the element
 *                   to click has a prefix @b group_
 */
function showHideBlock(elementID)
{
    var showHideElementId = elementID.substring(6);
    var caretId = "caret_" + showHideElementId;
    
    if($("#" + showHideElementId).css("display") == "none") {
        $("#" + showHideElementId).show("slow");
        if($("#" + caretId).length > 0) {
            $("#" + caretId).attr("class", "caret");
        }
    }
    else {
        $("#" + showHideElementId).hide("slow");
        if($("#" + caretId).length > 0) {    
            $("#" + caretId).attr("class", "caret-right");
        }
    }
}

// Stellt die Javascript Alert-Funktion als jQuery Colorbox dar
function jQueryAlert(messageID, messageVAR1, messageVAR2, widthID, heightID)
{
	var urlparameter = messageID;
	
	if (typeof(messageVAR1) != "undefined")
	{
		urlparameter = urlparameter + "&message_var1=" + messageVAR1;
	}
	
	if (typeof(messageVAR2) != "undefined")
	{
		urlparameter = urlparameter + "&message_var2=" + messageVAR2;
	}
	
	if (typeof(widthID) == "undefined")
	{
		var widthID = 500;
	}
	if (typeof(heightID) == "undefined")
	{
		var heightID = 250;
	}

	jQuery().colorbox({href: gRootPath + "/adm_program/system/jquery_alert.php?alert_message=" + urlparameter, open:true, iframe:true, width:widthID, height:heightID});	
}