<?php
/**
 ***********************************************************************************************
 * Search for existing user names and show users with similar names
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * new_user_uuid : UUID of user who should be assigned
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getNewUserUuid = admFuncVariableIsValid($_GET, 'new_user_uuid', 'string', array('requireValue' => true));

// only administrators could approve new users
if (!$gCurrentUser->approveUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// pruefen, ob Modul aufgerufen werden darf
if (!$gSettingsManager->getBool('registration_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('SYS_ASSIGN_REGISTRATION');

// create user object for new user
$newUser = new User($gDb, $gProfileFields);
$newUser->readDataByUuid($getNewUserUuid);

$lastName  = $gDb->escapeString($newUser->getValue('LAST_NAME', 'database'));
$firstName = $gDb->escapeString($newUser->getValue('FIRST_NAME', 'database'));

// search for users with similar names (SQL function SOUNDEX only available in MySQL)
// the following combinations within first name and last name will be checked:
// 1. first name and last name are equal (under consideration of soundex)
// 2. last name is equal and only first part of first name of existing members is equal
// 3. last name is equal and only first part of first name of new registration member is equal
// 4. last name is equal to first name and first name is equal to last name
if (DB_ENGINE === Database::PDO_ENGINE_MYSQL && $gSettingsManager->getBool('system_search_similar')) {
    $sqlSimilarName =
        '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX('. $lastName.'), 1, 4)
            AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX('. $firstName.'), 1, 4) )
         OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX('. $lastName.'), 1, 4)
            AND SUBSTRING(SOUNDEX(SUBSTRING(first_name.usd_value, 1, LOCATE(\' \', first_name.usd_value))), 1, 4) = SUBSTRING(SOUNDEX('. $firstName.'), 1, 4) )
         OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX('. $lastName.'), 1, 4)
            AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(SUBSTRING('. $firstName.', 1, LOCATE(\' \', '.$firstName.'))), 1, 4) )
         OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX('. $firstName.'), 1, 4)
            AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX('. $lastName.'), 1, 4) ) )';
} else {
    $sqlSimilarName =
        '(  (   last_name.usd_value  = '. $lastName.'
            AND first_name.usd_value = '. $firstName.')
         OR (   last_name.usd_value  = '. $lastName.'
            AND SUBSTRING(first_name.usd_value, 1, POSITION(\' \' IN first_name.usd_value)) = '. $firstName.')
         OR (   last_name.usd_value  = '. $lastName.'
            AND first_name.usd_value = SUBSTRING('. $firstName.', 1, POSITION(\' \' IN '. $firstName.')))
         OR (   last_name.usd_value  = '. $firstName.'
            AND first_name.usd_value = '. $lastName.') )';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_uuid, usr_login_name, last_name.usd_value AS last_name,
               first_name.usd_value AS first_name, street.usd_value AS street,
               zip_code.usd_value AS zip_code, city.usd_value AS city, email.usd_value AS email
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
         WHERE usr_valid = true
           AND '.$sqlSimilarName;
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('STREET', 'usf_id'),
    $gProfileFields->getProperty('POSTCODE', 'usf_id'),
    $gProfileFields->getProperty('CITY', 'usf_id'),
    $gProfileFields->getProperty('EMAIL', 'usf_id')
);
$usrStatement = $gDb->queryPrepared($sql, $queryParams);

// if current user can edit profiles than create link to profile otherwise create link to auto assign new registration
if ($gCurrentUser->editUsers()) {
    $urlCreateNewUser = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '3', 'user_uuid' => $getNewUserUuid));
} else {
    $urlCreateNewUser = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration/registration_function.php', array('mode' => '5', 'new_user_uuid' => $getNewUserUuid));
}

if ($usrStatement->rowCount() === 0) {
    // if user doesn't exists than show profile or auto assign roles
    admRedirect($urlCreateNewUser);
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('admidio-registration-assign', $headline);

$page->addHtml(
    '
    <p class="lead">'.$gL10n->get('SYS_SIMILAR_USERS_FOUND', array($newUser->getValue('FIRST_NAME'). ' '. $newUser->getValue('LAST_NAME'))).'</p>
    <div class="card admidio-blog">
        <div class="card-header">'.$gL10n->get('SYS_USER_FOUND').'</div>
        <div class="card-body">'
);

// show all found users with their address who have a similar name and show link for further handling
$i = 0;
while ($row = $usrStatement->fetch()) {
    if ($i > 0) {
        $page->addHtml('<hr />');
    }
    $page->addHtml('<p>
        <a class="btn" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['usr_uuid'])).'" title="'.$gL10n->get('SYS_SHOW_PROFILE').'">
            <i class="fas fa-user"></i>'.$row['first_name'].' '.$row['last_name'].'</a><br />');

    if ($row['street'] !== '') {
        $page->addHtml($row['street'].'<br />');
    }
    if ($row['zip_code'] !== '' || $row['city'] !== '') {
        $page->addHtml($row['zip_code'].' '.$row['city'].'<br />');
    }
    if ($row['email'] !== '') {
        if ($gSettingsManager->getBool('enable_mail_module')) {
            $page->addHtml('<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $row['usr_uuid'])).'">'.$row['email'].'</a><br />');
        } else {
            $page->addHtml('<a href="mailto:'.$row['email'].'">'.$row['email'].'</a><br />');
        }
    }
    $page->addHtml('</p>');

    if (isMember($row['usr_id'])) {
        // found user is member of this organization
        if ((string) $row['usr_login_name'] !== '') {
            // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken
            $page->addHtml('<p>'.$gL10n->get('SYS_USER_VALID_LOGIN'));
            if ($gSettingsManager->getBool('system_notifications_enabled')) {
                $page->addHtml('<br />'.$gL10n->get('SYS_REMINDER_SEND_LOGIN').'</p>

                <button class="btn btn-primary" onclick="window.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('new_user_uuid' => $getNewUserUuid, 'user_uuid' => $row['usr_uuid'], 'mode' => '6')).'\'">
                    <i class="fas fa-key"></i>'.$gL10n->get('SYS_SEND_LOGIN_INFORMATION').'</button>');
            }
        } else {
            // Logindaten sind NICHT vorhanden -> diese nun zuordnen
            $page->addHtml('<p>'.$gL10n->get('SYS_USER_NO_VALID_LOGIN').'</p>

            <button class="btn btn-primary" onclick="window.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('new_user_uuid' => $getNewUserUuid, 'user_uuid' => $row['usr_uuid'], 'mode' => '1')).'\'">
                <i class="fas fa-user-check"></i>'.$gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION').'</button>');
        }
    } else {
        // gefundene User ist noch KEIN Mitglied dieser Organisation
        $link = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('new_user_uuid' => $getNewUserUuid, 'user_uuid' => $row['usr_uuid'], 'mode' => '2'));

        if ($row['usr_login_name'] !== '') {
            // Logindaten sind bereits vorhanden
            $page->addHtml('<p>'.$gL10n->get('SYS_USER_NO_MEMBERSHIP_LOGIN', array($gCurrentOrganization->getValue('org_shortname'))).'</p>

            <button class="btn btn-primary" onclick="window.location.href=\''.$link.'\'">
                <i class="fas fa-user-check"></i>'.$gL10n->get('SYS_ASSIGN_MEMBERSHIP_AND_LOGIN').'</button>');
        } else {
            // KEINE Logindaten vorhanden
            $page->addHtml('<p>'.$gL10n->get('SYS_USER_NO_MEMBERSHIP_NO_LOGIN', array($gCurrentOrganization->getValue('org_shortname'))).'</p>

            <button class="btn btn-primary" onclick="window.location.href=\''.$link.'\'">
                <i class="fas fa-user-check"></i>'.$gL10n->get('SYS_ASSIGN_MEMBERSHIP').'</button>');
        }
    }
    ++$i;
}
$page->addHtml(
    '
    </div>
    </div>
    <div class="card admidio-blog">
        <div class="card-header">'.$gL10n->get('SYS_CREATE_NEW_USER').'</div>
        <div class="card-body">
            <p>'. $gL10n->get('SYS_CREATE_NOT_FOUND_USER'). '</p>

            <button class="btn btn-primary" onclick="window.location.href=\''.$urlCreateNewUser.'\'">
                <i class="fas fa-plus-circle"></i>'.$gL10n->get('SYS_CREATE_NEW_USER').'</button>
        </div>
    </div>'
);

$page->show();
