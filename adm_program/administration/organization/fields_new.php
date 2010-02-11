<?php
/******************************************************************************
 * Profilfelder anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes, das bearbeitet werden soll
 *
 ****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_user_field.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usf_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['usf_id']))
{
    if(is_numeric($_GET['usf_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_usf_id = $_GET['usf_id'];
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// benutzerdefiniertes Feldobjekt anlegen
$user_field = new TableUserField($g_db);

if($req_usf_id > 0)
{
    $user_field->readData($req_usf_id);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($user_field->getValue('cat_org_id') >  0
    && $user_field->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

if(isset($_SESSION['fields_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['fields_request'] as $key => $value)
    {
        // hidden muss 0 sein, wenn ein Haeckchen gesetzt wird
        if($key == 'usf_hidden')
        {
            if($value == 1)
            {
                $value = 0;
            }
            else
            {
                $value = 1;
            }
        }
        
        if(strpos($key, 'usf_') == 0)
        {
            $user_field->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['fields_request']);
}

$html_readonly = '';
$field_focus   = 'usf_name';
if($user_field->getValue('usf_system') == 1)
{
    $html_readonly = ' readonly="readonly" ';
    $field_focus   = 'usf_description';
}

// zusaetzliche Daten fuer den Html-Kopf setzen
if($req_usf_id > 0)
{
    $g_layout['title']  = 'Profilfeld bearbeiten';
}
else
{
    $g_layout['title']  = 'Profilfeld anlegen';
}

// Kopfinformationen
$g_layout['header'] = '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#'.$field_focus.'").focus();
	 	}); 
	//--></script>';

// Html-Kopf ausgeben
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<form id="edit_field" action="'.$g_root_path.'/adm_program/administration/organization/fields_function.php?usf_id='.$req_usf_id.'&amp;mode=1" method="post">
<div class="formLayout" id="edit_fields_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="usf_name">Name:</label></dt>
                    <dd><input type="text" name="usf_name" id="usf_name" '.$html_readonly.' style="width: 150px;" maxlength="30"
                        value="'. $user_field->getValue("usf_name"). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usf_description">Beschreibung:</label></dt>
                    <dd><textarea name="usf_description" id="usf_description" style="width: 330px;" rows="2" cols="40">'.
                        $user_field->getValue('usf_description'). '</textarea>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usf_cat_id">Kategorie:</label></dt>
                    <dd>';
                        if($user_field->getValue('usf_system') == 1)
                        {
                            // bei Systemfeldern darf die Kategorie nicht mehr veraendert werden
                            echo '<input type="text" name="usf_cat_id" id="usf_cat_id" readonly="readonly" style="width: 150px;" 
                                maxlength="30" value="'. $user_field->getValue("cat_name"). '" />';
                        }
                        else
                        {
                            echo '<select size="1" name="usf_cat_id" id="usf_cat_id">
                            <option value=" " '; 
                                if($user_field->getValue('usf_cat_id') == 0) 
                                {
                                    echo ' selected="selected" ';
                                }
                                echo '>- Bitte wählen -</option>';

                            $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
                                     WHERE (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                                           OR cat_org_id IS NULL )
                                       AND cat_type   = "USF"
                                     ORDER BY cat_sequence ASC ';
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_object($result))
                            {
                                echo '<option value="'.$row->cat_id.'"';
                                    if($user_field->getValue('usf_cat_id') == $row->cat_id)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                echo '>'.$row->cat_name.'</option>';
                            }
                            echo '</select>';
                        }
                        echo '<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usf_type">Datentyp:</label></dt>
                    <dd>';
                        $user_field_text = array('' => '- Bitte wählen -','DATE' => 'Datum','EMAIL' => 'E-Mail','CHECKBOX' => 'Ja / Nein',
                                                 'TEXT' => 'Text (50 Zeichen)','TEXT_BIG' => 'Text (255 Zeichen)','URL' => 'URL','NUMERIC' => 'Zahl');

                        if($user_field->getValue('usf_system') == 1)
                        {
                            // bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
                            echo '<input type="text" name="usf_type" id="usf_type" readonly="readonly" style="width: 150px;" 
                                maxlength="30" value="'. $user_field_text[$user_field->getValue('usf_type')]. '" />';
                        }
                        else
                        {
                            echo '<select size="1" name="usf_type" id="usf_type">';
                                // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
                                foreach($user_field_text as $key => $value)
                                {
                                    echo '<option value="'.$key.'" '; 
                                    if($user_field->getValue('usf_type') == $key) 
                                    {
                                        echo ' selected="selected"';
                                    }
                                    echo '>'.$value.'</option>';
                                }
                            echo '</select>';
                        }
                        echo '<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt>
                        <label for="usf_hidden">
                            <img src="'. THEME_PATH. '/icons/eye.png" alt="Feld für alle Benutzer sichtbar" />
                        </label>
                    </dt>
                    <dd>
                        <input type="checkbox" name="usf_hidden" id="usf_hidden" ';
                        if($user_field->getValue('usf_hidden') == 0)
                        {
                            echo ' checked="checked" ';
                        }
                        echo ' value="1" />
                        <label for="usf_hidden">Feld für alle Benutzer sichtbar</label>
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=field_hidden&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=field_hidden\',this)" onmouseout="ajax_hideTooltip()"
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for="usf_disabled">
                            <img src="'. THEME_PATH. '/icons/textfield_key.png" alt="Feld nur für berechtigte Benutzer bearbeitbar" />
                        </label>
                    </dt>
                    <dd>
                        <input type="checkbox" name="usf_disabled" id="usf_disabled" ';
                        if($user_field->getValue('usf_disabled') == 1)
                        {
                            echo ' checked="checked" ';
                        }
                        echo ' value="1" />
                        <label for="usf_disabled">Feld nur für berechtigte Benutzer bearbeitbar</label>
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=field_disabled&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=field_disabled\',this)" onmouseout="ajax_hideTooltip()"
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for="usf_mandatory">
                            <img src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="Pflichtfeld, muss vom Benutzer gefüllt werden" />
                        </label>
                    </dt>
                    <dd>
                        <input type="checkbox" name="usf_mandatory" id="usf_mandatory" ';
                        if($user_field->getValue('usf_mandatory') == 1)
                        {
                            echo ' checked="checked" ';
                        }
                        if($user_field->getValue('usf_name') == 'Nachname'
                        || $user_field->getValue('usf_name') == 'Vorname')
                        {
                            echo ' disabled="disabled" ';
                        }
                        echo ' value="1" />
                        <label for="usf_mandatory">Pflichtfeld, muss vom Benutzer gef&uuml;llt werden</label>
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=field_mandatory&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=field_mandatory\',this)" onmouseout="ajax_hideTooltip()"
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                    </dd>
                </dl>
            </li>            
        </ul>

        <hr />

        <div class="formSubmit">
            <button name="speichern" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
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