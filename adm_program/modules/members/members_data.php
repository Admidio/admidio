<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested userlist
 *
 * This script will read all necessary users and their data from the database. It is optimized to
 * work with the javascript DataTables and will return the data in json format.
 *
 * **Code example:**
 * ```
 * // the returned json data string
 * {
 *    "draw":1,
 *    "recordsTotal":"147",
 *    "data": [  [ 1,
 *                 "Link to profile",
 *                 "Webmaster, Heinz",
 *                 "Admin",
 *                 "Gender",
 *                 "16.06.1991",
 *                 "14.02.2009 15:24",
 *                 "Functions"],
 *                [ ... ],
 *             ],
 *    "recordsFiltered":"147"
 * }
 * ```
 *
 * Parameters:
 *
 * members - true : (Default) Show only active members of the current organization
 *           false  : Show active and inactive members of all organizations in database
 * draw    - Number to validate the right inquiry from DataTables.
 * start   - Paging first record indicator. This is the start point in the current data set
 *           (0 index based - i.e. 0 is the first record).
 * length  - Number of records that the table can display in the current draw. It is expected that
 *           the number of records returned will be equal to this number, unless the server has
 *           fewer records to return. Note that this can be -1 to indicate that all records should
 *           be returned (although that negates any benefits of server-side processing!)
 * search[value] - Global search value.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getMembers = admFuncVariableIsValid($_GET, 'members', 'bool', array('defaultValue' => true));
$getDraw    = admFuncVariableIsValid($_GET, 'draw',    'int',  array('requireValue' => true));
$getStart   = admFuncVariableIsValid($_GET, 'start',   'int',  array('requireValue' => true));
$getLength  = admFuncVariableIsValid($_GET, 'length',  'int',  array('requireValue' => true));
$getSearch  = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$jsonArray = array('draw' => $getDraw);

header('Content-Type: application/json');

// if only active members should be shown then set parameter
if(!$gSettingsManager->getBool('members_show_all_users'))
{
    $getMembers = true;
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit();
}

// create order statement
$orderCondition = '';
$orderColumns = array('no', 'member_this_orga', 'name', 'usr_login_name', 'gender', 'birthday', 'timestamp');

if(array_key_exists('order', $_GET))
{
    foreach($_GET['order'] as $order)
    {
        if(is_numeric($order['column']))
        {
            if($orderCondition === '')
            {
                $orderCondition = ' ORDER BY ';
            }
            else
            {
                $orderCondition .= ', ';
            }

            if(strtoupper($order['dir']) === 'ASC')
            {
                $orderCondition .= $orderColumns[$order['column']]. ' ASC ';
            }
            else
            {
                $orderCondition .= $orderColumns[$order['column']]. ' DESC ';
            }
        }
    }
}
else
{
    $orderCondition = ' ORDER BY name ASC ';
}

// create search conditions
$searchCondition = '';
$queryParamsSearch = array();
$searchColumns = array(
    'COALESCE(name, \' \')',
    'COALESCE(usr_login_name, \' \')',
    'CASE WHEN gender = \'1\' THEN \''.$gL10n->get('SYS_MALE').'\' WHEN gender = \'2\' THEN \''.$gL10n->get('SYS_FEMALE').'\' ELSE \' \' END ',
    'COALESCE(birthday, \'1900-02-01\')',
    'COALESCE(timestamp, \'1900-02-01\')'
);

if($getSearch !== '' && count($searchColumns) > 0)
{
    $searchString = explode(' ', $getSearch);

    foreach($searchString as $searchWord)
    {
        $searchCondition .= ' AND concat(' . implode(', ', $searchColumns) . ') LIKE \'%'.$searchWord.'%\' ';
    }

    $searchCondition = ' WHERE ' . substr($searchCondition, 4);
}

// create a subselect to check if the user is an active member of the current organization
$sqlSubSelect = '(SELECT COUNT(*) AS count_this
                    FROM '.TBL_MEMBERS.'
              INNER JOIN '.TBL_ROLES.'
                      ON rol_id = mem_rol_id
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE mem_usr_id  = usr_id
                     AND mem_begin  <= \''.DATE_NOW.'\'
                     AND mem_end     > \''.DATE_NOW.'\'
                     AND rol_valid = 1
                     AND cat_name_intern <> \'EVENTS\'
                     AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                         OR cat_org_id IS NULL ))';

if($getMembers)
{
    $memberOfThisOrganizationCondition = ' AND '.$sqlSubSelect.' > 0 ';
    $memberOfThisOrganizationSelect = ' 1 ';
}
else
{
    $memberOfThisOrganizationCondition = '';
    $memberOfThisOrganizationSelect = $sqlSubSelect;
}

// get count of all found users
$sql = 'SELECT COUNT(*) AS count_total
          FROM '.TBL_USERS.'
    INNER JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
    INNER JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
         WHERE usr_valid = 1
               '.$memberOfThisOrganizationCondition;
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
);
$countTotalStatement = $gDb->queryPrepared($sql, $queryParams); // TODO add more params

$jsonArray['recordsTotal'] = (int) $countTotalStatement->fetchColumn();

// create a subselect to check if the user is also an active member of another organization
$memberOfOtherOrganizationSelect = ' 0 ';
if($gCurrentOrganization->countAllRecords() > 1)
{
    $memberOfOtherOrganizationSelect = '
        (SELECT COUNT(*) AS count_other
           FROM '.TBL_MEMBERS.'
     INNER JOIN '.TBL_ROLES.'
             ON rol_id = mem_rol_id
     INNER JOIN '.TBL_CATEGORIES.'
             ON cat_id = rol_cat_id
          WHERE mem_usr_id  = usr_id
            AND mem_begin  <= \''.DATE_NOW.'\'
            AND mem_end     > \''.DATE_NOW.'\'
            AND rol_valid = 1
            AND cat_name_intern <> \'EVENTS\'
            AND cat_org_id <> '.$gCurrentOrganization->getValue('org_id').')';
}

// show all members (not accepted users should not be shown)
$mainSql = 'SELECT usr_id, last_name.usd_value || \', \' || first_name.usd_value AS name,
                   email.usd_value AS email, gender.usd_value AS gender, birthday.usd_value AS birthday,
                   usr_login_name, COALESCE(usr_timestamp_change, usr_timestamp_create) AS timestamp,
                   '.$memberOfThisOrganizationSelect.' AS member_this_orga,
                   '.$memberOfOtherOrganizationSelect.' AS member_other_orga
              FROM '.TBL_USERS.'
        INNER JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS email
                ON email.usd_usr_id = usr_id
               AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS gender
                ON gender.usd_usr_id = usr_id
               AND gender.usd_usf_id = ? -- $gProfileFields->getProperty(\'GENDER\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS birthday
                ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = ? -- $gProfileFields->getProperty(\'BIRTHDAY\', \'usf_id\')
             WHERE usr_valid = 1
                   '.$memberOfThisOrganizationCondition;
$queryParamsMain = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('EMAIL', 'usf_id'),
    $gProfileFields->getProperty('GENDER', 'usf_id'),
    $gProfileFields->getProperty('BIRTHDAY', 'usf_id')
); // TODO add more params

$limitCondition = '';
if($getLength > 0)
{
    $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;
}

if($getSearch === '')
{
    // no search condition entered then return all records in dependence of order, limit and offset
    $sql = $mainSql . $orderCondition . $limitCondition;
}
else
{
    $sql = 'SELECT usr_id, name, email, gender, birthday, usr_login_name, timestamp, member_this_orga, member_other_orga
              FROM ('.$mainSql.') AS members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$mglStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch)); // TODO add more params

$orgName   = $gCurrentOrganization->getValue('org_longname');
$rowNumber = $getStart; // count for every row

$jsonArray['data'] = array();

while($row = $mglStatement->fetch())
{
    ++$rowNumber;

    $memberOfThisOrganization  = (bool) $row['member_this_orga'];
    $memberOfOtherOrganization = (bool) $row['member_other_orga'];

    // Create row and add first column "Rownumber"
    $columnValues = array($rowNumber);

    // Add icon for "Orgamitglied" or "Nichtmitglied"
    if($memberOfThisOrganization)
    {
        $icon = 'profile.png';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName));
    }
    else
    {
        $icon = 'no_profile.png';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($orgName));
    }

    $columnValues[] = '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $row['usr_id'])).'">'.
        '<img src="'.THEME_URL.'/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" /></a>';

    // Add "Lastname" and "Firstname"
    $columnValues[] = '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $row['usr_id'])).'">'.$row['name'].'</a>';

    // Add "Loginname"
    if(strlen($row['usr_login_name']) > 0)
    {
        $columnValues[] = $row['usr_login_name'];
    }
    else
    {
        $columnValues[] = '';
    }

    // Add icon for "gender"
    if(strlen($row['gender']) > 0)
    {
        // show selected text of optionfield or combobox
        $arrListValues  = $gProfileFields->getProperty('GENDER', 'usf_value_list');
        $columnValues[] = $arrListValues[$row['gender']];
    }
    else
    {
        $columnValues[] = '';
    }

    // Add "birthday"
    if(strlen($row['birthday']) > 0)
    {
        // date must be formated
        $date = \DateTime::createFromFormat('Y-m-d', $row['birthday']);
        $columnValues[] = $date->format($gSettingsManager->getString('system_date'));
    }
    else
    {
        $columnValues[] = '';
    }

    // Add "change date"
    $timestampChange = \DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
    $columnValues[]  = $timestampChange->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));

    // Add "user-administration icons"
    $userAdministration = '';

    // Administrators can change or send password if login is configured and user is member of current organization
    if($memberOfThisOrganization && $gCurrentUser->isAdministrator()
    && strlen($row['usr_login_name']) > 0 && (int) $row['usr_id'] !== (int) $gCurrentUser->getValue('usr_id'))
    {
        if(strlen($row['email']) > 0 && $gSettingsManager->getBool('enable_system_mails'))
        {
            // if email is set and systemmails are activated then administrators can send a new password to user
            $userAdministration = '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $row['usr_id'], 'mode' => 5)).'">'.
                '<img src="'.THEME_URL.'/icons/key.png" alt="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" title="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" /></a>';
        }
        else
        {
            // if user has no email or send email is disabled then administrators could set a new password
            $userAdministration = '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('usr_id' => $row['usr_id'])).'">'.
                '<img src="'.THEME_URL.'/icons/key.png" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" /></a>';
        }
    }

    // add link to send email to user
    if(strlen($row['email']) > 0)
    {
        if(!$gSettingsManager->getBool('enable_mail_module'))
        {
            $mailLink = 'mailto:'.$row['email'];
        }
        else
        {
            $mailLink = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('usr_id' => $row['usr_id']));
        }
        $userAdministration .= '<a class="admidio-icon-link" href="'.$mailLink.'">'.
            '<img src="'.THEME_URL.'/icons/email.png" alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', array($row['email'])).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', array($row['email'])).'" /></a>';
    }

    $userAdministration .= '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_id' => $row['usr_id'], 'copy' => 1)).'">'.
        '<img src="'.THEME_URL.'/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>';

    // add link to edit user, but only edit users who are members of the current organization
    if($memberOfThisOrganization || !$memberOfOtherOrganization)
    {
        $userAdministration .= '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_id' => $row['usr_id'])).'">'.
            '<img src="'.THEME_URL.'/icons/edit.png" alt="'.$gL10n->get('MEM_EDIT_USER').'" title="'.$gL10n->get('MEM_EDIT_USER').'" /></a>';
    }

    // add link to delete user btw. remove user from the current organization
    if(((!$memberOfOtherOrganization && $gCurrentUser->isAdministrator()) // kein Mitglied einer anderen Orga, dann duerfen Administratoren loeschen
        || $memberOfThisOrganization)                              // aktive Mitglieder duerfen von berechtigten Usern entfernt werden
        && (int) $row['usr_id'] !== (int) $gCurrentUser->getValue('usr_id')) // das eigene Profil darf keiner entfernen
    {
        $userAdministration .= '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $row['usr_id'], 'mode' => 6)).'">'.
            '<img src="'.THEME_URL.'/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" title="'.$gL10n->get('MEM_REMOVE_USER').'" /></a>';
    }

    $columnValues[] = $userAdministration;

    // add current row to json array
    $jsonArray['data'][] = $columnValues;
}

// set count of filtered records
if($getSearch !== '')
{
    if($rowNumber < $getStart + $getLength)
    {
        $jsonArray['recordsFiltered'] = $rowNumber;
    }
    else
    {
        // read count of all filtered records without limit and offset
        $sql = 'SELECT COUNT(*) AS count
                  FROM ('.$mainSql.') AS members
                       '.$searchCondition;
        $countFilteredStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch));

        $jsonArray['recordsFiltered'] = (int) $countFilteredStatement->fetchColumn();
    }
}
else
{
    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
}

echo json_encode($jsonArray);
