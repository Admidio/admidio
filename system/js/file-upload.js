/**
 ***********************************************************************************************
 * Upload of files and photos with drag & drop, the drop zone, the correction of invalid file names and the
 * progress of the upload. The page part is created by FileUpload::getHtml() or FileUpload::getModalHtml(),
 * FileUpload::setHeaderData() adds this file and calls admFileUpload() with the settings of the page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Initialize the upload of the page.
 * @param {Object}  config
 * @param {boolean} config.isModal   The upload is shown in a dialog of the folder page, otherwise on its own page.
 * @param {string}  config.uploadUrl Url that receives the files (system/file_upload.php, mode upload_files).
 * @param {string}  config.csrfToken CSRF token of the session.
 * @param {number}  config.maxChunkSize Maximum size of one request in bytes.
 * @param {string}  config.backUrl   Url of the back button of the upload page.
 * @param {string}  config.invalidFilenamePattern Regular expression of the characters that are not allowed in file names.
 * @param {Object}  config.texts     Translated texts: fileUploaded, uploadSuccessful, uploadNotSuccessful, renamedTo,
 *                                   uploadCancel, uploadCancelled, uploading, remove, upload, filenameInvalidNotice,
 *                                   uploadCancelConfirm.
 */
function admFileUpload(config) {
    var countErrorFiles   = 0;
    var countFiles        = 0;
    var countAbortedFiles = 0;
    var dragCounter       = 0;
    var dropzone          = $("#admidio-dropzone");
    var isModal           = config.isModal;

    var pendingFiles      = {};
    var activeUploads     = {};
    var pendingSeq        = 0;
    var uploadSeq         = 0;

    var textFileUploaded          = config.texts.fileUploaded;
    var textUploadSuccessful      = config.texts.uploadSuccessful;
    var textUploadNotSuccessful   = config.texts.uploadNotSuccessful;
    var textRenamedTo             = config.texts.renamedTo;
    var textUploadCancel          = config.texts.uploadCancel;
    var textUploadCancelled       = config.texts.uploadCancelled;
    var textUploading             = config.texts.uploading;
    var textRemove                = config.texts.remove;
    var textUpload                = config.texts.upload;
    var textFilenameInvalidNotice = config.texts.filenameInvalidNotice;
    var textUploadCancelConfirm   = config.texts.uploadCancelConfirm;
    var invalidPattern            = config.invalidFilenamePattern;
    var invalidRegex              = new RegExp(invalidPattern);
    var invalidGlobalRegex        = new RegExp(invalidPattern, "g");

    /**
     * Escapes a text for the use in html, also in the value of an attribute (quotes are escaped as well).
     * @param {*} value Text that should be shown
     * @returns {string}
     */
    function escapeHtml(value) {
        return String(value === null || typeof value === "undefined" ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    /**
     * The server decodes a file name (urldecode) before it checks it. The same decoding is used here, so that
     * a name like "report%3F.txt" is detected as invalid before the upload.
     * @param {string} name File name
     * @returns {string}
     */
    function decodeFilename(name) {
        try {
            return decodeURIComponent(name);
        } catch (e) {
            return name;
        }
    }

    function isFilenameValid(name) {
        if (!name || typeof name !== "string") {
            return false;
        }
        var trimmed = decodeFilename(name).trim();
        if (trimmed === "" || trimmed.charAt(0) === ".") {
            return false;
        }
        if (trimmed.indexOf("/") !== -1 || trimmed.indexOf("\\") !== -1) {
            return false;
        }
        var lastDot = trimmed.lastIndexOf(".");
        if (lastDot <= 0 || lastDot === trimmed.length - 1) {
            return false;
        }
        return !invalidRegex.test(trimmed);
    }

    function sanitizeFilename(name) {
        if (!name || typeof name !== "string") {
            return "file";
        }
        var trimmed = decodeFilename(name).trim();
        var cleaned = trimmed.replace(invalidGlobalRegex, "-");
        cleaned = cleaned.replace(/^\.+/, "");
        var lastDot = cleaned.lastIndexOf(".");
        if (lastDot !== -1 && lastDot > 0) {
            var base = cleaned.substring(0, lastDot).replace(/\.{2,}/g, ".");
            var ext = cleaned.substring(lastDot + 1).replace(/\./g, "");
            base = base.replace(/^-+|-+$/g, "");
            cleaned = (base.length > 0 ? base : "file") + "." + ext;
        } else {
            cleaned = cleaned.replace(/^-+|-+$/g, "");
            cleaned = cleaned.length > 0 ? cleaned : "file";
        }
        cleaned = cleaned.replace(/-{2,}/g, "-");
        return cleaned;
    }

    /**
     * Badge that shows the name a file was renamed to before the upload.
     * @param {Object} file File object of jQuery File Upload (name = original name, uploadName = name after the correction)
     * @returns {string} Html, empty if the file was not renamed
     */
    function renamedBadgeHtml(file) {
        if (!file || !file.uploadName || file.uploadName === file.name) {
            return "";
        }
        var renamedText = textRenamedTo.replace("#VAR1_BOLD#", "<strong>" + escapeHtml(file.uploadName) + "</strong>");
        return " <span class=\"badge bg-secondary ms-2 small\">" + renamedText + "</span>";
    }

    var resultStyles = {
        success:   {box: "alert-success",   icon: "bi-check-lg",              color: "text-success"},
        error:     {box: "alert-danger",    icon: "bi-exclamation-circle-fill", color: "text-danger"},
        cancelled: {box: "alert-secondary", icon: "bi-dash-circle",           color: "text-secondary"}
    };

    /**
     * Shows the result of an upload, in the row of the upload or, if there is none, as a new message in the list.
     * All names in messageHtml and badgeHtml must be escaped (escapeHtml) or come already encoded from the server.
     * @param {jQuery|null} $row        Row of the upload (#upload-item-n) or null
     * @param {string}      kind        success, error or cancelled
     * @param {string}      messageHtml Message of the result
     * @param {string}      [badgeHtml] Optional badge after the message
     */
    function showResult($row, kind, messageHtml, badgeHtml) {
        var style = resultStyles[kind];
        badgeHtml = badgeHtml || "";
        if ($row && $row.length) {
            $row.removeClass("alert-light border").addClass(style.box);
            $row.html("<div class=\"d-flex align-items-center\"><i class=\"bi " + style.icon + " me-2 fs-5 " + style.color + "\"></i><span>"
                + messageHtml + "</span>" + badgeHtml + "</div>");
        } else {
            $("<div class=\"alert " + style.box + "\"><i class=\"bi " + style.icon + " me-2\"></i>" + messageHtml + badgeHtml + "</div>")
                .appendTo("#files");
        }
    }

    function isIdle() {
        return Object.keys(activeUploads).length === 0 && Object.keys(pendingFiles).length === 0;
    }

    /**
     * A new selection or drop after a finished upload starts with an empty list and fresh counters.
     */
    function resetResultsIfIdle() {
        if (isIdle()) {
            countErrorFiles = 0;
            countFiles = 0;
            countAbortedFiles = 0;
            $("#files").empty();
            $("#upload-summary-alert").remove();
        }
    }

    function checkIfAllFinished() {
        var activeCount = Object.keys(activeUploads).length;
        var pendingCount = Object.keys(pendingFiles).length;

        if (activeCount === 0) {
            $("#progress-wrapper").addClass("d-none");
            if (!isModal) {
                $("#back").removeClass("d-none");
            }

            if (pendingCount === 0 && (countFiles > 0 || countErrorFiles > 0 || countAbortedFiles > 0)) {
                $("#upload-summary-alert").remove();
                if (countErrorFiles === 0 && countAbortedFiles === 0 && countFiles > 0) {
                    $("<div id=\"upload-summary-alert\" class=\"alert alert-success mt-3\"><i class=\"bi bi-check-lg me-2 fs-5\"></i>" + textUploadSuccessful + "</div>").appendTo("#files");
                } else if (countFiles > 0 || countErrorFiles > 0) {
                    $("<div id=\"upload-summary-alert\" class=\"alert alert-danger mt-3\"><i class=\"bi bi-exclamation-circle-fill me-2 fs-5\"></i>" + textUploadNotSuccessful + "</div>").appendTo("#files");
                }
            }
        }
    }

    function updatePendingContainer() {
        var count = Object.keys(pendingFiles).length;
        if (count > 0) {
            $("#admidio-upload-invalid-container").removeClass("d-none");
        } else {
            $("#admidio-upload-invalid-container").addClass("d-none");
            checkIfAllFinished();
        }
    }

    function startUpload(data) {
        var file = data.files[0];
        var uploadId = ++uploadSeq;
        data.uploadId = uploadId;
        activeUploads[uploadId] = data;

        $("#progress-wrapper").removeClass("d-none");
        if (!isModal) {
            $("#back").addClass("d-none");
        }
        $("#upload-summary-alert").remove();

        var $row = $("<div class=\"upload-item alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-2\" id=\"upload-item-" + uploadId + "\">" +
            "<div class=\"d-flex align-items-center text-truncate me-2\">" +
                "<div class=\"spinner-border spinner-border-sm text-primary me-2 flex-shrink-0\" role=\"status\"></div>" +
                "<span class=\"fw-semibold text-truncate\">" + escapeHtml(file.name) + "</span>" +
                renamedBadgeHtml(file) +
                "<span class=\"text-muted small ms-2 upload-status-msg\">" + textUploading + "</span>" +
            "</div>" +
            "<button type=\"button\" class=\"btn btn-sm btn-outline-danger py-0 px-2 btn-abort-item\" title=\"" + textUploadCancel + "\">" +
                "<i class=\"bi bi-x-lg\"></i>" +
            "</button>" +
        "</div>");

        $row.find(".btn-abort-item").on("click", function () {
            if (data && data.abort) {
                data.abort();
            }
        });

        $("#files").append($row);
        data.submit();
    }

    function addPendingInvalidFile(data) {
        var file = data.files[0];
        var id = ++pendingSeq;
        var sanitized = sanitizeFilename(file.name);
        pendingFiles[id] = {
            id: id,
            data: data,
            originalName: file.name,
            sanitizedName: sanitized
        };

        var escapedOriginal = escapeHtml(file.name);
        var escapedSanitized = escapeHtml(sanitized);

        var $item = $("<div class=\"list-group-item px-3 py-3 border-bottom\" id=\"pending-item-" + id + "\">" +
            "<div class=\"row g-2 align-items-center\">" +
                "<div class=\"col-12 col-md-5\">" +
                    "<div class=\"text-truncate\" title=\"" + escapedOriginal + "\">" +
                        "<i class=\"bi bi-file-earmark-x text-danger me-1 align-middle\"></i>" +
                        "<span class=\"text-danger fw-semibold text-decoration-line-through align-middle\">" + escapedOriginal + "</span>" +
                    "</div>" +
                "</div>" +
                "<div class=\"col-8 col-md-5\">" +
                    "<input type=\"text\" class=\"form-control input-sanitized-name\" value=\"" + escapedSanitized + "\">" +
                    "<div class=\"invalid-feedback\">" + textFilenameInvalidNotice + "</div>" +
                "</div>" +
                "<div class=\"col-4 col-md-2 text-end\">" +
                    "<a class=\"admidio-icon-link btn-upload-single\" href=\"javascript:void(0);\" role=\"button\" title=\"" + textUpload + "\" aria-label=\"" + textUpload + "\">" +
                        "<i class=\"bi bi-upload\"></i>" +
                    "</a>" +
                    "<a class=\"admidio-icon-link btn-remove-single\" href=\"javascript:void(0);\" role=\"button\" title=\"" + textRemove + "\" aria-label=\"" + textRemove + "\">" +
                        "<i class=\"bi bi-trash\"></i>" +
                    "</a>" +
                "</div>" +
            "</div>" +
        "</div>");

        var $input = $item.find(".input-sanitized-name");
        var $btnUpload = $item.find(".btn-upload-single");

        function validateInput() {
            var val = $input.val().trim();
            if (isFilenameValid(val)) {
                $input.removeClass("is-invalid").addClass("is-valid");
                $btnUpload.removeClass("disabled").removeAttr("aria-disabled");
                return true;
            } else {
                $input.removeClass("is-valid").addClass("is-invalid");
                $btnUpload.addClass("disabled").attr("aria-disabled", "true");
                return false;
            }
        }

        $input.on("input keyup", validateInput);
        $input.on("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                $btnUpload.trigger("click");
            }
        });
        validateInput();

        $btnUpload.on("click", function () {
            if (!validateInput()) {
                return;
            }
            var newName = $input.val().trim();
            data.files[0].uploadName = newName;
            delete pendingFiles[id];
            $item.remove();
            updatePendingContainer();
            startUpload(data);
        });

        $item.find(".btn-remove-single").on("click", function () {
            delete pendingFiles[id];
            $item.remove();
            updatePendingContainer();
        });

        $("#admidio-upload-invalid-list").append($item);
        updatePendingContainer();
    }

    $("#btn-sanitize-all").on("click", function () {
        var valid = true;
        $("#admidio-upload-invalid-list .input-sanitized-name").each(function () {
            var val = $(this).val().trim();
            if (!isFilenameValid(val)) {
                $(this).removeClass("is-valid").addClass("is-invalid");
                valid = false;
            }
        });
        if (!valid) {
            return;
        }

        var toSubmit = [];
        $.each(pendingFiles, function (id, item) {
            var $row = $("#pending-item-" + id);
            var newName = $row.find(".input-sanitized-name").val().trim();
            item.data.files[0].uploadName = newName;
            toSubmit.push(item.data);
        });

        pendingFiles = {};
        $("#admidio-upload-invalid-list").empty();
        updatePendingContainer();

        $.each(toSubmit, function (i, d) {
            startUpload(d);
        });
    });

    $("#btn-cancel-pending, #btn-close-invalid").on("click", function () {
        pendingFiles = {};
        $("#admidio-upload-invalid-list").empty();
        updatePendingContainer();
    });

    $("#btn-cancel-all-uploads").on("click", function () {
        $.each(activeUploads, function (id, d) {
            if (d && d.abort) {
                d.abort();
            }
        });
        activeUploads = {};
        $("#progress-wrapper").addClass("d-none");
        checkIfAllFinished();
    });

    if (!isModal) {
        $("#back").click(function () {
            window.location.href = config.backUrl;
        });
    } else {
        var folderDragCounter = 0;

        function isDragFiles(e) {
            if (e.dataTransfer && e.dataTransfer.types) {
                for (var i = 0; i < e.dataTransfer.types.length; i++) {
                    if (e.dataTransfer.types[i] === "Files") {
                        return true;
                    }
                }
            }
            return false;
        }

        $(window).on("dragenter", function (e) {
            if (!isDragFiles(e.originalEvent)) return;
            if ($("#admidio-upload-modal").hasClass("show")) return;
            e.preventDefault();
            folderDragCounter++;
            if (folderDragCounter === 1) {
                $("#admidio-folder-drop-overlay").removeClass("d-none");
            }
        });

        $(window).on("dragover", function (e) {
            if (!isDragFiles(e.originalEvent)) return;
            e.preventDefault();
        });

        $(window).on("dragleave", function (e) {
            if (!isDragFiles(e.originalEvent)) return;
            e.preventDefault();
            folderDragCounter--;
            if (folderDragCounter <= 0) {
                folderDragCounter = 0;
                $("#admidio-folder-drop-overlay").addClass("d-none");
            }
        });

        $(window).on("drop", function (e) {
            folderDragCounter = 0;
            $("#admidio-folder-drop-overlay").addClass("d-none");
            if (!isDragFiles(e.originalEvent)) return;
            e.preventDefault();
            // while the dialog is open, the upload plugin handles the drop on the dialog itself
            if ($("#admidio-upload-modal").hasClass("show")) return;

            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                var modalEl = document.getElementById("admidio-upload-modal");
                if (modalEl) {
                    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();

                    if (Object.keys(activeUploads).length === 0 && Object.keys(pendingFiles).length === 0) {
                        countErrorFiles = 0;
                        countFiles = 0;
                        countAbortedFiles = 0;
                        $("#files").empty();
                        $("#upload-summary-alert").remove();
                    }

                    $("#fileupload").fileupload("add", { files: dt.files });
                }
            }
        });

        $("#menu_item_documents_upload_files, #menu_item_photos_upload_photo").on("click", function (e) {
            e.preventDefault();
            var modalEl = document.getElementById("admidio-upload-modal");
            if (modalEl) {
                var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
            }
        });

        $("#admidio-upload-modal").on("hidden.bs.modal", function () {
            if (countFiles > 0) {
                window.location.reload();
            }
        });

        $("#admidio-upload-modal").on("hide.bs.modal", function (e) {
            if (Object.keys(activeUploads).length > 0) {
                if (!confirm(textUploadCancelConfirm)) {
                    e.preventDefault();
                    return false;
                }
                $.each(activeUploads, function (id, d) {
                    if (d && d.abort) {
                        d.abort();
                    }
                });
                activeUploads = {};
            }
        });
    }

    // Clicking or keyboard interaction on the drop zone opens the file chooser
    dropzone.on("click", function (e) {
        if (!$(e.target).closest(".fileinput-button").length) {
            $("#fileupload").trigger("click");
        }
    }).on("keydown", function (e) {
        if ((e.key === "Enter" || e.key === " ") && !$(e.target).closest(".fileinput-button").length) {
            e.preventDefault();
            $("#fileupload").trigger("click");
        }
    });

    // Prevent default drag and drop handling to avoid browser navigating away
    $(document).on("dragenter dragover drop", function (e) {
        e.preventDefault();
    });

    // Drag visual indicators on dropzone
    dropzone.on("dragenter", function (e) {
        e.preventDefault();
        dragCounter++;
        dropzone.addClass("dragover");
    }).on("dragover", function (e) {
        e.preventDefault();
        dropzone.addClass("dragover");
    }).on("dragleave", function (e) {
        e.preventDefault();
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            dropzone.removeClass("dragover");
        }
    }).on("drop", function () {
        dragCounter = 0;
        dropzone.removeClass("dragover");
        resetResultsIfIdle();
    });

    $("#fileupload").on("change", function () {
        resetResultsIfIdle();
    });

    $("#fileupload").fileupload({
        url: config.uploadUrl,
        // the drop zone is part of the dialog content, so only the content is registered: a drop on the drop zone
        // would otherwise reach the plugin twice
        dropZone: isModal ? $("#admidio-upload-modal .modal-content") : dropzone,
        sequentialUploads: true,
        maxChunkSize: config.maxChunkSize,
        dataType: "json",
        formData: [{
            name: "adm_csrf_token",
            value: config.csrfToken
        }],
        add: function(e, data) {
            var file = data.files[0];
            if (!file) return;

            if (isFilenameValid(file.name)) {
                startUpload(data);
            } else {
                addPendingInvalidFile(data);
            }
        },
        done: function(e, data) {
            var uploadId = data.uploadId;
            if (uploadId && activeUploads[uploadId]) {
                delete activeUploads[uploadId];
            }
            var $row = uploadId ? $("#upload-item-" + uploadId) : null;

            $.each(data.result.files, function(index, file) {
                if (typeof file.error !== "undefined") {
                    countErrorFiles++;
                    // the error text comes encoded from the server
                    showResult($row, "error", escapeHtml(file.name) + " - <strong>" + file.error + "</strong>");
                } else {
                    countFiles++;
                    showResult($row, "success",
                        textFileUploaded.replace("#VAR1_BOLD#", "<strong>" + escapeHtml(file.name) + "</strong>"),
                        renamedBadgeHtml(data.files[0]));
                }
            });
            checkIfAllFinished();
        },
        chunkdone: function(e, data) {
            // remember if the server rejected a chunk, so the remaining chunks will not be sent
            if (data.result && data.result.files && data.result.files.length > 0
                && typeof data.result.files[0].error !== "undefined") {
                data.files[0].uploadError = data.result.files[0];
            }
        },
        chunksend: function(e, data) {
            if (typeof data.files[0].uploadError !== "undefined") {
                return false;
            }
        },
        fail: function(e, data) {
            var uploadId = data.uploadId;
            if (uploadId && activeUploads[uploadId]) {
                delete activeUploads[uploadId];
            }
            var $row = uploadId ? $("#upload-item-" + uploadId) : null;
            var isAborted = (data.errorThrown === "abort" || data.textStatus === "abort");

            if (isAborted) {
                countAbortedFiles++;
                var displayName = data.files[0] ? (data.files[0].uploadName || data.files[0].name) : "";
                showResult($row, "cancelled", escapeHtml(displayName) + " - <strong>" + textUploadCancelled + "</strong>");
            } else {
                countErrorFiles++;
                var fileName, fileError;
                if (typeof data.files[0].uploadError !== "undefined") {
                    // name and error were already encoded by the server
                    fileName = data.files[0].uploadError.name;
                    fileError = data.files[0].uploadError.error;
                } else {
                    fileName = escapeHtml(data.files[0].name);
                    fileError = escapeHtml(data.errorThrown || data.textStatus);
                }
                showResult($row, "error", fileName + " - <strong>" + fileError + "</strong>");
            }
            checkIfAllFinished();
        },
        progressall: function(e, data) {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            $("#progress-wrapper").removeClass("d-none");
            $("#progress .progress-bar").css(
                "width",
                progress + "%"
            );
            if (Object.keys(activeUploads).length > 0) {
                $("#upload-status-text").text(progress + "%");
            }
        },
        stop: function(e, data) {
            checkIfAllFinished();
        }
    }).prop("disabled", !$.support.fileInput).parent().addClass($.support.fileInput ? undefined : "disabled");
}
