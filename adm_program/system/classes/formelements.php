<?php
/******************************************************************************
 * Factory class that creates elements for html forms
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
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
        if($createFirstEntry === true)
        {
            $selectBoxHtml .= '<option value=" "';
            if($defaultEntry === '')
            {
                $selectBoxHtml .= ' selected="selected" ';
            }
            $selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
        }

        $value = reset($entryArray);
        $arrayCountMax = count($entryArray);
        for($arrayCount = 0; $arrayCount < $arrayCountMax; $arrayCount++)
        {
            // create entry in html
            $selectBoxHtml .= '<option value="'.key($entryArray).'"';
            if(key($entryArray) === $defaultEntry)
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

        if($fieldId === '')
        {
            $fieldId = 'rol_id';
        }

        // SQL-Statement entsprechend dem Modus zusammensetzen
        $condition = '';
        $active_roles = 1;
        if($showMode === 1 && $gCurrentUser->manageRoles() === false)
        {
            // keine Rollen mit Rollenzuordnungsrecht anzeigen
            $condition .= ' AND rol_assign_roles = 0 ';
        }
        elseif($showMode === 1 && $gCurrentUser->isWebmaster() === false)
        {
            // Webmasterrolle nicht anzeigen
            $condition .= ' AND rol_webmaster = 0 ';
        }
        elseif($showMode === 2)
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
        <select class="form-control" size="1" id="'.$fieldId.'" name="'.$fieldId.'"><option value="0" ';
        if($defaultRole === 0)
        {
            $selectBoxHtml .= ' selected="selected" ';
        }
        $selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';

        if($visitors === 1)
        {
            $selectBoxHtml .= '<option value="-1" ';
            if($defaultRole === -1)
            {
                $selectBoxHtml .= ' selected="selected" ';
            }
            $selectBoxHtml .= '>'.$gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')</option>';
        }

        while($row = $gDb->fetch_array($result_lst))
        {
            if($gCurrentUser->hasRightViewRole($row['rol_id']))
            {
                // if text is a translation-id then translate it
                if(strpos($row['cat_name'], '_') === 3)
                {
                    $row['cat_name'] = $gL10n->get(admStrToUpper($row['cat_name']));
                }

                // if new category then show label with category name
                if($act_category !== $row['cat_name'])
                {
                    if($act_category !== '')
                    {
                        $selectBoxHtml .= '</optgroup>';
                    }
                    $selectBoxHtml .= '<optgroup label="'.$row['cat_name'].'">';
                    $act_category = $row['cat_name'];
                }
                // wurde eine Rollen-Id uebergeben, dann Combobox mit dieser vorbelegen
                $selected = '';
                if($row['rol_id'] === $defaultRole)
                {
                    $selected = ' selected="selected" ';
                }
                $selectBoxHtml .= '<option '.$selected.' value="'.$row['rol_id'].'">'.$row['rol_name'].'</option>';
            }
        }
        $selectBoxHtml .= '</optgroup></select>';
        return $selectBoxHtml;
    }
}
