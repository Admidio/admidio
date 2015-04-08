/******************************************************************************
 * Common JavaScript functions that are used in multiple Admidio scripts.
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
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
function showHideBlock(elementID) {
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

var entryDeleted;

/** This function can be used to call a specific url and hide an html element 
 *  in dependence from the returned data. If the data received is "done" then 
 *  the element will be hidden otherwise the data will be shown in an error block.
 *  @param elementId This is the id of a html element that should be hidden.
 *  @param url       This is the url that will be called.
 */
function callUrlHideElement(elementId, url) {
    entryDeleted = document.getElementById(elementId);

    // send RequestObjekt and delete entry
    $.get(url, function(data) {
        if(data == "done") {
            $("#admidio_modal").modal("hide")
            $(entryDeleted).fadeOut("slow");
			'.$callbackSuccess.'
        }
        else {
			// entry could not be deleted, than show content of data or an common error message
			$("#btn_yes").hide();
			$("#btn_no").hide();
			$("#btn_close").attr("class", "btn btn-default");
			var html = $("#message_text").html();
			
			if(data.length > 0) {
				$("#message_text").html(html + "<br /><div class=\"alert alert-danger form-alert\"><span class=\"glyphicon glyphicon-exclamation-sign\">" + data + "</span></div>");
			} else {
				$("#message_text").html(html + "<br /><div class=\"alert alert-danger form-alert\"><span class=\"glyphicon glyphicon-exclamation-sign\">Error: Entry not deleted</span></div>");
			}
        }
    });
}