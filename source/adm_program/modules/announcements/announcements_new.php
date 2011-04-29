<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

if(!$g_current_user->editAnnouncements())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_ann_id   = 0;
$req_headline = $g_l10n->get('ANN_ANNOUNCEMENT');

// Uebergabevariablen pruefen

if(isset($_GET['ann_id']))
{
    if(is_numeric($_GET['ann_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_ann_id = $_GET['ann_id'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET['headline']);
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
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$announcement->setArray($_SESSION['announcements_request']);
    unset($_SESSION['announcements_request']);
}

// Html-Kopf ausgeben
if($req_ann_id > 0)
{
    $g_layout['title'] = $g_l10n->get('SYS_EDIT_VAR', $g_l10n->get('ANN_ANNOUNCEMENT'));
}
else
{
    $g_layout['title'] = $g_l10n->get('SYS_CREATE_VAR', $g_l10n->get('ANN_ANNOUNCEMENT'));
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
                    <dt><label for="ann_headline">'.$g_l10n->get('SYS_HEADLINE').':</label></dt>
                    <dd>
                        <input type="text" id="ann_headline" name="ann_headline" style="width: 345px;" maxlength="100" value="'. $announcement->getValue('ann_headline'). '" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
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
                    <dt><label for="ann_description">'.$g_l10n->get('SYS_TEXT').':</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="ann_description" name="ann_description" style="width: 345px;" rows="10" cols="40">'. $announcement->getValue('ann_description'). '</textarea>
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
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
                            <input type="checkbox" id="ann_global" name="ann_global" ';
                            if($announcement->getValue('ann_global') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="ann_global">'.$g_l10n->get('SYS_ENTRY_MULTI_ORGA').'</label>
                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
                        </dd>
                    </dl>
                </li>';
            }
        echo '</ul>

        <hr />';

        if($announcement->getValue('ann_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $announcement->getValue('ann_usr_id_create'));
                echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $announcement->getValue('ann_timestamp_create'));

                if($announcement->getValue('ann_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $announcement->getValue('ann_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $announcement->getValue('ann_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>