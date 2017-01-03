<?php
/**
 ***********************************************************************************************
 * Search for existing user names and show users with similar names
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * new_user_id : ID of user who should be assigned
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getNewUserId = admFuncVariableIsValid($_GET, 'new_user_id', 'int', array('requireValue' => true));

// only administrators could approve new users
if(!$gCurrentUser->approveUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('NWU_ASSIGN_REGISTRATION');

// create user object for new user
$newUser = new User($gDb, $gProfileFields, $getNewUserId);

$lastName  = $gDb->escapeString($newUser->getValue('LAST_NAME', 'database'));
$firstName = $gDb->escapeString($newUser->getValue('FIRST_NAME', 'database'));

// search for users with similar names (SQL function SOUNDEX only available in MySQL)
if($gDbType === 'mysql' && $gPreferences['system_search_similar'] == 1)
{
    $sqlSimilarName =
        '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $lastName.'\'), 1, 4)
            AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $firstName.'\'), 1, 4) )
         OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $firstName.'\'), 1, 4)
            AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $lastName.'\'), 1, 4) ) )';
}
else
{
    $sqlSimilarName =
        '(  (   last_name.usd_value  LIKE \''. $lastName.'\'
            AND first_name.usd_value LIKE \''. $firstName.'\')
         OR (   last_name.usd_value  LIKE \''. $firstName.'\'
            AND first_name.usd_value LIKE \''. $lastName.'\') )';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_login_name, last_name.usd_value AS last_name,
               first_name.usd_value AS first_name, address.usd_value AS address,
               zip_code.usd_value AS zip_code, city.usd_value AS city,
               email.usd_value AS email
          FROM '.TBL_USERS.'
    RIGHT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
    RIGHT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 1
           AND '.$sqlSimilarName;
$usrStatement = $gDb->query($sql);

// if current user can edit profiles than create link to profile otherwise create link to auto assign new registration
if($gCurrentUser->editUsers())
{
    $urlCreateNewUser = ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php?new_user=3&user_id=' . $getNewUserId;
}
else
{
    $urlCreateNewUser = ADMIDIO_URL . FOLDER_MODULES.'/registration/registration_function.php?mode=5&new_user_id=' . $getNewUserId;
}

if($usrStatement->rowCount() === 0)
{
    // if user doesn't exists than show profile or auto assign roles
    admRedirect($urlCreateNewUser);
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$registrationAssignMenu = $page->getMenu();
$registrationAssignMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('
    <p class="lead">'.$gL10n->get('SYS_SIMILAR_USERS_FOUND', $newUser->getValue('FIRST_NAME'). ' '. $newUser->getValue('LAST_NAME')).'</p>
    <div class="panel panel-default">
        <div class="panel-heading">'.$gL10n->get('SYS_USERS_FOUND').'</div>
        <div class="panel-body">'
);

// show all found users with their address who have a similar name and show link for further handling
$i = 0;
while($row = $usrStatement->fetchObject())
{
    if($i > 0)
    {
        $page->addHtml('<hr />');
    }
    $page->addHtml('<p>
        <a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php?user_id='.$row->usr_id.'"><img
            src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('SYS_SHOW_PROFILE').'" />'.$row->first_name.' '.$row->last_name.'</a><br />');

        if($row->address !== '')
        {
            $page->addHtml($row->address.'<br />');
        }
        if($row->zip_code !== '' || $row->city !== '')
        {
            $page->addHtml($row->zip_code.' '.$row->city.'<br />');
        }
        if($row->email !== '')
        {
            if($gPreferences['enable_mail_module'] == 1)
            {
                $page->addHtml('<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?usr_id='.$row->usr_id.'">'.$row->email.'</a><br />');
            }
            else
            {
                $page->addHtml('<a href="mailto:'.$row->email.'">'.$row->email.'</a><br />');
            }
        }
    $page->addHtml('</p>');

    if(isMember($row->usr_id))
    {
        // found user is member of this organization
        if(strlen($row->usr_login_name) > 0)
        {
            // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken
            $page->addHtml('<p>'.$gL10n->get('NWU_USER_VALID_LOGIN'));
            if($gPreferences['enable_system_mails'] == 1)
            {
                $page->addHtml('<br />'.$gL10n->get('NWU_REMINDER_SEND_LOGIN').'</p>

                <button class="btn btn-default btn-primary" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=6\'"><img
                    src="'. THEME_URL. '/icons/key.png" alt="'.$gL10n->get('NWU_SEND_LOGIN').'" />'.$gL10n->get('NWU_SEND_LOGIN').'</button>');
            }
        }
        else
        {
            // Logindaten sind NICHT vorhanden -> diese nun zuordnen
            $page->addHtml('<p>'.$gL10n->get('NWU_USER_NO_VALID_LOGIN').'</p>

            <button class="btn btn-default btn-primary" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=1\'"><img
                src="'. THEME_URL. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_LOGIN').'" />'.$gL10n->get('NWU_ASSIGN_LOGIN').'</button>');
        }
    }
    else
    {
        // gefundene User ist noch KEIN Mitglied dieser Organisation
        $link = ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php?new_user_id='.$getNewUserId.'&amp;user_id='.$row->usr_id.'&amp;mode=2';

        if($row->usr_login_name !== '')
        {
            // Logindaten sind bereits vorhanden
            $page->addHtml('<p>'.$gL10n->get('NWU_NO_MEMBERSHIP', $gCurrentOrganization->getValue('org_shortname')).'</p>

            <button class="btn btn-default btn-primary" onclick="window.location.href=\''.$link.'\'"><img src="'.THEME_URL.'/icons/new_registrations.png"
                alt="'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'" />'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'</button>');
        }
        else
        {
            // KEINE Logindaten vorhanden
            $page->addHtml('<p>'.$gL10n->get('NWU_NO_MEMBERSHIP_NO_LOGIN', $gCurrentOrganization->getValue('org_shortname')).'</p>

            <button class="btn btn-default btn-primary" onclick="window.location.href=\''.$link.'\'"><img src="'. THEME_URL. '/icons/new_registrations.png"
                alt="'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP').'" />'.$gL10n->get('NWU_ASSIGN_MEMBERSHIP').'</button>');
        }
    }
    ++$i;
}
$page->addHtml('
    </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading">'.$gL10n->get('SYS_CREATE_NEW_USER').'</div>
        <div class="panel-body">
            <p>'. $gL10n->get('SYS_CREATE_NOT_FOUND_USER'). '</p>

            <button class="btn btn-default btn-primary" onclick="window.location.href=\''.$urlCreateNewUser.'\'"><img
                src="'. THEME_URL. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_NEW_USER').'" />'.$gL10n->get('SYS_CREATE_NEW_USER').'</button>
        </div>
    </div>'
);

$page->show();
