<?php
/******************************************************************************
 * Show list with members of a role
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * rol_id       : Rolle der Mitglieder hinzugefuegt oder entfernt werden sollen
 * mem_show_all : 0 - (Default) nur Mitglieder der Organisation anzeigen
 *                1 - alle Benutzer aus der Datenbank anzeigen
 * mem_search   : Suchstring nach dem Mitglieder angezeigt werden sollen
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');

$gMessage->setExcludeThemeBody();

// Initialize and check the parameters
$getRoleId          = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', null, true);
$postMembersShowAll = admFuncVariableIsValid($_POST, 'mem_show_all', 'string', 'off');
$postMembersSearch  = admFuncVariableIsValid($_POST, 'mem_search', 'string');

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($gDb, $getRoleId);

// roles of other organizations can't be edited
if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check if user is allowed to assign members to this role
if($role->allowedToAssignMembers($gCurrentUser) == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$memberCondition = '';
$limit = '';

if($postMembersShowAll == 'on')
{
    // Falls gefordert, aufrufen alle Benutzer aus der Datenbank
    $memberCondition = ' usr_valid = 1 ';
}
else
{
    // Falls gefordert, nur Aufruf von aktiven Mitgliedern der Organisation
    $memberCondition = ' EXISTS 
        (SELECT 1
           FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
          WHERE mem_usr_id = usr_id
            AND mem_rol_id = rol_id
            AND mem_begin <= \''.DATE_NOW.'\'
            AND mem_end    > \''.DATE_NOW.'\'
            AND rol_valid  = 1
            AND rol_cat_id = cat_id
            AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                OR cat_org_id IS NULL )) ';
}

//Suchstring zerlegen
if(strlen($postMembersSearch) > 0)
{
    $postMembersSearch = str_replace('%', ' ', $postMembersSearch);
    $search_therms = explode(' ', $postMembersSearch);
    
    if(count($search_therms)>0)
    {
    	//in Condition einbinden
	    foreach($search_therms as $search_therm)
	    {
	    	$memberCondition .= ' AND (  (UPPER(last_name.usd_value)  LIKE UPPER(\''.$search_therm.'%\')) 
									   OR (UPPER(first_name.usd_value) LIKE UPPER(\''.$search_therm.'%\'))) ';
	    }
    }
    //Ergebnissmenge Limitieren
    $limit .= ' LIMIT 30 ';
}


 // SQL-Statement zusammensetzen
$sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, birthday.usd_value as birthday,
               city.usd_value as city, address.usd_value as address, zip_code.usd_value as zip_code, country.usd_value as country,
               mem_usr_id as member_this_role, mem_leader as leader_this_role,
                  (SELECT count(*)
                     FROM '. TBL_ROLES. ' rol2, '. TBL_CATEGORIES. ' cat2, '. TBL_MEMBERS. ' mem2
                    WHERE rol2.rol_valid   = 1
                      AND rol2.rol_cat_id  = cat2.cat_id
                      AND (  cat2.cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                          OR cat2.cat_org_id IS NULL )
                      AND mem2.mem_rol_id  = rol2.rol_id
                      AND mem2.mem_begin  <= \''.DATE_NOW.'\'
                      AND mem2.mem_end     > \''.DATE_NOW.'\'
                      AND mem2.mem_usr_id  = usr_id) as member_this_orga
        FROM '. TBL_USERS. '
        LEFT JOIN '. TBL_USER_DATA. ' as last_name
          ON last_name.usd_usr_id = usr_id
         AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as first_name
          ON first_name.usd_usr_id = usr_id
         AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as birthday
          ON birthday.usd_usr_id = usr_id
         AND birthday.usd_usf_id = '. $gProfileFields->getProperty('BIRTHDAY', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as city
          ON city.usd_usr_id = usr_id
         AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as address
          ON address.usd_usr_id = usr_id
         AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as zip_code
          ON zip_code.usd_usr_id = usr_id
         AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
        LEFT JOIN '. TBL_USER_DATA. ' as country
          ON country.usd_usr_id = usr_id
         AND country.usd_usf_id = '. $gProfileFields->getProperty('COUNTRY', 'usf_id'). '
        LEFT JOIN '. TBL_ROLES. ' rol
          ON rol.rol_valid   = 1
         AND rol.rol_id      = '.$getRoleId.'
        LEFT JOIN '. TBL_MEMBERS. ' mem
          ON mem.mem_rol_id  = rol.rol_id
         AND mem.mem_begin  <= \''.DATE_NOW.'\'
         AND mem.mem_end     > \''.DATE_NOW.'\'
         AND mem.mem_usr_id  = usr_id
        WHERE '. $memberCondition. '
        ORDER BY last_name, first_name '.$limit;
$resultUser = $gDb->query($sql);

if($gDb->num_rows($resultUser)>0)
{
    //Buchstaben Navigation bei mehr als 50 personen
    if($gDb->num_rows($resultUser) >= 50)
    {
        echo '<div class="pageNavigation">
            <a href="#" letter="all" class="pageNavigationLink">'.$gL10n->get('SYS_ALL').'</a>&nbsp;&nbsp;';
        
            // Nun alle Buchstaben mit evtl. vorhandenen Links im Buchstabenmenue anzeigen
            $letterMenu = 'A';
            
            for($i = 0; $i < 26;$i++)
            {
                // pruefen, ob es Mitglieder zum Buchstaben gibt
                // dieses SQL muss fuer jeden Buchstaben ausgefuehrt werden, ansonsten werden Sonderzeichen nicht immer richtig eingeordnet
                $sql = 'SELECT COUNT(1) as count
                          FROM '. TBL_USERS. ', '. TBL_USER_FIELDS. ', '. TBL_USER_DATA. '
                         WHERE usr_valid  = 1
                           AND usf_name_intern = \'LAST_NAME\'
                           AND usd_usf_id = usf_id
                           AND usd_usr_id = usr_id
                           AND usd_value LIKE \''.$letterMenu.'%\'
                           AND '.$memberCondition.'
                         GROUP BY UPPER(SUBSTRING(usd_value, 1, 1))';
                $result      = $gDb->query($sql);
                $letterRow  = $gDb->fetch_array($result);

                if($letterRow['count'] > 0)
                {
                    echo '<a href="#" letter="'.$letterMenu.'" class="pageNavigationLink">'.$letterMenu.'</a>';
                }
                else
                {
                    echo $letterMenu;
                }
        
                echo '&nbsp;&nbsp;';
        
                $letterMenu = strNextLetter($letterMenu);
            }
        echo '</div>';    
    }
    
    // create table header
    echo '
    <table class="tableList" cellspacing="0">
        <thead>
            <tr>
                <th><img class="iconInformation"
                    src="'. THEME_PATH. '/icons/profile.png" alt="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'"
                    title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'" /></th>
                <th style="text-align: center;">'.$gL10n->get('SYS_MEMBER').'</th>
                <th>'.$gL10n->get('SYS_LASTNAME').'</th>
                <th>'.$gL10n->get('SYS_FIRSTNAME').'</th>
                <th><img class="iconInformation" src="'. THEME_PATH. '/icons/map.png" 
                    alt="'.$gL10n->get('SYS_ADDRESS').'" title="'.$gL10n->get('SYS_ADDRESS').'" /></th>
                <th>'.$gL10n->get('SYS_BIRTHDAY').'</th>
                <th style="text-align: center;">'.$gL10n->get('SYS_LEADER');

					// show icon that leaders have no additional rights
					if($role->getValue('rol_leader_rights') == ROLE_LEADER_NO_RIGHTS)
					{
						echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/info.png"
						alt="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" title="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" />';
					}

					// show icon with edit user right if leader has this right
					if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_EDIT 
					|| $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
					{
						echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/profile_edit.png"
						alt="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" />';
					}

					// show icon with assign role right if leader has this right
					if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN 
					|| $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
					{
						echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/roles.png"
						alt="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" />';
					}
				echo '</th>
            </tr>
        </thead>';
        
    $letter_merker = '';
    $this_letter   = '';
    
    function convSpecialChar($specialChar)
    {
        $convTable = array('Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ö' => 'O', 'Ü' => 'U');
        
        if(array_key_exists($specialChar, $convTable))
        {
            return admstrtoupper($convTable[$specialChar]);
        }
        return $specialChar;
    }

    //Zeilen ausgeben
    while($user = $gDb->fetch_array($resultUser))
    {
    	if($gDb->num_rows($resultUser) >= 50)
    	{
            // Buchstaben auslesen
            $this_letter = admstrtoupper(substr($user['last_name'], 0, 1));
            
            if(ord($this_letter) < 65 || ord($this_letter) > 90)
            {
                $this_letter = convSpecialChar(substr($user['last_name'], 0, 2));
            }
            
            if($this_letter != $letter_merker)
            {
                if(mb_strlen($letter_merker) > 0)
                {
                    echo '</tbody>';
                }

                // Ueberschrift fuer neuen Buchstaben
                echo '<tbody block_head_id="'.$this_letter.'" class="letterBlockHead">
                    <tr>
                        <td class="tableSubHeader" colspan="7">
                            '.$this_letter.'
                        </td>
                    </tr>
                </tbody>
                <tbody block_body_id="'.$this_letter.'" class="letterBlockBody">';

                // aktuellen Buchstaben merken
                $letter_merker = $this_letter;
            }
        }

        //Datensatz ausgeben
        $user_text = '';
        if(strlen($user['address']) > 0)
        {
            $user_text = $user['address'];
        }
        if(strlen($user['zip_code']) > 0 || strlen($user['city']) > 0)
        {
            $user_text = $user_text. ' - '. $user['zip_code']. ' '. $user['city'];
        }
        if(strlen($user['country']) > 0)
        {
            $user_text = $user_text. ' - '. $user['country'];
        }

        // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
        if($user['member_this_orga'] > 0)
        {
            $icon = 'profile.png';
            $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
        }
        else
        {
            $icon = 'no_profile.png';
            $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
        }

        echo '
        <tr class="tableMouseOver" user_id="'.$user['usr_id'].'">
            <td><img class="iconInformation" src="'. THEME_PATH.'/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></td>

            <td style="text-align: center;">';
                //Haekchen setzen ob jemand Mitglied ist oder nicht
                if($user['member_this_role'])
                {
                    echo '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox" checkboxtype="member" />';
                }
                else
                {
                    echo '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" class="memlist_checkbox" checkboxtype="member"/>';
                }
            echo '<b id="loadindicator_member_'.$user['usr_id'].'"></b></td>
            <td>'.$user['last_name'].'</td>
            <td>'.$user['first_name'].'</td>
            <td>';
                if(strlen($user_text) > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH.'/icons/map.png" alt="'.$user_text.'" title="'.$user_text.'" />';
                }
                else
                {
                    echo '&nbsp';
                }
            echo '</td>
            <td>';
                //Geburtstag nur ausgeben wenn bekannt
                if(strlen($user['birthday']) > 0)
                {
                    $birthdayDate = new DateTimeExtended($user['birthday'], 'Y-m-d', 'date');
                    echo $birthdayDate->format($gPreferences['system_date']);
                }
            echo '</td>

            <td style="text-align: center;">';
                //Haekchen setzen ob jemand Leiter ist oder nicht
                if($user['leader_this_role'])
                {
                    echo '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox" checkboxtype="leader"/>';
                }
                else
                {
                    echo '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" class="memlist_checkbox" checkboxtype="leader" />';
                }
            echo '<b id="loadindicator_leader_'.$user['usr_id'].'"></b>
        </tr>';
    }//End While

    echo '</table>
    <p>'.$gL10n->get('SYS_CHECKBOX_AUTOSAVE').'</p>';
    
    //Hilfe nachladen
    echo '<script type="text/javascript">$("a[rel=\'colorboxHelp\']").colorbox({preloading:true,photo:false,speed:300,rel:\'nofollow\'})</script>';
}
else
{
	echo '<p>'.$gL10n->get('SYS_NO_ENTRIES_FOUND').'</p>';
}
?>