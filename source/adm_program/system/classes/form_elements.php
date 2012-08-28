<?php
/******************************************************************************
 * Factory class that creates elements for html forms
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class FormElements
{
	// creates a html select box with all entries that are stored in the parameter array
	// entryArray   : Array with all entries of the select box; 
	//                Array key will be the internal value of the entry
	//                Array value will be the visual value of the entry
	// defaultEntry : internal value of the entry that should be default selected
	// fieldId      : Id and name of the select box
	// createFirstEntry : First entry of select box will be "Please choose"
	public static function generateDynamicSelectBox($entryArray, $defaultEntry = '', $fieldId = 'admSelectBox', $createFirstEntry = false)
	{
		global $gL10n;

		$selectBoxHtml = '<select size="1" id="'.$fieldId.'" name="'.$fieldId.'">';
			if($createFirstEntry == true)
			{
				$selectBoxHtml .= '<option value=" "';
				if(strlen($defaultEntry) == 0)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
			}

			$value = reset($entryArray);
			for($arrayCount = 0; $arrayCount < count($entryArray); $arrayCount++)
			{
				// create entry in html
				$selectBoxHtml .= '<option value="'.key($entryArray).'"';
					if(key($entryArray) == $defaultEntry)
					{
						$selectBoxHtml .= ' selected="selected" ';
					}
				$selectBoxHtml .= '>'.$value.'</option>';
				$value = next($entryArray);
			}
		$selectBoxHtml .= '</select>';
		return $selectBoxHtml;
	}

	// Diese Funktion erzeugt eine Combobox mit allen Rollen, die der Benutzer sehen darf
	// Die Rollen werden dabei nach Kategorie gruppiert
	//
	// Parameters:
	// defaultRole : Id der Rolle die markiert wird
	// fieldId     : Id und Name der Select-Box
	// showMode    : Modus der bestimmt, welche Rollen angezeigt werden
	//          = 0 : Alle Rollen, die der Benutzer sehen darf
	//          = 1 : Alle sicheren Rollen, so dass der Benutzer sich kein "Rollenzuordnungsrecht" 
	//                dazuholen kann, wenn er es nicht schon besitzt
	//          = 2 : Alle nicht aktiven Rollen auflisten
	// visitors = 1 : weiterer Eintrag um auch Besucher auswaehlen zu koennen
	 public static function generateRoleSelectBox($defaultRole = 0, $fieldId = '', $showMode = 0, $visitors = 0)
	{
		global $gCurrentUser, $gCurrentOrganization, $gDb, $gL10n;
		
		if(strlen($fieldId) == 0)
		{
			$fieldId = 'rol_id';
		}

		// SQL-Statement entsprechend dem Modus zusammensetzen
		$condition = '';
		$active_roles = 1;
		if($showMode == 1 && $gCurrentUser->assignRoles() == false)
		{
			// keine Rollen mit Rollenzuordnungsrecht anzeigen
			$condition .= ' AND rol_assign_roles = 0 ';
		}
		elseif($showMode == 1 && $gCurrentUser->isWebmaster() == false)
		{
			// Webmasterrolle nicht anzeigen
			$condition .= ' AND rol_webmaster = 0 ';
		}
		elseif($showMode == 2)
		{
			$active_roles = 0;
		}
		
		$sql = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
				 WHERE rol_valid   = '.$active_roles.'
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
		<select size="1" id="'.$fieldId.'" name="'.$fieldId.'">
			<option value="0" ';
			if($defaultRole == 0)
			{
				$selectBoxHtml .= ' selected="selected" ';
			}
			$selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';

			if($visitors == 1)
			{
				$selectBoxHtml .= '<option value="-1" ';
				if($defaultRole == -1)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>'.$gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')</option>';
			}

			while($row = $gDb->fetch_array($result_lst))
			{
				if($gCurrentUser->viewRole($row['rol_id']))
				{
					// if text is a translation-id then translate it
					if(strpos($row['cat_name'], '_') == 3)
					{
						$row['cat_name'] = $gL10n->get(admStrToUpper($row['cat_name']));
					}

					// if new category then show label with category name
					if($act_category != $row['cat_name'])
					{
						if(strlen($act_category) > 0)
						{
							$selectBoxHtml .= '</optgroup>';
						}
						$selectBoxHtml .= '<optgroup label="'.$row['cat_name'].'">';
						$act_category = $row['cat_name'];
					}
					// wurde eine Rollen-Id uebergeben, dann Combobox mit dieser vorbelegen
					$selected = "";
					if($row['rol_id'] == $defaultRole)
					{
						$selected = ' selected="selected" ';
					}
					$selectBoxHtml .= '<option '.$selected.' value="'.$row['rol_id'].'">'.$row['rol_name'].'</option>';
				}
			}
			$selectBoxHtml .= '</optgroup>
		</select>';
		return $selectBoxHtml;
	}

    /** Method creates a html select box with all visible categories of a type (roles, dates, links ...)
	 *
	 *  @param $categoryType		Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
	 *  @param $defaultCategory		Id of selected category (if id = -1 then no default category will be selected)
	 *  @param $field_id			Id and name of select box
	 *  @param $firstEntry			value for the first entry of the select box
	 *  @param $showCategoryChoice	this mode shows only categories with elements and if default category will be selected there must be at least more then one category to select
	 *  @param $showSystemCategory	Show user defined and system categories
	 *  @return Html code string with a select box element
	 */
	public static function generateCategorySelectBox($categoryType, $defaultCategory = 0, $fieldId = '', 
	                           $firstEntry = '', $showCategoryChoice = false, $showSystemCategory = true)
	{
		global $gCurrentOrganization, $gDb, $gL10n, $gValidLogin;

        $sqlTables      = TBL_CATEGORIES;
        $sqlCondidtions = '';
        $selectBoxHtml  = '';

		if(strlen($fieldId) == 0)
		{
			$fieldId = 'cat_id';
		}
		if(strlen($firstEntry) == 0)
		{
			$firstEntry = '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -';
		}
		
		// create sql conditions if category must have child elements
		if($showCategoryChoice)
		{
            if($categoryType == 'DAT')
            {
                $sqlTables = TBL_CATEGORIES.', '.TBL_DATES;
                $sqlCondidtions = ' AND cat_id = dat_cat_id ';
            }
            elseif($categoryType == 'LNK')
            {
                $sqlTables = TBL_CATEGORIES.', '.TBL_LINKS;
                $sqlCondidtions = ' AND cat_id = lnk_cat_id ';
            }
            elseif($categoryType == 'ROL')
            {
				// don't show system categories
                $sqlTables = TBL_CATEGORIES.', '.TBL_ROLES;
                $sqlCondidtions = ' AND cat_id = rol_cat_id 
                                    AND rol_visible = 1 ';
            }
		}
		
		if($showSystemCategory == false)
		{
			 $sqlCondidtions .= ' AND cat_system = 0 ';
		}
		
		if($gValidLogin == false)
		{
			 $sqlCondidtions .= ' AND cat_hidden = 0 ';
		}
		
		$sql = 'SELECT DISTINCT cat_sequence, cat_id, cat_default, cat_name 
		          FROM '.$sqlTables.'
				 WHERE (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
				   AND cat_type   = \''.$categoryType.'\'
				       '.$sqlCondidtions.'
				 ORDER BY cat_sequence ASC ';
		$result = $gDb->query($sql);
		$countCategories = $gDb->num_rows($result);

        if($countCategories > 1 
        || ($countCategories > 0 && $showCategoryChoice == false ) )
        {
    		$selectBoxHtml = '
    		<select size="1" id="'.$fieldId.'" name="'.$fieldId.'">
    			<option value=" "';
    				if($defaultCategory == 0 || strlen($defaultCategory) == 0)
    				{
    					$selectBoxHtml .= ' selected="selected" ';
    				}
    				$selectBoxHtml .= '>'.$firstEntry.'</option>';
    
    			while($row = $gDb->fetch_array($result))
    			{
    				// if text is a translation-id then translate it
    				if(strpos($row['cat_name'], '_') == 3)
    				{
    					$row['cat_name'] = $gL10n->get(admStrToUpper($row['cat_name']));
    				}
    								
    				// create entry in html
    				$selectBoxHtml .= '<option value="'.$row['cat_id'].'"';
					
					// set selected if category id is the same as in parameters 
					// or it's system category and no category choice mode
					if($defaultCategory  == $row['cat_id']
					|| ($defaultCategory == 0 && $row['cat_default'] == 1 && $showCategoryChoice == false)
					|| $countCategories  == 1 )
					{
						$selectBoxHtml .= ' selected="selected" ';
					}
    				$selectBoxHtml .= '>'.$row['cat_name'].'</option>';
    			}
    		$selectBoxHtml .= '</select>';
        }
		return $selectBoxHtml;
	}
	
	// Diese Funktion erzeugt eine Combobox mit allen Eintraegen aus einer XML-Datei
	//
	// Parameters:
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
	
	// this function creates a combobox with all organizations in database
	//
	// Parameters:
	// defaultOrganization : shortname of default organization that is preselected
	// fieldId       : Id and name der selectbox
	// presentation  : 0 = show all organizations of database
	//                 1 = show organizations that of no parent orga and not the current orga
	public static function generateOrganizationSelectBox($defaultOrganization = '', $fieldId = '', $presentation = 0)
	{
		global $gCurrentOrganization, $gDb, $gL10n;

		if(strlen($fieldId) == 0)
		{
			$fieldId = 'admOrganization';
		}

		$sqlConditions = '';
		$selectBoxHtml = '';

		if($presentation == 0)
		{
			$firstEntry = '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -';
		}
		elseif($presentation == 1)
		{
			$firstEntry = $gL10n->get('SYS_NONE');
			$sqlConditions = ' WHERE org_id <> '. $gCurrentOrganization->getValue('org_id'). '
				                 AND org_org_id_parent is NULL ';
		}

		$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS.
				         $sqlConditions.'
				 ORDER BY org_longname ASC, org_shortname ASC ';
		$result = $gDb->query($sql);

		if($gDb->num_rows($result) > 0)
		{
			// Auswahlfeld fuer die uebergeordnete Organisation
			$selectBoxHtml = '<select size="1" id="'.$fieldId.'" name="'.$fieldId.'">
				<option value="0" ';
				if(strlen($defaultOrganization) == 0)
				{
					$selectBoxHtml .= ' selected="selected" ';
				}
				$selectBoxHtml .= '>'.$firstEntry.'</option>';

				while($row = $gDb->fetch_array($result))
				{
					$selectBoxHtml .= '<option value="'.$row['org_id'].'" ';
					if(is_numeric($defaultOrganization) == true && $defaultOrganization == $row['org_id'])
					{
						$selectBoxHtml .= ' selected="selected" ';
					}
					elseif(is_numeric($defaultOrganization) == false && $defaultOrganization == $row['org_shortname'])
					{
						$selectBoxHtml .= ' selected="selected" ';
					}
					$selectBoxHtml .= '>'.$row['org_longname'].'</option>';
				}
			$selectBoxHtml .= '</select>';
		}
		return $selectBoxHtml;
	}
}
?>