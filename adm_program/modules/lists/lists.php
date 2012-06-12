<?php
/******************************************************************************
 * Show a list of all list roles
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start    : Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * cat_id   : show only roles of this category id, if id is not set than show all roles
 * category-selection: 1 - (Default) Anzeige der Combobox mit den verschiedenen Rollen-Kategorien
 *                     0 - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_category.php');
require_once('../../system/classes/table_roles.php');
unset($_SESSION['mylist_request']);

// Initialize and check the parameters
$getStart      = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getCatId      = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getCategorySelection = admFuncVariableIsValid($_GET, 'category-selection', 'boolean', 1);
$getActiveRole = admFuncVariableIsValid($_GET, 'active_role', 'boolean', 1);

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Listen-SQL-Statement zusammensetzen
if($getActiveRole == 1)
{
    $sql_member_status = ' AND mem_begin <= \''.DATE_NOW.'\'
                           AND mem_end   >= \''.DATE_NOW.'\' ';
}
else
{
    $sql_member_status = ' AND mem_end < \''.DATE_NOW.'\' ';
}

$sql = 'SELECT rol.*, cat.*, 
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id '.$sql_member_status.' AND mem_leader = 0) as num_members,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id '.$sql_member_status.' AND mem_leader = 1) as num_leader,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem WHERE mem.mem_rol_id = rol.rol_id AND mem_end < \''. DATE_NOW.'\') as num_former
          FROM '. TBL_ROLES. ' rol, '. TBL_CATEGORIES. ' cat
         WHERE rol_valid   = '.$getActiveRole.'
           AND rol_visible = 1
           AND rol_cat_id = cat_id 
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL ) ';
if($gValidLogin == false)
{
    $sql .= ' AND cat_hidden = 0 ';
}
if($getCatId > 0)
{
    // if category is set then only show roles of this category
    $sql .= ' AND cat_id  = '.$getCatId;
}
$sql .= ' ORDER BY cat_sequence, rol_name ';

$result_lst = $gDb->query($sql);
$num_roles  = $gDb->num_rows();

if($num_roles == 0)
{
    if($gValidLogin == true)
    {
        // wenn User eingeloggt, dann Meldung, falls doch keine Rollen zur Verfuegung stehen
        if($getActiveRole == 0)
        {
            $gMessage->show($gL10n->get('LST_NO_ROLES_REMOVED'));
        }
        else
        {
            $gMessage->show($gL10n->get('LST_NO_RIGHTS_VIEW_LIST'));
        }
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require_once('../../system/login_valid.php');
    }
}

// Html-Kopf ausgeben
if($getActiveRole)
{
    $gLayout['title']  = $gL10n->get('LST_ACTIVE_ROLES');
}
else
{
    $gLayout['title']  = $gL10n->get('LST_INACTIVE_ROLES');
}

if($getCatId > 0)
{
    $category = new TableCategory($gDb, $getCatId);
    $gLayout['title'] .= ' - '.$category->getValue('cat_name');
}

$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#admCategory").change(function () {
                var categoryId = document.getElementById("admCategory").value;
                self.location.href = "lists.php?cat_id=" + categoryId + "&category-selection='. $getCategorySelection. '&active_role='.$getActiveRole.'";
            });
        });

        function showList(element, rol_id)
        {
            var lst_id = element.value;

            if(lst_id == "mylist")
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?rol_id=" + rol_id + "&active_role='.$getActiveRole.'";
            }
            else
            {
                self.location.href = gRootPath + "/adm_program/modules/lists/lists_show.php?mode=html&lst_id=" + lst_id + "&rol_id=" + rol_id;
            }
        }
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '<div id="lists_overview">
<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';

// Kategorienauswahlbox soll angezeigt werden oder der User darf neue Rollen anlegen
if($getCategorySelection == 1 || $gCurrentUser->assignRoles())
{
    echo '<ul class="iconTextLinkList">';
    //Neue Termine anlegen
    if($gCurrentUser->editDates())
    {
        echo '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
                    src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_ROLE').'" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">'.$gL10n->get('SYS_CREATE_ROLE').'</a>
            </span>
        </li>';
    }

    if($getCategorySelection == 1)
    {
        // create select box with all categories that have roles
        $calendarSelectBox = FormElements::generateCategorySelectBox('ROL', $getCatId, 'admCategory', $gL10n->get('SYS_ALL'), true);
        
        if(strlen($calendarSelectBox) > 0)
        {
            // show category select box with link to calendar preferences
            echo '<li>'.$gL10n->get('SYS_CATEGORY').':&nbsp;&nbsp;'.$calendarSelectBox;

                if($gCurrentUser->assignRoles())
                {
                    echo '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
                     src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" title="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>';
                }
            echo '</li>';
        }            
        elseif($gCurrentUser->assignRoles())
        {
            // show link to calendar preferences
            echo '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL">'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
            </span></li>';
        }    
    }
    echo '</ul>';
}

$previous_cat_id   = 0;
$count_cat_entries = 0;
// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$gDb->data_seek($result_lst, $getStart))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// SQL-Statement fuer alle Listenkonfigurationen vorbereiten, die angezeigt werdne sollen
$sql = 'SELECT lst_id, lst_name, lst_global FROM '. TBL_LISTS. '
     WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id'). '
       AND (  lst_usr_id = '. $gCurrentUser->getValue('usr_id'). '
           OR lst_global = 1)
       AND lst_name IS NOT NULL
     ORDER BY lst_global ASC, lst_name ASC';
$result_config = $gDb->query($sql);

// Anzahl Rollen pro Seite
if($gPreferences['lists_roles_per_page'] > 0)
{
    $roles_per_page = $gPreferences['lists_roles_per_page'];
}
else
{
    $roles_per_page = $num_roles;
}

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

for($i = 0; $i < $roles_per_page && $i + $getStart < $num_roles; $i++)
{
    if($row_lst = $gDb->fetch_array($result_lst))
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
                        echo $gL10n->get('LST_CATEGORY_NO_LISTS');
                    }
                    echo '</div></div><br />';
                }
                echo '<div class="formLayout">
                    <div class="formHead">'. $role->getValue('cat_name'). '</div>
                    <div class="formBody">';
                $previous_cat_id = $role->getValue('cat_id');
                $count_cat_entries = 0;
            }

			if($count_cat_entries > 0)
			{
				echo '<hr />';
			}
			echo '
			<div>
				<div style="float: left;">';
					//Dreieck zum ein und ausblenden der Details
					if($gPreferences['lists_hide_overview_details']==1)
					{
						$icon = THEME_PATH. '/icons/triangle_close.gif';
						$iconText = $gL10n->get('SYS_FADE_IN');
					}
					else
					{
						$icon = THEME_PATH. '/icons/triangle_open.gif';
						$iconText = $gL10n->get('SYS_HIDE');
					}
					echo '<a class="iconLink" href="javascript:showHideBlock(\'admRoleDetails'.$role->getValue('rol_id').'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')">
						<img id="admRoleDetails'.$role->getValue('rol_id').'Image"  src="'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>';

					// show link if user is allowed to see members and the role has members
					if($gCurrentUser->viewRole($role->getValue('rol_id'))
					&& ($row_lst['num_members'] > 0 || $row_lst['num_leader'] > 0))
					{
						echo '<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '">'. $role->getValue('rol_name'). '</a>';
					}
					else
					{
						echo '<strong>'. $role->getValue('rol_name'). '</strong>';
					}
	
					//Mail an Rolle schicken
					if($gCurrentUser->mailRole($role->getValue('rol_id')) && $gPreferences['enable_mail_module'] == 1)
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$role->getValue('rol_id').'"><img
							src="'. THEME_PATH. '/icons/email.png"  alt="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" title="'.$gL10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>';
					}

					// edit roles of you are allowed to assign roles
					if($gCurrentUser->assignRoles())
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'"><img
							src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('SYS_SETTINGS').'" title="'.$gL10n->get('SYS_SETTINGS').'" /></a>';
					}

					// link to assign or remove members if you are allowed to do it
					if($role->allowedToAssignMembers($gCurrentUser))
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img 
							src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
					}
				echo '</div>
				<div style="text-align: right;">';
					// show combobox with lists if user is allowed to see members and the role has members
					if($gCurrentUser->viewRole($role->getValue('rol_id'))
					&& ($row_lst['num_members'] > 0 || $row_lst['num_leader'] > 0))
					{
						echo '
						<select size="1" name="list'.$i.'" onchange="showList(this, '. $role->getValue('rol_id'). ')">
							<option value="" selected="selected">'.$gL10n->get('LST_SHOW_LISTS').' ...</option>';
							
							// alle globalen Listenkonfigurationen auflisten
							$gDb->data_seek($result_config, 0);
							$list_global_flag = '';
							
							while($row = $gDb->fetch_array($result_config))
							{
								if($list_global_flag != $row['lst_global'])
								{
									if($row['lst_global'] == 0)
									{
										echo '<optgroup label="'.$gL10n->get('LST_YOUR_LISTS').'">';
									}
									else
									{
										if($list_global_flag > 0)
										{
											echo '</optgroup>';
										}
										echo '<optgroup label="'.$gL10n->get('LST_GENERAL_LISTS').'">';
									}
									$list_global_flag = $row['lst_global'];
								}
								echo '<option value="'.$row['lst_id'].'">'.$row['lst_name'].'</option>';
							}
							
							// Link zu den eigenen Listen setzen
							echo '</optgroup>
							<optgroup label="'.$gL10n->get('LST_CONFIGURATION').'">
								<option value="mylist">'.$gL10n->get('LST_CREATE_OWN_LIST').'</option>
							</optgroup>
						</select>';
					}
					else
					{
						echo '&nbsp;';
					}
				echo '</div>
			</div>
			
			<ul id="admRoleDetails'.$role->getValue('rol_id').'" ';
				if($gPreferences['lists_hide_overview_details']==1)
				{
					echo ' style="display: none;" '; 
				}
				echo ' class="formFieldList">';
				if(strlen($role->getValue('rol_description')) > 0)
				{
					echo '
					<li>
						<dl>
							<dt>'.$gL10n->get('SYS_DESCRIPTION').':</dt>
							<dd>'.$role->getValue('rol_description').'</dd>
						</dl>
					</li>';
				}
	
				if(strlen($role->getValue('rol_start_date')) > 0)
				{
					echo '
					<li>
						<dl>
							<dt>'.$gL10n->get('SYS_PERIOD').':</dt>
							<dd>'.$gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])).'</dd>
						</dl>
					</li>';
				}
				if($role->getValue('rol_weekday') > 0
				|| strlen($role->getValue('rol_start_time')) > 0 )
				{
					echo '
					<li>
						<dl>
							<dt>'.$gL10n->get('DAT_DATE').':</dt>
							<dd>'; 
								if($role->getValue('rol_weekday') > 0)
								{
									echo TableRoles::getWeekdayDesc($role->getValue('rol_weekday')).' ';
								}
								if(strlen($role->getValue('rol_start_time')) > 0)
								{
									echo $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
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
							<dt>'.$gL10n->get('SYS_LOCATION').':</dt>
							<dd>'.$role->getValue('rol_location').'</dd>
						</dl>
					</li>';
				}
				//Teinehmer
				echo '
				<li>
					<dl>
						<dt>'.$gL10n->get('SYS_PARTICIPANTS').':</dt>
						<dd>'.$row_lst['num_members'];
							if($role->getValue('rol_max_members') > 0)
							{
								echo '&nbsp;'.$gL10n->get('LST_MAX', $role->getValue('rol_max_members'));
							}
							if($getActiveRole && $row_lst['num_former'] > 0)
							{
								// Anzahl Ehemaliger anzeigen
								if($row_lst['num_former'] == 1)
								{
									$text_former = $gL10n->get('SYS_FORMER');
								}
								else
								{
									$text_former = $gL10n->get('SYS_FORMER_PL');
								}
								
								// if user is allowed to see members then show link to former members
								if($gCurrentUser->viewRole($role->getValue('rol_id')))
								{
									echo '&nbsp;&nbsp;(<a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $role->getValue('rol_id'). '&amp;show_members=1">'.$row_lst['num_former'].' '.$text_former.'</a>) ';
								}
								else
								{
									echo '&nbsp;&nbsp;('.$row_lst['num_former'].' '.$text_former.') ';
								}
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
							<dt>'.$gL10n->get('SYS_LEADER').':</dt>
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
							<dt>'.$gL10n->get('SYS_CONTRIBUTION').':</dt>
							<dd>'.$role->getValue('rol_cost').' '.$gPreferences['system_currency'].'</dd>
						</dl>
					</li>';
				}

				//Beitragszeitraum
				if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
				{
					echo'<li>
						<dl>
							<dt>'.$gL10n->get('SYS_CONTRIBUTION_PERIOD').':</dt>
							<dd>'.TableRoles::getCostPeriodDesc($role->getValue('rol_cost_period')).'</dd>
						</dl>
					</li>';
				}

			echo '</ul>';
			$count_cat_entries++;
        }
    }
}

if($count_cat_entries == 0)
{
    echo $gL10n->get('LST_CATEGORY_NO_LISTS');
}
echo '</div></div>';

// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/lists/lists.php?cat_id='. $getCatId. '&category-selection='. $getCategorySelection. '&active_role='.$getActiveRole;
echo admFuncGeneratePagination($base_url, $num_roles, $roles_per_page, $getStart, TRUE);

echo '</div>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>