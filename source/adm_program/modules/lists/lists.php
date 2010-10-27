<?php
/******************************************************************************
 * Anzeigen von Listen
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * start    - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * category - Kategorie der Rollen, die angezeigt werden sollen
 *            Wird keine Kategorie uebergeben, werden alle Rollen angezeigt
 * category-selection: yes - (Default) Anzeige der Combobox mit den verschiedenen
 *                           Rollen-Kategorien
 *                     no  - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_roles.php');

unset($_SESSION['mylist_request']);

// Uebergabevariablen pruefen und ggf. vorbelegen

if(isset($_GET['start']) == false || is_numeric($_GET['start']) == false)
{
    $_GET['start'] = 0;
}

if(isset($_GET['category']) == false)
{
    $_GET['category'] = '';
}

if(isset($_GET['category-selection']) == false)
{
    $_GET['category-selection'] = 'yes';
    $show_ctg_sel = 1;
}
else
{
    if($_GET['category-selection'] == 'yes')
    {
        $show_ctg_sel = 1;
    }
    else
    {
        $show_ctg_sel = 0;
    }
}

if(isset($_GET['active_role']) == false)
{
    $active_role = 1;
}
else
{
    if($_GET['active_role'] != 0
    && $_GET['active_role'] != 1)
    {
        $active_role = 1;
    }
    else
    {
        $active_role = $_GET['active_role'];
    }
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Alle Rollen-IDs ermitteln, die der User sehen darf
$rol_id_list = '';
if($active_role)
{
    foreach($g_current_user->getListViewRights() as $key => $value)
    {
        if($value == 1)
        {
            $rol_id_list = $rol_id_list. $key. ', ';
        }
    }
    if(strlen($rol_id_list) > 0)
    {
        $rol_id_list = ' AND rol_id IN ('. substr($rol_id_list, 0, strlen($rol_id_list)-2). ') ';
    }
    else
    {
        $rol_id_list = ' AND rol_id = 0 ';
    }
}

// Listen-SQL-Statement zusammensetzen
if($active_role == 1)
{
    $sql_member_status = ' AND mem_begin <= "'.DATE_NOW.'"
                           AND mem_end   >= "'.DATE_NOW.'" ';
}
else
{
    $sql_member_status = ' AND mem_end < "'.DATE_NOW.'" ';
}

$sql = 'SELECT rol.*, cat.*, 
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id '.$sql_member_status.' AND mem_leader = 0) as num_members,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id '.$sql_member_status.' AND mem_leader = 1) as num_leader,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id AND mem_end < "'. DATE_NOW.'") as num_former
          FROM '. TBL_ROLES. ' rol, '. TBL_CATEGORIES. ' cat
         WHERE rol_valid   = '.$active_role.'
           AND rol_visible = 1
               '.$rol_id_list.'
           AND rol_cat_id = cat_id 
           AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
               OR cat_org_id IS NULL ) ';
if($g_valid_login == false)
{
    $sql .= ' AND cat_hidden = 0 ';
}
if(strlen($_GET['category']) > 0 && $_GET['category'] != 'Alle')
{
    // wenn eine Kategorie uebergeben wurde, dann nur Rollen dieser anzeigen
    $sql .= ' AND cat_type   = "ROL"
              AND cat_name   = "'. $_GET['category']. '" ';
}
$sql .= ' ORDER BY cat_sequence, rol_name ';

$result_lst = $g_db->query($sql);
$num_roles  = $g_db->num_rows();

if($num_roles == 0)
{
    if($g_valid_login == true)
    {
        // wenn User eingeloggt, dann Meldung, falls doch keine Rollen zur Verfuegung stehen
        if($active_role == 0)
        {
            $g_message->show($g_l10n->get('LST_PHR_NO_ROLES_REMOVED'));
        }
        else
        {
            $g_message->show($g_l10n->get('LST_PHR_NO_RIGHTS_VIEW_LIST'));
        }
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require_once('../../system/login_valid.php');
    }
}

// Html-Kopf ausgeben
if($active_role)
{
    $g_layout['title']  = $g_l10n->get('LST_PHR_ACTIVE_ROLES');
}
else
{
    $g_layout['title']  = $g_l10n->get('LST_PHR_INACTIVE_ROLES');
}
$g_layout['header'] = '
    <script type="text/javascript"><!--
        function showCategory()
        {
            var category = document.getElementById("category").value;
            self.location.href = "lists.php?category=" + category + "&category-selection='. $_GET['category-selection']. '&active_role='.$active_role.'";
        }

        function showList(element, rol_id)
        {
            var lst_id = element.value;

            if(lst_id == "mylist")
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + rol_id + "&active_role='.$active_role.'";
            }
            else
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + lst_id + "&rol_id=" + rol_id;
            }
        }
    //--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<div id="lists_overview">
<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>';

// Kategorienauswahlbox soll angezeigt werden oder der User darf neue Rollen anlegen
if($show_ctg_sel == 1 || $g_current_user->assignRoles())
{
    echo '<ul class="iconTextLinkList">';
    //Neue Termine anlegen
    if($g_current_user->editDates())
    {
        echo '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
                    src="'.THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get('SYS_CREATE_ROLE').'" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">'.$g_l10n->get('SYS_CREATE_ROLE').'</a>
            </span>
        </li>';
    }

    if($show_ctg_sel == 1)
    {
        // Combobox mit allen Kategorien anzeigen, denen auch Rollen zugeordnet sind
        $sql = 'SELECT DISTINCT cat_name 
                  FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                 WHERE (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                   AND cat_type    = "ROL" 
                   AND rol_cat_id  = cat_id
                   AND rol_visible = 1
                       '.$rol_id_list;
        if($g_valid_login == false)
        {
            $sql .= ' AND cat_hidden = 0 ';
        }
        $sql .= ' ORDER BY cat_sequence ASC ';
        $result = $g_db->query($sql);

        if($g_db->num_rows($result) > 1)
        {
            echo '<li>'.$g_l10n->get('SYS_CATEGORY').':&nbsp;&nbsp;
            <select size="1" id="category" onchange="showCategory()">
                <option value="Alle" ';
                if(strlen($_GET['category']) == 0)
                {
                    echo ' selected="selected" ';
                }
                echo '>'.$g_l10n->get('SYS_ALL').'</option>';

                while($row = $g_db->fetch_object($result))
                {
                    echo '<option value="'. urlencode($row->cat_name). '"';
                    if($_GET['category'] == $row->cat_name)
                    {
                        echo ' selected="selected" ';
                    }
                    echo '>'.$row->cat_name.'</option>';
                }
            echo '</select>';
            if($g_current_user->assignRoles())
            {
                echo '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
                 src="'. THEME_PATH. '/icons/options.png" alt="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" title="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>';
            }
            echo '</li>';
        }
        elseif($g_current_user->assignRoles())
        {
            echo '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL">'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
            </span></li>';
        }    
    }
    echo '</ul>';
}

$previous_cat_id   = 0;
$count_cat_entries = 0;
// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$g_db->data_seek($result_lst, $_GET['start']))
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
$sql = 'SELECT lst_id, lst_name, lst_global FROM '. TBL_LISTS. '
     WHERE lst_org_id = '. $g_current_organization->getValue('org_id'). '
       AND (  lst_usr_id = '. $g_current_user->getValue('usr_id'). '
           OR lst_global = 1)
       AND lst_name IS NOT NULL
     ORDER BY lst_global ASC, lst_name ASC';
$result_config = $g_db->query($sql);

// Anzahl Rollen pro Seite
if($g_preferences['lists_roles_per_page'] > 0)
{
    $roles_per_page = $g_preferences['lists_roles_per_page'];
}
else
{
    $roles_per_page = $num_roles;
}

// Rollenobjekt anlegen
$role = new TableRoles($g_db);

for($i = 0; $i < $roles_per_page && $i + $_GET['start'] < $num_roles; $i++)
{
    if($row_lst = $g_db->fetch_array($result_lst))
    {
        // Rollenobjekt mit Daten fuellen
        $role->setArray($row_lst);
        if($role->getValue('rol_visible') == 1)
        {    
            if($previous_cat_id != $role->getValue('cat_id'))
            {
                if($i > 0)
                {
                    if($count_cat_entries == 0)
                    {
                        echo $g_l10n->get('LST_PHR_CATEGORY_NO_LISTS');
                    }
                    echo '</div></div><br />';
                }
                echo '<div class="formLayout">
                    <div class="formHead">'. $role->getValue('cat_name'). '</div>
                    <div class="formBody">';
                $previous_cat_id = $role->getValue('cat_id');
                $count_cat_entries = 0;
            }
    
            
            //Nur anzeigen, wenn User auch die Liste einsehen darf
            if($g_current_user->viewRole($role->getValue('rol_id')))
            {
                if($count_cat_entries > 0)
                {
                    echo '<hr />';
                }
                echo '
                <div>
                    <div style="float: left;">';
                        //Dreieck zum ein und ausblenden der Details
                        if($g_preferences['lists_hide_overview_details']==1)
                        {
                            echo '<a class="iconLink" href="javascript:toggleElement(\'role_details_'.$role->getValue('rol_id').'\', \'triangle_'.$role->getValue('rol_id').'\')">
                                <img id="triangle_'.$role->getValue('rol_id').'"  src="'. THEME_PATH. '/icons/triangle_close.gif" alt="'.$g_l10n->get('SYS_FADE_IN').'" title="'.$g_l10n->get('SYS_FADE_IN').'" /></a>'; 
                        }
                        else
                        {
                            echo '<a class="iconLink" href="javascript:toggleElement(\'role_details_'.$role->getValue('rol_id').'\', \'triangle_'.$role->getValue('rol_id').'\')">
                                <img id="triangle_'.$role->getValue('rol_id').'"  src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>';
                        }
    
                        // Link nur anzeigen, wenn Rolle auch Mitglieder hat
                        if($row_lst['num_members'] > 0 || $row_lst['num_leader'] > 0)
                        {
                            echo '<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '">'. $role->getValue('rol_name'). '</a>';
                        }
                        else
                        {
                            echo '<strong>'. $role->getValue('rol_name'). '</strong>';
                        }
        
                        //Mail an Rolle schicken
                        if($g_current_user->mailRole($role->getValue('rol_id')) && $g_preferences['enable_mail_module'] == 1)
                        {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$role->getValue('rol_id').'"><img
                                src="'. THEME_PATH. '/icons/email.png"  alt="'.$g_l10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$g_l10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>';
                        }
                        
                        if($g_current_user->assignRoles() 
                        || isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')))
                        {
                            // die Webmasterrolle darf nur von Webmastern bearbeitet werden
                            if($role->getValue('rol_name')  != $g_l10n->get('SYS_WEBMASTER')
                            || ($role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER') && $g_current_user->isWebmaster()))
                            {
                                if($g_current_user->assignRoles())
                                {
                                    // nur Moderatoren duerfen Rollen editieren
                                    echo '
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
                                        src="'.THEME_PATH.'/icons/edit.png" alt="'.$g_l10n->get('SYS_SETTINGS').'" title="'.$g_l10n->get('SYS_SETTINGS').'" /></a>';
                                }
        
                                // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
                                if($role->getValue("rol_valid") == 1)
                                {
                                    echo '
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img 
                                        src="'.THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$g_l10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
                                }
                            }
                        }
                    echo '</div>
                    <div style="text-align: right;">';
                        // Kombobox mit Listen nur anzeigen, wenn die Rolle Mitglieder hat
                        if($row_lst['num_members'] > 0 || $row_lst['num_leader'] > 0)
                        {
                            echo '
                            <select size="1" name="list'.$i.'" onchange="showList(this, '. $role->getValue('rol_id'). ')">
                                <option value="" selected="selected">'.$g_l10n->get('LST_SHOW_LISTS').' ...</option>';
                                
                                // alle globalen Listenkonfigurationen auflisten
                                $g_db->data_seek($result_config, 0);
                                $list_global_flag = '';
                                
                                while($row = $g_db->fetch_array($result_config))
                                {
                                    if($list_global_flag != $row['lst_global'])
                                    {
                                        if($row['lst_global'] == 0)
                                        {
                                            echo '<optgroup label="'.$g_l10n->get('LST_YOUR_LISTS').'">';
                                        }
                                        else
                                        {
                                            if($list_global_flag > 0)
                                            {
                                                echo '</optgroup>';
                                            }
                                            echo '<optgroup label="'.$g_l10n->get('LST_GENERAL_LISTS').'">';
                                        }
                                        $list_global_flag = $row['lst_global'];
                                    }
                                    echo '<option value="'.$row['lst_id'].'">'.$row['lst_name'].'</option>';
                                }
                                
                                // Link zu den eigenen Listen setzen
                                echo '</optgroup>
                                <optgroup label="'.$g_l10n->get('LST_CONFIGURATION').'">
                                    <option value="mylist">'.$g_l10n->get('LST_CREATE_OWN_LIST').'</option>
                                </optgroup>
                            </select>';
                        }
                        else
                        {
                            echo '&nbsp;';
                        }
                    echo '</div>
                </div>
                
                <ul id="role_details_'.$role->getValue('rol_id').'" ';
                    if($g_preferences['lists_hide_overview_details']==1)
                    {
                        echo ' style="display: none;" '; 
                    }
                    echo ' class="formFieldList">';
                    if(strlen($role->getValue('rol_description')) > 0)
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('SYS_DESCRIPTION').':</dt>
                                <dd>'.$role->getValue('rol_description').'</dd>
                            </dl>
                        </li>';
                    }
        
                    if(strlen($role->getValue('rol_start_date')) > 0)
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('LST_PERIOD').':</dt>
                                <dd>'.$g_l10n->get('SYS_PHR_DATE_TO', $role->getValue('rol_start_date', $g_preferences['system_date']), $role->getValue('rol_end_date', $g_preferences['system_date'])).'</dd>
                            </dl>
                        </li>';
                    }
                    if($role->getValue('rol_weekday') > 0
                    || strlen($role->getValue('rol_start_time')) > 0 )
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('DAT_DATE').':</dt>
                                <dd>'; 
                                    if($role->getValue('rol_weekday') > 0)
                                    {
                                        echo $arrDay[$role->getValue('rol_weekday')-1].' ';
                                    }
                                    if(strlen($role->getValue('rol_start_time')) > 0)
                                    {
                                        echo $g_l10n->get('LST_PHR_FROM_TO', $role->getValue('rol_start_time', $g_preferences['system_time']), $role->getValue('rol_end_time', $g_preferences['system_time']));
                                    }
                                echo '</dd>
                            </dl>
                        </li>';
                    }
                    //Treffpunkt
                    if(strlen($role->getValue('rol_location')) > 0)
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('SYS_LOCATION').':</dt>
                                <dd>'.$role->getValue('rol_location').'</dd>
                            </dl>
                        </li>';
                    }
                    //Teinehmer
                    echo '
                    <li>
                        <dl>
                            <dt>'.$g_l10n->get('SYS_PARTICIPANTS').':</dt>
                            <dd>'.$row_lst['num_members'];
                                if($role->getValue('rol_max_members') > 0)
                                {
                                    echo $g_l10n->get('LST_PHR_MAX', $role->getValue('rol_max_members'));
                                }
                                if($active_role && $row_lst['num_former'] > 0)
                                {
                                    // Anzahl Ehemaliger anzeigen
                                    if($row_lst['num_former'] == 1)
                                    {
                                        $text_former = $g_l10n->get('SYS_FORMER');
                                    }
                                    else
                                    {
                                        $text_former = $g_l10n->get('SYS_FORMER_PL');
                                    }
                                    echo '&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row_lst['num_former'].' '.$text_former.'</a>) ';
                                }
                            echo '</dd>
                        </dl>
                    </li>';
        
                    //Leiter
                    if($row_lst['num_leader']>0)
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('SYS_LEADER').':</dt>
                                <dd>'.$row_lst['num_leader'].'</dd>
                            </dl>
                        </li>';
                    }
        
                    //Beitrag
                    if(strlen($role->getValue('rol_cost')) > 0)
                    {
                        echo '
                        <li>
                            <dl>
                                <dt>'.$g_l10n->get('SYS_CONTRIBUTION').':</dt>
                                <dd>'.$role->getValue('rol_cost').' '.$g_preferences['system_currency'].'</dd>
                            </dl>
                        </li>';
                    }

                    //Beitragszeitraum
                    if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                    {
                        echo'<li>
                            <dl>
                                <dt>'.$g_l10n->get('SYS_CONTRIBUTION_PERIOD').':</dt>
                                <dd>'.TableRoles::getRolCostPeriodDesc($role->getValue('rol_cost_period')).'</dd>
                            </dl>
                        </li>';
                    }

                echo '</ul>';
                $count_cat_entries++;
            }
            else
            {
                $num_roles--;
            }
        }
    }
}

if($count_cat_entries == 0)
{
    echo $g_l10n->get('LST_PHR_CATEGORY_NO_LISTS');
}
echo '</div></div>';

// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/lists/lists.php?category='. $_GET['category']. '&category-selection='. $_GET['category-selection']. '&active_role='.$active_role;
echo generatePagination($base_url, $num_roles, $roles_per_page, $_GET['start'], TRUE);

echo '</div>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>