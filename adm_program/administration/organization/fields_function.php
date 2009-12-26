<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Profilfelder
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes
 * mode:   1 - Feld anlegen oder updaten
 *         2 - Feld loeschen
 *         4 - Reihenfolge fuer die uebergebene usf_id anpassen
 * sequence: neue Reihenfolge fuer die uebergebene usf_id
 *
 *****************************************************************************/
 
require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_user_field.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 4)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['usf_id']))
{
    if(is_numeric($_GET['usf_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}

if(isset($_GET['sequence']) && admStrToUpper($_GET['sequence']) != 'UP' && admStrToUpper($_GET['sequence']) != 'DOWN')
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// UserField-objekt anlegen
$user_field = new TableUserField($g_db);

if($_GET['usf_id'] > 0)
{
    $user_field->readData($_GET['usf_id']);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($user_field->getValue('cat_org_id') >  0
    && $user_field->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

$err_code = '';

if($_GET['mode'] == 1)
{
   // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_REQUEST;
    
    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($user_field->getValue('usf_system') == 0 && strlen($_POST['usf_name']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Name'));
    }    

    if($user_field->getValue('usf_system') == 0 && strlen($_POST['usf_type']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Datentyp'));
    }    

    if($user_field->getValue('usf_system') == 0 && $_POST['usf_cat_id'] == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Kategorie'));
    }
    
    // Nachname und Vorname sollen immer Pflichtfeld bleiben
    if($user_field->getValue('usf_name') == 'Nachname'
    || $user_field->getValue('usf_name') == 'Vorname')
    {
        $_POST['usf_mandatory'] = 1;
    }
    
    if($user_field->getValue('usf_name') != $_POST['usf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql    = 'SELECT COUNT(*) as count 
                     FROM '. TBL_USER_FIELDS. '
                     JOIN '. TBL_CATEGORIES. '
                       ON usf_cat_id = cat_id
                      AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                          OR cat_org_id IS NULL )
                    WHERE usf_name LIKE "'. $_POST['usf_name']. '"
                      AND usf_id     <> '. $_GET['usf_id'];
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);

        if($row['count'] > 0)
        {
            $g_message->show($g_l10n->get('ORG_PHR_FIELD_EXIST'));
        }      
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if(isset($_POST['usf_hidden']))
    {
        $_POST['usf_hidden'] = 0;
    }
    else
    {
        $_POST['usf_hidden'] = 1;
    }
    if(isset($_POST['usf_disabled']) == false)
    {
        $_POST['usf_disabled'] = 0;
    }
    if(isset($_POST['usf_mandatory']) == false)
    {
        $_POST['usf_mandatory'] = 0;
    }
    
    if($user_field->getValue('usf_system') == 1)
    {
        unset($_POST['usf_name']);
        unset($_POST['usf_cat_id']);
        unset($_POST['usf_type']);
    }

    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'usf_') === 0)
        {
            $user_field->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $user_field->save();

    if($return_code < 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }    

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['fields_request']);

    $err_code = 'SYS_PHR_SAVE';
}
elseif($_GET['mode'] == 2)
{
    if($user_field->getValue('usf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Feld loeschen
    if($user_field->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($_GET['mode'] == 4)
{
    // Feldreihenfolge aktualisieren
    $user_field->moveSequence($_GET['sequence']);
    exit();
}
         
// zu den Organisationseinstellungen zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($g_l10n->get($err_code));
?>