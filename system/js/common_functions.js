/**
 ***********************************************************************************************
 * Common JavaScript functions that are used in multiple Admidio scripts.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * The function can be used to show or hide an element. Therefore, a small
 * caret is used that will change his orientation if the element is hidden.
 * @param {string} element This is the element you must click to show or hide another element.
 *                         The element must have a data-target attribut which contains the id of the
 *                         element to hide.
 */
function showHideBlock(element) {
    var targetElementId = $(element).data("target");
    if($("#" + targetElementId).is(":hidden")) {
        $("#" + targetElementId).show("slow");
        $("#" + $(element).attr("id") + " .bi").attr("class", "bi bi-caret-down-fill")
    } else {
        $("#" + targetElementId).hide("slow");
        $("#" + $(element).attr("id") + " .bi").attr("class", "bi bi-caret-right-fill")
    }
}

/**
 * This function checks if a tbody element is empty (i.e., has no visible tr elements).
 * @param   {HTMLElement} tbodyElement  The tbody element to check.
 * @returns {boolean}                   True if the tbody element is empty, false otherwise.
 */
function isTbodyEmpty(tbodyElement) {
    rows = tbodyElement.querySelectorAll("tr");
    count = 0;

    for (let i = 0; i < rows.length; i++) {
        if (rows[i].style.display === "none") {
            continue;
        }
        else {
            count++;
        }
    }

    if (count === 1) {
        return true;   // the tbody element only holds the last visible element
    }
    else {
        return false;
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
    if (!entryDeleted) {
        entryDeleted = document.getElementById("row_" + elementId);
    }

    // send RequestObject and delete entry
    $.post(url, {
        "adm_csrf_token": csrfToken,
        "uuid": elementId
        }, function(data) {
        const messageText = $("#adm_status_message");
        var returnStatus = "error";
        var returnMessage = "";

        try {
            const returnData = JSON.parse(data);
            returnStatus = returnData.status;
            if (typeof returnData.message !== 'undefined') {
                returnMessage = returnData.message;
            }
        } catch (e) {
            // fallback for old implementation without JSON response
            if (data === "done") {
                returnStatus = "success";
            } else {
                returnMessage = data;
            }
        }

        if (returnStatus === "success") {
            if (returnMessage !== "") {
                messageText.html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg\"></i>" + returnMessage + "</div>");
                setTimeout(function(){
                        $("#adm_modal").modal("hide");
                        $("#adm_modal_messagebox").modal("hide");
                        if (callback === "callbackRoles") {
                            $(entryDeleted).fadeOut("slow", callbackRoles);
                        } else if (callback === "callbackFormerRoles") {
                            $(entryDeleted).fadeOut("slow", callbackFormerRoles);
                        } else if (callback === "callbackFutureRoles") {
                            $(entryDeleted).fadeOut("slow", callbackFutureRoles);
                        } else if (callback === "callbackProfilePhoto") {
                            callbackProfilePhoto();
                        } else {
                            $(entryDeleted).fadeOut("slow");
                        }
                    }, 2000);
            } else {
                $("#adm_modal").modal("hide");
                $("#adm_modal_messagebox").modal("hide");
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
            }

            var tbodyElement = entryDeleted.closest("tbody");
            if (isTbodyEmpty(tbodyElement)) {
                $(tbodyElement).fadeOut("slow");
                var tbodyElement2 = tbodyElement.previousElementSibling;
                $(tbodyElement2).fadeOut("slow");
            }
            else {
                console.log("tbody is not empty");
            }
        } else {
            // entry could not be deleted, then show content of data or a common error message
            if (returnMessage.length === 0) {
                returnMessage = "Error: Undefined error occurred!";
            }
            messageText.html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill\"></i>" + returnMessage + "</div>");
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
    const formatMap = {
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
 * @param element Element that is clicked. Element should have data-uuid, data-direction and data-target attributes
 * @param {string} updateSequenceUrl Url to update the sequence of the element in the database
 * @param {string} csrfToken  If this is set than it will be added to the post request.
 */
function moveTableRow(element, updateSequenceUrl, csrfToken) {
    var uuid = $(element).data("uuid");
    var direction = $(element).data("direction");
    var target = $(element).data("target");

    $.post(updateSequenceUrl + "?mode=sequence&uuid=" + uuid + "&direction=" + direction, {
            "adm_csrf_token": csrfToken,
            "direction": direction,
            "uuid": uuid,
            "mode": "sequence"
        }, function(data) {
            var returnStatus = "error";
            var returnMessage = "";

            try {
                const returnData = JSON.parse(data);
                returnStatus = returnData.status;
                if (typeof returnData.message !== 'undefined') {
                    returnMessage = returnData.message;
                }
            } catch (e) {
                // fallback for old implementation without JSON response
                if (data === "done") {
                    returnStatus = "success";
                } else {
                    returnMessage = data;
                }
            }

            if (returnStatus === "success") {
                $(".admidio-icon-link .bi").tooltip("hide");

                if (direction === "UP") {
                    $("#"+target).prev().before($("#"+target));
                } else {
                    $("#"+target).next().after($("#"+target));
                }
            } else {
                // entry could not be deleted, then show content of data or a common error message
                if (returnMessage.length === 0) {
                    returnMessage = "Error: Undefined error occurred!";
                }
                alert(returnMessage);
            }
        });
}

/**
 * The function will show a modal window in bootstrap style with a message.
 * @param {string} message Text of the message that should be shown.
 * @param {string} title   Optional a title for the modal. If not set the default "notice" will be shown.
 * @param {string} type    Optional a type could be set.
 *                         "warning" - A warning icon and the message with alert-warning class will be shown
 *                         "error"   - A warning icon and the message with alert-danger class will be shown
 * @param {string} buttons Optional the setting of the buttons that should be shown.
 *                         "yes-no" - A primary "Yes" button with a secondary "No" button.
 * @param {string} href    Optional a link that will be called by a click of the "Yes" button..
 */
function messageBox(message, title, type, buttons, href) {
    $("#adm_status_message").html('');
    if (typeof title !== 'undefined') {
        $("#adm_modal_messagebox .modal-title").html(title);
    }
    if (typeof type === 'undefined') {
        $("#adm_modal_messagebox .modal-body").html("<p>" + message + "</p>");
    } else if (type === 'warning') {
        $("#adm_modal_messagebox .modal-body").html("<p class=\"alert alert-warning\"><i class=\"bi bi-exclamation-triangle-fill\"  style=\"font-size: 2rem;\"></i>" + message + "</p>");
    } else if (type === 'error') {
        $("#adm_modal_messagebox .modal-body").html("<p class=\"alert alert-danger\"><i class=\"bi bi-exclamation-triangle-fill\"  style=\"font-size: 2rem;\"></i>" + message + "</p>");
    }
    if (typeof buttons === 'undefined') {
        $("#adm_modal_messagebox .modal-footer").hide();
    } else if (buttons === 'yes-no') {
        $("#adm_messagebox_button_yes").attr('onClick', href);
    }

    const myModalAlternative = new bootstrap.Modal("#adm_modal_messagebox");
    myModalAlternative.show();
}

/**
 * The function will override the submitting of a form. It will call the action url and handle the response
 * of that url. Therefore, a json with status and message key is expected. Also a url key must be provided to
 * which the user will be guided if the form was successfully processed.
 */
function formSubmit(event) {
    var submitButtonID = $("#" + $(this).attr("id") + " button[type=submit]").attr("id");
    var submitButtonIcon = $("#" + submitButtonID + " i");
    var iconClass = submitButtonIcon.attr("class");
    var formAlert = $("#" + $(this).attr("id") + " .form-alert");
    submitButtonIcon.attr("class", "spinner-border spinner-border-sm");
    $("#" + submitButtonID).attr("disabled", true);
    formAlert.hide();

    // disable default form submit
    event.preventDefault();

    $.post({
        url: $(this).attr("action"),
        data: new FormData($(this)[0]),
        processData: false,
        contentType: false,
        success: function(data) {
            try {
                var returnData = JSON.parse(data);
                var returnStatus = returnData.status;
                var returnMessage = "";
                var forwardUrl = "";

                if (typeof returnData.message !== "undefined") {
                    returnMessage = returnData.message;
                }
                if (typeof returnData.url !== "undefined") {
                    forwardUrl = returnData.url;
                }
            } catch (e) {
                if (typeof $(".modal-body") !== "undefined") {
                    $(".modal-body").html(data);
                }
                // no expected JSON response
                returnStatus = "error";
                returnMessage = "Something went wrong while processing your request.<p>" + data + "</p>";
            }

            if (returnStatus === "success") {
                if (returnMessage.length > 0) {
                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"bi bi-check-lg\"></i><strong>" + returnMessage + "</strong>");
                    formAlert.fadeIn("slow");
                    if (forwardUrl !== "") {
                        setTimeout(function () {
                            self.location.href = forwardUrl;
                        }, 2500);
                    } else {
                        $("#" + submitButtonID).attr("disabled", false);
                        submitButtonIcon.attr("class", iconClass);
                        setTimeout(function () {
                            $("#adm_modal").modal("hide");
                            $(".form-alert").hide("slow");
                        }, 2500);
                    }
                } else {
                    self.location.href = forwardUrl;
                }
            } else {
                if ($("#adm_captcha").length > 0) {
                    $("#adm_captcha").attr("src", gRootPath + "/libs/securimage/securimage_show.php?" + Math.random());
                    $("#adm_captcha_code").val("");
                }
                if (returnMessage.length == 0) {
                    returnMessage = "Error: Undefined error occurred!";
                }
                $("#" + submitButtonID).attr("disabled", false);
                submitButtonIcon.attr("class", iconClass);
                formAlert.attr("class", "alert alert-danger form-alert");
                formAlert.html("<i class=\"bi bi-exclamation-circle-fill\"></i>" + returnMessage);
                formAlert.fadeIn();
            }
        }
    });
}
