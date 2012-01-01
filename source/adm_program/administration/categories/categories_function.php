<?php
/******************************************************************************
 * Various functions for categories
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
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

// Initialize and check the parameters
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getType     = admFuncVariableIsValid($_GET, 'type', 'string', null, true, array('ROL', 'LNK', 'USF', 'DAT'));
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);
$getTitle    = admFuncVariableIsValid($_GET, 'title', 'string', $gL10n->get('SYS_CATEGORY'));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', '', false, array('UP', 'DOWN'));

// Modus und Rechte pruefen
if($getType == 'ROL' && $gCurrentUser->assignRoles() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'LNK' && $gCurrentUser->editWeblinksRight() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'USF' && $gCurrentUser->editUsers() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'DAT' && $gCurrentUser->editDates() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create category object
$category = new TableCategory($gDb);

if($getCatId > 0)
{
    $category->readData($getCatId);

    // check if category belongs to actual organization
    if($category->getValue('cat_org_id') >  0
    && $category->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
	
	// if system category then set cat_name to default
	if($category->getValue('cat_system') == 1)
	{
		$_POST['cat_name'] = $category->getValue('cat_name');
	}
}
else
{
    // create a new category
    $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    $category->setValue('cat_type', $getType);
}

if($getMode == 1)
{
    // Kategorie anlegen oder updaten

    $_SESSION['categories_request'] = $_REQUEST;

    if(strlen($_POST['cat_name']) == 0 && $category->getValue('cat_system') == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY',$gL10n->get('SYS_NAME')));
    }

    $sqlSearchOrga = '';
    
    // Profilfelderkategorien bei einer Orga oder wenn Haekchen gesetzt, immer Orgaunabhaengig anlegen
    // Terminbestaetigungskategorie bleibt auch Orgaunabhaengig
    if(($getType == 'USF' && (  isset($_POST['show_in_several_organizations']) 
                              || $gCurrentOrganization->countAllRecords() == 1))
    || ($getType == 'ROL' && $category->getValue('cat_name_intern') == 'CONFIRMATION_OF_PARTICIPATION'))
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

    if($category->getValue('cat_name') != $_POST['cat_name'])
    {
        // Schauen, ob die Kategorie bereits existiert
        $sql    = 'SELECT COUNT(*) as count
                     FROM '. TBL_CATEGORIES. '
                    WHERE cat_type    = \''. $getType. '\'
                      AND cat_name LIKE \''. $_POST['cat_name']. '\'
                      AND cat_id     <> '.$getCatId. 
                          $sqlSearchOrga;
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('CAT_CATEGORY_EXIST'));
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
    $returnCode = $category->save();

    if($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // falls eine Kategorie von allen Orgas auf eine Bestimmte umgesetzt wurde oder anders herum,
    // dann muss die Sequenz fuer den alle Kategorien dieses Typs neu gesetzt werden
    if(isset($_POST['cat_org_id']) && $_POST['cat_org_id'] <> $cat_org_merker)
    {
        $sequenceCategory = new TableCategory($gDb);
        $sequence = 0;

        $sql    = 'SELECT *
                     FROM '. TBL_CATEGORIES. '
                    WHERE cat_type = "'. $getType. '"
                      AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                          OR cat_org_id IS NULL )
                    ORDER BY cat_org_id ASC, cat_sequence ASC';
        $result = $gDb->query($sql);

        while($row = $gDb->fetch_array($result))
        {
            $sequence++;
            $sequenceCategory->clear();
            $sequenceCategory->setArray($row);

            $sequenceCategory->setValue('cat_sequence', $sequence);
            $sequenceCategory->save();
        }
    }

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['categories_request']);

    $gMessage->setForwardUrl($_SESSION['navigation']->getUrl());
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));

}
elseif($getMode == 2)
{
    // delete category

    if($category->getValue('cat_system') == 1)
    {
        // system-category couldn't be deleted
        echo $gL10n->get('SYS_INVALID_PAGE_VIEW');
		exit();
    }

	$returnCode = $category->delete();

	if($returnCode)
	{
		echo 'done';
	}
}
elseif($getMode == 4)
{
    // Kategoriereihenfolge aktualisieren
    $category->moveSequence($getSequence);
    exit();
}
?>