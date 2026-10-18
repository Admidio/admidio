<?php
namespace Admidio\UI\Component;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\UI\Presenter\PagePresenter;

/**
 * @brief Class to create a file upload page for document & files or photos module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class FileUpload
{
    /**
     * @var PagePresenter Object of PagePresenter that represents the current page where the upload should be integrated
     */
    protected PagePresenter $page;
    /**
     * @var string Name module for which the upload should be done. Preferred modules are 'photos' and 'documents_files'
     */
    protected string $module;
    /**
     * @var string UUID of the destination object that could be the folder or the album
     */
    protected string $destinationUuid;

    /**
     * Constructor that will create an object of FileUpload.
     * @param PagePresenter $page       Object that represents the current page where the upload should be integrated
     * @param string   $module          Name module for which the upload should be done. Preferred modules are 'photos' and 'documents_files'
     * @param string   $destinationUuid UUID of the destination object that could be the folder or the album
     */
    public function __construct(PagePresenter $page, string $module, string $destinationUuid)
    {
        $this->page = $page;
        $this->module = $module;
        $this->destinationUuid = $destinationUuid;
    }

    /**
     * Creates the html output for the upload dialog with module specific strings.
     * @param string $destinationName Name of the folder or album where the file should be uploaded
     * @return string Returns the html output for an upload dialog.
     * @throws Exception
     */
    public function getHtml(string $destinationName, bool $isModal = false): string
    {
        global $gL10n;

        $textUploadDescription = '';
        $textSelectFiles = '';
        $textBackButton = '';
        $textDragAndDrop = '';

        if ($this->module === 'photos') {
            $textUploadDescription = $gL10n->get('SYS_PHOTO_UPLOAD_DESC', array($destinationName));
            $textSelectFiles = $gL10n->get('SYS_SELECT_PHOTOS');
            $textBackButton = $gL10n->get('SYS_BACK_TO_ALBUM');
            $textDragAndDrop = $gL10n->get('SYS_DRAG_AND_DROP_PHOTOS');
        } elseif ($this->module === 'documents_files') {
            $textUploadDescription = $gL10n->get('SYS_FILES_UPLOAD_DESC', array($destinationName));
            $textSelectFiles = $gL10n->get('SYS_SELECT_FILES');
            $textBackButton = $gL10n->get('SYS_BACK_TO_FOLDER');
            $textDragAndDrop = $gL10n->get('SYS_DRAG_AND_DROP_FILES');
        }

        $textInvalidFilesTitle = $gL10n->get('SYS_INVALID_FILENAMES_DETECTED');
        $textInvalidFilesDesc = $gL10n->get('SYS_INVALID_FILENAMES_DESC');
        $textApplyAllAndUpload = $gL10n->get('SYS_APPLY_ALL_AND_UPLOAD');
        $textCancelAll = $gL10n->get('SYS_CANCEL_ALL');
        $textCancelUpload = $gL10n->get('SYS_UPLOAD_CANCEL');

        $leadHtml = $isModal ? '' : '<p class="lead">' . $textUploadDescription . '</p>';
        $backButtonHtml = $isModal ? '' : '<button id="back" class="btn btn-secondary admidio-margin-bottom d-none">' . $textBackButton . '</button>';

        return '
        ' . $leadHtml . '

        <div id="admidio-dropzone" class="admidio-dropzone mb-4" tabindex="0" role="region" aria-label="' . $textDragAndDrop . '">
            <div class="admidio-dropzone-content">
                <i class="bi bi-cloud-arrow-up admidio-dropzone-icon"></i>
                <div class="admidio-dropzone-title">' . $textDragAndDrop . '</div>
                <div class="admidio-dropzone-or">' . $gL10n->get('SYS_OR') . '</div>
                <span class="btn btn-primary fileinput-button">
                    <i class="bi bi-upload me-2"></i>' . $textSelectFiles . '
                    <input id="fileupload" class="btn-secondary" type="file" name="files[]" multiple>
                </span>
            </div>
        </div>

        <div id="admidio-upload-invalid-container" class="card mb-4 d-none">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 d-flex align-items-center">
                    <i class="bi bi-pencil-square text-primary me-2"></i>
                    <span>' . $textInvalidFilesTitle . '</span>
                </h5>
                <button type="button" class="btn-close" id="btn-close-invalid" aria-label="' . $textCancelAll . '"></button>
            </div>
            <div class="card-body">
                <p class="card-text text-muted mb-3">' . $textInvalidFilesDesc . '</p>
                <div id="admidio-upload-invalid-list" class="list-group list-group-flush"></div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-end align-items-center gap-2">
                <button id="btn-cancel-pending" type="button" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i>' . $textCancelAll . '
                </button>
                <button id="btn-sanitize-all" type="button" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>' . $textApplyAllAndUpload . '
                </button>
            </div>
        </div>

        <div id="progress-wrapper" class="mb-4 d-none">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span id="upload-status-text" class="text-muted small"></span>
                <button id="btn-cancel-all-uploads" type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="' . $textCancelUpload . '">
                    <i class="bi bi-x-circle me-1"></i>' . $textCancelUpload . '
                </button>
            </div>
            <div id="progress" class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"></div>
            </div>
        </div>
        <div id="files" class="files mb-3"></div>
        ' . $backButtonHtml;
    }

    /**
     * Creates the html output for the upload modal and full-screen drag & drop overlay.
     * @param string $destinationName Name of the folder or album where the file should be uploaded
     * @return string Returns the html output for an upload modal and drag overlay.
     * @throws Exception
     */
    public function getModalHtml(string $destinationName): string
    {
        global $gL10n;

        $headline = $this->module === 'photos' ? $gL10n->get('SYS_UPLOAD_PHOTOS') : $gL10n->get('SYS_UPLOAD_FILES');
        $textClose = $gL10n->get('SYS_CLOSE');
        $textDragAndDrop = $this->module === 'photos' ? $gL10n->get('SYS_DRAG_AND_DROP_PHOTOS') : $gL10n->get('SYS_DRAG_AND_DROP_FILES');

        return '
        <div id="admidio-folder-drop-overlay" class="admidio-folder-drop-overlay d-none">
            <div class="admidio-folder-drop-overlay-box">
                <i class="bi bi-cloud-arrow-up-fill admidio-folder-drop-overlay-icon"></i>
                <h3 class="admidio-folder-drop-overlay-title">' . $textDragAndDrop . '</h3>
                <p class="admidio-folder-drop-overlay-desc">' . htmlspecialchars($destinationName, ENT_QUOTES, 'UTF-8') . '</p>
            </div>
        </div>

        <div class="modal fade" id="admidio-upload-modal" tabindex="-1" aria-labelledby="admidio-upload-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center" id="admidio-upload-modal-title">
                            <i class="bi bi-cloud-arrow-up text-primary me-2"></i>
                            <span>' . $headline . ' - ' . htmlspecialchars($destinationName, ENT_QUOTES, 'UTF-8') . '</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $textClose . '"></button>
                    </div>
                    <div class="modal-body">
                        ' . $this->getHtml($destinationName, true) . '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>' . $textClose . '
                        </button>
                    </div>
                </div>
            </div>
        </div>';
    }

    /**
     * Adds the necessary css and javascript files of the jquery-file-upload library to the page header of the
     * PagePresenter object that was set in the constructor.
     */
    public function setHeaderData(bool $isModal = false)
    {
        if ($this->module === 'photos') {
            $textFileUploaded = $GLOBALS['gL10n']->get('SYS_PHOTO_FILE_UPLOADED');
            $textUploadSuccessful = $GLOBALS['gL10n']->get('SYS_PHOTO_UPLOAD_SUCCESSFUL');
            $textUploadNotSuccessful = $GLOBALS['gL10n']->get('SYS_PHOTO_UPLOAD_NOT_SUCCESSFUL');
            $textUpload = $GLOBALS['gL10n']->get('SYS_UPLOAD_PHOTOS');
        } elseif ($this->module === 'documents_files') {
            $textFileUploaded = $GLOBALS['gL10n']->get('SYS_FILE_UPLOADED');
            $textUploadSuccessful = $GLOBALS['gL10n']->get('SYS_FILES_UPLOAD_SUCCESSFUL');
            $textUploadNotSuccessful = $GLOBALS['gL10n']->get('SYS_FILES_UPLOAD_NOT_SUCCESSFUL');
            $textUpload = $GLOBALS['gL10n']->get('SYS_UPLOAD_FILES');
        }

        $textRenamedTo = $GLOBALS['gL10n']->get('SYS_RENAMED_TO');
        $textUploadCancel = $GLOBALS['gL10n']->get('SYS_UPLOAD_CANCEL');
        $textUploadCancelled = $GLOBALS['gL10n']->get('SYS_UPLOAD_CANCELLED');
        $textUploading = $GLOBALS['gL10n']->get('SYS_UPLOADING');
        $textRemove = $GLOBALS['gL10n']->get('SYS_REMOVE');
        $textFilenameInvalidNotice = $GLOBALS['gL10n']->get('SYS_FILENAME_INVALID', array(''));
        $textFilenameInvalidNotice = str_replace(array("\n", "\r"), ' ', trim(strip_tags($textFilenameInvalidNotice)));
        $invalidFilenamePattern = StringUtils::getInvalidFilenameCharsPattern();

        $this->page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/css/jquery.fileupload.css');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/vendor/jquery.ui.widget.js');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/jquery.fileupload.js');

        $serverMaxUpload = PhpIniUtils::getUploadMaxSize();
        // Capped at 10 MB, minimum 1 MB, leaving 100 KB headroom for multipart form headers
        $maxChunkSize = min(10000000, max(1000000, $serverMaxUpload - 102400));

        $this->page->addJavascript(
            '
            var countErrorFiles   = 0;
            var countFiles        = 0;
            var countAbortedFiles = 0;
            var dragCounter       = 0;
            var dropzone          = $("#admidio-dropzone");
            var isModal           = ' . ($isModal ? 'true' : 'false') . ';

            var pendingFiles      = {};
            var activeUploads     = {};
            var pendingSeq        = 0;
            var uploadSeq         = 0;

            var textFileUploaded          = ' . json_encode($textFileUploaded) . ';
            var textUploadSuccessful      = ' . json_encode($textUploadSuccessful) . ';
            var textUploadNotSuccessful   = ' . json_encode($textUploadNotSuccessful) . ';
            var textRenamedTo             = ' . json_encode($textRenamedTo) . ';
            var textUploadCancel          = ' . json_encode($textUploadCancel) . ';
            var textUploadCancelled       = ' . json_encode($textUploadCancelled) . ';
            var textUploading             = ' . json_encode($textUploading) . ';
            var textRemove                = ' . json_encode($textRemove) . ';
            var textUpload                = ' . json_encode($textUpload) . ';
            var textFilenameInvalidNotice = ' . json_encode($textFilenameInvalidNotice) . ';
            var invalidPattern            = ' . json_encode($invalidFilenamePattern) . ';
            var invalidRegex              = new RegExp(invalidPattern);
            var invalidGlobalRegex        = new RegExp(invalidPattern, "g");

            function isFilenameValid(name) {
                if (!name || typeof name !== "string") {
                    return false;
                }
                var trimmed = name.trim();
                if (trimmed === "" || trimmed.charAt(0) === ".") {
                    return false;
                }
                if (trimmed.indexOf("/") !== -1 || trimmed.indexOf("\\\\") !== -1) {
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
                var trimmed = name.trim();
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

                var renamedBadge = "";
                if (file.uploadName && file.uploadName !== file.name) {
                    var renamedText = textRenamedTo.replace("#VAR1_BOLD#", "<strong>" + $("<div>").text(file.uploadName).html() + "</strong>");
                    renamedBadge = " <span class=\"badge bg-secondary ms-2 small\">" + renamedText + "</span>";
                }

                var $row = $("<div class=\"upload-item alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-2\" id=\"upload-item-" + uploadId + "\">" +
                    "<div class=\"d-flex align-items-center text-truncate me-2\">" +
                        "<div class=\"spinner-border spinner-border-sm text-primary me-2 flex-shrink-0\" role=\"status\"></div>" +
                        "<span class=\"fw-semibold text-truncate\">" + $("<div>").text(file.name).html() + "</span>" +
                        renamedBadge +
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

                var escapedOriginal = $("<div>").text(file.name).html();
                var escapedSanitized = $("<div>").text(sanitized).html();

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
                            "<div class=\"btn-group upload-action-group\" role=\"group\">" +
                                "<button type=\"button\" class=\"btn btn-outline-primary btn-upload-single\" title=\"" + textUpload + "\" aria-label=\"" + textUpload + "\">" +
                                    "<i class=\"bi bi-upload\"></i>" +
                                "</button>" +
                                "<button type=\"button\" class=\"btn btn-outline-danger btn-remove-single\" title=\"" + textRemove + "\" aria-label=\"" + textRemove + "\">" +
                                    "<i class=\"bi bi-trash\"></i>" +
                                "</button>" +
                            "</div>" +
                        "</div>" +
                    "</div>" +
                "</div>");

                var $input = $item.find(".input-sanitized-name");
                var $btnUpload = $item.find(".btn-upload-single");

                function validateInput() {
                    var val = $input.val().trim();
                    if (isFilenameValid(val)) {
                        $input.removeClass("is-invalid").addClass("is-valid");
                        $btnUpload.prop("disabled", false);
                        return true;
                    } else {
                        $input.removeClass("is-valid").addClass("is-invalid");
                        $btnUpload.prop("disabled", true);
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
                    window.location.href = "' . $GLOBALS['gNavigation']->getPreviousUrl() . '";
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

                $("#menu_item_documents_upload_files, #menu_item_photos_upload_photos").on("click", function (e) {
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
                        if (!confirm(textUploadCancel)) {
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
                if (Object.keys(activeUploads).length === 0 && Object.keys(pendingFiles).length === 0) {
                    countErrorFiles = 0;
                    countFiles = 0;
                    countAbortedFiles = 0;
                    $("#files").empty();
                    $("#upload-summary-alert").remove();
                }
            });

            $("#fileupload").on("change", function () {
                if (Object.keys(activeUploads).length === 0 && Object.keys(pendingFiles).length === 0) {
                    countErrorFiles = 0;
                    countFiles = 0;
                    countAbortedFiles = 0;
                    $("#files").empty();
                    $("#upload-summary-alert").remove();
                }
            });

            $("#fileupload").fileupload({
                url: "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php', array('module' => $this->module, 'mode' => 'upload_files', 'uuid' => $this->destinationUuid)) . '",
                dropZone: isModal ? $("#admidio-dropzone, #admidio-upload-modal .modal-content") : dropzone,
                sequentialUploads: true,
                maxChunkSize: ' . $maxChunkSize . ',
                dataType: "json",
                formData: [{
                    name: "adm_csrf_token",
                    value: "' . $GLOBALS['gCurrentSession']->getCsrfToken() . '"
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
                            if ($row && $row.length) {
                                $row.removeClass("alert-light").addClass("alert-danger");
                                $row.html("<div class=\"d-flex align-items-center\"><i class=\"bi bi-exclamation-circle-fill me-2 text-danger fs-5\"></i><span>"
                                    + $("<div>").text(file.name).html() + " - <strong>" + file.error + "</strong></span></div>");
                            } else {
                                $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill me-2\"></i>"
                                    + $("<div>").text(file.name).html() + " - <strong>" + file.error + "</strong></div>").appendTo("#files");
                            }
                        } else {
                            countFiles++;
                            var message = textFileUploaded;
                            var newMessage = message.replace("#VAR1_BOLD#", "<strong>" + $("<div>").text(file.name).html() + "</strong>");
                            var renamedBadge = "";
                            if (data.files[0] && data.files[0].uploadName && data.files[0].uploadName !== data.files[0].name) {
                                var renamedText = textRenamedTo.replace("#VAR1_BOLD#", "<strong>" + $("<div>").text(data.files[0].uploadName).html() + "</strong>");
                                renamedBadge = " <span class=\"badge bg-secondary ms-2 small\">" + renamedText + "</span>";
                            }
                            if ($row && $row.length) {
                                $row.removeClass("alert-light border").addClass("alert-success");
                                $row.html("<div class=\"d-flex align-items-center\"><i class=\"bi bi-check-lg text-success me-2 fs-5\"></i><span>" + newMessage + "</span>" + renamedBadge + "</div>");
                            } else {
                                $("<p/>").html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg me-2\"></i>" + newMessage + renamedBadge + "</div>").appendTo("#files");
                            }
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
                        if ($row && $row.length) {
                            $row.removeClass("alert-light border").addClass("alert-secondary");
                            $row.html("<div class=\"d-flex align-items-center\"><i class=\"bi bi-dash-circle me-2 text-secondary fs-5\"></i><span>"
                                + $("<div>").text(displayName).html() + " - <strong>" + textUploadCancelled + "</strong></span></div>");
                        } else {
                            $("<p/>").html("<div class=\"alert alert-secondary\"><i class=\"bi bi-dash-circle me-2\"></i>"
                                + $("<div>").text(displayName).html() + " - <strong>" + textUploadCancelled + "</strong></div>").appendTo("#files");
                        }
                    } else {
                        countErrorFiles++;
                        var fileName, fileError;
                        if (typeof data.files[0].uploadError !== "undefined") {
                            // name and error were already encoded by the server
                            fileName = data.files[0].uploadError.name;
                            fileError = data.files[0].uploadError.error;
                        } else {
                            fileName = $("<div/>").text(data.files[0].name).html();
                            fileError = $("<div/>").text(data.errorThrown || data.textStatus).html();
                        }
                        if ($row && $row.length) {
                            $row.removeClass("alert-light border").addClass("alert-danger");
                            $row.html("<div class=\"d-flex align-items-center\"><i class=\"bi bi-exclamation-circle-fill me-2 text-danger fs-5\"></i><span>"
                                + fileName + " - <strong>" + fileError + "</strong></span></div>");
                        } else {
                            $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill me-2\"></i>"
                                + fileName + " - <strong>" + fileError + "</strong></div>").appendTo("#files");
                        }
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
            }).prop("disabled", !$.support.fileInput).parent().addClass($.support.fileInput ? undefined : "disabled");',
            true
        );
    }
}
