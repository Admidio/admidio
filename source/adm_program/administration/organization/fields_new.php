<?php
/******************************************************************************
 * Create and edit profile fields
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usf_id : profile field id that should be edited
 *
 ****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/ckeditor_special.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_user_field.php');

// Initialize and check the parameters
$getUsfId = admFuncVariableIsValid($_GET, 'usf_id', 'numeric', 0);

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// benutzerdefiniertes Feldobjekt anlegen
$userField = new TableUserField($gDb);

if($getUsfId > 0)
{
    $userField->readData($getUsfId);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($userField->getValue('cat_org_id') >  0
    && $userField->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['fields_request']))
{
	// hidden muss 0 sein, wenn ein Haeckchen gesetzt wird
	if($_SESSION['fields_request']['usf_hidden'] == 1)
	{
		$_SESSION['fields_request']['usf_hidden'] = 0;
	}
	else
	{
		$_SESSION['fields_request']['usf_hidden'] = 1;
	}

    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$userField->setArray($_SESSION['fields_request']);
    unset($_SESSION['fields_request']);
}

$html_disabled = '';
$field_focus   = 'usf_name';
if($userField->getValue('usf_system') == 1)
{
    $html_disabled = ' disabled="disabled" ';
    $field_focus   = 'usf_description';
}

// create an object of ckeditor and replace textarea-element
$ckEditor = new CKEditorSpecial();

// zusaetzliche Daten fuer den Html-Kopf setzen
if($getUsfId > 0)
{
    $gLayout['title']  = $gL10n->get('ORG_EDIT_PROFILE_FIELD');
}
else
{
    $gLayout['title']  = $gL10n->get('ORG_CREATE_PROFILE_FIELD');
}

// Kopfinformationen
$gLayout['header'] = '
<script type="text/javascript"><!--
	function setValueList()
	{
		if($("#usf_type").val() == "DROPDOWN" || $("#usf_type").val() == "RADIO_BUTTON")
		{
			$("#admValueList").show("slow");
		}
		else
		{
			$("#admValueList").hide();
		}
	}
	
	$(document).ready(function() 
	{
		setValueList();
		$("#'.$field_focus.'").focus();
		$("#usf_type").click(function() {setValueList();});
	}); 
//--></script>';

// Html-Kopf ausgeben
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<form id="edit_field" action="'.$g_root_path.'/adm_program/administration/organization/fields_function.php?usf_id='.$getUsfId.'&amp;mode=1" method="post">
<div class="formLayout" id="edit_fields_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admDesignation">
			<div class="groupBoxHeadline" id="admDesignationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admDesignationBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admDesignationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_DESIGNATION').'
			</div>

			<div class="groupBoxBody" id="admDesignationBody">	
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="usf_name">'.$gL10n->get('SYS_NAME').':</label></dt>
							<dd><input type="text" name="usf_name" id="usf_name" '.$html_disabled.' style="width: 90%;" maxlength="100"
								value="'. $userField->getValue('usf_name', 'plain'). '" />
								<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
							</dd>
						</dl>
					</li>';
					// show internal field name for information
					if($getUsfId > 0)
					{
						echo '<li>
							<dl>
								<dt><label for="usf_name">'.$gL10n->get('SYS_INTERNAL_NAME').':</label></dt>
								<dd><input type="text" name="usf_name_intern" id="usf_name_intern" disabled="disabled" style="width: 90%;" maxlength="100"
									value="'. $userField->getValue('usf_name_intern'). '" />
									<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_INTERNAL_NAME_DESC&amp;inline=true"><img 
										onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_INTERNAL_NAME_DESC\',this)" onmouseout="ajax_hideTooltip()"
										class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
								</dd>
							</dl>
						</li>';
					}
            echo '
					<li>
						<dl>
							<dt><label for="usf_cat_id">'.$gL10n->get('SYS_CATEGORY').':</label></dt>
							<dd>';
								if($userField->getValue('usf_system') == 1)
								{
									// bei Systemfeldern darf die Kategorie nicht mehr veraendert werden
									echo '<input type="text" name="usf_cat_id" id="usf_cat_id" disabled="disabled" style="width: 150px;" 
										maxlength="30" value="'. $userField->getValue('cat_name'). '" />';
								}
								else
								{
									echo FormElements::generateCategorySelectBox('USF', $userField->getValue('usf_cat_id'), 'usf_cat_id');
								}
								echo '<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
							</dd>
						</dl>
					</li>
				</ul>
			</div>
		</div>
		<div class="groupBox" id="admPresentation">
			<div class="groupBoxHeadline" id="admPresentationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admPresentationBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admPresentationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_PRESENTATION').'
			</div>

			<div class="groupBoxBody" id="admPresentationBody">	
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="usf_type">'.$gL10n->get('ORG_DATATYPE').':</label></dt>
							<dd>';
								$userFieldText = array(''         => '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -',
													   'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
													   'DATE'     => $gL10n->get('SYS_DATE'),
													   'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
													   'EMAIL'    => $gL10n->get('SYS_EMAIL'),
													   'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
													   'TEXT'     => $gL10n->get('SYS_TEXT').' (50)',
													   'TEXT_BIG' => $gL10n->get('SYS_TEXT').' (255)',
													   'URL'      => $gL10n->get('ORG_URL'),
													   'NUMERIC'  => $gL10n->get('SYS_NUMBER'));

								if($userField->getValue('usf_system') == 1)
								{
									// bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
									echo '<input type="text" name="usf_type" id="usf_type" disabled="disabled" style="width: 150px;" 
										maxlength="30" value="'. $userFieldText[$userField->getValue('usf_type')]. '" />';
								}
								else
								{
									echo '<select size="1" name="usf_type" id="usf_type">';
										// fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
										foreach($userFieldText as $key => $value)
										{
											echo '<option value="'.$key.'" '; 
											if($userField->getValue('usf_type') == $key) 
											{
												echo ' selected="selected"';
											}
											echo '>'.$value.'</option>';
										}
									echo '</select>';
								}
								echo '<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
							</dd>
						</dl>
					</li>
					<li id="admValueList">
						<dl>
							<dt><label for="usf_value_list">'.$gL10n->get('ORG_VALUE_LIST').':</label></dt>
							<dd><textarea name="usf_value_list" id="usf_value_list" style="width: 90%;" rows="6" cols="40">'.
								$userField->getValue('usf_value_list', 'plain'). '</textarea>
								<span class="mandatoryFieldMarker" style="margin: 0px;" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_VALUE_LIST_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_VALUE_LIST_DESC\',this)" onmouseout="ajax_hideTooltip()"
									class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" style="margin: 0px;" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="usf_icon">'.$gL10n->get('SYS_ICON').':</label></dt>
							<dd><input type="text" name="usf_icon" id="usf_icon" style="width: 90%;" maxlength="100"
								value="'. $userField->getValue('usf_icon', 'plain'). '" />
							</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="usf_url">'.$gL10n->get('ORG_URL').':</label></dt>
							<dd><input type="text" name="usf_url" id="usf_url" style="width: 90%;" maxlength="100"
								value="'. $userField->getValue('usf_url'). '" />
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_URL_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_URL_DESC\',this)" onmouseout="ajax_hideTooltip()"
									class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" style="margin: 0px;" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>					
				</ul>
			</div>
		</div>
		<div class="groupBox" id="admAuthorization">
			<div class="groupBoxHeadline" id="admAuthorizationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admAuthorizationBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admAuthorizationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_AUTHORIZATION').'
			</div>

			<div class="groupBoxBody" id="admAuthorizationBody">	
				<ul class="formFieldList">		
					<li>
						<dl>
							<dt>
								<label for="usf_hidden">
									<img src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />
								</label>
							</dt>
							<dd>
								<input type="checkbox" name="usf_hidden" id="usf_hidden" ';
								if($userField->getValue('usf_hidden') == 0)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="usf_hidden">'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_HIDDEN_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_HIDDEN_DESC\',this)" onmouseout="ajax_hideTooltip()"
									class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>            
					<li>
						<dl>
							<dt>
								<label for="usf_disabled">
									<img src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED').'" />
								</label>
							</dt>
							<dd>
								<input type="checkbox" name="usf_disabled" id="usf_disabled" ';
								if($userField->getValue('usf_disabled') == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="usf_disabled">'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_DISABLED_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_DISABLED_DESC\',this)" onmouseout="ajax_hideTooltip()"
									class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>            
					<li>
						<dl>
							<dt>
								<label for="usf_mandatory">
									<img src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_MANDATORY').'" />
								</label>
							</dt>
							<dd>
								<input type="checkbox" name="usf_mandatory" id="usf_mandatory" ';
								if($userField->getValue('usf_mandatory') == 1)
								{
									echo ' checked="checked" ';
								}
								if($userField->getValue('usf_name_intern') == 'LAST_NAME'
								|| $userField->getValue('usf_name_intern') == 'FIRST_NAME')
								{
									echo ' disabled="disabled" ';
								}
								echo ' value="1" />
								<label for="usf_mandatory">'.$gL10n->get('ORG_FIELD_MANDATORY').'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_MANDATORY_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_MANDATORY_DESC\',this)" onmouseout="ajax_hideTooltip()"
									class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
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
                    <li>'.$ckEditor->createEditor('usf_description', $userField->getValue('usf_description'), 'AdmidioDefault', 200).'</li>
                </ul>
			</div>
		</div>';

        if($userField->getValue('usf_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $userField->getValue('usf_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $userField->getValue('usf_timestamp_create'));

                if($userField->getValue('usf_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $userField->getValue('usf_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $userField->getValue('usf_timestamp_change'));
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