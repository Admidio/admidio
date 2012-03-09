<?php
/******************************************************************************
 * Verwaltung der aller Mitglieder in der Datenbank
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * members - 1 : (Default) Nur Mitglieder der Gliedgemeinschaft anzeigen
 *           0 : Mitglieder, Ehemalige, Mitglieder anderer Gliedgemeinschaften
 * letter      : alle User deren Nachnamen mit dem Buchstaben beginnt, werden angezeigt
 * start       : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen
 * search      : Inhalt des Suchfeldes, damit dieser beim Blaettern weiter genutzt werden kann
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
unset($_SESSION['import_request']);

// wurde der Suchstring ueber das Formular als POST uebergeben, dann an GET weiterleiten
if (isset($_POST['admSearchMembers']) && strlen($_POST['admSearchMembers']) > 0)
{
    $_GET['search'] = $_POST['admSearchMembers'];
}

// Initialize and check the parameters
$getLetter  = admFuncVariableIsValid($_GET, 'letter', 'string', '');
$getMembers = admFuncVariableIsValid($_GET, 'members', 'boolean', 1);
$getStart   = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getSearch  = admFuncVariableIsValid($_GET, 'search', 'string', '');

if(strlen($getLetter) > 1)
{
	$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen initialisieren
$members_per_page = 25; // Anzahl der Mitglieder, die auf einer Seite angezeigt werden

// Die zum Caching in der Session zwischengespeicherten Namen werden beim
// neu laden der Seite immer abgeraeumt...
unset($_SESSION['QuerySuggestions']);

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);


// Bedingungen fuer das SQL-Statement je nach Modus setzen
if(strlen($getSearch) > 0)
{
    // Bedingung fuer die Suchanfrage
    $search_string = str_replace(',', '', $getSearch). '%';
    $search_condition = ' AND (  last_name.usd_value  || \' \' || first_name.usd_value LIKE \''.$search_string.'\'
                              OR first_name.usd_value || \' \' || last_name.usd_value  LIKE \''.$search_string.'\' ) ';
}
else
{
    $search_condition = ' AND last_name.usd_value LIKE \''.$getLetter.'%\' ';
}

$member_condition = '';

if($getMembers == 1)
{
    $member_condition = ' AND EXISTS 
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

// Anzahl relevanter Datensaetze ermitteln
$sql = 'SELECT COUNT(1) as count
		  FROM '. TBL_USERS. '
          JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
          JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
         WHERE usr_valid = 1
               '.$member_condition.
                 $search_condition;
$result = $gDb->query($sql);
$row    = $gDb->fetch_array($result);
$num_members = $row['count'];

// alle Mitglieder zur Auswahl selektieren
// unbestaetigte User werden dabei nicht angezeigt
$sql    = 'SELECT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name,
                  email.usd_value as email, website.usd_value as website,
                  usr_login_name, COALESCE(usr_timestamp_change, usr_timestamp_create) as timestamp,
                  (SELECT count(*)
                     FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. '
                    WHERE rol_valid   = 1
                      AND rol_cat_id  = cat_id
                      AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                          OR cat_org_id IS NULL )
                      AND mem_rol_id  = rol_id
                      AND mem_begin  <= \''.DATE_NOW.'\'
                      AND mem_end     > \''.DATE_NOW.'\'
                      AND mem_usr_id  = usr_id) as member_this_orga,
                  (SELECT count(*)
                     FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. '
                    WHERE rol_valid   = 1
                      AND rol_cat_id  = cat_id
                      AND cat_org_id <> '. $gCurrentOrganization->getValue('org_id'). '
                      AND mem_rol_id  = rol_id
                      AND mem_begin  <= \''.DATE_NOW.'\'
                      AND mem_end     > \''.DATE_NOW.'\'
                      AND mem_usr_id  = usr_id) as member_other_orga
             FROM '. TBL_USERS. '
             JOIN '. TBL_USER_DATA. ' as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
             JOIN '. TBL_USER_DATA. ' as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as website
               ON website.usd_usr_id = usr_id
              AND website.usd_usf_id = '. $gProfileFields->getProperty('WEBSITE', 'usf_id'). '
            WHERE usr_valid = 1
                  '.$member_condition.
                    $search_condition.'
            ORDER BY last_name.usd_value, first_name.usd_value 
			LIMIT '.$members_per_page.' OFFSET '.$getStart;
$result_mgl  = $gDb->query($sql);

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('MEM_USER_MANAGEMENT');
$gLayout['header'] = ' 
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.Ajax.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.DOM.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.AutoSuggest.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/tooltip/text_tooltip.js"></script>
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("a[rel=\'lnkNewUser\']").colorbox({rel:\'nofollow\',onComplete:function(){$("#lastname").focus();}});
            
            var options = {
                        script:"'.$g_root_path.'/adm_program/administration/members/query_suggestions.php?members='.$getMembers.'&",
                        varname:"search",
                        minchars:1,
                        timeout:5000
            };
            var as = new AutoSuggest("admSearchMembers", options);         
            
            //Checkbox alle Benutzer anzeigen
            $("input[type=checkbox]#mem_show_all").live("click", function(){
                window.location.href = $("#mem_show_all").attr("link");
            });
            
        }); 
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';

// Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
if($getMembers == 1)
{
    $link_members = 0;
    $show_all_checked = '';
    
}
else
{
    $link_members = 1;
    $show_all_checked = 'checked';
}


echo'
    <ul class="iconTextLinkList" style="margin-bottom: 0px;">
    <li>
        <span class="iconTextLink">
            <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php"><img
            src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('MEM_CREATE_USER').'" /></a>
            <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php">'.$gL10n->get('MEM_CREATE_USER').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/members/import.php"><img
            src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('MEM_IMPORT_USERS').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/members/import.php">'.$gL10n->get('MEM_IMPORT_USERS').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php"><img
            src="'. THEME_PATH. '/icons/application_form.png" alt="'.$gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS').'" /></a>
            <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php">'.$gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS').'</a>
        </span>
    </li>    
</ul>';

//Hier gibt es jetzt noch die Suchbox...
echo '
<form id="autosuggest" action="'.$g_root_path.'/adm_program/administration/members/members.php?members='.$getMembers.'" method="post">
    <ul id="search_members" class="iconTextLinkList">
        <li>
            <input type="text" value="'.$getSearch.'" name="admSearchMembers" id="admSearchMembers" style="width: 200px;"  />
            <input type="submit" value="'.$gL10n->get('SYS_SEARCH').'" />
        </li>
        <li>    
            <input type="checkbox" name="mem_show_all" id="mem_show_all" 
                link="'.$g_root_path.'/adm_program/administration/members/members.php?members='.$link_members.'&amp;letter='.$getLetter.'&amp;search='.$getSearch.'" title="'.$gL10n->get('MEM_SHOW_USERS').'" '.$show_all_checked.'/><label for="mem_show_all">'.$gL10n->get('MEM_SHOW_ALL_USERS').'</label>
        </li>
    </ul>
</form>

<div class="pageNavigation">';
    // Leiste mit allen Buchstaben des Alphabets anzeigen
    if (strlen($getLetter) == 0 && strlen($getSearch) == 0)
    {
        echo '<span class="selected">'.$gL10n->get('SYS_ALL').'</span>&nbsp;&nbsp;&nbsp;';
    }
    else
    {
        echo '<a href="'.$g_root_path.'/adm_program/administration/members/members.php?members='.$getMembers.'">'.$gL10n->get('SYS_ALL').'</a>&nbsp;&nbsp;&nbsp;';
    }

    // Nun alle Buchstaben mit evtl. vorhandenen Links im Buchstabenmenue anzeigen
    $letter_menu = 'A';
    
    for($i = 0; $i < 26;$i++)
    {
        // pruefen, ob es Mitglieder zum Buchstaben gibt
        // dieses SQL muss fuer jeden Buchstaben ausgefuehrt werden, ansonsten werden Sonderzeichen nicht immer richtig eingeordnet
        $sql = 'SELECT COUNT(1) as count
                  FROM '. TBL_USERS. ', '. TBL_USER_FIELDS. ', '. TBL_USER_DATA. '
                 WHERE usr_valid = 1
                   AND usf_name_intern = \'LAST_NAME\'
                   AND usd_usf_id = usf_id
                   AND usd_usr_id = usr_id
                   AND usd_value LIKE \''.$letter_menu.'%\'
                       '.$member_condition;
        $result      = $gDb->query($sql);
        $letter_row  = $gDb->fetch_array($result);

        if($letter_menu == substr($getLetter, 0, 1))
        {
            echo '<span class="selected">'.$letter_menu.'</span>';
        }
        elseif($letter_row['count'] > 0)
        {
            echo '<a href="'.$g_root_path.'/adm_program/administration/members/members.php?members='.$getMembers.'&amp;letter='.$letter_menu.'" title="'. $letter_row['count']. ' Benutzer gefunden">'.$letter_menu.'</a>';
        }
        else
        {
            echo $letter_menu;
        }

        echo '&nbsp;&nbsp;';

        $letter_menu = strNextLetter($letter_menu);
    }
echo '</div>';

if($num_members > 0)
{
    echo '<table class="tableList" cellspacing="0">
        <tr>
            <th>'.$gL10n->get('SYS_ABR_NO').'</th>
            <th><img class="iconInformation"
                src="'. THEME_PATH. '/icons/profile.png" alt="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'"
                title="'.$gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname')).'" /></th>
            <th>'.$gL10n->get('SYS_NAME').'</th>
            <th><img class="iconInformation"
                src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" /></th>
            <th><img class="iconInformation"
                src="'. THEME_PATH. '/icons/weblinks.png" alt="'.$gL10n->get('SYS_WEBSITE').'" title="'.$gL10n->get('SYS_WEBSITE').'" /></th>
            <th>'.$gL10n->get('SYS_USER').'</th>
            <th>'.$gL10n->get('MEM_UPDATED_ON').'</th>
            <th style="text-align: center;">'.$gL10n->get('SYS_FEATURES').'</th>
        </tr>';

		$irow = $getStart + 1;  // Zahler fuer die jeweilige Zeile

		while($row = $gDb->fetch_array($result_mgl))
		{
			$timestampChange = new DateTimeExtended($row['timestamp'], 'Y-m-d H:i:s');

			// Icon fuer Orgamitglied und Nichtmitglied auswaehlen
			if($row['member_this_orga'] > 0)
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
			<tr class="tableMouseOver">
				<td>'. $irow. '</td>
				<td><a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '"><img
							src="'. THEME_PATH. '/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>
				</td>
				<td><a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '">'. $row['last_name']. ',&nbsp;'. $row['first_name']. '</a></td>
				<td>';
					if(strlen($row['email']) > 0)
					{
						if($gPreferences['enable_mail_module'] != 1)
						{
							$mail_link = 'mailto:'. $row['email'];
						}
						else
						{
							$mail_link = $g_root_path.'/adm_program/modules/mail/mail.php?usr_id='. $row['usr_id'];
						}
						echo '
						<a class="iconLink" href="'.$mail_link.'"><img src="'. THEME_PATH. '/icons/email.png"
							alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'" /></a>';
					}
				echo '</td>
				<td>';
					if(strlen($row['website']) > 0)
					{
						echo '
						<a class="iconLink" href="'. $row['website']. '" target="_blank"><img
							src="'. THEME_PATH. '/icons/weblinks.png" alt="'. $row['website']. '" title="'. $row['website']. '" /></a>';
					}
				echo '</td>
				<td>'. $row['usr_login_name']. '</td>
				<td>'. $timestampChange->format($gPreferences['system_date'].' '.$gPreferences['system_time']). '</td>
				<td style="text-align: center;">';
					// Link um E-Mail mit neuem Passwort zu zuschicken
					// nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
					if($row['member_this_orga'] > 0
					&& $gCurrentUser->isWebmaster()
					&& strlen($row['usr_login_name']) > 0
					&& strlen($row['email']) > 0
					&& $gPreferences['enable_system_mails'] == 1
					&& $row['usr_id'] != $gCurrentUser->getValue('usr_id'))
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $row['usr_id']. '&amp;mode=5"><img
							src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" title="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" /></a>';
					}
					else
					{
						echo '&nbsp;<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
					}

					// Link um User zu editieren
					// es duerfen keine Nicht-Mitglieder editiert werden, die Mitglied in einer anderen Orga sind
					if($row['member_this_orga'] > 0 || $row['member_other_orga'] == 0)
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='. $row['usr_id']. '"><img
							src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('MEM_EDIT_USER').'" title="'.$gL10n->get('MEM_EDIT_USER').'" /></a>';
					}
					else
					{
						echo '&nbsp;<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
					}

					// Mitglieder entfernen
					if( (($row['member_other_orga'] == 0 && $gCurrentUser->isWebmaster()) // kein Mitglied einer anderen Orga, dann duerfen Webmaster loeschen
					  || $row['member_this_orga'] > 0)                              // aktive Mitglieder duerfen von berechtigten Usern entfernt werden
					&& $row['usr_id'] != $gCurrentUser->getValue('usr_id'))       // das eigene Profil darf keiner entfernen
					{
						echo '
						<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/members/members_function.php?usr_id='.$row['usr_id'].'&amp;mode=6"><img
							src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" title="'.$gL10n->get('MEM_REMOVE_USER').'" /></a>';
					}
					else
					{
						echo '&nbsp;<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
					}
				echo '</td>
			</tr>';
			$irow++;
		}
    echo '</table>';

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = $g_root_path.'/adm_program/administration/members/members.php?letter='.$getLetter.'&amp;members='.$getMembers.'&amp;search='.$getSearch;
    echo admFuncGeneratePagination($base_url, $num_members, $members_per_page, $getStart, true);
}
else
{
    echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>