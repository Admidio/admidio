/******************************************************************************
 * Allgemeine JavaScript-Funktionen, die an diversen Stellen in Admidio 
 * benoetigt werden
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// das uebergebene Element wird optisch schick ein- und ausgeblendet
// soll ein Pfeil-Icon veraendert werden, muss dies die ID des Elements
// mit dem Suffix 'Image' besitzen. Der Text des Icons wird mit uebergeben
function showHideBlock(elementID, textFadeIn, textHide)
{
	var iconID = elementID + 'Image';
    
    if($("#" + elementID).css("display") == "none")
    {
        $("#" + elementID).show("slow");
        if($("#" + iconID).length > 0)
        {
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_open.gif");
            $("#" + iconID).attr("title", textHide);
            $("#" + iconID).attr("alt",   textHide);
        }
    }
    else
    {
        $("#" + elementID).hide("slow");
        if($("#" + iconID).length > 0)
        {    
            $("#" + iconID).attr("src",   gThemePath + "/icons/triangle_close.gif");
            $("#" + iconID).attr("title", textFadeIn);
            $("#" + iconID).attr("alt",   textFadeIn);
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