<?php
/******************************************************************************
 * Grusskarte Draw Dropdown Menue
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   = 0 : (Default) Liste mit Benutzern anzeigen
 *        = 1 : Liste mit allen Rollen 
 * rol_id     : Durch die ID der Rolle bekomme ich alle Mitglieder dieser
 * usr_id     : Durch die User ID bekommt man den vollstaendigen Namen + E-mail
 * extern = 1 : Die Grusskarte geht an einen externen Empfaenger
 *
 *****************************************************************************/
 
require_once('../../system/common.php');

// Uebergabevariablen pruefen und ggf. initialisieren
$get_mode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', 0);
$get_rol_id = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$get_usr_id = admFuncVariableIsValid($_GET, 'usr_id', 'string', 0);
$get_extern = admFuncVariableIsValid($_GET, 'extern', 'boolean', 0);

// Wenn das erste Menue mit den aufgelisteten Rollen gezeichnet werden soll (Uebergabe mode == 1)
// Es werden alle Rollen die in dieser Organisation vorhanden sind aufgelistet und stehen nun bereit zur Auswahl
if($g_valid_login && $get_mode == 1)
{
    echo '<select size="1" id="rol_id" name="rol_id" onchange="javascript:ecardJS.getMenuRecepientName()">';
    if (isset($form_values['rol_id']) == '')
    {
        echo '<option value="" selected="selected" disabled="disabled">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>
        <optgroup label="'.$g_l10n->get("ECA_OTHER_RECIPIENT").'">
        <option value="externMail" >'.$g_l10n->get("ECA_EXTERNAL_RECIPIENT").'</option>';
    }
    
    if ($g_valid_login)
    {
        // im eingeloggten Zustand nur an Rollen schreiben, die die Einstellung besitzen
       $sql = 'SELECT rol_name, rol_id, cat_name 
                 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_mail_this_role > 0
                  AND rol_valid  = 1
                  AND rol_cat_id = cat_id
                  AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
                ORDER BY cat_sequence, rol_name ';
    }
    else
    {
        // alle Rollen auflisten,
        // an die im nicht eingeloggten Zustand Mails versendet werden duerfen
        $sql = 'SELECT rol_name, rol_id, cat_name 
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                 WHERE rol_mail_this_role = 3
                   AND rol_valid  = 1
                   AND rol_cat_id = cat_id
                   AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
                 ORDER BY cat_sequence, rol_name ';
    }
    $result = $g_db->query($sql);
    $act_category = '';
    
    while ($row = $g_db->fetch_object($result))
    {
        if($act_category != $row->cat_name)
        {
            if(strlen($act_category) > 0)
            {
                echo '</optgroup>';
            }
            echo '<optgroup label="'.$row->cat_name.'">';
            $act_category = $row->cat_name;
        }
        if($g_current_user->mailRole($row->rol_id))
        {
            echo '<option value='.$row->rol_id.' ';
            if ($row->rol_id == isset($form_values['rol_id']))
            {
                echo 'selected="selected"';
            }
            echo '>'.$row->rol_name.'</option>';
        }
    }
    
    echo '</optgroup>
    </select>
    <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'" >*</span>
    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ECA_SEND_ECARD_TO_ROLE&amp;inline=true"><img 
        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ECA_SEND_ECARD_TO_ROLE\',this)" onmouseout="ajax_hideTooltip()"
        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$g_l10n->get("SYS_HELP").'" title="" /></a>';                  
}
// Wenn die Rolle ausgewaehlt worden ist wird dieses Menue gezeichnet
// Es werden alle Mitglieder in dieser Rolle aufgelistet die eine gueltuige 
// E-mail besitzen und stehen bereit zur Auswahl
elseif($g_valid_login && $get_rol_id > 0 && $get_mode == 0 && $get_usr_id == '0')
{
	$sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
		      FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
			  LEFT JOIN '. TBL_USER_DATA. ' as last_name
			    ON last_name.usd_usr_id = usr_id
			   AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id').'
			  LEFT JOIN '. TBL_USER_DATA. ' as first_name
				ON first_name.usd_usr_id = usr_id
			   AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id').'
			  LEFT JOIN '. TBL_USER_DATA. ' as email
				ON email.usd_usr_id = usr_id
			   AND email.usd_usf_id = '. $g_current_user->getProperty('EMAIL', 'usf_id').'
			 WHERE usr_id   = mem_usr_id
			   AND mem_rol_id = '.$get_rol_id.'
			   AND mem_begin <= \''.DATE_NOW.'\'
			   AND mem_end    > \''.DATE_NOW.'\'
			   AND usr_valid  = 1
			   AND email.usd_usr_id = email.usd_usr_id
			 ORDER BY last_name,first_name ASC';
	
	$result       = $g_db->query($sql);
	$menuheader   = '<select size="1" id="menu" name="menu" onchange="javascript:ecardJS.getMenuRecepientNameEmail(this.value)">';
	$menubody     = '</select>';
	if($g_db->num_rows($result)>0)
	{
		$menudata     = '<option value="Rolle_'.$get_rol_id.'" style="font-weight:bold;"><b>'.$g_l10n->get("ECA_TO_ALL_MEMBERS_FROM_A_ROLE").'</b></option>';
	}
	while ($row = $g_db->fetch_object($result))
	{
		$menudata.='<option value="'.$row->usr_id.'">'.$row->last_name.' '.$row->first_name.'</option>';
	}
	if (!empty($menudata))
	{
		echo $menuheader.'<option value="bw" selected="selected" disabled="disabled">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>'.$menudata.$menubody;
	}
	else
	{
		echo '<div style="width:340px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:    1px;  border:       1px solid #ccc;padding:          5px; background-color: #FFFFE0; padding-left:     28px;\">'.$g_l10n->get("ECA_NO_USER_WITH_VALID_EMAIL").'</div>';
	}
}
// Wenn ein User ausgewaehlt worden ist werden zwei input Boxen ausgegeben
// Es wird von dem ausgewaehlten User der Name und die Email jeweils in eine input Box geschrieben und 
// ausgegeben wobei nur die input Box mit den Namen sichtbar ist (schreibgeschuetzt!)
elseif($g_valid_login && strlen($get_usr_id) > 0)
{
    if(is_numeric($get_usr_id) == 1)
    {
        $sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
                  FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
                  LEFT JOIN '. TBL_USER_DATA. ' as last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA. ' as first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA. ' as email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = '. $g_current_user->getProperty('EMAIL', 'usf_id').'
                 WHERE usr_id   = '.$get_usr_id.'
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND usr_valid  = 1
                 ORDER BY last_name, first_name';
        
        $result = $g_db->query($sql);
        while ($row = $g_db->fetch_object($result))
        {
            $full_name = $row->first_name.' '.$row->last_name;
            echo '<input type="hidden" name="ecard[name_recipient]" value="'.$full_name.'" /><input type="hidden" name="ecard[email_recipient]" value="'.$row->email.'" />';
        }
    }
    elseif($get_usr_id != 'bw')
    {
        echo '<input type="hidden" name="ecard[name_recipient]" value="die gesamte Rolle" /><input type="hidden" name="ecard[email_recipient]" value="'.$get_usr_id.'@rolle.com" />';
    }
}
// Wenn der User sich entschliesst diese Grusskarte an einen Empfaenger zu senden der nicht
// in dieser Organisation vorhanden ist wird ihm die Moeglichkeit der manuellen Eingabe des
// Namen und Empfaenger geboten
if($g_valid_login == true && $get_extern == true)
{
    echo '<input id="name_recipient" type="text" name="ecard[name_recipient]"  style="margin-bottom:3px; width: 200px;" 
		onclick="javascript:ecardJS.blendout(this.id);" onfocus="javascript:ecardJS.blendout(this.id);" onmouseout="javascript:ecardJS.blendin(this.id,1);" 
		onkeydown="javascript:ecardJS.blendout(this.id);" onkeyup="javascript:ecardJS.blendout(this.id);" 
		onkeypress="javascript:ecardJS.blendout(this.id);" maxlength="50" value="< '.$g_l10n->get("ECA_RECIPIENT_NAME").' >" />
		<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>

		<input id="email_recipient" type="text" name="ecard[email_recipient]" style="width: 330px;" onclick="javascript:ecardJS.blendout(this.id);" 
		onfocus="javascript:ecardJS.blendout(this.id);" onmouseout="javascript:ecardJS.blendin(this.id,2);" 
		onkeydown="javascript:ecardJS.blendout(this.id);" onkeyup="javascript:ecardJS.blendout(this.id);" 
		onkeypress="javascript:ecardJS.blendout(this.id);" maxlength="50" value="< '.$g_l10n->get("ECA_RECIPIENT_EMAIL").' >" />
		<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>';
}

?>
