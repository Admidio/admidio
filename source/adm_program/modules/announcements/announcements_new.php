<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * ann_id    - ID der Ankuendigung, die bearbeitet werden soll
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_announcement.php');
if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

if(!$g_current_user->editAnnouncements())
{
    $g_message->show('norights');
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_ann_id   = 0;
$req_headline = 'Ankündigungen';

// Uebergabevariablen pruefen

if(isset($_GET['ann_id']))
{
    if(is_numeric($_GET['ann_id']) == false)
    {
        $g_message->show('invalid');
    }
    $req_ann_id = $_GET['ann_id'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET["headline"]);
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($g_db);

if($req_ann_id > 0)
{
    $announcement->readData($req_ann_id);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $g_message->show('norights');
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['announcements_request'] as $key => $value)
    {
        if(strpos($key, 'ann_') == 0)
        {
            $announcement->setValue($key, stripslashes($value));
        }
    }
    unset($_SESSION['announcements_request']);
}

// Html-Kopf ausgeben
if($req_ann_id > 0)
{
    $g_layout['title'] = $req_headline. ' ändern';
}
else
{
    $g_layout['title'] = $req_headline. ' anlegen';
}
//Script für BBCode laden
$javascript = '';
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('ann_description');
}

$g_layout['header'] = $javascript. '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#ann_headline").focus();
	 	}); 
	//--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/announcements/announcements_function.php?ann_id='.$req_ann_id.'&amp;headline='. $_GET['headline']. '&amp;mode=1" >
<div class="formLayout" id="edit_announcements_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="ann_headline">Überschrift:</label></dt>
                    <dd>
                        <input type="text" id="ann_headline" name="ann_headline" style="width: 350px;" tabindex="1" maxlength="100" value="'. $announcement->getValue('ann_headline'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            ';
         if ($g_preferences['enable_bbcode'] == 1)
         {
            printBBcodeIcons();
         }
         echo '
            <li>
                <dl>
                    <dt><label for="ann_description">Text:</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="ann_description" name="ann_description" style="width: 350px;" tabindex="2" rows="10" cols="40">'. $announcement->getValue('ann_description'). '</textarea>
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>';

            // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf 'global' gesetzt werden
            if($g_current_organization->getValue('org_org_id_parent') > 0
            || $g_current_organization->hasChildOrganizations())
            {
                echo '
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type="checkbox" id="ann_global" name="ann_global" tabindex="3" ';
                            if($announcement->getValue('ann_global') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="ann_global">'.$req_headline.' für mehrere Organisationen sichtbar</label>
                            <a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=date_global&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=320&amp;width=580"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=date_global\',this)" onmouseout="ajax_hideTooltip()"
                                src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                        </dd>
                    </dl>
                </li>';
            }
        echo '</ul>

        <hr />

        <div class="formSubmit">
            <button name="speichern" type="submit" value="speichern" tabindex="4"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>