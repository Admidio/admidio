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
		global $g_current_user, $g_current_organization, $g_db, $g_l10n;
		
		if(strlen($field_id) == 0)
		{
			$field_id = 'rol_id';
		}

		// SQL-Statement entsprechend dem Modus zusammensetzen
		$condition = '';
		$active_roles = 1;
		if($show_mode == 1 && $g_current_user->assignRoles() == false)
		{
			// keine Rollen mit Rollenzuordnungsrecht anzeigen
			$condition .= ' AND rol_assign_roles = 0 ';
		}
		elseif($show_mode == 1 && $g_current_user->isWebmaster() == false)
		{
			// Webmasterrolle nicht anzeigen
			$condition .= ' AND rol_name <> "'.$g_l10n->get('SYS_WEBMASTER').'" ';
		}
		elseif($show_mode == 2)
		{
			$active_roles = 0;
		}
		
		$sql = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
				 WHERE rol_valid   = '.$active_roles.'
				   AND rol_visible = 1
				   AND rol_cat_id  = cat_id
				   AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
					   '.$condition.'
				 ORDER BY cat_sequence, rol_name';
		$result_lst = $g_db->query($sql);

		// Selectbox mit allen selektierten Rollen zusammensetzen
		$act_category = '';
		$selectBoxHtml = '
		<select size="1" id="'.$field_id.'" name="'.$field_id.'">
			<option value="0" ';
			if($default_role == 0)
			{
				$selectBoxHtml .= ' selected="selected" ';
			}
			$selectBoxHtml .= '>- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			if($visitors == 1)
			{
				$selectBoxHtml .= '<option value="-1" ';
				if($default_role == -1)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>'.$g_l10n->get('SYS_ALL').' ('.$g_l10n->get('SYS_ALSO_VISITORS').')</option>';
			}

			while($row = $g_db->fetch_object($result_lst))
			{
				if($g_current_user->viewRole($row->rol_id))
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
		global $g_current_organization, $g_db, $g_l10n;

		if(strlen($field_id) == 0)
		{
			$field_id = 'cat_id';
		}
		
		$sql = 'SELECT cat_id, cat_default, cat_name 
		          FROM '. TBL_CATEGORIES. '
				 WHERE (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
				   AND cat_type   = "'.$category_type.'"
				 ORDER BY cat_sequence ASC ';
		$result = $g_db->query($sql);

		$selectBoxHtml = '
		<select size="1" id="'.$field_id.'" name="'.$field_id.'">
			<option value=" "';
				if($default_category == 0)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			while($row = $g_db->fetch_array($result))
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
}
?>