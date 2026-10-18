<?php
namespace Admidio\UI\Component;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
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
    public function getHtml(string $destinationName): string
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

        return '
        <p class="lead">' . $textUploadDescription . '</p>

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

        <div id="progress" class="progress mb-4 d-none" style="max-width: 600px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"></div>
        </div>
        <div id="files" class="files mb-3"></div>
        <button id="back" class="btn btn-secondary admidio-margin-bottom d-none">' . $textBackButton . '</button>';
    }

    /**
     * Adds the necessary css and javascript files of the jquery-file-upload library to the page header of the
     * PagePresenter object that was set in the constructor.
     */
    public function setHeaderData()
    {
        if ($this->module === 'photos') {
            $textFileUploaded = $GLOBALS['gL10n']->get('SYS_PHOTO_FILE_UPLOADED');
            $textUploadSuccessful = $GLOBALS['gL10n']->get('SYS_PHOTO_UPLOAD_SUCCESSFUL');
            $textUploadNotSuccessful = $GLOBALS['gL10n']->get('SYS_PHOTO_UPLOAD_NOT_SUCCESSFUL');
        } elseif ($this->module === 'documents_files') {
            $textFileUploaded = $GLOBALS['gL10n']->get('SYS_FILE_UPLOADED');
            $textUploadSuccessful = $GLOBALS['gL10n']->get('SYS_FILES_UPLOAD_SUCCESSFUL');
            $textUploadNotSuccessful = $GLOBALS['gL10n']->get('SYS_FILES_UPLOAD_NOT_SUCCESSFUL');
        }

        $this->page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/css/jquery.fileupload.css');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/vendor/jquery.ui.widget.js');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/jquery.fileupload.js');

        $serverMaxUpload = PhpIniUtils::getUploadMaxSize();
        // Capped at 10 MB, minimum 1 MB, leaving 100 KB headroom for multipart form headers
        $maxChunkSize = min(10000000, max(1000000, $serverMaxUpload - 102400));

        $this->page->addJavascript(
            '
            var countErrorFiles = 0;
            var countFiles      = 0;
            var dragCounter     = 0;
            var dropzone        = $("#admidio-dropzone");

            $("#back").click(function () {
                window.location.href = "' . $GLOBALS['gNavigation']->getPreviousUrl() . '";
            });

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
            });

            $("#fileupload").fileupload({
                url: "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php', array('module' => $this->module, 'mode' => 'upload_files', 'uuid' => $this->destinationUuid)).'",
                dropZone: dropzone,
                sequentialUploads: true,
                maxChunkSize: ' . $maxChunkSize . ',
                dataType: "json",
                formData: [{
                    name: "adm_csrf_token",
                    value: "' . $GLOBALS['gCurrentSession']->getCsrfToken() . '"
                }],
                add: function(e, data) {
                    $("#files").html("");
                    $("#progress").removeClass("d-none");
                    $("#progress .progress-bar").css("width", "0%");
                    countErrorFiles = 0;
                    countFiles = 0;
                    data.submit();
                },
                done: function(e, data) {
                    $("#back").attr("class", "btn btn-secondary admidio-margin-bottom");
                    $.each(data.result.files, function(index, file) {
                        if (typeof file.error !== "undefined") {
                            $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill me-2\"></i>"
                                + file.name + " - <strong>" + file.error + "</strong></div>").appendTo("#files");
                            countErrorFiles++;
                        } else {
                            const message = "'.$textFileUploaded.'";
                            const newMessage = message.replace("#VAR1_BOLD#", "<strong>" + file.name + "</strong>");
                            $("<p/>").html(newMessage).appendTo("#files");
                            countFiles++
                        }
                    });
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
                    $("#back").attr("class", "btn btn-secondary admidio-margin-bottom");
                    let fileName, fileError;
                    if (typeof data.files[0].uploadError !== "undefined") {
                        // name and error were already encoded by the server
                        fileName = data.files[0].uploadError.name;
                        fileError = data.files[0].uploadError.error;
                    } else {
                        fileName = $("<div/>").text(data.files[0].name).html();
                        fileError = $("<div/>").text(data.errorThrown || data.textStatus).html();
                    }
                    $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill me-2\"></i>"
                        + fileName + " - <strong>" + fileError + "</strong></div>").appendTo("#files");
                    countErrorFiles++;
                },
                progressall: function(e, data) {
                    const progress = parseInt(data.loaded / data.total * 100, 10);
                    $("#progress").removeClass("d-none");
                    $("#progress .progress-bar").css(
                        "width",
                        progress + "%"
                    );
                },
                stop: function(e, data) {
                    $("#back").attr("class", "btn btn-secondary admidio-margin-bottom");
                    if (countErrorFiles === 0 && countFiles > 0) {
                        $("<p/>").html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg me-2\"></i>'.$textUploadSuccessful.'</div>").appendTo("#files");
                    } else {
                        $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill me-2\"></i>'.$textUploadNotSuccessful.'</div>").appendTo("#files");
                    }
                }
            }).prop("disabled", !$.support.fileInput).parent().addClass($.support.fileInput ? undefined : "disabled");',
            true
        );
    }
}
