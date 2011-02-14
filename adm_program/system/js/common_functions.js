/******************************************************************************
 * Allgemeine JavaScript-Funktionen, die an diversen Stellen in Admidio 
 * benoetigt werden
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
// das uebergebene Element wird optisch schick ein- und ausgeblendet
// diese Funktion kann nicht auf <table>-Elemente angewendet werden
function toggleElement(elementID, iconID)
{
    if($("#" + elementID).css("display") == "none")
    {
        $("#" + elementID).show("slow");
        if($("#" + iconID).length > 0)
        {
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_open.gif");
            $("#" + iconID).attr("title", "Ausblenden");
            $("#" + iconID).attr("alt",   "Ausblenden");
        }
    }
    else
    {
        $("#" + elementID).hide("slow");
        if($("#" + iconID).length > 0)
        {    
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_close.gif");
            $("#" + iconID).attr("title", "Einblenden");
            $("#" + iconID).attr("alt",   "Einblenden");
        }
    }
}

// Identisch zu toggleElement allerdings nicht so schick, 
// dafuer aber auch auf <table>-Element anwendbar
function showHideBlock(elementID)
{
	var imageID = 'img_' + elementID;
    
    if($("#" + elementID).css("display") == "none")
    {
        $("#" + elementID).css("visibility", "visible")
        $("#" + elementID).css("display", "")
        $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_open.gif");
        $("#" + iconID).attr("title", "Ausblenden");
        $("#" + iconID).attr("alt",   "Ausblenden");
    }
    else
    {
        $("#" + elementID).css("visibility", "hidden")
        $("#" + elementID).css("display", "none")
        $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_close.gif");
        $("#" + iconID).attr("title", "Einblenden");
        $("#" + iconID).attr("alt",   "Einblenden");
    }
}

// Stellt die Javascript Alert-Funktion als jQuery Colorbox dar
function jQueryAlert(messageID, messageVAR1, messageVAR2, widthID, heightID)
{
	var urlparameter = messageID;
	
	if (typeof(messageVAR1) != "undefined")
	{
		urlparameter = urlparameter + "&message_var1" + messageVAR1;
	}
	
	if (typeof(messageVAR2) != "undefined")
	{
		urlparameter = urlparameter + "&message_var2" + messageVAR2;
	}
	
	if (typeof(widthID) == "undefined")
	{
		var widthID = 350;
	}
	if (typeof(heightID) == "undefined")
	{
		var heightID = 175;
	}

	jQuery().colorbox({href: gRootPath + "/adm_program/system/jquery_alert.php?alert_message=" + urlparameter, open:true, iframe:true, width:widthID, height:heightID});	
}