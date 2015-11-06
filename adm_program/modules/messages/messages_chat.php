<?php
/******************************************************************************
 * easy chat system
 *
 * Copyright    : (c) 2004 - 2014 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 *
 *****************************************************************************/

require_once('../../system/common.php');

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// check if the call of the page was allowed by settings
if ($gPreferences['enable_chat_module'] != 1)
{
    // message if the Chat is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

$headline = 'Admidio Chat';

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

$page->addJavascriptFile($g_root_path.'/adm_program/modules/messages/chat.js');
$page->addCssFile(THEME_PATH.'/css/chat.css');

$page->addJavascript('
    // kick off chat
    var chat = new Chat();

    chat.getState();

    $(function() {
        // watch textarea for release of key press [enter]
        $("#sendie").keyup(function(e) {
            if (e.keyCode === 13) {
                var text = $(this).val().trim();
                if (text.length > 0)
                {
                    chat.send(text);
                }
                $(this).val("");
            }
        });
    });

    $(document).ready(function()
    {
        var intervalID = setInterval(chat.update, 2500);
    });

');

// add back link to module menu
$messagesChatMenu = $page->getMenu();
$messagesChatMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<div id="chat-wrap"><div id="chat-area"></div></div>');

// show form
$form = new HtmlForm('send-message-area', '', $page, array('enableFileUpload' => true));

$form->addMultilineTextInput('sendie', 'Enter Message:', null, 2, array('maxLength' => 100));

// add form to html page
$page->addHtml($form->show(false));

// show page
$page->show();
