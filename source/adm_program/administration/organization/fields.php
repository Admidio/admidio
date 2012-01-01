<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_user_field.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['fields_request']);

// zusaetzliche Daten fuer den Html-Kopf setzen
$gLayout['title']  = $gL10n->get('ORG_PROFILE_FIELDS');
$gLayout['header'] = '
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
<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php"><img 
            src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('ORG_CREATE_PROFILE_FIELD').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php">'.$gL10n->get('ORG_CREATE_PROFILE_FIELD').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=USF"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=USF">'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
        </span>
    </li>
</ul>';

$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
         WHERE cat_type   = \'USF\'
           AND usf_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ';
$result = $gDb->query($sql);

echo '
<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>'.$gL10n->get('SYS_FIELD').'<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=ORG_FIELD_DESCRIPTION&amp;inline=true"><img 
                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a></th>
            <th>&nbsp;</th>
            <th>'.$gL10n->get('SYS_DESCRIPTION').'</th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_MANDATORY').'" /></th>
            <th>'.$gL10n->get('ORG_DATATYPE').'</th>
            <th style="width: 40px;">&nbsp;</th>
        </tr>
    </thead>';
    
    $categoryId = 0;
    $userField  = new TableUserField($gDb);

    if($gDb->num_rows($result) > 0)
    {
        while($row = $gDb->fetch_array($result))
        {
            $userField->clear();
            $userField->setArray($row);
        
            if($categoryId != $userField->getValue('cat_id'))
            {
                if($categoryId > 0)
                {
                    echo '</tbody>';
                }
                $block_id = 'admCategory'.$userField->getValue('usf_cat_id');
                echo '<tbody>
                    <tr>
                        <td class="tableSubHeader" colspan="8">
                            <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img 
                            id="'.$block_id.'Image" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$userField->getValue('cat_name').'
                        </td>
                    </tr>
                </tbody>
                <tbody id="'.$block_id.'">';
                $categoryId = $userField->getValue('usf_cat_id');
            }           
            echo '
            <tr id="row_usf_'.$userField->getValue('usf_id').'" class="tableMouseOver">
                <td><a href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php?usf_id='.$userField->getValue('usf_id').'">'.$userField->getValue('usf_name').'</a></td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="javascript:moveCategory(\'up\', '.$userField->getValue('usf_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('ORG_FIELD_UP').'" title="'.$gL10n->get('ORG_FIELD_UP').'" /></a>
                    <a class="iconLink" href="javascript:moveCategory(\'down\', '.$userField->getValue('usf_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('ORG_FIELD_DOWN').'" title="'.$gL10n->get('ORG_FIELD_DOWN').'" /></a>
                </td>
                <td>';
                    // laengere Texte kuerzen und Tooltip mit Popup anbieten
                    if(strlen($userField->getValue('usf_description')) > 22)
                    {
                        echo substr($userField->getValue('usf_description', 'plain'), 0, 22). ' 
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $userField->getValue('usf_name_intern'). '&amp;inline=true"
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $userField->getValue('usf_name_intern'). '\',this)" 
                            onmouseout="ajax_hideTooltip()">[..]</a>';
                    }
                    else
                    {
                        echo $userField->getValue('usf_description');
                    }
                echo '</td>
                <td>';
                    if($userField->getValue('usf_hidden') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye_gray.png" alt="'.$gL10n->get('ORG_FIELD_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_HIDDEN').'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />';
                    }
                echo '</td>
                <td>';
                    if($userField->getValue('usf_disabled') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield.png" alt="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" title="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" />';
                    }
                echo '</td>
                <td>';
                    if($userField->getValue('usf_mandatory') == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_MANDATORY').'" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_gray.png" alt="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" />';
                    }
                echo '</td>
                <td>';
                    $userFieldText = array('CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
                                           'DATE'     => $gL10n->get('SYS_DATE'),
                                           'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                                           'EMAIL'    => $gL10n->get('SYS_EMAIL'),
                                           'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                                           'TEXT'     => $gL10n->get('SYS_TEXT').' (50)',
                                           'TEXT_BIG' => $gL10n->get('SYS_TEXT').' (255)',
                                           'URL'      => $gL10n->get('ORG_URL'),
                                           'NUMERIC'  => $gL10n->get('SYS_NUMBER'));
					echo $userFieldText[$userField->getValue('usf_type')].
                '</td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/organization/fields_new.php?usf_id='.$userField->getValue('usf_id').'"><img 
                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
                    if($userField->getValue('usf_system') == 1)
                    {
                        echo '&nbsp;<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
                    }
                    else
                    {
                        echo '
                        <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=usf&amp;element_id=row_usf_'.
                            $userField->getValue('usf_id').'&amp;name='.urlencode($userField->getValue('usf_name')).'&amp;database_id='.$userField->getValue('usf_id').'"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
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
                <p>'.$gL10n->get('ORG_NO_FIELD_CREATED').'</p>
            </td>
        </tr>';
    }
echo '</table>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>