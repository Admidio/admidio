<?php
/******************************************************************************
 * Factory-Klasse welches das relevante Forumobjekt erstellt
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class FormElements
{
	// Diese Funktion erzeugt eine Combobox mit allen Rollen, die der Benutzer sehen darf
	// Die Rollen werden dabei nach Kategorie gruppiert
	//
	// Uebergaben:
	// default_role : Id der Rolle die markiert wird
	// field_id     : Id und Name der Select-Box
	// show_mode    : Modus der bestimmt, welche Rollen angezeigt werden
	//          = 0 : Alle Rollen, die der Benutzer sehen darf
	//          = 1 : Alle sicheren Rollen, so dass der Benutzer sich kein "Rollenzuordnungsrecht" 
	//                dazuholen kann, wenn er es nicht schon besitzt
	//          = 2 : Alle nicht aktiven Rollen auflisten
	// visitors = 1 : weiterer Eintrag um auch Besucher auswaehlen zu koennen
	 public static function generateRoleSelectBox($default_role = 0, $field_id = '', $show_mode = 0, $visitors = 0)
	{
		global $gCurrentUser, $gCurrentOrganization, $gDb, $gL10n;
		
		if(strlen($field_id) == 0)
		{
			$field_id = 'rol_id';
		}

		// SQL-Statement entsprechend dem Modus zusammensetzen
		$condition = '';
		$active_roles = 1;
		if($show_mode == 1 && $gCurrentUser->assignRoles() == false)
		{
			// keine Rollen mit Rollenzuordnungsrecht anzeigen
			$condition .= ' AND rol_assign_roles = 0 ';
		}
		elseif($show_mode == 1 && $gCurrentUser->isWebmaster() == false)
		{
			// Webmasterrolle nicht anzeigen
			$condition .= ' AND rol_name <> \''.$gL10n->get('SYS_WEBMASTER').'\' ';
		}
		elseif($show_mode == 2)
		{
			$active_roles = 0;
		}
		
		$sql = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
				 WHERE rol_valid   = \''.$active_roles.'\'
				   AND rol_visible = 1
				   AND rol_cat_id  = cat_id
				   AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
					   '.$condition.'
				 ORDER BY cat_sequence, rol_name';
		$result_lst = $gDb->query($sql);

		// Selectbox mit allen selektierten Rollen zusammensetzen
		$act_category = '';
		$selectBoxHtml = '
		<select size="1" id="'.$field_id.'" name="'.$field_id.'">
			<option value="0" ';
			if($default_role == 0)
			{
				$selectBoxHtml .= ' selected="selected" ';
			}
			$selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			if($visitors == 1)
			{
				$selectBoxHtml .= '<option value="-1" ';
				if($default_role == -1)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>'.$gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')</option>';
			}

			while($row = $gDb->fetch_object($result_lst))
			{
				if($gCurrentUser->viewRole($row->rol_id))
				{
					if($act_category != $row->cat_name)
					{
						if(strlen($act_category) > 0)
						{
							$selectBoxHtml .= '</optgroup>';
						}
						$selectBoxHtml .= '<optgroup label="'.$row->cat_name.'">';
						$act_category = $row->cat_name;
					}
					// wurde eine Rollen-Id uebergeben, dann Combobox mit dieser vorbelegen
					$selected = "";
					if($row->rol_id == $default_role)
					{
						$selected = ' selected="selected" ';
					}
					$selectBoxHtml .= '<option '.$selected.' value="'.$row->rol_id.'">'.$row->rol_name.'</option>';
				}
			}
			$selectBoxHtml .= '</optgroup>
		</select>';
		return $selectBoxHtml;
	}

	// Diese Funktion erzeugt eine Combobox mit allen Kategorien zu einem Typ (Rollen, Termine, Links ...)
	//
	// Uebergaben:
	// category_type  : Typ der Kategorien ('ROL', 'DAT', 'LNK',...) die angezeigt werden sollen
	// default_category : Id der Kategorie die markiert wird
	// field_id       : Id und Name der Select-Box
	public static function generateCategorySelectBox($category_type, $default_category = 0, $field_id = '')
	{
		global $gCurrentOrganization, $gDb, $gL10n;

		if(strlen($field_id) == 0)
		{
			$field_id = 'cat_id';
		}
		
		$sql = 'SELECT cat_id, cat_default, cat_name 
		          FROM '. TBL_CATEGORIES. '
				 WHERE (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
				   AND cat_type   = \''.$category_type.'\'
				 ORDER BY cat_sequence ASC ';
		$result = $gDb->query($sql);

		$selectBoxHtml = '
		<select size="1" id="'.$field_id.'" name="'.$field_id.'">
			<option value=" "';
				if($default_category == 0)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			while($row = $gDb->fetch_array($result))
			{
				$selectBoxHtml .= '<option value="'.$row['cat_id'].'"';
					if($default_category == $row['cat_id']
					|| ($default_category == 0 && $row['cat_default'] == 1))
					{
						$selectBoxHtml .= ' selected="selected" ';
					}
				$selectBoxHtml .= '>'.$row['cat_name'].'</option>';
			}
		$selectBoxHtml .= '</select>';
		return $selectBoxHtml;
	}
	
	// Diese Funktion erzeugt eine Combobox mit allen Eintraegen aus einer XML-Datei
	//
	// Uebergaben:
	// xmlFile      : Serverpfad zur XML-Datei
	// xmlValueTag  : Name des XML-Tags der den jeweiligen Wert des Comboboxeintrags beinhaltet
	// xmlViewTag   : Name des XML-Tags der den jeweiligen angezeigten Wert des Comboboxeintrags beinhaltet
	// htmlFieldId  : (optional) Html-Id der select-Box
	// defaultValue : (optional) Eintrag des xmlValueTag der vorausgewaehlt sein soll
	 public static function generateXMLSelectBox($xmlFile, $xmlValueTag, $xmlViewTag, $htmlFieldId = '', $defaultValue = '')
	{
		global $gL10n;

		// Inhalt der XML-Datei in Arrays schreiben
		$data = implode('', file($xmlFile));
		$p = xml_parser_create();
		xml_parse_into_struct($p, $data, $vals, $index);
		xml_parser_free($p);
		
		// SelectBox ausgeben
		$selectBoxHtml = '<select size="1" id="'.$htmlFieldId.'" name="'.$htmlFieldId.'">
			<option value="">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			for($i = 0; $i < count($index[$xmlValueTag]); $i++)
			{
				$selected = '';
				if($vals[$index[$xmlValueTag][$i]]['value'] == $defaultValue)
				{
					$selected = ' selected="selected" ';
				}
				$selectBoxHtml .= '<option '.$selected.' value="'.$vals[$index[$xmlValueTag][$i]]['value'].'">'.$vals[$index[$xmlViewTag][$i]]['value'].'</option>';
			}
		$selectBoxHtml .= '</select>';
		return $selectBoxHtml;
	}
}
?>