<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Kategorien
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 *         USF = Profilfelder
 *         DAT = Termine
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
 *         4 - Reihenfolge fuer die uebergebene usf_id anpassen
 * sequence: neue Reihenfolge fuer die uebergebene usf_id
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_category.php');

// Uebergabevariablen pruefen und ggf. initialisieren
$get_cat_id   = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$get_type     = admFuncVariableIsValid($_GET, 'type', 'string', null, true, array('ROL', 'LNK', 'USF', 'DAT'));
$get_mode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);
$get_title    = admFuncVariableIsValid($_GET, 'title', 'string', $g_l10n->get('SYS_CATEGORY'));
$get_sequence = admFuncVariableIsValid($_GET, 'sequence', 'string', '', false, array('UP', 'DOWN'));

// Modus und Rechte pruefen
if($get_type == 'ROL' && $g_current_user->assignRoles() == false)
{
	$g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}
elseif($get_type == 'LNK' && $g_current_user->editWeblinksRight() == false)
{
	$g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}
elseif($get_type == 'USF' && $g_current_user->editUsers() == false)
{
	$g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}
elseif($get_type == 'DAT' && $g_current_user->editDates() == false)
{
	$g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// Kategorie-Objekt anlegen
$category = new TableCategory($g_db);

if($get_cat_id > 0)
{
    $category->readData($get_cat_id);

    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue('cat_org_id') >  0
    && $category->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}
else
{
    // es wird eine neue Kategorie angelegt
    $category->setValue('cat_org_id', $g_current_organization->getValue('org_id'));
    $category->setValue('cat_type', $get_type);
}

if($get_mode == 1)
{
    // Kategorie anlegen oder updaten

    $_SESSION['categories_request'] = $_REQUEST;

    if(strlen($_POST['cat_name']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_FIELD_EMPTY',$g_l10n->get('SYS_NAME')));
    }

    $search_orga = '';
    
    // Profilfelderkategorien bei einer Orga oder wenn Haekchen gesetzt, immer Orgaunabhaengig anlegen
    // Terminbestaetigungskategorie bleibt auch Orgaunabhaengig
    if(($get_type == 'USF' && (  isset($_POST['show_in_several_organizations']) 
                              || $g_current_organization->countAllRecords() == 1))
    || ($get_type == 'ROL' && $category->getValue('cat_name_intern') == 'CONFIRMATION_OF_PARTICIPATION'))
    {
        $category->setValue('cat_org_id', '0');
        $search_orga = ' AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                             OR cat_org_id IS NULL )';
    }
    else
    {
        $category->setValue('cat_org_id', $g_current_organization->getValue('org_id'));
        $search_orga = ' AND cat_org_id  = '. $g_current_organization->getValue('org_id');
    }

    if($category->getValue('cat_name') != $_POST['cat_name'])
    {
        // Schauen, ob die Kategorie bereits existiert
        $sql    = 'SELECT COUNT(*) as count
                     FROM '. TBL_CATEGORIES. '
                    WHERE cat_type    = \''. $get_type. '\'
                      AND cat_name LIKE \''. $_POST['cat_name']. '\'
                      AND cat_id     <> '. $_GET['cat_id']. 
                          $search_orga;
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);

        if($row['count'] > 0)
        {
            $g_message->show($g_l10n->get('CAT_CATEGORY_EXIST'));
        }
    }
	
	// bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
	// falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird
	$checkboxes = array('cat_hidden','cat_default');

	foreach($checkboxes as $key => $value)
	{
		if(isset($_POST[$value]) == false || $_POST[$value] != 1)
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
    $return_code = $category->save();

    if($return_code < 0)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    // falls eine Kategorie von allen Orgas auf eine Bestimmte umgesetzt wurde oder anders herum,
    // dann muss die Sequenz fuer den alle Kategorien dieses Typs neu gesetzt werden
    if(isset($_POST['cat_org_id']) && $_POST['cat_org_id'] <> $cat_org_merker)
    {
        $sequence_category = new TableCategory($g_db);
        $sequence = 0;

        $sql    = 'SELECT *
                     FROM '. TBL_CATEGORIES. '
                    WHERE cat_type = "'. $get_type. '"
                      AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                          OR cat_org_id IS NULL )
                    ORDER BY cat_org_id ASC, cat_sequence ASC';
        $result = $g_db->query($sql);

        while($row = $g_db->fetch_array($result))
        {
            $sequence++;
            $sequence_category->clear();
            $sequence_category->setArray($row);

            $sequence_category->setValue('cat_sequence', $sequence);
            $sequence_category->save();
        }
    }

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['categories_request']);

    $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
    $g_message->show($g_l10n->get('SYS_SAVE_DATA'));

}
elseif($get_mode == 2)
{
    // delete category

    if($category->getValue('cat_system') == 1)
    {
        // system-category couldn't be deleted
        echo $g_l10n->get('SYS_INVALID_PAGE_VIEW');
		exit();
    }

	$ret_code = $category->delete();

	if($ret_code)
	{
		echo 'done';
	}
}
elseif($get_mode == 4)
{
    // Kategoriereihenfolge aktualisieren
    $category->moveSequence($get_sequence);
    exit();
}
?>