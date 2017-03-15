<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested userlist
 *
 * This script will read all necessary users and their data from the database. It is optimized to
 * work with the javascript DataTables and will return the data in json format.
 *
 * @par Examples
 * @code // the returned json data string
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
 * } @endcode
 *
 * Parameters:
 *
 * rol_id        - Id of role to which members should be assigned or removed
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
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getRoleId         = admFuncVariableIsValid($_GET, 'rol_id',        'int', array('requireValue' => true, 'directOutput' => true));
$getFilterRoleId   = admFuncVariableIsValid($_GET, 'filter_rol_id', 'int');
$getMembersShowAll = admFuncVariableIsValid($_GET, 'mem_show_all',  'bool', array('defaultValue' => false));
$getDraw    = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
$getStart   = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
$getLength  = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
$getSearch  = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$gLogger->info('mem_show_all: ' . $getMembersShowAll);

$jsonArray = array('draw' => $getDraw);

// create object of the commited role
$role = new TableRoles($gDb, $getRoleId);

// roles of other organizations can't be edited
if((int) $role->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
{
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
}

// check if user is allowed to assign members to this role
if(!$role->allowedToAssignMembers($gCurrentUser))
{
    echo json_encode(array('error' => $gL10n->get('SYS_NO_RIGHTS')));
}

if($getFilterRoleId > 0 && !$gCurrentUser->hasRightViewRole($getFilterRoleId))
{
    echo json_encode(array('error' => $gL10n->get('LST_NO_RIGHTS_VIEW_LIST')));
}

$rowNumber = $getStart; // count for every row
$memberOfThisOrganizationSelect = '';
$memberOfThisOrganizationCondition = '';
$filterRoleCondition = '';
$searchCondition = '';
$limitCondition = '';
$orderCondition = '';
$orderColumns = array('member_this_orga', 'member_this_role', 'last_name', 'first_name', 'birthday', 'address', 'leader_this_role');

if($getMembersShowAll)
{
    $getFilterRoleId = 0;
}
else
{
    // show only members of current organization
    if($getFilterRoleId > 0)
    {
        $filterRoleCondition = ' AND mem_rol_id = '.$getFilterRoleId.' ';
    }
}

// create order statement
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
    $orderCondition = ' ORDER BY last_name ASC, first_name ASC ';
}

// create search conditions
$searchColumns = array(
    'COALESCE(last_name, \' \')',
    'COALESCE(first_name, \' \')',
    'COALESCE(birthday, \' \')',
    'COALESCE(address, \' \')',
    'COALESCE(city, \' \')',
    'COALESCE(zip_code, \' \')',
    'COALESCE(country, \' \')'
);

if($getSearch !== '')
{
    $searchString = explode(' ', $getSearch);

    foreach($searchString as $searchWord)
    {
        $searchCondition .= ' AND concat(' . implode(', ', $searchColumns) . ') LIKE \'%'.$searchWord.'%\' ';
    }

    $searchCondition = ' WHERE ' . substr($searchCondition, 4);
}

// create a subselect to check if the user is an acitve member of the current organization
$sql = '(SELECT COUNT(*) AS count_this
           FROM '.TBL_MEMBERS.'
     INNER JOIN '.TBL_ROLES.'
             ON rol_id = mem_rol_id
     INNER JOIN '.TBL_CATEGORIES.'
             ON cat_id = rol_cat_id
          WHERE mem_usr_id  = usr_id
            AND mem_begin  <= \''.DATE_NOW.'\'
            AND mem_end     > \''.DATE_NOW.'\'
                '.$filterRoleCondition.'
            AND rol_valid = 1
            AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
            AND cat_org_id = '.$gCurrentOrganization->getValue('org_id').')';

if($getMembersShowAll)
{
    // show all users
    $memberOfThisOrganizationCondition = '';
    $memberOfThisOrganizationSelect = $sql;
}
else
{
    $memberOfThisOrganizationCondition = ' AND '.$sql.' > 0 ';
    $memberOfThisOrganizationSelect = ' 1 ';
}

if($getLength > 0)
{
    $limitCondition = ' LIMIT '.$getLength.' OFFSET '.$getStart;
}

// get count of all found users
$sql = 'SELECT COUNT(*) AS count_total
          FROM '.TBL_USERS.'
    INNER JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
    INNER JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
         WHERE usr_valid = 1
               '.$memberOfThisOrganizationCondition;
$countTotalStatement = $gDb->query($sql);

$jsonArray['recordsTotal'] = (int) $countTotalStatement->fetchColumn();

 // SQL-Statement zusammensetzen
$mainSql = 'SELECT DISTINCT usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name, birthday.usd_value AS birthday,
               city.usd_value AS city, address.usd_value AS address, zip_code.usd_value AS zip_code, country.usd_value AS country,
               mem_usr_id AS member_this_role, mem_leader AS leader_this_role, '.$memberOfThisOrganizationSelect.' AS member_this_orga
          FROM '.TBL_USERS.'
    INNER JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
    INNER JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS birthday
            ON birthday.usd_usr_id = usr_id
           AND birthday.usd_usf_id = '. $gProfileFields->getProperty('BIRTHDAY', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' AS country
            ON country.usd_usr_id = usr_id
           AND country.usd_usf_id = '. $gProfileFields->getProperty('COUNTRY', 'usf_id'). '
     LEFT JOIN '.TBL_ROLES.' rol
            ON rol.rol_valid   = 1
           AND rol.rol_id      = '.$getRoleId.'
     LEFT JOIN '.TBL_MEMBERS.' mem
            ON mem.mem_rol_id  = rol.rol_id
           AND mem.mem_begin  <= \''.DATE_NOW.'\'
           AND mem.mem_end     > \''.DATE_NOW.'\'
           AND mem.mem_usr_id  = usr_id
         WHERE usr_valid = 1
               '. $memberOfThisOrganizationCondition;

if($getSearch === '')
{
    // no search condition entered then return all records in dependence of order, limit and offset
    $sql = $mainSql. $orderCondition. $limitCondition;
}
else
{
    $sql = 'SELECT usr_id, last_name, first_name, birthday, city, address, zip_code, country, member_this_role, leader_this_role, member_this_orga
              FROM ('.$mainSql.') members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$userStatement = $gDb->query($sql);

// show rows with all organization users
while($user = $userStatement->fetch())
{
    $rowNumber++;

    $addressText  = ' ';
    $htmlAddress  = '&nbsp;';
    $htmlBirthday = '&nbsp;';

    if($user['member_this_orga'] > 0)
    {
        $memberOfThisOrganization = '1';
    }
    else
    {
        $memberOfThisOrganization = '0';
    }

    // create string with user address
    if(strlen($user['country']) > 0)
    {
        $addressText .= $gL10n->getCountryByCode($user['country']);
    }
    if(strlen($user['zip_code']) > 0 || strlen($user['city']) > 0)
    {
        $addressText .= ' - '. $user['zip_code']. ' '. $user['city'];
    }
    if(strlen($user['address']) > 0)
    {
        $addressText .= ' - '. $user['address'];
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

    // Haekchen setzen ob jemand Mitglied ist oder nicht
    if($user['member_this_role'])
    {
        $htmlMemberStatus = '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_'.$user['usr_id'].'"></b>';
    }
    else
    {
        $htmlMemberStatus = '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_'.$user['usr_id'].'"></b>';
    }

    if(strlen($addressText) > 1)
    {
        $htmlAddress = '<img class="admidio-icon-info" src="'. THEME_URL.'/icons/map.png" alt="'.$addressText.'" title="'.$addressText.'" />';
    }

    // Haekchen setzen ob jemand Leiter ist oder nicht
    if($user['leader_this_role'])
    {
        $htmlRoleLeader = '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox memlist_leader" />';
    }
    else
    {
        $htmlRoleLeader = '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" class="memlist_checkbox memlist_leader" />';
    }

    // Geburtstag nur ausgeben wenn bekannt
    if(strlen($user['birthday']) > 0)
    {
        $birthdayDate = DateTime::createFromFormat('Y-m-d', $user['birthday']);
        $htmlBirthday = $birthdayDate->format($gPreferences['system_date']);
    }

    // create array with all column values and add it to the json array
    $jsonArray['data'][] = array(
        '<img class="admidio-icon-info" src="'. THEME_URL.'/icons/'.$icon.'" alt="'.$iconText.'" title="'.$iconText.'" />',
        $htmlMemberStatus,
        '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.$user['usr_id'].'">'.$user['last_name'].'</a>',
        '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.$user['usr_id'].'">'.$user['first_name'].'</a>',
        $htmlAddress,
        $htmlBirthday,
        $htmlRoleLeader.'<b id="loadindicator_leader_'.$user['usr_id'].'"></b>');
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
                  FROM ('.$mainSql.') members
                       '.$searchCondition;
        $countFilteredStatement = $gDb->query($sql);
        $jsonArray['recordsFiltered'] = (int) $countFilteredStatement->fetchColumn();
    }
}
else
{
    $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
}

// add empty data element if no rows where found
if(!array_key_exists('data', $jsonArray))
{
    $jsonArray['data'] = array();
}

echo json_encode($jsonArray);
