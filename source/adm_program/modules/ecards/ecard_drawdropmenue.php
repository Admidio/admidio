<?php
/******************************************************************************
 * Grusskarte Draw Dropdown Menue
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   = 0 : (Default) Liste mit Benutzern anzeigen
 *        = 1 : Liste mit allen Rollen 
 * rol_id     : Durch die ID der Rolle bekomme ich alle Mitglieder dieser
 * usr_id     : Durch die User ID bekommt man den vollstaendigen Namen + E-mail
 * extern = 1 : Die Grusskarte geht an einen externen Empfaenger
 *
 *****************************************************************************/
 
require_once('../../system/common.php');

// Initialize and check the parameters
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', 0);
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'string', 0);
$getExtern = admFuncVariableIsValid($_GET, 'extern', 'boolean', 0);

// Wenn das erste Menue mit den aufgelisteten Rollen gezeichnet werden soll (Uebergabe mode == 1)
// Es werden alle Rollen die in dieser Organisation vorhanden sind aufgelistet und stehen nun bereit zur Auswahl
if($gValidLogin && $getMode == 1)
{
    echo '<select size="1" id="rol_id" name="rol_id" onchange="javascript:ecardJS.getMenuRecepientName()">';
    if (isset($form_values['rol_id']) == '')
    {
        echo '<option value="" selected="selected" disabled="disabled">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>
        <optgroup label="'.$gL10n->get('ECA_OTHER_RECIPIENT').'">
        <option value="externMail" >'.$gL10n->get("ECA_EXTERNAL_RECIPIENT").'</option>';
    }
    
    if ($gValidLogin)
    {
        // im eingeloggten Zustand nur an Rollen schreiben, die die Einstellung besitzen
       $sql = 'SELECT rol_name, rol_id, cat_name 
                 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_mail_this_role > 0
                  AND rol_valid  = 1
                  AND rol_cat_id = cat_id
                  AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
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
                   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                 ORDER BY cat_sequence, rol_name ';
    }
    $result = $gDb->query($sql);
    $act_category = '';
    
    while ($row = $gDb->fetch_array($result))
    {
		// if text is a translation-id then translate it
		if(strpos($row['cat_name'], '_') == 3)
		{
			$row['cat_name'] = $gL10n->get(admStrToUpper($row['cat_name']));
		}

        if($act_category != $row['cat_name'])
        {
            if(strlen($act_category) > 0)
            {
                echo '</optgroup>';
            }
            echo '<optgroup label="'.$row['cat_name'].'">';
            $act_category = $row['cat_name'];
        }
        if($gCurrentUser->mailRole($row['rol_id']))
        {
            echo '<option value='.$row['rol_id'].' ';
            if ($row['rol_id'] == isset($form_values['rol_id']))
            {
                echo 'selected="selected"';
            }
            echo '>'.$row['rol_name'].'</option>';
        }
    }
    
    echo '</optgroup>
    </select>
    <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'" >*</span>
    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ECA_SEND_ECARD_TO_ROLE&amp;inline=true"><img 
        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ECA_SEND_ECARD_TO_ROLE\',this)" onmouseout="ajax_hideTooltip()"
        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get("SYS_HELP").'" title="" /></a>';                  
}
// Wenn die Rolle ausgewaehlt worden ist wird dieses Menue gezeichnet
// Es werden alle Mitglieder in dieser Rolle aufgelistet die eine gueltuige 
// E-mail besitzen und stehen bereit zur Auswahl
elseif($gValidLogin && $getRoleId > 0 && $getMode == 0 && $getUserId == '0')
{
	$sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
		      FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
			  LEFT JOIN '. TBL_USER_DATA. ' as last_name
			    ON last_name.usd_usr_id = usr_id
			   AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
			  LEFT JOIN '. TBL_USER_DATA. ' as first_name
				ON first_name.usd_usr_id = usr_id
			   AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
			  LEFT JOIN '. TBL_USER_DATA. ' as email
				ON email.usd_usr_id = usr_id
			   AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id').'
			 WHERE usr_id   = mem_usr_id
			   AND mem_rol_id = '.$getRoleId.'
			   AND mem_begin <= \''.DATE_NOW.'\'
			   AND mem_end    > \''.DATE_NOW.'\'
			   AND usr_valid  = 1
			   AND email.usd_usr_id = email.usd_usr_id
			 ORDER BY last_name,first_name ASC';
	
	$result       = $gDb->query($sql);
	$menuheader   = '<select size="1" id="menu" name="menu" onchange="javascript:ecardJS.getMenuRecepientNameEmail(this.value)">';
	$menubody     = '</select>';
	if($gDb->num_rows($result)>0)
	{
		$menudata     = '<option value="Rolle_'.$getRoleId.'" style="font-weight:bold;"><b>'.$gL10n->get('ECA_TO_ALL_MEMBERS_FROM_A_ROLE').'</b></option>';
	}
	while ($row = $gDb->fetch_object($result))
	{
		$menudata.='<option value="'.$row->usr_id.'">'.$row->last_name.' '.$row->first_name.'</option>';
	}
	if (!empty($menudata))
	{
		echo $menuheader.'<option value="bw" selected="selected" disabled="disabled">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>'.$menudata.$menubody;
	}
	else
	{
		echo '<div style="width:340px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:    1px;  border:       1px solid #ccc;padding:          5px; background-color: #FFFFE0; padding-left:     28px;\">'.$gL10n->get("ECA_NO_USER_WITH_VALID_EMAIL").'</div>';
	}
}
// Wenn ein User ausgewaehlt worden ist werden zwei input Boxen ausgegeben
// Es wird von dem ausgewaehlten User der Name und die Email jeweils in eine input Box geschrieben und 
// ausgegeben wobei nur die input Box mit den Namen sichtbar ist (schreibgeschuetzt!)
elseif($gValidLogin && strlen($getUserId) > 0)
{
    if(is_numeric($getUserId) == 1)
    {
        $sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
                  FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
                  LEFT JOIN '. TBL_USER_DATA. ' as last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA. ' as first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA. ' as email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id').'
                 WHERE usr_id   = '.$getUserId.'
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND usr_valid  = 1
                 ORDER BY last_name, first_name';
        
        $result = $gDb->query($sql);
        while ($row = $gDb->fetch_object($result))
        {
            $full_name = $row->first_name.' '.$row->last_name;
            echo '<input type="hidden" name="ecard[name_recipient]" value="'.$full_name.'" /><input type="hidden" name="ecard[email_recipient]" value="'.$row->email.'" />';
        }
    }
    elseif($getUserId != 'bw')
    {
		// Rollen Domain von der angegebenen Homepage parsen oder falls nicht vorhanden standard nehmen
		$orgHP = trim($gCurrentOrganization->getValue('org_homepage'));
		$rolDomain = 'organisation.com';
		if( $orgHP != '' )
		{
			$parseUrl = parse_url($orgHP); 
			$newUrl = trim($parseUrl['host'] ? $parseUrl['host'] : array_shift(explode('/', $parseUrl['path'], 2)));
			$newUrlArray = split("[\.]",$newUrl);
			$count = count($newUrlArray);
			
			if( $count >= 2 )
			{
				$rolDomain = $newUrlArray[$count-2].'.'.$newUrlArray[$count-1];
            }
		}
		
		list ($rolName, $rolId) = split("[\_]", $getUserId);
		if( $rolId != '' )
		{
			$sql = 'SELECT rol_name 
					 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
					WHERE rol_mail_this_role > 0
					  AND rol_valid  = 1
					  AND rol_cat_id = cat_id
					  AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					  AND rol_id = '.$rolId.'
					ORDER BY cat_sequence, rol_name ';
			
			$result = $gDb->query($sql);
			$row = $gDb->fetch_object($result);
			// replace all special chars that make problems in option value field from role name
			$getUserId = strtr($row->rol_name, ' =', '--');
		}
        echo '<input type="hidden" name="ecard[name_recipient]" value="die gesamte Rolle" />
			  <input type="hidden" name="ecard[email_recipient]" value="'.$getUserId.'@'.$rolDomain.'" />
			  <input type="hidden" name="ecard[email_rolId]" value="'.$rolId.'" />';
    }
}
// Wenn der User sich entschliesst diese Grusskarte an einen Empfaenger zu senden der nicht
// in dieser Organisation vorhanden ist wird ihm die Moeglichkeit der manuellen Eingabe des
// Namen und Empfaenger geboten
if($gValidLogin == true && $getExtern == true)
{
    echo '<input id="name_recipient" type="text" name="ecard[name_recipient]"  style="margin-bottom:3px; width: 200px;" 
		onclick="javascript:ecardJS.blendout(this.id);" onfocus="javascript:ecardJS.blendout(this.id);" onmouseout="javascript:ecardJS.blendin(this.id,1);" 
		onkeydown="javascript:ecardJS.blendout(this.id);" onkeyup="javascript:ecardJS.blendout(this.id);" 
		onkeypress="javascript:ecardJS.blendout(this.id);" maxlength="50" value="< '.$gL10n->get("ECA_RECIPIENT_NAME").' >" />
		<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>

		<input id="email_recipient" type="text" name="ecard[email_recipient]" style="width: 330px;" onclick="javascript:ecardJS.blendout(this.id);" 
		onfocus="javascript:ecardJS.blendout(this.id);" onmouseout="javascript:ecardJS.blendin(this.id,2);" 
		onkeydown="javascript:ecardJS.blendout(this.id);" onkeyup="javascript:ecardJS.blendout(this.id);" 
		onkeypress="javascript:ecardJS.blendout(this.id);" maxlength="50" value="< '.$gL10n->get("ECA_RECIPIENT_EMAIL").' >" />
		<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
}

?>
