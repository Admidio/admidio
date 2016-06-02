<?php
/**
 ***********************************************************************************************
 * Various functions for categories
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * men_id: Id of the menu that should be edited
 * mode  : 1 - Create or edit menu
 *         2 - Delete menu
 *         3 - Change sequence for parameter men_id
 * sequence: New sequence for the parameter men_id
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getMenId    = admFuncVariableIsValid($_GET, 'men_id',    'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',      'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence',  'string', array('validValues' => array('UP', 'DOWN')));

// check rights
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create menu object
$menu = new TableMenu($gDb);

if($getMenId > 0)
{
    $menu->readDataById($getMenId);
}
else
{
    // create a new menu
    $menu->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    $menu->setValue('cat_type', $getType);
}

if($getMode === 1)
{
    // Kategorie anlegen oder updaten

    $_SESSION['categories_request'] = $_POST;

    if((!array_key_exists('cat_name', $_POST) || $_POST['cat_name'] === '')
    && $category->getValue('cat_system') == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }

    $sqlSearchOrga = '';

    // Profilfelderkategorien bei einer Orga oder wenn Haekchen gesetzt, immer Orgaunabhaengig anlegen
    // Terminbestaetigungskategorie bleibt auch Orgaunabhaengig
    if(($getType === 'USF'
    && (isset($_POST['show_in_several_organizations']) || $gCurrentOrganization->countAllRecords() == 1))
    || ($getType === 'ROL' && $category->getValue('cat_name_intern') === 'CONFIRMATION_OF_PARTICIPATION'))
    {
        $category->setValue('cat_org_id', '0');
        $sqlSearchOrga = ' AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                             OR cat_org_id IS NULL )';
    }
    else
    {
        $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
        $sqlSearchOrga = ' AND cat_org_id  = '. $gCurrentOrganization->getValue('org_id');
    }

    if($category->getValue('cat_name') !== $_POST['cat_name'])
    {
        // Schauen, ob die Kategorie bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_type    = \''. $getType. '\'
                   AND cat_name LIKE \''. $_POST['cat_name']. '\'
                   AND cat_id     <> '.$getCatId.
                       $sqlSearchOrga;
        $categoriesStatement = $gDb->query($sql);
        $row = $categoriesStatement->fetch();

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('CAT_CATEGORY_EXIST'));
        }
    }

    // bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
    // falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird
    $checkboxes = array('cat_hidden', 'cat_default');

    foreach($checkboxes as $key => $value)
    {
        if(!isset($_POST[$value]) || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }

    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'cat_') === 0)
        {
            $category->setValue($key, $value);
        }
    }

    $cat_org_merker = $category->getValue('cat_org_id');

    // Daten in Datenbank schreiben
    $returnCode = $category->save();

    if($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // falls eine Kategorie von allen Orgas auf eine Bestimmte umgesetzt wurde oder anders herum,
    // dann muss die Sequenz fuer den alle Kategorien dieses Typs neu gesetzt werden
    if(isset($_POST['cat_org_id']) && $_POST['cat_org_id'] != $cat_org_merker)
    {
        $sequenceCategory = new TableCategory($gDb);
        $sequence = 0;

        $sql = 'SELECT *
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_type = "'. $getType. '"
                   AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
              ORDER BY cat_org_id ASC, cat_sequence ASC';
        $categoriesStatement = $gDb->query($sql);

        while($row = $categoriesStatement->fetch())
        {
            ++$sequence;
            $sequenceCategory->clear();
            $sequenceCategory->setArray($row);

            $sequenceCategory->setValue('cat_sequence', $sequence);
            $sequenceCategory->save();
        }
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['categories_request']);

    header('Location: '. $gNavigation->getUrl());
    exit();
}
elseif($getMode === 2)
{
    // delete menu
    try
    {
        if($menu->delete())
        {
            echo 'done';
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
    }
}
elseif($getMode === 3)
{
    // Kategoriereihenfolge aktualisieren
    $menu->moveSequence($getSequence);
    exit();
}
