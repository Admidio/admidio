<?php
/**
 ***********************************************************************************************
 * easy chat system
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// check if the call of the page was allowed by settings
if (!$gSettingsManager->getBool('enable_chat_module'))
{
    // message if the Chat is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$headline = 'Admidio Chat';

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

$page->addCssFile(THEME_URL.'/css/chat.css');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES . '/messages/chat.js');

$page->addJavascript('
    var chat = new Chat("#sendie", "#chat-area");
', true);

// add back link to module menu
$messagesChatMenu = $page->getMenu();
$messagesChatMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<div id="chat-wrap"><div id="chat-area"></div></div>');

// show form
$form = new HtmlForm('send-message-area', '', $page, array('enableFileUpload' => true));

$form->addMultilineTextInput('sendie', 'Enter Message:', '', 2, array('maxLength' => 100));

// add form to html page
$page->addHtml($form->show());

// show page
$page->show();
