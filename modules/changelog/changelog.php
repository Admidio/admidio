<?php
/**
 ***********************************************************************************************
 * Show history of generic database record changes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * table            : The type of changes to be listed (name of the DB table, excluding the prefix)
 * id...............: If set only show the change history of that database record
 * uuid             : If set only show the change history of that database record
 * related_id       : If set only show the change history of objects related to that id (e.g. membership of a role/group)
 * filter_date_from : is set to actual date,
 *                    if no date information is delivered
 * filter_date_to   : is set to 31.12.9999,
 *                    if no date information is delivered
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Language;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Roles\Entity\Role;
use Admidio\Hooks\Hooks;



require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');


try {

    // calculate default date from which the profile fields history should be shown
    $filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
    $filterDateFrom->modify('-' . $gSettingsManager->getInt('contacts_field_history_days') . ' day');


    // Initialize and check the parameters
    $getTable = admFuncVariableIsValid($_GET, 'table','string');
    $getTables = ($getTable !== null && $getTable != "") ? array_map('trim', explode(",", $getTable)) : [];
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'string');
    $getId = admFuncVariableIsValid($_GET, 'id', 'int');
    $getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'string');
    $getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

    $haveID = !empty($getId) || !empty($getUuid);

    // named array of permission flag (true/false/"user-specific" per table)
    $tablesPermitted = ChangelogService::getPermittedTables($gCurrentUser);
    if ($gSettingsManager->getInt('changelog_module_enabled') == 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }
    if ($gSettingsManager->getInt('changelog_module_enabled') == 2 && !$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }
    $accessAll = $gCurrentUser->isAdministrator() ||
        (!empty($getTables) && empty(array_diff($getTables, $tablesPermitted)));

    // create a user object. Will fill it later if we encounter a user id
    $user = new User($gDb, $gProfileFields);
    $userUuid = null;
    // User log contains at most four tables: User, user_data, user_relations and members -> they have many more permissions than other tables!
    $isUserLog = (!empty($getTables) && empty(array_diff($getTables, ['users', 'user_data', 'user_relations', 'members'])));
    if ($isUserLog) {
        if (!empty($getUuid)) {
            $user->readDataByUuid($getUuid);
        } elseif (!empty($getId)) {
            $user->readDataById($getId);
        }
        if (!$user->isNewRecord()) {
            $userUuid = $user->getValue('usr_uuid');
        }
    }

    // Access permissions:
    // Special case: Access to profile history on a per-user basis: Either admin or at least edit user rights are required, or explicit access to the desired user:
    if (!$accessAll &&
            !(!empty($getTables) && empty(array_diff($getTables, $tablesPermitted))) &&
            $isUserLog) {
        // If a user UUID is given, we need access to that particular user
        // if no UUID is given, isAdministratorUsers permissions are required
        if (($userUuid === '' && !$gCurrentUser->isAdministratorUsers())
            || ($userUuid !== '' && !$gCurrentUser->hasRightEditProfile($user))) {
//                throw new Exception('SYS_NO_RIGHTS');
                $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
       }
    }



    // Page Headline: Depending on the tables and ID/UUID/RelatedIDs, we have different cases:
    //  * Userlog (tables users,user_data,members): Either "Change history of NAME" or "Change history of user data and memberships" (if no ID/UUID)
    //  * No object ID/UUIDs given: "Change history: Table description 1[, Table description 2, ...]" or "Change history"  (if no tables given)
    //  * Only one table (table column will be hidden): "Change history: OBJECTNAME (Table description)"
    //  *
    $tableTitles = array_map([ChangelogService::class, 'getTableLabel'], $getTables);
    // set headline of the script
    if ($isUserLog && $haveID) {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->readableName()));
    } elseif ($isUserLog) {
        $headline = $gL10n->get('SYS_CHANGE_HISTORY_USERDATA');
    } elseif (empty($getUuid) && empty($getId) && empty($getRelatedId)) {
        if (count($tableTitles) > 0) {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY_GENERIC', [implode(', ', $tableTitles)]);
        } else {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY');
        }
    } else {
        $objName = '';
        $useTable = $getTables[0]??'users';
        $object = ChangelogService::getObjectForTable($useTable);
        if ($useTable == 'members') {
            // Memberships are special-cased, as the membership Role UUID is stored as relatedID
            $object = new Role($gDb);
            $object->readDataByUuid($getRelatedId);
        }
        // We have an ID or UUID and/or a relatedID -> Object depends on the table(s)!
        if (!empty($object)) {
            if ($useTable == 'members') {
                // already handled
            } elseif (!empty($getUuid)) {
                $object->readDataByUuid($getUuid);
            } elseif (!empty($getId)) {
                $object->readDataById($getId);
            }
            $objName = $object->readableName();
        }
        if (count($getTables) == 0) {
            if (empty($objName)) {
                $headline = $gL10n->get('SYS_CHANGE_HISTORY');
            } else {
                $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', [$objName]);
            }
        } else {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY_GENERIC2', [$objName, implode(', ', $tableTitles)]);
        }
    }
    $headline =  Hooks::apply_filters('changelog_headline', $headline);

    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // filter_date_from and filter_date_to can have different formats
    // now we try to get a default format for intern use and html output
    $objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
    if ($objDateFrom === false) {
        // check if date has system format
        $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
        if ($objDateFrom === false) {
            $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    $objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
    if ($objDateTo === false) {
        // check if date has system format
        $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
        if ($objDateTo === false) {
            $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern = $objDateTo->format('Y-m-d');
    $dateToHtml = $objDateTo->format($gSettingsManager->getString('system_date'));


    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-history', $headline);
    $page->setContentFullWidth();

    // Logic for hiding certain columns:
    // If we have only one table name given, hide the table column
    // If we view the user profile field changes page, hide the column, too
    $showTableColumn = true;
    if (count($getTables) == 1) {
        $showTableColumn = false;
    }
    // If none of the related-to values is set, hide the related_to column
    $showRelatedColumn = true;
    $noShowRelatedTables = ['user_fields', 'user_field_select_options', 'users', 'user_data'];


    $form = new FormPresenter(
        'adm_navbar_filter_form',
        'sys-template-parts/form.filter.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/changelog/changelog.php',
        $page,
        array('type' => 'navbar', 'setFocus' => false)
    );

    // create filter menu with input elements for start date and end date
    $form->addInput('table', '', $getTable, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('uuid', '', $getUuid, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('id', '', $getId, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('related_id', '', $getRelatedId, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
    $form->addToHtmlPage();

    $table = new DataTables($page, 'adm_history_table');


    /* For now, simply show all column of the changelog table. As time permits, we can improve this by hiding unneccessary columns and by better naming columns depending on the table.
     *
     * Columns to be displayed / hidden:
     *   0. If there is only one value in the table column, hide it and display it in the title of the page.
     *   1. If there is a single ID or UUID, the record name is not displayed. It should be shown in the title of the page.
     *   2. If there is a single related-to ID, and the table is memberships, the role name should already be displayed in the title, so don't show it again.
     *   3. If none of the entries have a related ID, hide the related ID column.
     */
    $columnHeading = array();
    $columnHeading[] = $gL10n->get('SYS_ABR_NO');

    // $table->setDatatablesOrderColumns(array(array(8, 'desc')));
    if ($showTableColumn) {
        $columnHeading[] = $gL10n->get('SYS_TABLE');
    }
    $columnHeading[] = $gL10n->get('SYS_NAME');
    if ($showRelatedColumn) {
        $columnHeading[] = $gL10n->get('SYS_RELATED_TO');
    }
    $columnHeading[] = $gL10n->get('SYS_FIELD');
    $columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
    $columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
    $columnHeading[] = $gL10n->get('SYS_EDITED_BY');
    $columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

    $page->assignSmartyVariable('headers', $columnHeading);

    $filterFields = array(
        'table' => $getTable,
        'uuid' => $getUuid,
        'id' => $getId,
        'related_id' => $getRelatedId,
        'filter_date_from' => $getDateFrom,
        'filter_date_to' => $getDateTo
    );

    $table->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog/changelog_data.php', $filterFields));
//    $table->setColumnAlignByArray($columnAlignment);
    $table->disableColumnsSort(array(1, count($columnHeading)));// disable sort in last column
    $table->setColumnsNotHideResponsive(array(count($columnHeading)));
    // $table->setDatatablesRowsPerPage($gSettingsManager->getInt('contacts_per_page'));
    $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES');
    $table->createJavascript(0, count($columnHeading));




    $page->addHtml('<div class="alert alert-danger form-alert" id="DT_notice" style="display: none;"></div>');
    $page->addHtmlByTemplate('modules/changelog.list.tpl');
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
