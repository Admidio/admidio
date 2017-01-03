<?php
/**
 ***********************************************************************************************
 * Search for existing user names and show users with similar names
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// this script should return errors in ajax mode
$gMessage->showHtmlTextOnly(true);

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if(strlen($_POST['lastname']) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_LASTNAME')));
    // => EXIT
}
if(strlen($_POST['firstname']) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_FIRSTNAME')));
    // => EXIT
}

// Initialize and check the parameters
$getLastname  = admFuncVariableIsValid($_POST, 'lastname',  'string', array('requireValue' => true));
$getFirstname = admFuncVariableIsValid($_POST, 'firstname', 'string', array('requireValue' => true));

// search for users with similar names (SQL function SOUNDEX only available in MySQL)
if($gPreferences['system_search_similar'] == 1 && $gDbType === 'mysql')
{
    $sql_similar_name =
    '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $gDb->escapeString($getLastname).'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $gDb->escapeString($getFirstname).'\'), 1, 4) )
     OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $gDb->escapeString($getFirstname).'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $gDb->escapeString($getLastname).'\'), 1, 4) ) )';
}
else
{
    $sql_similar_name =
    '(  (   last_name.usd_value  LIKE \''. $gDb->escapeString($getLastname).'\'
        AND first_name.usd_value LIKE \''. $gDb->escapeString($getFirstname).'\')
     OR (   last_name.usd_value  LIKE \''. $gDb->escapeString($getFirstname).'\'
        AND first_name.usd_value LIKE \''. $gDb->escapeString($getLastname).'\') )';
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
           AND '.$sql_similar_name;
$usrStatement = $gDb->query($sql);
$memberCount = $usrStatement->rowCount();

if($memberCount === 0)
{
    // no user with that name found so go back and allow to create a new user
    echo 'success';
    exit();
}

// html output
echo '
<p class="lead">'.$gL10n->get('SYS_SIMILAR_USERS_FOUND', $getFirstname. ' '. $getLastname).'</p>

<div class="panel panel-default">
    <div class="panel-heading">'.$gL10n->get('SYS_USERS_FOUND').'</div>
    <div class="panel-body">';

        // show all found users with their address who have a similar name and show link for further handling
        $i = 0;
        while($row = $usrStatement->fetch())
        {
            if($i > 0)
            {
                echo '<hr />';
            }
            echo '<p>
                <a class="btn" href="'. ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php?user_id='.$row['usr_id'].'"><img
                    src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('SYS_SHOW_PROFILE').'" />'.$row['first_name'].' '.$row['last_name'].'</a><br />';

                if(strlen($row['address']) > 0)
                {
                    echo $row['address'].'<br />';
                }
                if(strlen($row['zip_code']) > 0 || strlen($row['city']) > 0)
                {
                    echo $row['zip_code'].' '.$row['city'].'<br />';
                }
                if(strlen($row['email']) > 0)
                {
                    if($gPreferences['enable_mail_module'] == 1)
                    {
                        echo '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a><br />';
                    }
                    else
                    {
                        echo '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a><br />';
                    }
                }
            echo '</p>';

            if(!isMember($row['usr_id']))
            {
                // gefundene User ist noch KEIN Mitglied dieser Organisation
                $link = ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php?usr_id='.$row['usr_id'];

                // KEINE Logindaten vorhanden
                echo '<p>'.$gL10n->get('MEM_NO_MEMBERSHIP', $gCurrentOrganization->getValue('org_shortname')).'</p>

                <button class="btn btn-default btn-primary" onclick="window.location.href=\''.$link.'\'"><img src="'. THEME_URL. '/icons/new_registrations.png"
                    alt="'.$gL10n->get('MEM_ASSIGN_ROLES').'" />'.$gL10n->get('MEM_ASSIGN_ROLES').'</button>';
            }
            ++$i;
        }
    echo '</div>
</div>
<div class="panel panel-default">
    <div class="panel-heading">'.$gL10n->get('SYS_CREATE_NEW_USER').'</div>
    <div class="panel-body">
        <p>'. $gL10n->get('SYS_CREATE_NOT_FOUND_USER').'</p>

        <button class="btn btn-default btn-primary" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php?new_user=1&lastname='. $getLastname.'&firstname='. $getFirstname.'\'"><img
            src="'. THEME_URL. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_NEW_USER').'" />'.$gL10n->get('SYS_CREATE_NEW_USER').'</button>
    </div>
</div>';
