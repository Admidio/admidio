<?php
/******************************************************************************
 * Links anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lnk_id        - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Links steht
 *                 (Default) Links
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_weblink.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('LNK_WEBLINKS'));

if ($gPreferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Ist ueberhaupt das Recht vorhanden?
if (!$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Weblinkobjekt anlegen
$link = new TableWeblink($gDb, $getLinkId);

if(isset($_SESSION['links_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$link->setArray($_SESSION['links_request']);
    unset($_SESSION['links_request']);
}

// Html-Kopf ausgeben
if($getLinkId > 0)
{
    $gLayout['title'] = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
}
else
{
    $gLayout['title'] = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
}

//Script für BBCode laden
$javascript = "";
if ($gPreferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('lnk_description');
}

$gLayout['header'] = $javascript. '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#lnk_name").focus();
	 	}); 
	//--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
if($getLinkId > 0)
{
    $new_mode = '3';
}
else
{
    $new_mode = '1';
}

echo '
<form action="'.$g_root_path.'/adm_program/modules/links/links_function.php?lnk_id='. $getLinkId. '&amp;headline='. $getHeadline. '&amp;mode='.$new_mode.'" method="post">
<div class="formLayout" id="edit_links_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="lnk_name">'.$gL10n->get('LNK_LINK_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="lnk_name" name="lnk_name" style="width: 90%;" maxlength="250" value="'. $link->getValue('lnk_name'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_url">'.$gL10n->get('LNK_LINK_ADDRESS').':</label></dt>
                    <dd>
                        <input type="text" id="lnk_url" name="lnk_url" style="width: 90%;" maxlength="250" value="'. $link->getValue('lnk_url'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_cat_id">'.$gL10n->get('SYS_CATEGORY').':</label></dt>
                    <dd>
						'.FormElements::generateCategorySelectBox('LNK', $link->getValue('lnk_cat_id'), 'lnk_cat_id').'
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            ';
         if ($gPreferences['enable_bbcode'] == 1)
         {
            printBBcodeIcons();
         }
         echo '
            <li>
                <dl>
                    <dt><label for="lnk_description">'.$gL10n->get('SYS_DESCRIPTION').':</label>';
                        if($gPreferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="lnk_description" name="lnk_description" style="width: 90%;" rows="10" cols="40">'. $link->getValue('lnk_description'). '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($link->getValue('lnk_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $link->getValue('lnk_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $link->getValue('lnk_timestamp_create'));

                if($link->getValue('lnk_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $link->getValue('lnk_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $link->getValue('lnk_timestamp_change'));
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
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>