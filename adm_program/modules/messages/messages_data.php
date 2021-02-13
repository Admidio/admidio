<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested messages list
 *
 * This script will read all necessary messages and their data from the database. It is optimized to
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
 * filter_rol_id - If set only users from this role will be shown in list.
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
$getFilterRoleId   = admFuncVariableIsValid($_GET, 'filter_rol_id', 'int');
$getDraw   = admFuncVariableIsValid($_GET, 'draw',   'int', array('requireValue' => true));
$getStart  = admFuncVariableIsValid($_GET, 'start',  'int', array('requireValue' => true));
$getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
$getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');

$jsonArray = array('draw' => $getDraw);

header('Content-Type: application/json');

// create order statement
$orderCondition = '';
$orderColumns = array('msg_type', 'msg_subject', 'recipients', 'msg_timestamp');

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
    $orderCondition = ' ORDER BY msg_timestamp DESC ';
}

// create search conditions
$searchCondition = '';
$queryParamsSearch = array();
$searchColumns = array(
    'COALESCE(msg_subject, \' \')',
    'COALESCE(recipients, \' \')'
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

$filterRoleCondition = '';
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

 // SQL-Statement zusammensetzen
$mainSql = 'SELECT msg_id, msg_type, msg_subject, msg_timestamp
              FROM ' . TBL_MESSAGES . '
             WHERE (  msg_usr_id_sender = ? -- $gCurrentUser->getValue(\'usr_id\')
                   OR EXISTS (
                      SELECT 1
                        FROM ' . TBL_MESSAGES_RECIPIENTS . '
                       WHERE msr_msg_id = msg_id
                         AND msr_usr_id = ? -- $gCurrentUser->getValue(\'usr_id\')
                      )
                   )';
$queryParamsMain = array(
    $gCurrentUser->getValue('usr_id'),
    $gCurrentUser->getValue('usr_id')
);

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
    $sql = 'SELECT msg_id, msg_type, msg_subject, msg_timestamp
              FROM ('.$mainSql.') AS members
               '.$searchCondition
                .$orderCondition
                .$limitCondition;
}
$messageStatement = $gDb->queryPrepared($sql, array_merge($queryParamsMain, $queryParamsSearch)); // TODO add more params

$rowNumber = $getStart; // count for every row

// show rows with all organization users
while($message = $messageStatement->fetch())
{
    ++$rowNumber;
    $arrContent  = array();
    $addressText = '';

    // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
    if($message['member_this_orga'] > 0)
    {
        $icon = 'fa-user';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname')));
    }
    else
    {
        $icon = 'fa-user-times';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($gCurrentOrganization->getValue('org_longname')));
    }
    $arrContent[] = '<i class="fas ' . $icon . '" data-toggle="tooltip" title="' . $iconText . '"></i>';

    // set flag if user is member of the current organization or not
    if($message['member_this_role'])
    {
        $arrContent[] = '<input type="checkbox" id="member_'.$message['usr_id'].'" name="member_'.$message['usr_id'].'" checked="checked" class="memlist_checkbox memlist_member" />';
    }
    else
    {
        $arrContent[] = '<input type="checkbox" id="member_'.$message['usr_id'].'" name="member_'.$message['usr_id'].'" class="memlist_checkbox memlist_member" />';
    }

    if($gProfileFields->isVisible('LAST_NAME', $gCurrentUser->editUsers()))
    {
        $arrContent[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $message['usr_id'])).'">'.$message['last_name'].'</a>';
    }

    if($gProfileFields->isVisible('FIRST_NAME', $gCurrentUser->editUsers()))
    {
        $arrContent[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $message['usr_id'])).'">'.$message['first_name'].'</a>';
    }

    // create string with user address
    if(strlen($message['country']) > 0 && $gProfileFields->isVisible('COUNTRY', $gCurrentUser->editUsers()))
    {
        $addressText .= $gL10n->getCountryName($message['country']);
    }
    if((strlen($message['zip_code']) > 0 && $gProfileFields->isVisible('POSTCODE', $gCurrentUser->editUsers()))
    || (strlen($message['city']) > 0 && $gProfileFields->isVisible('CITY', $gCurrentUser->editUsers())))
    {
        // some countries have the order postcode city others have city postcode
        if((int) $gProfileFields->getProperty('CITY', 'usf_sequence') > (int) $gProfileFields->getProperty('POSTCODE', 'usf_sequence'))
        {
            $addressText .= ' - '. $message['zip_code']. ' '. $message['city'];
        }
        else
        {
            $addressText .= ' - '. $message['city']. ' '. $message['zip_code'];
        }
    }
    if(strlen($message['street']) > 0 && $gProfileFields->isVisible('STREET', $gCurrentUser->editUsers()))
    {
        $addressText .= ' - '. $message['street'];
    }

    if($gProfileFields->isVisible('COUNTRY', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('POSTCODE', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('CITY', $gCurrentUser->editUsers())
    || $gProfileFields->isVisible('STREET', $gCurrentUser->editUsers()))
    {
        if(strlen($addressText) > 0)
        {
            $arrContent[] = '<i class="fas fa-map-marker-alt" data-toggle="tooltip" title="' . trim($addressText, ' -') . '"></i>';
        }
        else
        {
            $arrContent[] = '&nbsp;';
        }
    }

    if($gProfileFields->isVisible('BIRTHDAY', $gCurrentUser->editUsers()))
    {
        // show birthday if it's known
        if(strlen($message['birthday']) > 0)
        {
            $birthdayDate = \DateTime::createFromFormat('Y-m-d', $message['birthday']);
            $arrContent[] = $birthdayDate->format($gSettingsManager->getString('system_date'));
        }
        else
        {
            $arrContent[] = '&nbsp;';
        }
    }

    // set flag if user is a leader of the current role or not
    if($message['leader_this_role'])
    {
        $arrContent[] = '<input type="checkbox" id="leader_'.$message['usr_id'].'" name="leader_'.$message['usr_id'].'" checked="checked" class="memlist_checkbox memlist_leader" />';
    }
    else
    {
        $arrContent[] = '<input type="checkbox" id="leader_'.$message['usr_id'].'" name="leader_'.$message['usr_id'].'" class="memlist_checkbox memlist_leader" />';
    }

    // create array with all column values and add it to the json array
    $jsonArray['data'][] = $arrContent;
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

// add empty data element if no rows where found
if(!array_key_exists('data', $jsonArray))
{
    $jsonArray['data'] = array();
}

echo json_encode($jsonArray);
