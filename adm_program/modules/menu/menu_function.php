<?php
/**
 ***********************************************************************************************
 * Various functions for categories
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * uuid : UUID of the menu that should be edited
 * mode      : edit - Create or edit menu
 *             delete - Delete menu
 *             sequence - Change sequence for parameter men_id
 * direction : Direction to change the sequence of the menu entry
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$postMenuUUID = admFuncVariableIsValid($_POST, 'uuid', 'string');
$postMode     = admFuncVariableIsValid($_POST, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));

if (in_array($postMode, array('delete', 'sequence'))) {
    $gMessage->showHtmlTextOnly();
}

// check rights
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create menu object
$menu = new TableMenu($gDb);

if ($postMenuUUID !== '') {
    $menu->readDataByUuid($postMenuUUID);
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($postMode === 'edit') {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

// create menu or update it
if ($postMode === 'edit') {
    $_SESSION['menu_request'] = $_POST;

    $postIdParent = admFuncVariableIsValid($_POST, 'men_men_id_parent', 'int');
    $postComId    = admFuncVariableIsValid($_POST, 'men_com_id', 'int');
    $postName     = admFuncVariableIsValid($_POST, 'men_name', 'string', array('default' => ''));
    $postDesc     = admFuncVariableIsValid($_POST, 'men_description', 'string', array('default' => ''));
    $postUrl      = admFuncVariableIsValid($_POST, 'men_url', 'string', array('default' => ''));
    $postIcon     = admFuncVariableIsValid($_POST, 'men_icon', 'string', array('default' => ''));

    // within standard menu items the url should not be changed
    if ($menu->getValue('men_standard')) {
        $postUrl = $menu->getValue('men_url');
    }

    // Check if mandatory fields are filled
    if ($postName === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if(!StringUtils::strValidCharacters($postUrl, 'url')
    && !preg_match('=^[^*;:~<>|\"\\\\]+$=', $postUrl)) {
        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_URL'))));
        // => EXIT
    }

    if ($postUrl === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_URL'))));
        // => EXIT
    }

    try {
        $menu->setValue('men_icon', $postIcon);
        $menu->setValue('men_men_id_parent', $postIdParent);
        $menu->setValue('men_name', $postName);
        $menu->setValue('men_description', $postDesc);
        if (!$menu->getValue('men_standard')) {
            $menu->setValue('men_url', $postUrl);
            $menu->setValue('men_com_id', $postComId);
        }
        $returnCode = $menu->save();
    } catch (AdmException $e) {
        $e->showHtml();
    }

    // save changed roles rights of the menu
    if(isset($_POST['menu_view'])) {
        $menuViewRoles = array_map('intval', $_POST['menu_view']);
    } else {
        $menuViewRoles = array();
    }

    $rightMenuView = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
    $rightMenuView->saveRoles($menuViewRoles);

    if ($gNavigation->count() > 1) {
        $gNavigation->deleteLastUrl();
    } else {
        $gNavigation->addUrl($gHomepage, 'Home');
    }

    unset($_SESSION['menu_request']);

    header('Location: '. $gNavigation->getUrl());
    exit();
} elseif ($postMode === 'delete') {
    // delete menu
    if ($menu->delete()) {
        echo 'done';
    }
    exit();
} elseif ($postMode === 'sequence') {
    // Update menu sequence
    $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableMenu::MOVE_UP, TableMenu::MOVE_DOWN)));

    if ($menu->moveSequence($postDirection)) {
        echo 'done';
    } else {
        echo 'Sequence could not be changed.';
    }
    exit();
}
