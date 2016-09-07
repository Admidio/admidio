<?php
/**
 ***********************************************************************************************
 * Install the Menu for calendar
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 *
 ****************************************************************************/

$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
 
if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$plugin_folder = '/adm_plugins/calendar/calendar.php';
$standart = 0;

// set module headline
$headline = $gL10n->get('SYS_MENU');
$headline = $gL10n->get('SYS_CREATE_VAR', $headline);

// create html page object
$page = new HtmlPage($headline);

$sql = "SELECT *
  FROM ".TBL_MENU."
  where men_modul_name = 'plg_calendar'";
$statement = $gDb->query($sql);

if($statement->rowCount() > 0)
{
}
else
{
    // alle aus der DB aus lesen
    $sqlRoles =  'SELECT *
                    FROM '.TBL_ROLES.'
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE rol_valid  = 1
                     AND rol_system = 0
                ORDER BY rol_name';
    $rolesViewStatement = $gDb->query($sqlRoles);

    while($rowViewRoles = $rolesViewStatement->fetchObject())
    {
        // Jede Rolle wird nun dem Array hinzugefuegt
        $parentRoleViewSet[] = array($rowViewRoles->rol_id, $rowViewRoles->rol_name, $rowViewRoles->cat_name);
    }

    // show form
    $form = new HtmlForm('menu_install_form', $g_root_path.'/adm_program/modules/menu/menu_function.php?mode=1', $page);

    $form->addInput('men_group', 'Menu Group',  'Special entry', array('property' => FIELD_DISABLED));
    $form->addInput('men_group', null, 4, array('type' => 'hidden'));
    

    $form->addInput('men_modul_name', $gL10n->get('SYS_NAME'), 'plg_calendar', array('property' => FIELD_DISABLED));
    $form->addInput('men_modul_name', null, 'plg_calendar', array('type' => 'hidden'));

    $form->addSelectBox('men_display_right', 'Display in right main menu '.$gL10n->get('DAT_VISIBLE_TO'), $parentRoleViewSet, 
                                       array('property'  => FIELD_REQUIRED, 'multiselect'  => true));

    $form->addInput('men_url', null, $plugin_folder, array('type' => 'hidden'));
    $form->addInput('men_url', $gL10n->get('ORG_URL'), $plugin_folder, array('property' => FIELD_DISABLED));

    $form->addInput('men_display_index', null, $standart, array('type' => 'hidden'));
    $form->addInput('men_display_boot', null, $standart, array('type' => 'hidden'));

    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
}

$page->show();
