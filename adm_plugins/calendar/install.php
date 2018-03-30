<?php
/**
 ***********************************************************************************************
 * Install the Menu for calendar
 *
 * @copyright 2004-2018 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));

require_once($rootPath . '/adm_program/system/common.php');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$pluginUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/calendar/calendar.php';

// set module headline
$headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_MENU')));

// create html page object
$page = new HtmlPage($headline);

$sql = 'SELECT *
          FROM '.TBL_MENU.'
         WHERE men_name_intern = \'plg_calendar\'';
$statement = $gDb->queryPrepared($sql);

if($statement->rowCount() > 0)
{
    $page->addHtml('<div class="panel-body">This Plugin is already installed</div>');
    $page->addHtml('<div class="panel-body"><a href="'.ADMIDIO_URL.FOLDER_MODULES.'/menu/menu.php">use this menu for delete</a></div>');
}
else
{
    // alle aus der DB auslesen
    $parentRoleViewSet = array();
    $sqlRoles = 'SELECT *
                   FROM '.TBL_ROLES.'
             INNER JOIN '.TBL_CATEGORIES.'
                     ON cat_id = rol_cat_id
                  WHERE rol_valid  = 1
                    AND rol_system = 0
               ORDER BY rol_name';
    $rolesViewStatement = $gDb->queryPrepared($sqlRoles);

    while($rowViewRoles = $rolesViewStatement->fetch())
    {
        // Jede Rolle wird nun dem Array hinzugefuegt
        $parentRoleViewSet[] = array($rowViewRoles['rol_id'], $rowViewRoles['rol_name'], $rowViewRoles['cat_name']);
    }

    // show form
    $form = new HtmlForm('menu_install_form', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 1)), $page);

    $form->addSelectBoxForCategories('men_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'MEN', 'EDIT_CATEGORIES', array('property' => FIELD_REQUIRED));

    $form->addInput('men_name', $gL10n->get('SYS_NAME'), 'Plugin - Calendar', array('maxLength' => 100, 'property' => FIELD_REQUIRED));

    $form->addInput('men_name_intern', $gL10n->get('SYS_NAME'), 'plg_calendar', array('property' => FIELD_DISABLED));
    $form->addInput('men_name_intern', '', 'plg_calendar', array('type' => 'hidden'));

    $form->addSelectBox('menu_view', $gL10n->get('DAT_VISIBLE_TO'), $parentRoleViewSet, array('property'  => FIELD_REQUIRED, 'multiselect' => true));

    $form->addInput('men_url', '', $pluginUrl, array('type' => 'hidden'));
    $form->addInput('men_url', $gL10n->get('ORG_URL'), $pluginUrl, array('property' => FIELD_DISABLED));

    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));

    // add form to html page and show page
    $page->addHtml($form->show());
}

$page->show();
