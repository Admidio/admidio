<?php
/**
 ***********************************************************************************************
 * Show and manage all written emails and private messages
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;

require_once(__DIR__ . '/../../system/common.php');

try {
    if (!$gValidLogin) {
        // Visitors could not view messages, they are only able to write messages to specific roles
        admRedirect(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php');
        // => EXIT
    }

    // check if the call of the page was allowed
    if (!$gSettingsManager->getBool('pm_module_enabled') && !($gSettingsManager->getInt('mail_module_enabled') > 0)) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getMsgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'uuid');

    if ($getMsgUuid !== '') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        $delMessage = new Admidio\Messages\Entity\Message($gDb);
        $delMessage->readDataByUuid($getMsgUuid);

        // only delete messages of the current user is allowed
        if ($delMessage->getValue('msg_usr_id_sender') === $gCurrentUserId) {
            $returnCode = $delMessage->delete();

            if ($returnCode) {
                echo 'done';
                exit();
            }
        }

        echo 'delete not OK';
        exit();
    }

    $headline = $gL10n->get('SYS_MESSAGES');

    // add current url to navigation stack
    $gNavigation->clear();
    $gNavigation->addUrl(CURRENT_URL, $headline, 'bi-envelope-fill');

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-messages', $headline);
    $page->setContentFullWidth();

    // link to write new email
    if ($gSettingsManager->getInt('mail_module_enabled') > 0) {
        $page->addPageFunctionsMenuItem(
            'menu_item_messages_new_email',
            $gL10n->get('SYS_WRITE_EMAIL'),
            ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php',
            'bi-envelope-open-fill'
        );
    }
    // link to write new PM
    if ($gSettingsManager->getBool('pm_module_enabled')) {
        $page->addPageFunctionsMenuItem(
            'menu_item_messages_new_pm',
            $gL10n->get('SYS_WRITE_PM'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_type' => 'PM')),
            'bi-chat-left-fill'
        );
    }

    $table = new HtmlTable('adm_message_table', $page, true, true);
    $table->setServerSideProcessing(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_data.php');

    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
    $table->addRowHeadingByArray(array(
        '<i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_CATEGORY') . '"></i>',
        $gL10n->get('SYS_SUBJECT'),
        $gL10n->get('SYS_CONVERSATION_PARTNER'),
        '<i class="bi bi-paperclip" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ATTACHMENT') . '"></i>',
        $gL10n->get('SYS_DATE'),
        ''
    ));

    $table->disableDatatablesColumnsSort(array(3, 6));
    $table->setDatatablesColumnsNotHideResponsive(array(6));
    // special settings for the table
    $table->setDatatablesOrderColumns(array(array(5, 'desc')));

    // add table to the form
    $page->addHtml($table->show());

    // add form to html page and show page
    $page->show();
} catch (Exception $e) {
    if ($getMsgUuid !== '') {
        echo $e->getMessage();
    } else {
        $gMessage->show($e->getMessage());
    }
}
