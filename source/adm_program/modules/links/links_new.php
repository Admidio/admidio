<?php
/******************************************************************************
 * Create and edit weblinks
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lnk_id    - ID of the weblink that should be edited
 * headline  - Title of the weblink module. This will be shown in the whole module.
 *             (Default) LNK_WEBLINKS
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('LNK_WEBLINKS'));

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

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $gLayout['title']);
    
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// show back link
echo $gNavigation->getHtmlBackButton();

// show headline of module
echo '<h1 class="admHeadline">'.$gLayout['title'].'</h1>';

// Html des Modules ausgeben
if($getLinkId > 0)
{
    $modeEditOrCreate = '3';
}
else
{
    $modeEditOrCreate = '1';
}

// show form
$form = new Form('weblinks-edit-form', $g_root_path.'/adm_program/modules/links/links_function.php?lnk_id='. $getLinkId. '&amp;headline='. $getHeadline. '&amp;mode='.$modeEditOrCreate);
$form->openGroupBox('gb-weblink-name');
$form->addTextInput('lnk_name', $gL10n->get('LNK_LINK_NAME'), $link->getValue('lnk_name'), 250, true);
$form->addTextInput('lnk_url', $gL10n->get('LNK_LINK_ADDRESS'), $link->getValue('lnk_url'), 250, true);
// add selectbox !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
$form->addEditor('lnk_description', $gL10n->get('SYS_DESCRIPTION'), $link->getValue('lnk_description'), false, 'AdmidioDefault', '150px');
$form->closeGroupBox();
$form->addString(admFuncShowCreateChangeInfoById($link->getValue('lnk_usr_id_create'), $link->getValue('lnk_timestamp_create'), $link->getValue('lnk_usr_id_change'), $link->getValue('lnk_timestamp_change')));
$form->addSubmitButton('btnSave', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->show();
/*
echo '
<form action="'.$g_root_path.'/adm_program/modules/links/links_function.php?lnk_id='. $getLinkId. '&amp;headline='. $getHeadline. '&amp;mode='.$modeEditOrCreate.'" method="post">
<div class="formLayout" id="edit_links_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admProperties">
			<div class="groupBoxHeadline" id="admPropertiesHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admPropertiesBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admPropertiesBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_NAME').' &amp; '.$gL10n->get('SYS_PROPERTIES').'
			</div>

			<div class="groupBoxBody" id="admPropertiesBody">
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
				</ul>
			</div>
		</div>

		<div class="groupBox" id="admDescription">
			<div class="groupBoxHeadline" id="admDescriptionHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admDescriptionBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admDescriptionBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_DESCRIPTION').'
			</div>

			<div class="groupBoxBody" id="admDescriptionBody">
                <ul class="formFieldList">
                    <li>
                         '.$ckEditor->createEditor('lnk_description', $link->getValue('lnk_description'), 'AdmidioDefault', 150).'
                    </li>
                </ul>
            </div>
        </div>';

        // show informations about user who creates the recordset and changed it
        echo admFuncShowCreateChangeInfoById($link->getValue('lnk_usr_id_create'), $link->getValue('lnk_timestamp_create'), $link->getValue('lnk_usr_id_change'), $link->getValue('lnk_timestamp_change')).'

        <div class="formSubmit">
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
</ul>';*/

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>