<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested userlist
 *
 * This script will read all necessary users and their data from the database. It is optimized to
 * work with the javascript DataTables and will return the data in json format.
 *
 * **Code example**
 * ```
 * // the returned json data string
 * {
 *    "draw":1,
 *    "recordsTotal":"147",
 *    "data": [  [ 1,
 *                 "Link to profile",
 *                 "Smith, Heinz",
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
$getDraw    = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
$getStart   = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
$getLength  = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
$getSearch  = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$jsonArray = array('draw' => $getDraw);

header('Content-Type: application/json');

// if only active members should be shown then set parameter
if (!$gSettingsManager->getBool('members_show_all_users')) {
    $getMembers = true;
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers()) {
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit();
}

if (isset($_SESSION['members_list_config'])) {
    $membersListConfig = $_SESSION['members_list_config'];
}

// create order statement
$useOrderBy = false;
$orderCondition = '';
$orderColumns = array_merge(array('no', 'member_this_orga'), $membersListConfig->getColumnNamesSql());

if (array_key_exists('order', $_GET)) {
    foreach ($_GET['order'] as $order) {
        if (is_numeric($order['column'])) {
            if ($orderCondition === '') {
                $orderCondition = ' ORDER BY ';
            } else {
                $orderCondition .= ', ';
            }

            if (strtoupper($order['dir']) === 'ASC') {
                $orderCondition .= $orderColumns[$order['column']]. ' ASC ';
            } else {
                $orderCondition .= $orderColumns[$order['column']]. ' DESC ';
            }
        }
    }
} else {
    $useOrderBy = true;
}

// create search conditions
$searchCondition = '';
$queryParamsSearch = array();
$searchColumns = $membersListConfig->getSearchConditions();

if ($getSearch !== '' && count($searchColumns) > 0) {
    $searchString = explode(' ', $getSearch);

    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
        $searchValue = ' ?::text ';
    } else {
        // mysql
        $searchValue = ' ? ';
    }

    foreach ($searchString as $searchWord) {
        $searchCondition .= ' AND CONCAT(' . implode(', \' \', ', $searchColumns) . ') LIKE CONCAT(\'%\', '.$searchValue.', \'%\') ';
        $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
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
                     AND rol_valid = true
                     AND cat_name_intern <> \'EVENTS\'
                     AND (  cat_org_id = '.$gCurrentOrgId.'
                         OR cat_org_id IS NULL ))';

if ($getMembers) {
    $memberOfThisOrganizationCondition = ' AND '.$sqlSubSelect.' > 0 ';
    $memberOfThisOrganizationSelect = ' 1 ';
} else {
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
         WHERE usr_valid = true
               '.$memberOfThisOrganizationCondition;
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
);
$countTotalStatement = $gDb->queryPrepared($sql, $queryParams); // TODO add more params

$jsonArray['recordsTotal'] = (int) $countTotalStatement->fetchColumn();

// create a subselect to check if the user is also an active member of another organization
$memberOfOtherOrganizationSelect = ' 0 ';
if ($gCurrentOrganization->countAllRecords() > 1) {
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
            AND rol_valid = true
            AND cat_name_intern <> \'EVENTS\'
            AND cat_org_id <> '.$gCurrentOrgId.')';
}

// create sql to show all members (not accepted users should not be shown)
if ($getMembers) {
    $mainSql = $membersListConfig->getSql(array('showAllMembersThisOrga' => true, 'useConditions' => false, 'useOrderBy' => $useOrderBy));
} else {
    $mainSql = $membersListConfig->getSql(array('showAllMembersDatabase' => true, 'useConditions' => false, 'useOrderBy' => $useOrderBy));
}
$mainSql = 'SELECT DISTINCT '.$memberOfThisOrganizationSelect.' AS member_this_orga, '.$memberOfOtherOrganizationSelect.' AS member_other_orga, usr_login_name as loginname,
                (SELECT email.usd_value FROM '.TBL_USER_DATA.' email
                  WHERE  email.usd_usr_id = usr_id
                    AND email.usd_usf_id = ? /* $gProfileFields->getProperty(\'email\', \'usf_id\') */
                 ) AS member_email, '.
            substr($mainSql, 15);
$queryParamsEmail = array(
    $gProfileFields->getProperty('EMAIL', 'usf_id')
); // TODO add more params
$limitCondition = '';
if ($getLength > 0) {
    $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;
}

if ($getSearch === '') {
    // no search condition entered then return all records in dependence of order, limit and offset
    $sql = $mainSql . $searchCondition . $orderCondition . $limitCondition;
} else {
    $sql = 'SELECT *
              FROM ('.$mainSql.') AS members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$queryParamsMain = array_merge($queryParamsEmail, $queryParamsSearch);
$mglStatement = $gDb->queryPrepared($sql, $queryParamsMain); // TODO add more params

$orgName   = $gCurrentOrganization->getValue('org_longname');
$rowNumber = $getStart; // count for every row

$jsonArray['data'] = array();

while ($row = $mglStatement->fetch(\PDO::FETCH_BOTH)) {
    ++$rowNumber;
    $ColumnNumberSql = 7;
    $columnNumberJson = 2;

    $memberOfThisOrganization  = (bool) $row['member_this_orga'];
    $memberOfOtherOrganization = (bool) $row['member_other_orga'];

    // Create row and add first column "Rownumber"
    $columnValues = array('DT_RowId' => 'row_members_' . $row['usr_id'], '0' => $rowNumber);

    // Add icon for "Orgamitglied" or "Nichtmitglied"
    if ($memberOfThisOrganization) {
        $icon = 'fa-user';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName));
    } else {
        $icon = 'fa-user-alt-slash';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($orgName));
    }

    $columnValues['1'] = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['usr_uuid'])).'">
        <i class="fas ' . $icon . '" data-toggle="tooltip" title="' . $iconText . '"></i>';

    // add all columns of the list configuration to the json array
    // start columnNumber with 4 because the first 2 columns are not of the list configuration
    for ($columnNumber = 1; $columnNumber <= $membersListConfig->countColumns(); $columnNumber++) {
        if (!empty($row[$ColumnNumberSql])) {
            $columnValues[(string) $columnNumberJson] = $membersListConfig->convertColumnContentForOutput($columnNumber, 'html', $row[$ColumnNumberSql], $row['usr_uuid']);
        } else {
            $columnValues[(string) $columnNumberJson] = '';
        }

        $columnNumberJson++;
        $ColumnNumberSql++;
    }

    // Add "user-administration icons"
    $userAdministration = '';

    // Administrators can change or send password if login is configured and user is member of current organization
    if ($memberOfThisOrganization && $gCurrentUser->isAdministrator()
    && !empty($row['loginname']) && (int) $row['usr_id'] !== $gCurrentUserId) {
        if (!empty($row['member_email']) && $gSettingsManager->getBool('system_notifications_enabled')) {
            // if email is set and systemmails are activated then administrators can send a new password to user
            $userAdministration = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $row['usr_uuid'], 'mode' => 5)).'">'.
                '<i class="fas fa-key" data-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_USERNAME_PASSWORD') . '"></i></a>';
        } else {
            // if user has no email or send email is disabled then administrators could set a new password
            $userAdministration = '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/password.php', array('user_uuid' => $row['usr_uuid'])).'">'.
                '<i class="fas fa-key" data-toggle="tooltip" title="' . $gL10n->get('SYS_CHANGE_PASSWORD') . '"></i></a>';
        }
    }

    // add link to send email to user
    if (!empty($row['member_email'])) {
        if (!$gSettingsManager->getBool('enable_mail_module')) {
            $mailLink = 'mailto:'.$row['member_email'];
        } else {
            $mailLink = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('user_uuid' => $row['usr_uuid']));
        }
        $userAdministration .= '<a class="admidio-icon-link" href="'.$mailLink.'">'.
            '<i class="fas fa-envelope" data-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_EMAIL_TO', array($row['member_email'])) . '"></i></a>';
    }

    $userAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_uuid' => $row['usr_uuid'], 'copy' => 1)).'">'.
        '<i class="fas fa-clone" data-toggle="tooltip" title="' . $gL10n->get('SYS_COPY') . '"></i></a>';

    // add link to edit user, but only edit users who are members of the current organization
    if ($memberOfThisOrganization || !$memberOfOtherOrganization) {
        $userAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('user_uuid' => $row['usr_uuid'])).'">'.
            '<i class="fas fa-edit" data-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT_USER') . '"></i></a>';
    }

    // add link to delete user btw. remove user from the current organization
    if (((!$memberOfOtherOrganization && $gCurrentUser->isAdministrator()) // kein Mitglied einer anderen Orga, dann duerfen Administratoren loeschen
        || $memberOfThisOrganization)                              // aktive Mitglieder duerfen von berechtigten Usern entfernt werden
        && (int) $row['usr_id'] !== $gCurrentUserId) { // das eigene Profil darf keiner entfernen
        $userAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $row['usr_uuid'], 'mode' => 6)) . '">'.
                '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_REMOVE_USER').'"></i>
            </a>';
    }

    $columnValues[(string) $columnNumberJson] = $userAdministration;

    // add current row to json array
    $jsonArray['data'][] = $columnValues;
}

// set count of filtered records
if ($getSearch !== '') {
    if ($rowNumber < $getStart + $getLength) {
        $jsonArray['recordsFiltered'] = $rowNumber;
    } else {
        // read count of all filtered records without limit and offset
        $sql = 'SELECT COUNT(*) AS count
                  FROM ('.$mainSql.') AS members
                       '.$searchCondition;
        $countFilteredStatement = $gDb->queryPrepared($sql, $queryParamsMain);

        $jsonArray['recordsFiltered'] = (int) $countFilteredStatement->fetchColumn();
    }
} else {
    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
}

echo json_encode($jsonArray);
