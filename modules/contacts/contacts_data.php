<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Server side script for Datatables to return the requested the list of contacts
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
 * mem_show_filter - 0  : (Default) Show only active contacts for current organization
 *                    1  : Show only inactive contacts for current organization
 *                    2  : Show active and inactive contacts for current organization
 *                    3  : Show active and inactive contacts for all organizations (only Admin)
 * draw             - Number to validate the right inquiry from DataTables.
 * start            - Paging first record indicator. This is the start point in the current data set
 *                    (0 index based - i.e. 0 is the first record).
 * length           - Number of records that the table can display in the current draw. It is expected that
 *                    the number of records returned will be equal to this number, unless the server has
 *                    fewer records to return. Note that this can be -1 to indicate that all records should
 *                    be returned (although that negates any benefits of server-side processing!)
 * search[value]    - Global search value.
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMembersShowFilter = admFuncVariableIsValid($_GET, 'mem_show_filter', 'int', array('defaultValue' => 0));
    $getDraw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
    $getLength = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
    $getSearch = admFuncVariableIsValid($_GET['search'], 'value', 'string');

    $jsonArray = array('draw' => $getDraw);

    header('Content-Type: application/json');

    // show all members of all organizations
    $getMembersAllOrgs = $gSettingsManager->getBool('contacts_show_all');

    if (isset($_SESSION['contacts_list_configuration'])) {
        $contactsListConfig = $_SESSION['contacts_list_configuration'];
    }

    // create order statement
    $useOrderBy = false;
    $orderCondition = '';
    $orderColumns = array_merge(array('no', 'member_this_orga'), $contactsListConfig->getColumnNamesSql());
    if (($getMembersShowFilter < 3) && $gCurrentUser->isAdministratorUsers()) {
        array_unshift($orderColumns, 'checkbox');
    }

    if (array_key_exists('order', $_GET)) {
        foreach ($_GET['order'] as $order) {
            if (is_numeric($order['column'])) {
                if ($orderCondition === '') {
                    $orderCondition = ' ORDER BY ';
                } else {
                    $orderCondition .= ', ';
                }

                if (strtoupper($order['dir']) === 'ASC') {
                    $orderCondition .= $orderColumns[$order['column']] . ' ASC ';
                } else {
                    $orderCondition .= $orderColumns[$order['column']] . ' DESC ';
                }
            }
        }
    } else {
        $useOrderBy = true;
    }

    // create search conditions
    $searchCondition = '';
    $queryParamsSearch = array();
    $searchColumns = $contactsListConfig->getSearchConditions();

    if ($getSearch !== '' && count($searchColumns) > 0) {
        $searchString = explode(' ', $getSearch);

        if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
            $searchValue = ' ?::text ';
        } else {
            // mysql
            $searchValue = ' ? ';
        }

        foreach ($searchString as $searchWord) {
            $searchCondition .= ' AND CONCAT(' . implode(', \' \', ', $searchColumns) . ') LIKE LOWER(CONCAT(\'%\', ' . $searchValue . ', \'%\')) ';
            $queryParamsSearch[] = htmlspecialchars_decode($searchWord, ENT_QUOTES | ENT_HTML5);
        }

        $searchCondition = ' WHERE ' . substr($searchCondition, 4);
    }

    // create a subselect to check if the user is an active member of the current organization
    $contactsOfThisOrganizationSelectPlaceholder = '
                    FROM ' . TBL_MEMBERS . '
              INNER JOIN ' . TBL_ROLES . '
                      ON rol_id = mem_rol_id
              INNER JOIN ' . TBL_CATEGORIES . '
                      ON cat_id = rol_cat_id
                   WHERE mem_usr_id  = usr_id
                     AND mem_begin  <= \'' . DATE_NOW . '\'
                     %s    -- logic placeholder for mem_end
                     AND rol_valid = true
                     AND cat_name_intern <> \'EVENTS\'
                     AND (  cat_org_id = ' . $gCurrentOrgId . '
                         OR cat_org_id IS NULL ))';

    $placeholderCurrentThisOrg = ' AND mem_end > \'' . DATE_NOW . '\'';
    $placeholderFormerThisOrg = ' AND mem_end <= \'' . DATE_NOW . '\'
            AND NOT EXISTS (
                SELECT 1
                FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '      ON rol_id    = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id    = rol_cat_id
                WHERE mem_usr_id   = usr_id
                AND mem_begin   <= \'' . DATE_NOW . '\'
                AND mem_end      > \'' . DATE_NOW . '\'
                AND rol_valid    = true
                AND cat_name_intern <> \'EVENTS\'
                AND (  cat_org_id   = ' . $gCurrentOrgId . '
                         OR cat_org_id IS NULL ))';

    if ($getMembersShowFilter === 0) {
        // show only active members of the current organization
        $contactsOfThisOrganizationSelect = '(SELECT COUNT(*) AS count_this' . sprintf($contactsOfThisOrganizationSelectPlaceholder, $placeholderCurrentThisOrg);
        $formerContactsOfThisOrganizationSelect = ' 0 '; // no former members of the current organization should be shown
    } elseif ($getMembersShowFilter === 1) {
        // show only former members of the current organization
        $contactsOfThisOrganizationSelect = ' 0 '; // no current members of the current organization should be shown
        $formerContactsOfThisOrganizationSelect = '(SELECT COUNT(*) AS count_this_former' . sprintf($contactsOfThisOrganizationSelectPlaceholder, $placeholderFormerThisOrg);
    } elseif ($getMembersShowFilter === 2 || $getMembersShowFilter === 3) {
        // show all members of current organization
        $contactsOfThisOrganizationSelect = '(SELECT COUNT(*) AS count_this' . sprintf($contactsOfThisOrganizationSelectPlaceholder, $placeholderCurrentThisOrg);
        $formerContactsOfThisOrganizationSelect = '(SELECT COUNT(*) AS count_this_former' . sprintf($contactsOfThisOrganizationSelectPlaceholder, $placeholderFormerThisOrg);
    }

    // create a subselect to check if the user is also an active member of another organization
    if ($gCurrentOrganization->countAllRecords() > 1 && $gCurrentUser->isAdministrator() && $getMembersShowFilter === 3) {
        $contactsOfOtherOrganizationSelectPlaceholder = '
            FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
            WHERE mem_usr_id  = usr_id
                AND mem_begin  <= \'' . DATE_NOW . '\'
                %s    -- logic placeholder for mem_end
                AND rol_valid = true
                AND cat_name_intern <> \'EVENTS\'
                AND cat_org_id <> ' . $gCurrentOrgId . ')';

        $placeholderCurrentOtherOrg = "AND mem_end > '" . DATE_NOW . "'";
        $placeholderFormerOtherOrg = "AND mem_end <= '" . DATE_NOW . "'
            AND NOT EXISTS (
                SELECT 1
                FROM " . TBL_MEMBERS . "
            INNER JOIN " . TBL_ROLES . "      ON rol_id    = mem_rol_id
            INNER JOIN " . TBL_CATEGORIES . " ON cat_id    = rol_cat_id
                WHERE mem_usr_id   = usr_id
                AND mem_begin   <= '" . DATE_NOW . "'
                AND mem_end      > '" . DATE_NOW . "'
                AND rol_valid    = true
                AND cat_name_intern <> 'EVENTS'
                AND cat_org_id   <> " . $gCurrentOrgId . ")";

        // show all members of other organizations
        $contactsOfOtherOrganizationSelect = '(SELECT COUNT(*) AS count_other' . sprintf($contactsOfOtherOrganizationSelectPlaceholder, $placeholderCurrentOtherOrg);
        $formerContactsOfOtherOrganizationSelect = '(SELECT COUNT(*) AS count_other_former' . sprintf($contactsOfOtherOrganizationSelectPlaceholder, $placeholderFormerOtherOrg);
    } else {
        // if there is only one organization or user is no admin then no other members of other organizations should be shown
        $contactsOfOtherOrganizationSelect = ' 0 ';
        $formerContactsOfOtherOrganizationSelect = ' 0 ';
    }

    // create main sql statement
    if (($getMembersShowFilter === 0) && $gCurrentUser->isAdministratorUsers()) {
        $mainSql = $contactsListConfig->getSql(
            array(
                'showAllMembersThisOrga' => true,
                'showUserUUID' => true,
                'useConditions' => false,
                'useOrderBy' => $useOrderBy
            )
        );
    } elseif (($getMembersShowFilter === 1) && $gCurrentUser->isAdministratorUsers()) {
        $mainSql = $contactsListConfig->getSql(
            array(
                'showFormerMembers' => true,
                'showUserUUID' => true,
                'useConditions' => false,
                'useOrderBy' => $useOrderBy
            )
        );
    } elseif (($getMembersShowFilter === 2) && $gCurrentUser->isAdministratorUsers()) {
        $mainSql = $contactsListConfig->getSql(
            array(
                'showAllMembersThisOrga' => true,
                'showFormerMembers' => true,
                'showUserUUID' => true,
                'useConditions' => false,
                'useOrderBy' => $useOrderBy
            )
        );
    } elseif (($getMembersShowFilter === 3) && $gCurrentUser->isAdministratorUsers()) {
        $mainSql = $contactsListConfig->getSql(
            array(
                'showAllMembersDatabase' => true,
                'showUserUUID' => true,
                'useConditions' => false,
                'useOrderBy' => $useOrderBy
            )
        );
    } else {
        $mainSql = $contactsListConfig->getSql(
            array(
                'showRolesMembers' => $gCurrentUser->getRolesViewProfiles(),
                'showUserUUID' => true,
                'useConditions' => false,
                'useOrderBy' => $useOrderBy
            )
        );
    }

    if ($gDbType === 'pgsql') {
        $sqlOrganizationConcat = ' STRING_AGG(CAST(cat_org.cat_org_id AS text), \',\' ORDER BY cat_org.cat_org_id) ';
    } else {
        $sqlOrganizationConcat = ' GROUP_CONCAT(DISTINCT cat_org.cat_org_id ORDER BY cat_org.cat_org_id SEPARATOR \',\') ';
    }

    $mainSql = 'SELECT DISTINCT ' . $contactsOfThisOrganizationSelect . ' AS member_this_orga, ' . $formerContactsOfThisOrganizationSelect . ' AS former_member_this_orga, ' . $contactsOfOtherOrganizationSelect . ' AS member_other_orga, ' . $formerContactsOfOtherOrganizationSelect . ' AS former_member_other_orga,
                (SELECT ' . $sqlOrganizationConcat . '
                    FROM ' . TBL_MEMBERS . ' AS mem_org
                    INNER JOIN ' . TBL_ROLES . ' AS rol_org
                        ON rol_org.rol_id = mem_org.mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . ' AS cat_org
                        ON cat_org.cat_id = rol_org.rol_cat_id
                    WHERE mem_org.mem_usr_id = usr_id
                ) AS member_org_ids,
                usr_login_name as loginname,
                (SELECT email.usd_value FROM ' . TBL_USER_DATA . ' email
                  WHERE  email.usd_usr_id = usr_id
                    AND email.usd_usf_id = ? /* $gProfileFields->getProperty(\'email\', \'usf_id\') */
                 ) AS member_email, ' .
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
        $sql = $mainSql . $orderCondition . $limitCondition;
    } else {
        $sql = 'SELECT *
              FROM (' . $mainSql . ') AS members
               ' . $searchCondition
            . $orderCondition
            . $limitCondition;
    }
    $queryParamsMain = array_merge($queryParamsEmail, $queryParamsSearch);
    $mglStatement = $gDb->queryPrepared($sql, $queryParamsMain); // TODO add more params

    $currentOrgName = $gCurrentOrganization->getValue('org_longname');
    $rowNumber = $getStart; // count for every row

    // get count of all members and store into json
    $countSql = 'SELECT COUNT(*) AS count_total FROM (' . $mainSql . ') contacts ';
    $countTotalStatement = $gDb->queryPrepared($countSql, $queryParamsEmail); // TODO add more params
    $jsonArray['recordsTotal'] = (int)$countTotalStatement->fetchColumn();

    $jsonArray['data'] = array();

    while ($row = $mglStatement->fetch(PDO::FETCH_BOTH)) {
        ++$rowNumber;
        if (($getMembersShowFilter < 3) && $gCurrentUser->isAdministratorUsers()) {
            $columnNumberJson = 3;
        } else {
            $columnNumberJson = 2;
        }
        $ColumnNumberSql = 8;

        $contactsOfThisOrganization = (bool)$row['member_this_orga'];
        $formerContactsOfThisOrganization = (bool)$row['former_member_this_orga'];
        $contactsOfOtherOrganization = (bool)$row['member_other_orga'];
        $formerContactsOfOtherOrganization = (bool)$row['former_member_other_orga'];

        $otherOrgName = '';
        if ($row['member_org_ids'] !== null) {
            $usrOrgIds = explode(',', $row['member_org_ids']);
            if (count($usrOrgIds) > 0) {
                // remove current organization from the list of user organizations
                $usrOrgIds = array_values(array_diff($usrOrgIds, array($gCurrentOrgId)));
                // if user is member of more than one organization then show the name of the first organization
                if (count($usrOrgIds) > 0) {
                    $otherOrg = new Organization($gDb, $usrOrgIds[0]);
                    $otherOrgName = $otherOrg->getValue('org_longname');
                }
            }
        }

        // Create row and add first column
        if (($getMembersShowFilter < 3) && $gCurrentUser->isAdministratorUsers()) {
            $columnNumberValues = '2';
            $columnValues = array('DT_RowId' => 'row_members_' . $row['usr_uuid'], '0' => '<input type="checkbox"/>', '1' => $rowNumber);
        } else {
            $columnNumberValues = '1';
            $columnValues = array('DT_RowId' => 'row_members_' . $row['usr_uuid'], '0' => $rowNumber);
        }

        // Add icon for member or no member of the organization
        if ($contactsOfThisOrganization) {
            $icon = 'bi-person-fill-check';
            $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($currentOrgName));
        } elseif ($formerContactsOfThisOrganization) {
            $icon = 'bi-person-fill-dash';
            $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($currentOrgName));
        } elseif ($contactsOfOtherOrganization) {
            $icon = 'bi-person-fill-check text-warning';
            $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($otherOrgName));
        } elseif ($formerContactsOfOtherOrganization) {
            $icon = 'bi-person-fill-dash text-warning';
            $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', array($otherOrgName));
        } else {
            $icon = 'bi-person-fill-x text-danger';
            $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ANY_ORGANIZATION');
        }

        // add icon link to user profile
        $columnValues[$columnNumberValues] = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])) . '">
        <i class="bi ' . $icon . '" data-bs-toggle="tooltip" title="' . $iconText . '"></i></a>';

        // add all columns of the list configuration to the json array
        // start columnNumber with 4 because the first 2 columns are not of the list configuration
        for ($columnNumber = 1; $columnNumber <= $contactsListConfig->countColumns(); $columnNumber++) {
            if (!empty($row[$ColumnNumberSql])) {
                $columnValues[(string)$columnNumberJson] = $contactsListConfig->convertColumnContentForOutput($columnNumber, 'html', $row[$ColumnNumberSql], $row['usr_uuid'], false);
            } else {
                $columnValues[(string)$columnNumberJson] = '';
            }

            $columnNumberJson++;
            $ColumnNumberSql++;
        }

        // Add "user-administration icons"
        $userAdministration = '';

        // Administrators can change or send password if login is configured and user is member of current organization
        if ($contactsOfThisOrganization && $gCurrentUser->isAdministrator()
            && !empty($row['loginname']) && $row['usr_uuid'] !== $gCurrentUserUUID) {
            if (!empty($row['member_email']) && $gSettingsManager->getBool('system_notifications_enabled')) {
                // if email is set and systemmails are activated then administrators can send a new password to user
                $userAdministration = '
                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                        data-message="' . $gL10n->get('SYS_SEND_NEW_LOGIN', array($row['first_name'] . ' ' . $row['last_name'])) . '"
                        data-href="callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'send_login', 'user_uuid' => $row['usr_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-key-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_USERNAME_PASSWORD') . '"></i></a>';
            } else {
                // if user has no email or send email is disabled then administrators could set a new password
                $userAdministration = '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/password.php', array('user_uuid' => $row['usr_uuid'])) . '">' .
                    '<i class="bi bi-key-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_CHANGE_PASSWORD') . '"></i></a>';
            }
        }

        if ($gCurrentUser->isAdministratorUsers()) {
            // add link to send email to user
            if (!empty($row['member_email'])) {
                if (!($gSettingsManager->getInt('mail_module_enabled') > 0)) {
                    $mailLink = 'mailto:' . $row['member_email'];
                } else {
                    $mailLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $row['usr_uuid']));
                }
                $userAdministration .= '<a class="admidio-icon-link" href="' . $mailLink . '">' .
                    '<i class="bi bi-envelope" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_EMAIL_TO', array($row['member_email'])) . '"></i></a>';
            }

            $userAdministration .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('user_uuid' => $row['usr_uuid'], 'copy' => 1)) . '">' .
                '<i class="bi bi-copy" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_COPY') . '"></i></a>';

            // add link to edit user, but only edit users who are members of the current organization
            if ($contactsOfThisOrganization || !$contactsOfOtherOrganization) {
                $userAdministration .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('user_uuid' => $row['usr_uuid'])) . '">' .
                    '<i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT_USER') . '"></i></a>';
            }

            // add link to delete user btw. remove user from the current organization, no one is allowed to remove their own profile
            if (!$contactsOfOtherOrganization && $gCurrentUser->isAdministrator() && $row['usr_uuid'] !== $gCurrentUserUUID) {
                if ($contactsOfThisOrganization) {
                    // User is ONLY member of this organization -> ask if user should make to former member or delete completely
                    $userAdministration .= '
                        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('user_uuid' => $row['usr_uuid'], 'mode' => 'delete_explain_msg', 'custom_callback' => ($getMembersShowFilter >= 2 ))) . '">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_REMOVE_CONTACT') . '"></i>
                        </a>';
                } else {
                    // User is not member of any organization -> ask if delete completely
                    $userAdministration .= '
                        <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                            data-message="' . $gL10n->get('SYS_USER_DELETE_DESC', array($row['first_name'] . ' ' . $row['last_name'])) . '"
                            data-href="callUrlHideElement(\'row_members_' . $row['usr_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'delete', 'user_uuid' => $row['usr_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_REMOVE_CONTACT') . '"></i>
                        </a>';
                }
            } elseif ($contactsOfOtherOrganization && $contactsOfThisOrganization) {
                // User could only be removed from this organization -> ask so
                $userAdministration .= '
                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                        data-message="' . $gL10n->get('SYS_END_MEMBERSHIP_OF_USER', array($row['first_name'] . ' ' . $row['last_name'], $gCurrentOrganization->getValue('org_longname'))) . '"
                        data-href="callUrlHideElement(\'row_members_' . $row['usr_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'remove', 'user_uuid' => $row['usr_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_REMOVE_CONTACT') . '"></i>
                    </a>';
            }
        }

        $columnValues[(string)$columnNumberJson] = $userAdministration;

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
                  FROM (' . $mainSql . ') AS members
                       ' . $searchCondition;
            $countFilteredStatement = $gDb->queryPrepared($sql, $queryParamsMain);

            $jsonArray['recordsFiltered'] = (int)$countFilteredStatement->fetchColumn();
        }
    } else {
        $jsonArray['recordsFiltered'] = $jsonArray['recordsTotal'];
    }

    echo json_encode($jsonArray);
} catch (Exception $e) {
    $jsonArray['error'] = $e->getMessage();
    echo json_encode($jsonArray);
    exit();
}
