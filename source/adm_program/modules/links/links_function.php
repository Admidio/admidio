<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Links
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lnk_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neuen Link anlegen
 *           2 - Link loeschen
 *           3 - Link editieren
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Links steht
 *           (Default) Links
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_weblink.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editWeblinksRight())
{
    $g_message->show('norights');
}

// Uebergabevariablen pruefen
if (array_key_exists('lnk_id', $_GET))
{
    if (is_numeric($_GET['lnk_id']) == false)
    {
         $g_message->show('invalid');
    }
}
else
{
    $_GET['lnk_id'] = 0;
}


if (array_key_exists('mode', $_GET))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show('invalid');
    }
}

if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = 'Links';
}

// Linkobjekt anlegen
$link = new TableWeblink($g_db, $_GET['lnk_id']);

$_SESSION['links_request'] = $_REQUEST;

if ($_GET['mode'] == 1 || ($_GET['mode'] == 3 && $_GET['lnk_id'] > 0) )
{
    if(strlen(strStripTags($_POST['lnk_name'])) == 0)
    {
        $g_message->show('feld', 'Linkname');
    }
    if(strlen(strStripTags($_POST['lnk_url'])) == 0)
    {
        $g_message->show('feld', 'Linkadresse');
    }
    if(strlen($_POST['lnk_cat_id']) == 0)
    {
        $g_message->show('feld', 'Kategorie');
    }
   
    // POST Variablen in das Ankuendigungs-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'lnk_') === 0)
        {
            $link->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $link->save();

    if($return_code < 0)
    {
        $g_message->show('norights');
    }

    unset($_SESSION['links_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}

elseif ($_GET['mode'] == 2 && $_GET['lnk_id'] > 0)
{
    // Loeschen von Weblinks...
    $link->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}

else
{
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $g_message->show('invalid');
}

?>