/**
 ***********************************************************************************************
 * Common JavaScript functions that are used in multiple Admidio scripts.
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * The function can be used to show or hide a element. Therefore a small
 * caret is used that will change his orientation if the element is hidden.
 * @param {string} elementId This is the id of the element you must click to show or hide another element.
 *                           The elements have the same id but the element to click has a prefix **group_**
 */
function showHideBlock(elementId) {
    var showHideElementId = $('#'+elementId).attr("id").substring(6);

    if($("#"+showHideElementId).is(":hidden")) {
        $("#"+showHideElementId).show("slow");
        $('#'+elementId+" .fas").attr("class", "fas fa-caret-down")
    } else {
        $("#"+showHideElementId).hide("slow");
        $('#'+elementId+" .fas").attr("class", "fas fa-caret-right")
    }
}

/**
 * This function can be used to call a specific url and hide an html element
 * in dependence from the returned data. If the data received is "done" then
 * the element will be hidden otherwise the data will be shown in an error block.
 * @param {string}   elementId  This is the id of a html element that should be hidden.
 * @param {string}   url        This is the url that will be called.
 * @param {string}   csrfToken  If this is set than it will be added to the post request.
 * @param {function} [callback] A name of a function that should be called if the return was positive.
 */
function callUrlHideElement(elementId, url, csrfToken, callback) {
    var entryDeleted = document.getElementById(elementId);

    // send RequestObject and delete entry
    $.post(url, {"admidio-csrf-token": csrfToken}, function(data) {
        if (data === "done") {
            $("#admidio-modal").modal("hide");

            if (callback === 'callbackRoles') {
                $(entryDeleted).fadeOut("slow", callbackRoles);
            } else if (callback === 'callbackFormerRoles') {
                $(entryDeleted).fadeOut("slow", callbackFormerRoles);
            } else if (callback === 'callbackFutureRoles') {
                $(entryDeleted).fadeOut("slow", callbackFutureRoles);
            } else if (callback === 'callbackProfilePhoto') {
                callbackProfilePhoto();
            } else {
                $(entryDeleted).fadeOut("slow");
            }
        } else {
            // entry could not be deleted, than show content of data or an common error message
            $("#btn_yes").hide();
            $("#btn_no").hide();
            $("#btn_close").attr("class", "btn btn-secondary");

            var message = (data.length > 0) ? data : "Error: Entry not deleted";
            var messageText = $("#message_text");
            messageText.html(messageText.html() + "<br /><div class=\"alert alert-danger form-alert\"><i class=\"fas fa-exclamation-circle\"></i>" + message + "</div>");
        }
    });
}

/**
 * The function converts the format of the php date() function
 * to the format that is used of the luxon.js script.
 * @param {string} format A string with the format definition of the php date() function
 * @return {string} Format of moment.js script
 */
function formatPhpToLuxon(format) {
    var formatMap = {
        d: "dd",
        D: "ccc",
        j: "d",
        l: "cccc",
        N: "E",
        S: function() {
            return "[" + this.format("Do").replace(/\d*/g, "") + "]";
        },
        w: "d",
        z: function() {
            return this.format("DDD") - 1;
        },
        W: "W",
        F: "MMMM",
        m: "MM",
        M: "MMM",
        n: "M",
        t: function() {
            return this.daysInMonth();
        },
        L: function() {
            return this.isLeapYear() ? 1 : 0;
        },
        o: "GGGG",
        Y: "yyyy",
        y: "yy",
        a: "a",
        A: "A",
        B: function() {
            var thisUTC = this.clone().utc(),
                // Shamelessly stolen from http://javascript.about.com/library/blswatch.htm
                swatch = ((thisUTC.hours() + 1) % 24) + (thisUTC.minutes() / 60) + (thisUTC.seconds() / 3600);
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
        I: function() {
            return this.isDST() ? 1 : 0;
        },
        O: "ZZ",
        P: "Z",
        T: "[T]", // deprecated in moment
        Z: function() {
            return parseInt(this.format("ZZ"), 10) * 36;
        },
        c: "YYYY-MM-DD[T]HH:mm:ssZ",
        r: "ddd, DD MMM YYYY HH:mm:ss ZZ",
        U: "X"
    },
    formatEx = /[dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU]/g;

    return format.replace(formatEx, function(phpStr) {
        return formatMap[phpStr];
    });
}

function redirectPost(url, data) {
    var form = document.createElement("form");
    document.body.appendChild(form);
    form.method = "post";
    form.action = url;
    for (var name in data) {
        if (data.hasOwnProperty(name)) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = data[name];
            form.appendChild(input);
        }
    }
    form.submit();
}

/**
 * The function will move a table row one step up or down in the current table.
 * After that an url is called that should update the database with the new sequence of the row object.
 * @param {string} direction The direction in which the row should be moved.
 *                 Valid values are UP or DOWN.
 * @param {string} elementId Id of the row that should be moved
 * @param {string} updateSequenceUrl Url to update the sequence of the element in the database
 * @param {string} csrfToken  If this is set than it will be added to the post request.
 */
function moveTableRow(direction, elementId, updateSequenceUrl, csrfToken) {
    $.post(updateSequenceUrl, {"admidio-csrf-token": csrfToken}, function(data) {
        if (data === "done") {
            var id = "#" + elementId;
            $(".admidio-icon-link .fas").tooltip("hide");

            if (direction === "UP") {
                $(id).prev().before($(id));
            } else {
                $(id).next().after($(id));
            }
        } else {
            if(data.length > 0) {
                alert(data);
            }
        }
    });
}
