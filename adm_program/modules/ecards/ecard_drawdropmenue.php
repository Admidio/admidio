<?php
/******************************************************************************
 * Grusskarte Draw Dropdown Menue
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * base:		Ist diese Variable gesetzt bekommt man das Menue mit allen Rollen 
 * rol_id:		Durch die ID der Rolle bekomme ich alle Mitglieder dieser
 * usr_id:		Durch die User ID bekommt man den vollstaendigen Namen + E-mail
 *
 *****************************************************************************/
 
require_once("../../system/common.php");
// Wenn das erste Menue mit den aufgelisteten Rollen gezeichnet werden soll (Uebergabe base == 1)
// Es werden alle Rollen die in dieser Organisation vorhanden sind aufgelistet und stehen nun bereit 
// zur Auswahl
if ($g_valid_login && isset($_GET['base']) =="1")
{
	echo '<select size="1" id="rol_id" name="rol_id" onchange="javascript:getMenuRecepientName()">';
	if (isset($form_values['rol_id']) == "")
	{
		echo '<option value="" selected="selected" disabled="disabled">- Bitte w&auml;hlen -</option>';
	}
	
	if ($g_valid_login)
	{
		if ($g_current_user->assignRoles())
		{
			// im eingeloggten Zustand duerfen nur Moderatoren an gelocked Rollen schreiben
		   $sql    = "SELECT rol_name, rol_id, cat_name 
				   FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
				   WHERE rol_mail_login = 1
				   AND rol_valid        = 1
				   AND rol_cat_id       = cat_id
				   AND cat_org_id       = ". $g_current_organization->getValue("org_id"). "
				   ORDER BY cat_sequence, rol_name ";
		}
		else
		{
			// alle nicht gelocked Rollen auflisten,
			// an die im eingeloggten Zustand Mails versendet werden duerfen
		   $sql    = "SELECT rol_name, rol_id, cat_name 
				   FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
				   WHERE rol_mail_login = 1
				   AND rol_locked       = 0
				   AND rol_valid        = 1
				   AND rol_cat_id       = cat_id
				   AND cat_org_id       = ". $g_current_organization->getValue("org_id"). "
				   ORDER BY cat_sequence, rol_name ";
		}
	}
	else
	{
		// alle Rollen auflisten,
		// an die im nicht eingeloggten Zustand Mails versendet werden duerfen
		$sql    = "SELECT rol_name, rol_id, cat_name 
				   FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
				   WHERE rol_mail_logout = 1
				   AND rol_valid         = 1
				   AND rol_cat_id        = cat_id
				   AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
				   ORDER BY cat_sequence, rol_name ";
	}
	$result = $g_db->query($sql);
	$act_category = "";
	
	while ($row = $g_db->fetch_object($result))
	{
		if($act_category != $row->cat_name)
		{
			if(strlen($act_category) > 0)
			{
				echo "</optgroup>";
			}
			echo '<optgroup label="'.$row->cat_name.'">';
			$act_category = $row->cat_name;
		}
		echo '<option value='.$row->rol_id.' ';
		if ($row->rol_id == isset($form_values['rol_id']))
		{
			echo 'selected="selected"';
		}
		echo '>'.$row->rol_name.'</option>';
	}
	
	echo '</optgroup>
	</select>
	<img class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="Hilfe" onclick="window.open(\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=rolle_ecard&amp;window=true\',\'Message\',\'width=400,height=250,left=300,top=200,scrollbars=yes\')"
	onmouseover="ajax_showTooltip(\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=rolle_ecard\',this)" onmouseout="ajax_hideTooltip()" />
	<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
	
	';					
}
// Wenn die Rolle ausgewaehlt worden ist wird dieses Menue gezeichnet
// Es werden alle Mitglieder in dieser Rolle aufgelistet die eine gueltuige 
// E-mail besitzen und stehen bereit zur Auswahl
else if ($g_valid_login && isset($_GET['rol_id']) && !isset($_GET['base']) && !isset($_GET['usrid']))
{
    if(is_numeric($_GET['rol_id']))
	{
		$sql = "SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
				FROM ". TBL_MEMBERS. ", ". TBL_USERS. "
				LEFT JOIN ". TBL_USER_DATA. " as last_name
					ON last_name.usd_usr_id = usr_id
					AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id")."
				LEFT JOIN ". TBL_USER_DATA. " as first_name
					ON first_name.usd_usr_id = usr_id
					AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id")."
				LEFT JOIN ". TBL_USER_DATA. " as email
					ON email.usd_usr_id = usr_id
					AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id")."
				WHERE usr_id = mem_usr_id
				AND mem_rol_id = ".$_GET['rol_id']."
				AND mem_valid = 1
				AND usr_valid = 1
				AND email.usd_usr_id = email.usd_usr_id
				ORDER BY last_name,first_name ASC";
		
		$result 	  = $g_db->query($sql);
		$menuheader   = '<select size="1" id="menu" name="menu" onchange="javascript:getMenuRecepientNameEmail(this.value)">';
		$menubody     = '</select>';
		if(mysql_num_rows($result)>0)
		{
			$menudata     = '<option value="Rolle_'.$_GET['rol_id'].'" style="font-weight:bold;"><b>An die gesamte Rolle</b></option>';
		}
		while ($row = $g_db->fetch_object($result))
		{
			$menudata.='<option value="'.$row->usr_id.'">'.$row->last_name.' '.$row->first_name.'</option>';
		}
		if (!empty($menudata))
		{
			echo $menuheader.'<option value="" selected="selected">- Bitte w&auml;hlen -</option>'.$menudata.$menubody;
		}
		else
		{
		    echo '<div style="width:300px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:    1px;	border:	      1px solid #ccc;padding:          5px; background-color: #FFFFE0; padding-left:     28px;\">Kein User vorhanden der eine g&uuml;ltige E-mail besitzt!</div>';
		}
	}
	else
	{
	    echo '<div style="width:300px; background-image: url(\''.THEME_PATH.'/icons/error.png\');background-repeat: no-repeat; background-position:5px 5px;margin-top:1px;	border:1px solid #ccc;padding:5px;background-color: #FFFFE0; padding-left:28px;\">Bitte w&auml;hlen Sie eine g&uuml;ltige Rolle aus!</div>';
	}
}
// Wenn ein User ausgewaehlt worden ist werden zwei input Boxen ausgegeben
// Es wird von dem ausgewaehlten User der Name und die Email jeweils in eine input Box geschrieben und 
// ausgegeben wobei nur die input Box mit den Namen sichtbar ist (schreibgeschuetzt!)
else if($g_valid_login && isset($_GET['usrid']) && $_GET['usrid']!="extern")
{
	if(is_numeric($_GET['usrid']) == 1)
	{
		$sql = "SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, email.usd_value as email
					FROM ". TBL_MEMBERS. ", ". TBL_USERS. "
					LEFT JOIN ". TBL_USER_DATA. " as last_name
						ON last_name.usd_usr_id = usr_id
						AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id")."
					LEFT JOIN ". TBL_USER_DATA. " as first_name
						ON first_name.usd_usr_id = usr_id
						AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id")."
					LEFT JOIN ". TBL_USER_DATA. " as email
						ON email.usd_usr_id = usr_id
						AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id")."
					WHERE usr_id = ".$_GET['usrid']."
					AND mem_valid = 1
					AND usr_valid = 1
					ORDER BY last_name, first_name";
		
		$result = $g_db->query($sql);
		while ($row = $g_db->fetch_object($result))
		{
			$full_name	= ''.$row->first_name.' '.$row->last_name.'';
			echo '<table summary="DataSender" style="border:0px;" border="0" cellpadding="0" cellspacing="0" cols="0" rules="none" width="100%">
			<tr>
			<td align="left"><input type="text" name="ecard[name_recipient]" size="25" class="readonly" readonly="readonly"  maxlength="40" style="width: 200px;" value="'.$full_name.'" /></td>
			<td align="right"><a href="javascript:getMenuRecepientName();">anderer Empf&auml;nger</a></td>
			</tr>
			</table>
			<input type="hidden" name="ecard[email_recipient]" value="'.$row->email.'" />
			';
		}
	}
	else
	{
		echo '<table summary="DataSender" style="border:0px;" border="0" cellpadding="0" cellspacing="0" cols="0" rules="none" width="100%">
			<tr>
			<td align="left"><input type="text" name="ecard[name_recipient]" size="25" class="readonly" readonly="readonly"  maxlength="40" style="width: 200px;" value="die gesamte Rolle" /></td>
			<td align="right"><a href="javascript:getMenuRecepientName();">anderer Empf&auml;nger</a></td>
			</tr>
			</table>
			<input type="hidden" name="ecard[email_recipient]" value="'.$_GET['usrid'].'@rolle.com" />
			';
	}
}
// Wenn der User sich entschliesst diese Grusskarte an einen Empfaenger zu senden der nicht
// in dieser Organisation vorhanden ist wird ihm die Moeglichkeit der manuellen Eingabe des
// Namen und Empfaenger geboten
else if($g_valid_login && isset($_GET['usrid']) == "extern")
{
	echo '<input id="name_recipient" type="text" name="ecard[name_recipient]"  style="margin-bottom:3px; width: 200px;" onclick="javascript:blendout(this.id);" onfocus="javascript:blendout(this.id);" onmouseout="javascript:blendin(this.id,1);" maxlength="50" value="< Empf&auml;nger Name >"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
	echo '<input id="email_recipient" type="text" name="ecard[email_recipient]" style="width: 330px;" onclick="javascript:blendout(this.id);" onfocus="javascript:blendout(this.id);" onmouseout="javascript:blendin(this.id,2);" maxlength="50" value="< Empf&auml;nger E-Mail >"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
}

?>
