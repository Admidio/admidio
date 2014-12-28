<?php
/******************************************************************************
 * Search for existing user names and show users with similar names
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * new_user_id : ID of user who should be assigned
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getNewUserId = admFuncVariableIsValid($_GET, 'new_user_id', 'numeric', array('requireValue' => true));

// nur Webmaster duerfen User zuordnen, ansonsten Seite verlassen
if($gCurrentUser->approveUsers() == false)
{
   $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// set headline of the script
$headline = $gL10n->get('NWU_ASSIGN_REGISTRATION');

// create user object for new user
$new_user = new User($gDb, $gProfileFields, $getNewUserId);

// search for users with similar names (SQL function SOUNDEX only available in MySQL)
if($gPreferences['system_search_similar'] == 1 && $gDbType == 'mysql')
{
    $sql_similar_name = 
    '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $new_user->getValue('LAST_NAME').'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $new_user->getValue('FIRST_NAME').'\'), 1, 4) )
     OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $new_user->getValue('FIRST_NAME').'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $new_user->getValue('LAST_NAME').'\'), 1, 4) ) )';
}
else
{
    $sql_similar_name = 
    '(  (   last_name.usd_value  LIKE \''. $new_user->getValue('LAST_NAME').'\'
        AND first_name.usd_value LIKE \''. $new_user->getValue('FIRST_NAME').'\')
     OR (   last_name.usd_value  LIKE \''. $new_user->getValue('FIRST_NAME').'\'
        AND first_name.usd_value LIKE \''. $new_user->getValue('LAST_NAME').'\') )';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_login_name, last_name.usd_value as last_name, 
               first_name.usd_value as first_name, address.usd_value as address,
               zip_code.usd_value as zip_code, city.usd_value as city,
               email.usd_value as email
          FROM '. TBL_USERS. '
         RIGHT JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
         RIGHT JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 1 
           AND '.$sql_similar_name;
$result_usr   = $gDb->query($sql);
$member_found = $gDb->num_rows($result_usr);

// if current user can edit profiles than create link to profile otherwise create link to auto assign new registration
if($gCurrentUser->editUsers())
{
	$urlCreateNewUser = $g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=3&user_id='.$getNewUserId;
}
else
{
	$urlCreateNewUser = $g_root_path.'/adm_program/modules/registration/registration_function.php?mode=5&new_user_id='.$getNewUserId;
}

if($member_found == 0)
{
    // if user doesn't exists than show profile or auto assign roles
	header('Location: '.$urlCreateNewUser);
	exit();
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage();

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$registrationAssignMenu = new HtmlNavbar('menu_registration_assign', $headline, $page);
$registrationAssignMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($registrationAssignMenu->show(false));

$page->addHtml('<p class="lead">'.$gL10n->get('SYS_SIMILAR_USERS_FOUND', $new_user->getValue('FIRST_NAME'). ' '. $new_user->getValue('LAST_NAME')).'</p>

<div class="panel panel-default">
    <div class="panel-heading">'.$gL10n->get('SYS_USERS_FOUND').'</div>
    <div class="panel-body">');

        // show all found users with their address who have a similar name and show link for further handling
        $i = 0;
        while($row = $gDb->fetch_object($result_usr))
        {
            if($i > 0)
            {
                $page->addHtml('<hr />');
            }
            $page->addHtml('<div style="margin-left: 20px;">
				<a class="icon-text-link" href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row->usr_id.'"><img 
                     src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_SHOW_PROFILE').'" />'.$row->first_name.' '.$row->last_name.'</a><br />');
                if(strlen($row->address) > 0)
                {
                    $page->addHtml($row->address.'<br />');
                }
                if(strlen($row->zip_code) > 0 || strlen($row->city) > 0)
                {
                    $page->addHtml($row->zip_code.' '.$row->city.'<br />');
                }
                if(strlen($row->email) > 0)
                {
                    if($gPreferences['enable_mail_module'] == 1)
                    {
                        $page->addHtml('<a href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?usr_id='.$row->usr_id.'">'.$row->email.'</a><br />');
                    }
                    else
                    {
                        $page->addHtml('<a href="mailto:'.$row->email.'">'.$row->email.'</a><br />');
                    }
                }

                if(isMember($row->usr_id))
                {
                    // gefundene User ist bereits Mitglied dieser Organisation
                    if(strlen($row->usr_login_name) > 0)
                    {
                        // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken                    
                        $page->addHtml('<br />'.$gL10n->get('NWU_USER_VALID_LOGIN'));
                        if($gPreferences['enable_system_mails'] == 1)
                        {
                            $page->addHtml('<br />'.$gL10n->get('NWU_REMINDER_SEND_LOGIN').'<br />

                            <a class="icon-text-link" href="'.$g_root_path.'/adm_program/modules/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=6"><img
                                src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('NWU_SEND_LOGIN').'" />'.$gL10n->get('NWU_SEND_LOGIN').'</a>');
                        }
                    }
                    else
                    {
                        // Logindaten sind NICHT vorhanden -> diese nun zuordnen
                        $page->addHtml('<br />'.$gL10n->get('NWU_USER_NO_VALID_LOGIN').'<br />

                        <a class="icon-text-link" href="'.$g_root_path.'/adm_program/modules/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=1"><img
                            src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_LOGIN').'" />'.$gL10n->get('NWU_ASSIGN_LOGIN').'</a>');
                    }
                }
                else
                {
                    // gefundene User ist noch KEIN Mitglied dieser Organisation
                    $link = $g_root_path.'/adm_program/modules/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=2';

                    if(strlen($row->usr_login_name) > 0)
                    {
                        // Logindaten sind bereits vorhanden
                        $page->addHtml('<br />'.$gL10n->get('NWU_NO_MEMBERSHIP', $gCurrentOrganization->getValue('org_shortname')).'<br />

                        <a class="icon-text-link" href="'.$link.'"><img src="'.THEME_PATH.'/icons/new_registrations.png" 
                            alt="'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'" />'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'</a>');
                    }               
                    else
                    {
                        // KEINE Logindaten vorhanden
                        $page->addHtml('<br />'.$gL10n->get('NWU_NO_MEMBERSHIP_NO_LOGIN', $gCurrentOrganization->getValue('org_shortname')).'<br />
                        
                        <a class="icon-text-link" href="'.$link.'"><img src="'. THEME_PATH. '/icons/new_registrations.png" 
                            alt="'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP').'" />'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP').'</a>');
                    }               
                }
            $page->addHtml('</div>');
            $i++;
        }
    $page->addHtml('</div>
</div>
<div class="panel panel-default">
    <div class="panel-heading">'.$gL10n->get('SYS_CREATE_NEW_USER').'</div>
    <div class="panel-body">
        <div style="margin-left: 20px;">
            '. $gL10n->get('SYS_CREATE_NOT_FOUND_USER'). '<br />
            
            <a class="icon-text-link" href="'.$urlCreateNewUser.'"><img 
				src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_NEW_USER').'" />'.$gL10n->get('SYS_CREATE_NEW_USER').'</a>
        </div>
    </div>
</div>');

$page->show();

?>