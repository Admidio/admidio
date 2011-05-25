<?php
/******************************************************************************
 * Funktionen zuordnen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen user_id aendern
 * new_user: 0 - (Default) Daten eines vorhandenen Users werden bearbeitet
 *           1 - Der User ist gerade angelegt worden -> Rollen muessen zugeordnet werden
 * inline:   0 - (Default) wird als eigene Seite angezeigt
 *           1 - nur "body" HTML Code (z.B. für colorbox)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!$g_current_user->assignRoles() && !isGroupLeader($g_current_user->getValue('usr_id')))
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id   = 0;
$req_new_user = 0;
$req_inlineView = 0;

// Uebergabevariablen pruefen
if(isset($_GET['inline']))
{
    if(is_numeric($_GET['inline']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_inlineView = $_GET['inline'];
}
if(isset($_GET['user_id']))
{
    if(is_numeric($_GET['user_id']) == false)
    {
        if($req_inlineView == 0)
        {
            $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }
        else
        {
            echo $g_l10n->get('SYS_INVALID_PAGE_VIEW');
        }
    }
    $req_usr_id = $_GET['user_id'];
}

if(isset($_GET['new_user']))
{
    if(is_numeric($_GET['new_user']) == false)
    {
        if($req_inlineView == 0)
        {
            $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
        }
        else
        {
            echo $g_l10n->get('SYS_INVALID_PAGE_VIEW');
        }
    }
    $req_new_user = $_GET['new_user'];
}



$user     = new User($g_db, $req_usr_id);
if($req_inlineView == 0)
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}
//Testen ob Feste Rolle gesetzt ist
if(isset($_SESSION['set_rol_id']))
{
    $set_rol_id = $_SESSION['set_rol_id'];
    unset($_SESSION['set_rol_id']);
}
else
{
    $set_rol_id = NULL;
}

// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('ROL_ROLE_ASSIGNMENT',$user->getValue('LAST_NAME'),$user->getValue('FIRST_NAME'));
$g_layout['header'] = '<script type="text/javascript" src="'.$g_root_path.'/adm_program/modules/profile/profile.js"></script>
<script type="text/javascript">
    var profileJS = new profileJSClass();
	profileJS.init();
</script>';
if($req_inlineView == 0)
{
    require(SERVER_PATH. '/adm_program/system/overall_header.php');
}

echo '
<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>

<form id="rolesForm" action="'.$g_root_path.'/adm_program/modules/profile/roles_save.php?user_id='.$req_usr_id.'&amp;new_user='.$req_new_user.'&amp;inline='.$req_inlineView.'" method="post">
    <table class="tableList" cellspacing="0">
        <thead>
            <tr>
                <th>&nbsp;</th>
                <th>'.$g_l10n->get('ROL_ROLE').'</th>
                <th>'.$g_l10n->get('SYS_DESCRIPTION').'</th>
                <th style="text-align: center; width: 80px;">'.$g_l10n->get('SYS_LEADER');
				if($req_inlineView == 0)
				{
					echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_LEADER_DESCRIPTION&amp;inline=true"><img 
		            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_LEADER_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
		            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$g_l10n->get('SYS_HELP').'" title="" /></a>';
				}
				else
				{
					echo '<img onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_LEADER_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
		            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$g_l10n->get('SYS_HELP').'" title="" />';
				}
                echo'</th>
            </tr>
        </thead>';

        if($g_current_user->assignRoles())
        {
            // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
            $sql    = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_visible, mem_usr_id, mem_leader
                         FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                         LEFT JOIN '. TBL_MEMBERS. '
                           ON rol_id      = mem_rol_id
                          AND mem_usr_id  = '.$req_usr_id.'
                          AND mem_begin  <= \''.DATE_NOW.'\'
                          AND mem_end     > \''.DATE_NOW.'\'
                        WHERE rol_valid   = \'1\'
                          AND rol_visible = \'1\'
                          AND rol_cat_id  = cat_id
                          AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                              OR cat_org_id IS NULL )
                        ORDER BY cat_sequence, cat_id, rol_name';
        }
        else
        {
            // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
            $sql    = 'SELECT cat_id, cat_name, rol_name, rol_description, rol_id, rol_visible,
                              mgl.mem_usr_id as mem_usr_id, mgl.mem_leader as mem_leader
                         FROM '. TBL_MEMBERS. ' bm, '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                         LEFT JOIN '. TBL_MEMBERS. ' mgl
                           ON rol_id         = mgl.mem_rol_id
                          AND mgl.mem_usr_id = '.$req_usr_id.'
                          AND mgl.mem_begin <= \''.DATE_NOW.'\'
                          AND mgl.mem_end    > \''.DATE_NOW.'\'
                        WHERE bm.mem_usr_id  = '. $g_current_user->getValue('usr_id'). '
                          AND bm.mem_begin  <= \''.DATE_NOW.'\'
                          AND bm.mem_end     > \''.DATE_NOW.'\'
                          AND bm.mem_leader  = \'1\'
                          AND rol_id         = bm.mem_rol_id
                          AND rol_valid      = \'1\'
                          AND rol_visible    = \'1\'
                          AND rol_cat_id     = cat_id
                          AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                              OR cat_org_id IS NULL
                        ORDER BY cat_sequence, cat_id, rol_name';
        }
        $result = $g_db->query($sql);
        $category = '';

        while($row = $g_db->fetch_object($result))
        {
            if($row->rol_visible==1)
            {
                if($category != $row->cat_id)
                {
                    if(strlen($category) > 0)
                    {
                        echo '</tbody>';
                    }
                    $block_id = 'admCategory'.$row->cat_id;
                    echo '<tbody>
                        <tr>
                            <td class="tableSubHeader" colspan="4">
                                <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\');"><img
                                id="'.$block_id.'Image" src="'.THEME_PATH.'/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$row->cat_name.'
                            </td>
                        </tr>
                    </tbody>
                    <tbody id="'.$block_id.'">';
    
                    $category = $row->cat_id;
                }
                echo '
                <tr class="tableMouseOver">
                   <td style="text-align: center;">
                      <input type="checkbox" id="role-'.$row->rol_id.'" name="role-'.$row->rol_id.'" ';
                         if($row->mem_usr_id > 0)
                         {
                            echo ' checked="checked" ';
                         }
    
                         // wenn der User aus der Mitgliederzuordnung heraus neu angelegt wurde
                         // entsprechende Rolle sofort hinzufuegen
                         if($row->rol_id == $set_rol_id)
                         {
                            echo ' checked="checked" ';
                         }
    
                         // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                         if($row->rol_name == $g_l10n->get('SYS_WEBMASTER') && (!$g_current_user->isWebmaster()
                            ||  // man darf sich selbst an dieser Stelle aber nicht aus der Rolle Webmaster entfernen
                            ($g_current_user->isWebmaster() && $req_usr_id == $g_current_user->getValue("usr_id")))
                           )
                         {
                           echo ' readonly="readonly" ';
                         }
    
                         echo ' onclick="javascript:profileJS.unMarkLeader(this);" value="1" />
                   </td>
                   <td><label for="role-'.$row->rol_id.'">'.$row->rol_name.'</label></td>
                   <td>'.$row->rol_description.'</td>
                   <td style="text-align: center;">
                            <input type="checkbox" id="leader-'.$row->rol_id.'" name="leader-'.$row->rol_id.'" ';
                            if($row->mem_leader > 0)
                            {
                                echo ' checked="checked" ';
                            }
    
                            // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                            if($row->rol_name == $g_l10n->get('SYS_WEBMASTER') && !$g_current_user->isWebmaster())
                            {
                                echo ' disabled="disabled" ';
                            }
    
                            echo ' onclick="javascript:profileJS.markLeader(this);" value="1" />
                   </td>
                </tr>';
            }
        }
        echo '</tbody>
    </table>

    <div class="formSubmit">
        <button id="btnSave" type="submit"><img src="'.THEME_PATH.'/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
    </div>';
    if($req_inlineView == 0)
    {
        echo '<ul class="iconTextLinkList">
                <li>
                    <span class="iconTextLink">
                        <a href="$g_root_path/adm_program/system/back.php"><img
                        src="'.THEME_PATH.'/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                        <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
                    </span>
                </li>
             </ul>';
    }
echo '</form>';
if($req_inlineView == 0)
{
    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}
?>