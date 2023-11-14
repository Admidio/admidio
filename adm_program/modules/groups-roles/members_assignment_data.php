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
 * rol_id        - ID of role to which members should be assigned or removed
 * filter_rol_id - If set only users from this role will be shown in list.
 * mem_show_all  - true  : (Default) Show active and inactive members of all organizations in database
 *                 false : Show only active members of the current organization
 * draw          - Number to validate the right inquiry from DataTables.
 * start         - Paging first record indicator. This is the start point in the current data set
 *                 (0 index based - i.e. 0 is the first record).
 * length        - Number of records that the table can display in the current draw. It is expected that
 *                 the number of records returned will be equal to this number, unless the server has
 *                 fewer records to return. Note that this can be -1 to indicate that all records should
 *                 be returned (although that negates any benefits of server-side processing!)
 * search[value] - Global search value.
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getRoleUuid       = admFuncVariableIsValid($_GET, 'role_uuid', 'string', array('requireValue' => true, 'directOutput' => true));
$getFilterRoleId   = admFuncVariableIsValid($_GET, 'filter_rol_id', 'int');
$getMembersShowAll = admFuncVariableIsValid($_GET, 'mem_show_all', 'bool', array('defaultValue' => false));
$getDraw   = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
$getStart  = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
$getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
$getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$gLogger->info('mem_show_all: ' . $getMembersShowAll);

$jsonArray = array('draw' => $getDraw);

header('Content-Type: application/json');

// create object of the commited role
$role = new TableRoles($gDb);
$role->readDataByUuid($getRoleUuid);

// roles of other organizations can't be edited
if ((int) $role->getValue('cat_org_id') !== $gCurrentOrgId && $role->getValue('cat_org_id') > 0) {
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit();
}

// check if user is allowed to assign members to this role
if (!$role->allowedToAssignMembers($gCurrentUser)) {
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
    exit();
}

if ($getFilterRoleId > 0 && !$gCurrentUser->hasRightViewRole($getFilterRoleId)) {
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS_VIEW_LIST')));
    exit();
}

// create order statement
$orderCondition = '';
$orderColumns = array('member_this_orga', 'member_this_role', 'last_name', 'first_name', 'birthday', 'street', 'leader_this_role');

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
    $orderCondition = ' ORDER BY last_name ASC, first_name ASC ';
}

// create search conditions
$searchCondition = '';
$queryParamsSearch = array();
$searchColumns = array(
    'COALESCE(last_name, \' \')',
    'COALESCE(first_name, \' \')',
    'COALESCE(birthday, \' \')',
    'COALESCE(street, \' \')',
    'COALESCE(city, \' \')',
    'COALESCE(zip_code, \' \')',
    'COALESCE(country, \' \')'
);

if ($getSearch !== '' && count($searchColumns) > 0) {
    $searchString = explode(' ', $getSearch);

    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
        $searchValue = ' ?::text ';
    } else {
        // mysql
        $searchValue = ' ? ';
    }

    foreach ($searchString as $searchWord) {
        $searchCondition .= ' AND concat(' . implode(', ', $searchColumns) . ') LIKE CONCAT(\'%\', '.$searchValue.', \'%\') ';
        $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
    }

    $searchCondition = ' WHERE ' . substr($searchCondition, 4);
}

$filterRoleCondition = '';
if ($getMembersShowAll) {
    $getFilterRoleId = 0;
} else {
    // show only members of current organization
    if ($getFilterRoleId > 0) {
        $filterRoleCondition = ' AND mem_rol_id = '.$getFilterRoleId.' ';
    }
}

// create a subselect to check if the user is an acitve member of the current organization
$sqlSubSelect = '(SELECT COUNT(*) AS count_this
                    FROM '.TBL_MEMBERS.'
              INNER JOIN '.TBL_ROLES.'
                      ON rol_id = mem_rol_id
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE mem_usr_id  = usr_id
                     AND mem_begin  <= \''.DATE_NOW.'\'
                     AND mem_end     > \''.DATE_NOW.'\'
                         '.$filterRoleCondition.'
                     AND rol_valid = true
                     AND cat_name_intern <> \'EVENTS\'
                     AND cat_org_id = '.$gCurrentOrgId.')';

if ($getMembersShowAll) {
    // show all users
    $memberOfThisOrganizationCondition = '';
    $memberOfThisOrganizationSelect = $sqlSubSelect;
} else {
    $memberOfThisOrganizationCondition = ' AND '.$sqlSubSelect.' > 0 ';
    $memberOfThisOrganizationSelect = ' 1 ';
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

 // SQL-Statement zusammensetzen
$mainSql = 'SELECT DISTINCT usr_id, usr_uuid, last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                   birthday.usd_value AS birthday, city.usd_value AS city, street.usd_value AS street,
                   zip_code.usd_value AS zip_code, country.usd_value AS country, mem_usr_id AS member_this_role,
                   mem_leader AS leader_this_role, '.$memberOfThisOrganizationSelect.' AS member_this_orga
              FROM '.TBL_USERS.'
        INNER JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS birthday
                ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = ? -- $gProfileFields->getProperty(\'BIRTHDAY\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS city
                ON city.usd_usr_id = usr_id
               AND city.usd_usf_id = ? -- $gProfileFields->getProperty(\'CITY\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS street
                ON street.usd_usr_id = usr_id
               AND street.usd_usf_id = ? -- $gProfileFields->getProperty(\'STREET\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS zip_code
                ON zip_code.usd_usr_id = usr_id
               AND zip_code.usd_usf_id = ? -- $gProfileFields->getProperty(\'POSTCODE\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS country
                ON country.usd_usr_id = usr_id
               AND country.usd_usf_id = ? -- $gProfileFields->getProperty(\'COUNTRY\', \'usf_id\')
         LEFT JOIN '.TBL_ROLES.' AS rol
                ON rol.rol_valid   = true
               AND rol.rol_id      = ? -- $role->getValue(\'rol_id\')
         LEFT JOIN '.TBL_MEMBERS.' AS mem
                ON mem.mem_rol_id  = rol.rol_id
               AND mem.mem_begin  <= ? -- DATE_NOW
               AND mem.mem_end     > ? -- DATE_NOW
               AND mem.mem_usr_id  = usr_id
             WHERE usr_valid = true
                   '. $memberOfThisOrganizationCondition;
$queryParamsMain = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('BIRTHDAY', 'usf_id'),
    $gProfileFields->getProperty('CITY', 'usf_id'),
    $gProfileFields->getProperty('STREET', 'usf_id'),
    $gProfileFields->getProperty('POSTCODE', 'usf_id'),
    $gProfileFields->getProperty('COUNTRY', 'usf_id'),
    $role->getValue('rol_id'),
    DATE_NOW,
    DATE_NOW
); // TODO add more params

$limitCondition = '';
if ($getLength > 0) {
    $limitCondition = ' LIMIT ' . $getLength . ' OFFSET ' . $getStart;
}

if ($getSearch === '') {
    // no search condition entered then return all records in dependence of order, limit and offset
    $sql = $mainSql . $orderCondition . $limitCondition;
} else {
    $sql = 'SELECT usr_id, usr_uuid, last_name, first_name, birthday, city, street, zip_code, country, member_this_role, leader_this_role, member_this_orga
              FROM ('.$mainSql.') AS members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$userStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch)); // TODO add more params

$rowNumber = $getStart; // count for every row

// show rows with all organization users
while ($user = $userStatement->fetch()) {
    ++$rowNumber;
    $arrContent  = array();
    $addressText = '';

    // Select icon for member and non-member of the organisation
    if ($user['member_this_orga'] > 0) {
        $icon = 'fa-user';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname')));
    } else {
        $icon = 'fa-user-times';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname')));
    }
    $arrContent[] = '<i class="fas ' . $icon . '" data-toggle="tooltip" title="' . $iconText . '"></i>';

    // set flag if user is member of the current organization or not
    if ($user['member_this_role']) {
        $arrContent[] = '<input type="checkbox" id="member_'.$user['usr_uuid'].'" name="member_'.$user['usr_uuid'].'" checked="checked" class="memlist_checkbox memlist_member" />';
    } else {
        $arrContent[] = '<input type="checkbox" id="member_'.$user['usr_uuid'].'" name="member_'.$user['usr_uuid'].'" class="memlist_checkbox memlist_member" />';
    }

    if ($gProfileFields->isVisible('LAST_NAME', $gCurrentUser->editUsers())) {
        $arrContent[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user['usr_uuid'])).'">'.$user['last_name'].'</a>';
    }

    if ($gProfileFields->isVisible('FIRST_NAME', $gCurrentUser->editUsers())) {
        $arrContent[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user['usr_uuid'])).'">'.$user['first_name'].'</a>';
    }

    // create string with user address
    if ((string) $user['country'] !== '' && $gProfileFields->isVisible('COUNTRY', $gCurrentUser->editUsers())) {
        $addressText .= $gL10n->getCountryName($user['country']);
    }
    if (((string) $user['zip_code'] !== '' && $gProfileFields->isVisible('POSTCODE', $gCurrentUser->editUsers()))
    || ((string) $user['city'] !== '' && $gProfileFields->isVisible('CITY', $gCurrentUser->editUsers()))) {
        // some countries have the order postcode city others have city postcode
        if ((int) $gProfileFields->getProperty('CITY', 'usf_sequence') > (int) $gProfileFields->getProperty('POSTCODE', 'usf_sequence')) {
            $addressText .= ' - '. $user['zip_code']. ' '. $user['city'];
        } else {
            $addressText .= ' - '. $user['city']. ' '. $user['zip_code'];
        }
    }
    if ((string) $user['street'] !== '' && $gProfileFields->isVisible('STREET', $gCurrentUser->editUsers())) {
        $addressText .= ' - '. $user['street'];
    }

    if ($gProfileFields->isVisible('COUNTRY', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('POSTCODE', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('CITY', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('STREET', $gCurrentUser->editUsers())) {
        if ($addressText !== '') {
            $arrContent[] = '<i class="fas fa-map-marker-alt" data-toggle="tooltip" title="' . trim($addressText, ' -') . '"></i>';
        } else {
            $arrContent[] = '&nbsp;';
        }
    }

    if ($gProfileFields->isVisible('BIRTHDAY', $gCurrentUser->editUsers())) {
        // show birthday if it's known
        if ((string) $user['birthday'] !== '') {
            $birthdayDate = DateTime::createFromFormat('Y-m-d', $user['birthday']);
            $arrContent[] = $birthdayDate->format($gSettingsManager->getString('system_date'));
        } else {
            $arrContent[] = '&nbsp;';
        }
    }

    // set flag if user is a leader of the current role or not
    if ($user['leader_this_role']) {
        $arrContent[] = '<input type="checkbox" id="leader_'.$user['usr_uuid'].'" name="leader_'.$user['usr_uuid'].'" checked="checked" class="memlist_checkbox memlist_leader" />';
    } else {
        $arrContent[] = '<input type="checkbox" id="leader_'.$user['usr_uuid'].'" name="leader_'.$user['usr_uuid'].'" class="memlist_checkbox memlist_leader" />';
    }

    // create array with all column values and add it to the json array
    $jsonArray['data'][] = $arrContent;
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
        $countFilteredStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch));
        $jsonArray['recordsFiltered'] = (int) $countFilteredStatement->fetchColumn();
    }
} else {
    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
}

// add empty data element if no rows where found
if (!array_key_exists('data', $jsonArray)) {
    $jsonArray['data'] = array();
}

echo json_encode($jsonArray);
