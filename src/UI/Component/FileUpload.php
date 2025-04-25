<?php
namespace Admidio\UI\Component;

use Admidio\Infrastructure\Exception;
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

        if ($this->module === 'photos') {
            $textUploadDescription = $gL10n->get('SYS_PHOTO_UPLOAD_DESC', array($destinationName));
            $textSelectFiles = $gL10n->get('SYS_SELECT_PHOTOS');
            $textBackButton = $gL10n->get('SYS_BACK_TO_ALBUM');
        } elseif ($this->module === 'documents_files') {
            $textUploadDescription = $gL10n->get('SYS_FILES_UPLOAD_DESC', array($destinationName));
            $textSelectFiles = $gL10n->get('SYS_SELECT_FILES');
            $textBackButton = $gL10n->get('SYS_BACK_TO_FOLDER');
        }

        return '
        <p class="lead">'.$textUploadDescription.'</p>

        <span class="btn btn-primary fileinput-button mb-3">
            <i class="bi bi-upload"></i>'.$textSelectFiles.'
            <input id="fileupload" type="file" name="files[]" multiple>
        </span>
        <div id="progress" class="progress mb-5" style="max-width: 600px;">
            <div class="progress-bar progress-bar-success"></div>
        </div>
        <div id="files" class="files"></div>
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

        $this->page->addJavascript(
            '
            var countErrorFiles = 0;
            var countFiles      = 0;

            $("#back").click(function () {
                window.location.href = "' . $GLOBALS['gNavigation']->getPreviousUrl() . '";
            });

            $("#fileupload").fileupload({
                url: "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php', array('module' => $this->module, 'mode' => 'upload_files', 'uuid' => $this->destinationUuid)).'",
                sequentialUploads: true,
                dataType: "json",
                formData: [{
                    name: "adm_csrf_token",
                    value: "' . $GLOBALS['gCurrentSession']->getCsrfToken() . '"
                }],
                add: function(e, data) {
                    $("#files").html("");
                    countErrorFiles = 0;
                    countFiles = 0;
                    data.submit();
                },
                done: function(e, data) {
                    $("#back").attr("class", "btn btn-secondary admidio-margin-bottom");
                    $.each(data.result.files, function(index, file) {
                        if (typeof file.error !== "undefined") {
                            $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill\"></i>"
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
                progressall: function(e, data) {
                    const progress = parseInt(data.loaded / data.total * 100, 10);
                    $("#progress .progress-bar").css(
                        "width",
                        progress + "%"
                    );
                },
                stop: function(e, data) {
                    $("#back").attr("class", "btn btn-secondary admidio-margin-bottom");
                    if (countErrorFiles === 0 && countFiles > 0) {
                        $("<p/>").html("<div class=\"alert alert-success\"><i class=\"bi bi-check-lg\"></i>'.$textUploadSuccessful.'</div>").appendTo("#files");
                    } else {
                        $("<p/>").html("<div class=\"alert alert-danger\"><i class=\"bi bi-exclamation-circle-fill\"></i>'.$textUploadNotSuccessful.'</div>").appendTo("#files");
                    }
                }
            }).prop("disabled", !$.support.fileInput).parent().addClass($.support.fileInput ? undefined : "disabled");',
            true
        );
    }
}
