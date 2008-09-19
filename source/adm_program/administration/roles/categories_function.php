<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Kategorien
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
 *         3 - Frage, ob Kategorie geloescht werden soll
 *         4 - Reihenfolge fuer die uebergebene usf_id anpassen
 * sequence: neue Reihenfolge fuer die uebergebene usf_id
 * this_orga: 0 - Reihenfolge von orga-unabhaenigen Kategorien wird veraendert
 *            1 - 0 - Reihenfolge von orga-abhaenigen Kategorien wird veraendert
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/category.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_cat_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['cat_id']))
{
    if(is_numeric($_GET['cat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_cat_id = $_GET['cat_id'];
}

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK" && $_GET['type'] != "USF")
    {
        $g_message->show("invalid");
    }
    if($_GET['type'] == "ROL" && $g_current_user->assignRoles() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "LNK" && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "USF" && $g_current_user->editUsers() == false)
    {
        $g_message->show("norights");
    }
}
else
{
    $g_message->show("invalid");
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 4)
{
    $g_message->show("invalid");
}

if(isset($_GET['sequence']))
{
    if(is_numeric($_GET['sequence']) == false)
    {
        $g_message->show("invalid");
    }
}

// UserField-objekt anlegen
$category = new Category($g_db);

if($req_cat_id > 0)
{
    $category->readData($req_cat_id);
    
    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue("cat_org_id") >  0
    && $category->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}
else
{
    // es wird eine neue Kategorie angelegt
    $category->setValue("cat_org_id", $g_current_organization->getValue("org_id"));
    $category->setValue("cat_type", $_GET['type']);
}

$err_code = "";

if($_GET['mode'] == 1)
{
    // Kategorie anlegen oder updaten

    $_SESSION['categories_request'] = $_REQUEST;

    if(strlen($_POST['cat_name']) == 0)
    {
        $g_message->show("feld", "Name");
    }
    
    // Kategorie ist immer Orga-spezifisch, ausser manuell angelegte Orga-Felder-Kategorie
    $check_all_orgas = false;
    
    if($_GET['type'] == "USF"
    && (isset($_POST['cat_org_id']) || $category->getValue("cat_system") == 1 || $g_current_organization->countAllRecords() == 1))
    {
        $_POST['cat_org_id'] = NULL;
        $check_all_orgas = true;
    }
    else
    {
        $_POST['cat_org_id'] = $g_current_organization->getValue("org_id");
    }
        
    if($req_cat_id == 0)
    {
        // Schauen, ob die Kategorie bereits existiert
        $search_orga = "";
        if($check_all_orgas == false)
        {
            $search_orga = " AND (  cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                                 OR cat_org_id IS NULL )";
        }
        $sql    = "SELECT COUNT(*) as count 
                     FROM ". TBL_CATEGORIES. "
                    WHERE cat_type = '". $_GET['type']. "'
                      AND cat_name LIKE '". $_POST['cat_name']. "'
                          $search_orga ";
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);

        if($row['count'] > 0)
        {
            $g_message->show("category_exist");
        }      
    }

    if(isset($_POST['cat_hidden']) == false)
    {
        $_POST['cat_hidden'] = 0;
    }
    
    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "cat_") === 0)
        {
            $category->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $category->save();

    if($return_code < 0)
    {
        $g_message->show("norights");
    } 

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['categories_request']);

    $err_code = "save";
}
elseif($_GET['mode'] == 2 || $_GET["mode"] == 3)
{
    // Kategorie loeschen
    
    if($category->getValue("cat_system") == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $g_message->show("invalid");
    }
    
    if($_GET['mode'] == 2)
    {
        // Feld loeschen
        $category->delete();

        $err_code = "delete";
    }
    elseif($_GET["mode"] == 3)
    {
        // Frage, ob Kategorie geloescht werden soll
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$req_cat_id&mode=2&type=". $_GET['type']);
        $g_message->show("delete_category", $category->getValue("cat_name"), "Löschen");
    }    
}
elseif($_GET['mode'] == 4)
{
    // Kategoriereihenfolge aktualisieren
    $sequence_old = $category->getValue("cat_sequence");

    if($_GET['type'] == "USF" && $_GET['this_orga'] == 1)
    {
        $sql = "SELECT COUNT(1) as count FROM ". TBL_CATEGORIES. "
                 WHERE cat_type   = 'USF'
                   AND cat_org_id IS NULL
                   AND cat_system = 0 ";
        $g_db->query($sql);
        $row = $g_db->fetch_array();
        $_GET['sequence'] = $_GET['sequence'] + $row['count'];
    }
    
    if($sequence_old != $_GET['sequence'])
    {
        $category->setValue("cat_sequence", $_GET['sequence']);
        $category->save();
    }
    exit();
}
         
// zur Kategorienuebersicht zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>