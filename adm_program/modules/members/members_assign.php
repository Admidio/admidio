<?php
/**
 ***********************************************************************************************
 * Search for existing user names and show users with similar names
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

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
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_LASTNAME'))));
    // => EXIT
}
if(strlen($_POST['firstname']) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FIRSTNAME'))));
    // => EXIT
}

// Initialize and check the parameters
$getLastname  = admFuncVariableIsValid($_POST, 'lastname',  'string', array('requireValue' => true));
$getFirstname = admFuncVariableIsValid($_POST, 'firstname', 'string', array('requireValue' => true));

// search for users with similar names (SQL function SOUNDEX only available in MySQL)
if($gSettingsManager->getBool('system_search_similar') && DB_ENGINE === Database::PDO_ENGINE_MYSQL)
{
    $sqlSimilarName = '
        (  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(?), 1, 4)     -- $gDb->escapeString($getLastname)
           AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(?), 1, 4) )   -- $gDb->escapeString($getFirstname)
        OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(?), 1, 4)     -- $gDb->escapeString($getFirstname)
           AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(?), 1, 4) ) ) -- $gDb->escapeString($getLastname)';
}
else
{
    $sqlSimilarName = '
        (  (   last_name.usd_value  = ?    -- $getLastname
           AND first_name.usd_value = ?)   -- $getFirstname
        OR (   last_name.usd_value  = ?    -- $getFirstname
           AND first_name.usd_value = ?) ) -- $getLastname';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_login_name, last_name.usd_value AS last_name,
               first_name.usd_value AS first_name, street.usd_value AS street,
               zip_code.usd_value AS zip_code, city.usd_value AS city,
               email.usd_value AS email
          FROM '.TBL_USERS.'
    RIGHT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
    RIGHT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS street
            ON street.usd_usr_id = usr_id
           AND street.usd_usf_id = ? -- $gProfileFields->getProperty(\'STREET\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = ? -- $gProfileFields->getProperty(\'POSTCODE\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = ? -- $gProfileFields->getProperty(\'CITY\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
         WHERE usr_valid = 1
           AND '.$sqlSimilarName;
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('STREET', 'usf_id'),
    $gProfileFields->getProperty('POSTCODE', 'usf_id'),
    $gProfileFields->getProperty('CITY', 'usf_id'),
    $gProfileFields->getProperty('EMAIL', 'usf_id'),
    $getLastname,
    $getFirstname,
    $getFirstname,
    $getLastname
);
$usrStatement = $gDb->queryPrepared($sql, $queryParams);
$memberCount = $usrStatement->rowCount();

if($memberCount === 0)
{
    // no user with that name found so go back and allow to create a new user
    echo 'success';
    exit();
}

// html output
echo '
<p class="lead">'.$gL10n->get('SYS_SIMILAR_USERS_FOUND', array($getFirstname. ' '. $getLastname)).'</p>

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
                <a class="btn" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php', array('user_id' => $row['usr_id'])).'"><img
                    src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('SYS_SHOW_PROFILE').'" />'.$row['first_name'].' '.$row['last_name'].'</a><br />';

                if(strlen($row['street']) > 0)
                {
                    echo $row['street'].'<br />';
                }
                if(strlen($row['zip_code']) > 0 || strlen($row['city']) > 0)
                {
                    // some countries have the order postcode city others have city postcode
                    if($gProfileFields->getProperty('CITY', 'usf_sequence') > $gProfileFields->getProperty('POSTCODE', 'usf_sequence'))
                    {
                        echo $row['zip_code'].' '.$row['city'].'<br />';
                    }
                    else
                    {
                        echo $row['city'].' '.$row['zip_code'].'<br />';
                    }
                }
                if(strlen($row['email']) > 0)
                {
                    if($gSettingsManager->getBool('enable_mail_module'))
                    {
                        echo '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('usr_id' => $row['usr_id'])).'">'.$row['email'].'</a><br />';
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
                $link = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/roles.php', array('usr_id' => $row['usr_id']));

                // KEINE Logindaten vorhanden
                echo '<p>'.$gL10n->get('MEM_NO_MEMBERSHIP', array($gCurrentOrganization->getValue('org_shortname'))).'</p>

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

        <button class="btn btn-default btn-primary" onclick="window.location.href=\''.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => 1, 'lastname' => $getLastname, 'firstname' => $getFirstname)).'\'"><img
            src="'. THEME_URL. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_NEW_USER').'" />'.$gL10n->get('SYS_CREATE_NEW_USER').'</button>
    </div>
</div>';
