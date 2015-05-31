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
 *  @param callbackFunction A name of a function that should be called if the return was positive.
 */
function callUrlHideElement(elementId, url, callbackFunction) {
	callbackFunction = typeof callbackFunction !== 'undefined' ?  callbackFunction : "";
    entryDeleted = document.getElementById(elementId);
    var fn = window[callbackFunction];

    // send RequestObjekt and delete entry
    $.get(url, function(data) {
        if(data == "done") {
            $("#admidio_modal").modal("hide");
            
            if(typeof fn === "function") {
                $(entryDeleted).fadeOut("slow", fn());
            }
            else {
                $(entryDeleted).fadeOut("slow");                
            }
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

/** The function converts the format of the php date() function
 *  to the format that is used of the moment.js script.
 *  @param format A string with the format definition of the php date() function
 *  @return Format of moment.js script
 */
function formatPhpToMoment(format){
	var formatMap = {
        d: "DD",
        D: "ddd",
        j: "D",
        l: "dddd",
        N: "E",
        S: function(){
            return "["+this.format("Do").replace(/\d*/g, "")+"]";
        },
        w: "d",
        z: function(){
            return this.format("DDD") - 1;
        },
        W: "W",
        F: "MMMM",
        m: "MM",
        M: "MMM",
        n: "M",
        t: function(){
            return this.daysInMonth();
        },
        L: function(){
            return this.isLeapYear() ? 1 : 0;
        },
        o: "GGGG",
        Y: "YYYY",
        y: "YY",
        a: "a",
        A: "A",
        B: function(){
            var thisUTC = this.clone().utc(),
            	// Shamelessly stolen from http://javascript.about.com/library/blswatch.htm
            	swatch = ((thisUTC.hours()+1) % 24) + (thisUTC.minutes() / 60) + (thisUTC.seconds() / 3600);
            return Math.floor(swatch * 1000 / 24);
        },
        g: "h",
        G: "H",
        h: "hh",
        H: "HH",
        i: "mm",
        s: "ss",
        u: "[u]", // not sure if moment has this
        e: "[e]", // moment does not have this
        I: function(){
            return this.isDST() ? 1 : 0;
        },
        O: "ZZ",
        P: "Z",
        T: "[T]", // deprecated in moment
        Z: function(){
            return parseInt(this.format("ZZ"), 10) * 36;
        },
        c: "YYYY-MM-DD[T]HH:mm:ssZ",
        r: "ddd, DD MMM YYYY HH:mm:ss ZZ",
        U: "X"
	},
	formatEx = /[dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU]/g;

	return format.replace(formatEx, function(phpStr){
	  return formatMap[phpStr];
	});
};
