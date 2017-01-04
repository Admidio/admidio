<?php
/**
 ***********************************************************************************************
 * Import assistant for user data
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') !== '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

$headline = $gL10n->get('MEM_IMPORT_USERS');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

if(isset($_SESSION['import_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $form_values = $_SESSION['import_request'];
    unset($_SESSION['import_request']);
}
else
{
    $form_values['user_import_mode'] = 1;
    $form_values['import_coding']    = 'iso-8859-1';
    $form_values['import_role_id']   = 0;
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$importMenu = $page->getMenu();
$importMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('import_users_form', ADMIDIO_URL.FOLDER_MODULES.'/members/import_function.php', $page, array('enableFileUpload' => true));
$form->addStaticControl('format', $gL10n->get('MEM_FORMAT'), 'CSV');
$form->addFileUpload('userfile', $gL10n->get('MEM_CHOOSE_FILE'), array('property' => FIELD_REQUIRED, 'allowedMimeTypes' => array('text/comma-separated-values')));
$selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
$form->addSelectBox('import_coding', $gL10n->get('MEM_CODING'), $selectBoxEntries, array('property' => FIELD_REQUIRED, 'defaultValue' => $form_values['import_coding']));

// add a selectbox to the form where the user can choose a role from all roles he could see
// first read all relevant roles from database and create an array with them
$condition = '';

if(!$gCurrentUser->manageRoles())
{
    // keine Rollen mit Rollenzuordnungsrecht anzeigen
    $condition .= ' AND rol_assign_roles = 0 ';
}
if(!$gCurrentUser->isAdministrator())
{
    // Don't show administrator role
    $condition .= ' AND rol_administrator = 0 ';
}

$sql = 'SELECT *
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_valid   = 1
           AND rol_visible = 1
           AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )'.
               $condition.'
      ORDER BY cat_sequence, rol_name';
$statement = $gDb->query($sql);
$roles = array();

while($row = $statement->fetch())
{
    $roles[] = array($row['rol_id'], $row['rol_name'], $row['cat_name']);
}
$form->addSelectBox('import_role_id', $gL10n->get('MEM_ASSIGN_ROLE'), $roles, array('property'        => FIELD_REQUIRED,
                                                                                    'defaultValue'    => $form_values['import_role_id'],
                                                                                    'helpTextIdLabel' => 'MEM_ASSIGN_ROLE_FOR_IMPORT'));

$selectBoxEntries = array(1 => $gL10n->get('MEM_NOT_EDIT'), 2 => $gL10n->get('MEM_DUPLICATE'), 3 => $gL10n->get('MEM_REPLACE'), 4 => $gL10n->get('MEM_COMPLEMENT'));
$form->addSelectBox('user_import_mode', $gL10n->get('MEM_EXISTING_USERS'), $selectBoxEntries, array('property'                       => FIELD_REQUIRED,
                                                                                                    'defaultValue'                   => $form_values['user_import_mode'],
                                                                                                    'showContextDependentFirstEntry' => false,
                                                                                                    'helpTextIdLabel'                => 'MEM_IDENTIFY_USERS'));
$form->addSubmitButton('btn_forward', $gL10n->get('SYS_NEXT'), array('icon' => THEME_URL.'/icons/forward.png', 'class' => ' col-sm-offset-3'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
