<?php
// load standart layouts and processes from system
require_once('/adm_program/system/common.php');

// check for valid login (comment the next 3 lines out if you want to allow everyone to see this page)
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$headline = 'Headline Text';

// create html page object
$page = new HtmlPage($headline);

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// add back link to module menu
$messagesWriteMenu = $page->getMenu();
$messagesWriteMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// put your HTML Code here
$page->addHtml('<br>put your HTML Code here!');

// show page
$page->show();
?>
