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
     * Creates the html output for the upload dialog with module specific strings. The html is in the template
     * sys-template-parts/file-upload.tpl of the theme.
     * @param string $destinationName Name of the folder or album where the file should be uploaded
     * @param bool $isModal The upload is shown in the dialog of the folder page, otherwise on its own page.
     * @return string Returns the html output for an upload dialog.
     * @throws Exception
     */
    public function getHtml(string $destinationName, bool $isModal = false): string
    {
        return $this->fetchTemplate('sys-template-parts/file-upload.tpl', $destinationName, $isModal);
    }

    /**
     * Creates the html output for the upload modal and full-screen drag & drop overlay. The html is in the template
     * sys-template-parts/file-upload.modal.tpl of the theme.
     * @param string $destinationName Name of the folder or album where the file should be uploaded
     * @return string Returns the html output for an upload modal and drag overlay.
     * @throws Exception
     */
    public function getModalHtml(string $destinationName): string
    {
        return $this->fetchTemplate('sys-template-parts/file-upload.modal.tpl', $destinationName, true);
    }

    /**
     * Fill a template of the upload with the texts of the module.
     * @param string $template Name of the template.
     * @param string $destinationName Name of the folder or album where the file should be uploaded
     * @param bool $isModal The upload is shown in the dialog of the folder page.
     * @return string Returns the html of the template.
     * @throws Exception
     */
    private function fetchTemplate(string $template, string $destinationName, bool $isModal): string
    {
        global $gL10n;

        if ($this->module === 'photos') {
            $texts = array(
                'textUploadDescription' => $gL10n->get('SYS_PHOTO_UPLOAD_DESC', array($destinationName)),
                'textSelectFiles' => $gL10n->get('SYS_SELECT_PHOTOS'),
                'textBackButton' => $gL10n->get('SYS_BACK_TO_ALBUM'),
                'textDragAndDrop' => $gL10n->get('SYS_DRAG_AND_DROP_PHOTOS'),
                'headline' => $gL10n->get('SYS_UPLOAD_PHOTOS')
            );
        } else {
            $texts = array(
                'textUploadDescription' => $gL10n->get('SYS_FILES_UPLOAD_DESC', array($destinationName)),
                'textSelectFiles' => $gL10n->get('SYS_SELECT_FILES'),
                'textBackButton' => $gL10n->get('SYS_BACK_TO_FOLDER'),
                'textDragAndDrop' => $gL10n->get('SYS_DRAG_AND_DROP_FILES'),
                'headline' => $gL10n->get('SYS_UPLOAD_FILES')
            );
        }

        try {
            $smarty = PagePresenter::createSmartyObject();
            $smarty->assign($texts);
            $smarty->assign('destinationName', $destinationName);
            $smarty->assign('isModal', $isModal);
            $smarty->assign('l10n', $gL10n);
            return $smarty->fetch($template);
        } catch (\Smarty\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * Adds the files of the upload to the page: the jquery-file-upload library, the styles of the theme
     * (css/file-upload.css) and the script system/js/file-upload.js, which is started with the settings of the page.
     * @param bool $isModal The upload is shown in the dialog of the folder page, otherwise on its own page.
     * @throws Exception
     */
    public function setHeaderData(bool $isModal = false)
    {
        global $gL10n, $gNavigation, $gCurrentSession;

        if ($this->module === 'photos') {
            $texts = array(
                'fileUploaded' => $gL10n->get('SYS_PHOTO_FILE_UPLOADED'),
                'uploadSuccessful' => $gL10n->get('SYS_PHOTO_UPLOAD_SUCCESSFUL'),
                'uploadNotSuccessful' => $gL10n->get('SYS_PHOTO_UPLOAD_NOT_SUCCESSFUL'),
                'upload' => $gL10n->get('SYS_UPLOAD_PHOTOS')
            );
        } else {
            $texts = array(
                'fileUploaded' => $gL10n->get('SYS_FILE_UPLOADED'),
                'uploadSuccessful' => $gL10n->get('SYS_FILES_UPLOAD_SUCCESSFUL'),
                'uploadNotSuccessful' => $gL10n->get('SYS_FILES_UPLOAD_NOT_SUCCESSFUL'),
                'upload' => $gL10n->get('SYS_UPLOAD_FILES')
            );
        }
        $texts['renamedTo'] = $gL10n->get('SYS_RENAMED_TO');
        $texts['uploadCancel'] = $gL10n->get('SYS_UPLOAD_CANCEL');
        $texts['uploadCancelled'] = $gL10n->get('SYS_UPLOAD_CANCELLED');
        $texts['uploading'] = $gL10n->get('SYS_UPLOADING');
        $texts['remove'] = $gL10n->get('SYS_REMOVE');
        $texts['filenameInvalidNotice'] = $gL10n->get('SYS_FILENAME_INVALID_HINT');
        $texts['uploadCancelConfirm'] = $gL10n->get('SYS_UPLOAD_CANCEL_CONFIRM');

        // Capped at 10 MB, minimum 1 MB, leaving 100 KB headroom for multipart form headers
        $serverMaxUpload = PhpIniUtils::getUploadMaxSize();
        $maxChunkSize = min(10000000, max(1000000, $serverMaxUpload - 102400));

        $config = array(
            'isModal' => $isModal,
            'uploadUrl' => SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php',
                array('module' => $this->module, 'mode' => 'upload_files', 'uuid' => $this->destinationUuid)
            ),
            'csrfToken' => $gCurrentSession->getCsrfToken(),
            'maxChunkSize' => $maxChunkSize,
            'backUrl' => $gNavigation->getPreviousUrl(),
            'invalidFilenamePattern' => StringUtils::getInvalidFilenameCharsPattern(),
            'texts' => $texts
        );

        $this->page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/css/jquery.fileupload.css');
        $this->page->addCssFile(getThemedFileUrl('/css/file-upload.css') . '?v=' . filemtime(getThemedFile('/css/file-upload.css')));
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/vendor/jquery.ui.widget.js');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-file-upload/js/jquery.fileupload.js');
        $this->page->addJavascriptFile(ADMIDIO_URL . FOLDER_SYSTEM . '/js/file-upload.js?v=' . filemtime(ADMIDIO_PATH . FOLDER_SYSTEM . '/js/file-upload.js'));
        $this->page->addJavascript('admFileUpload(' . json_encode($config) . ');', true);
    }
}
