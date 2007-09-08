<?php
/******************************************************************************
 * E@card Draw Dropdown Manue
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * base:		Ist diese Variable gesetzt bekommt man das Menü mit allen Rollen 
 * rol_id:		Durch die ID der Rolle bekomme ich alle Mitglieder dieser
 * usr_id:		Durch die User ID bekommt man den vollständigen Namen + E-mail
 *
 *****************************************************************************/
 
require_once("../../system/common.php");
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
	<img class="iconHelpLink" src="'.$g_root_path.'/adm_program/images/help.png" alt="Hilfe" title="Hilfe"
	onclick="window.open(\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=rolle_ecard\',\'Message\',\'width=400,height=400,left=310,top=200\')" />
	<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
	
	
	';								
}
else if ($g_valid_login && isset($_GET['rol_id']) && !isset($_GET['base']))
{
    if(is_numeric($_GET['rol_id']))
	{
		$sql = "SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name 
				FROM ". TBL_MEMBERS. ", ". TBL_USERS. "
				LEFT JOIN ". TBL_USER_DATA. " as last_name
					ON last_name.usd_usr_id = usr_id
					AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id")."
				LEFT JOIN ". TBL_USER_DATA. " as first_name
					ON first_name.usd_usr_id = usr_id
					AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id")."
				WHERE usr_id = mem_usr_id
				AND mem_rol_id = ".$_GET['rol_id']."
				AND mem_valid = 1
				AND usr_valid = 1
				ORDER BY last_name, first_name";
		
		$result 	  = $g_db->query($sql);
		$act_category = "";
		$menuheader   = '<select size="1" id="menu" name="menu" onchange="javascript:getMenuRecepientNameEmail(this.value)">';
		$menubody     = '</select>';
		$menudata     = "";
		while ($row = $g_db->fetch_object($result))
		{
			$menudata.='<option value="'.$row->usr_id.'">'.$row->first_name.' '.$row->last_name.'</option>';
		}
		
		if (!empty($menudata))
		{
			echo $menuheader.'<option value="" selected="selected">- Bitte w&auml;hlen -</option>'.$menudata.$menubody;
		}
		else
		{
		    echo " Kein User vorhanden der eine g&uuml;ltige E-mail besitzt! <br /> Bitte w&auml;hlen Sie eine andere Rolle aus! ";
		}
	}
	else
	{
	    echo " Bitte w&auml;hlen Sie eine andere Rolle aus diese ist ung&uuml;ltig! ";
	}
	echo '<input type="hidden" name="ecard[email_recepient]" value="" />
		  <input type="hidden" name="ecard[name_recepient]"  value="" />';
}
else if($g_valid_login && isset($_GET['usrid']) && $_GET['usrid']!="extern")
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
		echo '<input type="hidden" name="ecard[email_recepient]" value="'.$row->email.'" />
		<input type="text" name="ecard[name_recepient]" size="25" class="readonly" readonly="readonly"  maxlength="40" style="width: 200px;" value="'.$row->first_name.' '.$row->last_name.'" />
		';
	}
}
else if($g_valid_login && isset($_GET['usrid']) == "extern")
{
		echo '<input id="name_recepient" type="text" name="ecard[name_recepient]"  style="margin-bottom:3px; width: 200px;" onclick="javascript:blendout(this.id);" onfocus="javascript:blendout(this.id);" maxlength="50" value="<Empf&auml;nger Name>"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
        echo '<input id="email_recepient" type="text" name="ecard[email_recepient]" style="width: 350px;" onclick="javascript:blendout(this.id);" onfocus="javascript:blendout(this.id);" maxlength="50" value="<Empf&auml;nger E-mail>"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
}
else
{
	echo '<input type="hidden" name="ecard[email_recepient]" value="" />
		  <input type="hidden" name="ecard[name_recepient]"  value="" />';
}

?>