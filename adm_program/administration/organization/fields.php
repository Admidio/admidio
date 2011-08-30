<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_user_field.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['fields_request']);

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = $g_l10n->get('ORG_PROFILE_FIELDS');
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        }); 

        function moveCategory(direction, usfID)
        {
            var actRow = document.getElementById("row_usf_" + usfID);
            var childs = actRow.parentNode.childNodes;
            var prevNode    = null;
            var nextNode    = null;
            var actRowCount = 0;
            var actSequence = 0;
            var secondSequence = 0;
            
            // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
            for(i=0;i < childs.length; i++)
            {
                if(childs[i].tagName == "TR")
                {
                    actRowCount++;
                    if(actSequence > 0 && nextNode == null)
                    {
                        nextNode = childs[i];
                    }
                    
                    if(childs[i].id == "row_usf_" + usfID)
                    {
                        actSequence = actRowCount;
                    }
                    
                    if(actSequence == 0)
                    {
                        prevNode = childs[i];
                    }
                }
            }
            
            // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
            if(direction == "up")
            {
                if(prevNode != null)
                {
                    actRow.parentNode.insertBefore(actRow, prevNode);
                    secondSequence = actSequence - 1;
                }
            }
            else
            {
                if(nextNode != null)
                {
                    actRow.parentNode.insertBefore(nextNode, actRow);
                    secondSequence = actSequence + 1;
                }
            }

            if(secondSequence > 0)
            {
                // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
                $.get(gRootPath + "/adm_program/administration/organization/fields_function.php?usf_id=" + usfID + "&mode=4&sequence=" + direction);
            }
        }
    //--></script>';
    
// Html-Kopf ausgeben
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php"><img 
            src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('ORG_CREATE_PROFILE_FIELD').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php">'.$g_l10n->get('ORG_CREATE_PROFILE_FIELD').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=USF"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=USF">'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
        </span>
    </li>
</ul>';

$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
         WHERE cat_type   = \'USF\'
           AND usf_cat_id = cat_id
           AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ';
$result = $g_db->query($sql);

$js_drag_drop = '';

echo '
<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>'.$g_l10n->get('SYS_FIELD').'<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_DESCRIPTION&amp;inline=true"><img 
                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a></th>
            <th>&nbsp;</th>
            <th>'.$g_l10n->get('SYS_DESCRIPTION').'</th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="'.$g_l10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$g_l10n->get('ORG_FIELD_NOT_HIDDEN').'" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$g_l10n->get('ORG_FIELD_DISABLED', $g_l10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$g_l10n->get('ORG_FIELD_DISABLED', $g_l10n->get('ROL_RIGHT_EDIT_USER')).'" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$g_l10n->get('ORG_FIELD_MANDATORY').'" title="'.$g_l10n->get('ORG_FIELD_MANDATORY').'" /></th>
            <th>'.$g_l10n->get('ORG_DATATYPE').'</th>
            <th style="width: 40px;">&nbsp;</th>
        </tr>
    </thead>';
    
    $cat_id = 0;
    $field  = new TableUserField($g_db);

    if($g_db->num_rows($result) > 0)
    {
        while($row = $g_db->fetch_array($result))
        {
            $field->clear();
            $field->setArray($row);
        
            if($cat_id != $row['cat_id'])
            {
                if($cat_id > 0)
                {
                    echo '</tbody>';
                }
                $block_id = 'admCategory'.$row['cat_id'];
                echo '<tbody>
                    <tr>
                        <td class="tableSubHeader" colspan="8">
                            <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\', \''.$g_l10n->get('SYS_FADE_IN').'\', \''.$g_l10n->get('SYS_HIDE').'\')"><img 
                            id="'.$block_id.'Image" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$row['cat_name'].'
                        </td>
                    </tr>
                </tbody>
                <tbody id="'.$block_id.'">';
                $cat_id = $row['cat_id'];
            }           
            echo '
            <tr id="row_usf_'.$field->getValue('usf_id').'" class="tableMouseOver">
                <td><a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php?usf_id='.$field->getValue('usf_id').'">'.$field->getValue('usf_name').'</a></td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="javascript:moveCategory(\'up\', '.$field->getValue('usf_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$g_l10n->get('ORG_FIELD_UP').'" title="'.$g_l10n->get('ORG_FIELD_UP').'" /></a>
                    <a class="iconLink" href="javascript:moveCategory(\'down\', '.$field->getValue('usf_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$g_l10n->get('ORG_FIELD_DOWN').'" title="'.$g_l10n->get('ORG_FIELD_DOWN').'" /></a>
                </td>
                <td>';
                    // laengere Texte kuerzen und Tooltip mit Popup anbieten
                    if(strlen($field->getValue('usf_description')) > 30)
                    {
                        echo substr($field->getValue('usf_description'), 0, 30). ' 
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $field->getValue('usf_name_intern'). '&amp;inline=true"
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $field->getValue('usf_name_intern'). '\',this)" 
                            onmouseout="ajax_hideTooltip()">[..]</a>';
                    }
                    else
                    {
                        echo $field->getValue('usf_description');
                    }
                echo '</td>
                <td>';
                    if($field->getValue('usf_hidden') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye_gray.png" alt="'.$g_l10n->get('ORG_FIELD_HIDDEN').'" title="'.$g_l10n->get('ORG_FIELD_HIDDEN').'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="'.$g_l10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$g_l10n->get('ORG_FIELD_NOT_HIDDEN').'" />';
                    }
                echo '</td>
                <td>';
                    if($field->getValue('usf_disabled') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$g_l10n->get('ORG_FIELD_DISABLED', $g_l10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$g_l10n->get('ORG_FIELD_DISABLED', $g_l10n->get('ROL_RIGHT_EDIT_USER')).'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield.png" alt="'.$g_l10n->get('ORG_FIELD_NOT_DISABLED').'" title="'.$g_l10n->get('ORG_FIELD_NOT_DISABLED').'" />';
                    }
                echo '</td>
                <td>';
                    if($field->getValue('usf_mandatory') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$g_l10n->get('ORG_FIELD_MANDATORY').'" title="'.$g_l10n->get('ORG_FIELD_MANDATORY').'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_gray.png" alt="'.$g_l10n->get('ORG_FIELD_NOT_MANDATORY').'" title="'.$g_l10n->get('ORG_FIELD_NOT_MANDATORY').'" />';
                    }
                echo '</td>
                <td>';
                    $userFieldText = array('CHECKBOX' => $g_l10n->get('SYS_CHECKBOX'),
                                           'DATE'     => $g_l10n->get('SYS_DATE'),
                                           'DROPDOWN' => $g_l10n->get('SYS_DROPDOWN_LISTBOX'),
                                           'EMAIL'    => $g_l10n->get('SYS_EMAIL'),
                                           'RADIO_BUTTON' => $g_l10n->get('SYS_RADIO_BUTTON'),
                                           'TEXT'     => $g_l10n->get('SYS_TEXT').' (50)',
                                           'TEXT_BIG' => $g_l10n->get('SYS_TEXT').' (255)',
                                           'URL'      => $g_l10n->get('ORG_URL'),
                                           'NUMERIC'  => $g_l10n->get('SYS_NUMBER'));
					echo $userFieldText[$field->getValue('usf_type')].
                '</td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php?usf_id='.$field->getValue('usf_id').'"><img 
                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>';
                    if($field->getValue('usf_system') == 1)
                    {
                        echo '&nbsp;<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
                    }
                    else
                    {
                        echo '
                        <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=usf&amp;element_id=row_usf_'.
                            $field->getValue('usf_id').'&amp;name='.urlencode($field->getValue('usf_name')).'&amp;database_id='.$field->getValue('usf_id').'"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
                    }
                echo '</td>
            </tr>';
        }
        echo '</tbody>';
    }
    else
    {
        echo '<tr>
            <td colspan="5" style="text-align: center;">
                <p>'.$g_l10n->get('ORG_NO_FIELD_CREATED').'</p>
            </td>
        </tr>';
    }
echo '</table>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>