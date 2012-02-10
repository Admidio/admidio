<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * ann_id    - ID der Ankuendigung, die bearbeitet werden soll
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) ANN_ANNOUNCEMENTS
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/ckeditor_special.php');
require_once('../../system/classes/table_announcement.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

if(!$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getAnnId    = admFuncVariableIsValid($_GET, 'ann_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('ANN_ANNOUNCEMENTS'));

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($gDb);

if($getAnnId > 0)
{
    $announcement->readData($getAnnId);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$announcement->setArray($_SESSION['announcements_request']);
    unset($_SESSION['announcements_request']);
}

// create an object of ckeditor and replace textarea-element
$ckEditor = new CKEditorSpecial();

// Html-Kopf ausgeben
if($getAnnId > 0)
{
    $gLayout['title'] = $gL10n->get('SYS_EDIT_VAR', $gL10n->get('ANN_ANNOUNCEMENT'));
}
else
{
    $gLayout['title'] = $gL10n->get('SYS_CREATE_VAR', $gL10n->get('ANN_ANNOUNCEMENT'));
}

$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#ann_headline").focus();
        });
        
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/announcements/announcements_function.php?ann_id='.$getAnnId.'&amp;headline='. $getHeadline. '&amp;mode=1" >
<div class="formLayout" id="edit_announcements_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admAnnouncementTitle">
			<div class="groupBoxHeadline" id="admAnnouncementTitleHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admAnnouncementTitleBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admAnnouncementTitleBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_TITLE').'
			</div>

			<div class="groupBoxBody" id="admAnnouncementTitleBody">
                <ul class="formFieldList">
                    <li>
                        <div>
                                <input type="text" id="ann_headline" name="ann_headline" style="width: 95%;" maxlength="100" value="'. $announcement->getValue('ann_headline'). '" />
                                <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        </div>
                    </li>';
                    
        
                    // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf 'global' gesetzt werden
                    if($gCurrentOrganization->getValue('org_org_id_parent') > 0
                    || $gCurrentOrganization->hasChildOrganizations())
                    {
                        echo '
                        <li>
                            <div>
                                    <input type="checkbox" id="ann_global" name="ann_global" ';
                                    if($announcement->getValue('ann_global') == 1)
                                    {
                                        echo ' checked="checked" ';
                                    }
                                    echo ' value="1" />
                                    <label for="ann_global">'.$gL10n->get('SYS_ENTRY_MULTI_ORGA').'</label>
                                    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL&amp;inline=true"><img 
                                        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL\',this)" onmouseout="ajax_hideTooltip()"
                                        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
                            </div>
                        </li>';
                    }
                echo '</ul>
            </div>
        </div>
		<div class="groupBox" id="admDescription">
			<div class="groupBoxHeadline" id="admDescriptionHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admDescriptionBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admDescriptionBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_TEXT').'
			</div>

			<div class="groupBoxBody" id="admDescriptionBody">
                <ul class="formFieldList">
                    <li>
                         '.$ckEditor->createEditor('ann_description', $announcement->getValue('ann_description')).'
                         <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </li>
                </ul>
            </div>
        </div>';

        if($announcement->getValue('ann_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $announcement->getValue('ann_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $announcement->getValue('ann_timestamp_create'));

                if($announcement->getValue('ann_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $announcement->getValue('ann_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $announcement->getValue('ann_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>