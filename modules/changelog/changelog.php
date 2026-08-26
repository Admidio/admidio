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
 * group_changes    : Show the entries that were written by the same change as one row that can be
 *                    expanded. Active by default, see filter_submitted.
 * filter_submitted : Marks a request that comes from the filter form. Only then does a missing
 *                    group_changes mean that the checkbox was unticked and not that the request
 *                    simply does not carry the parameter.
 * filter_date_from : is set to actual date,
 *                    if no date information is delivered
 * filter_date_to   : is set to 31.12.9999,
 *                    if no date information is delivered
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\DateTimeUtils;
use Admidio\Infrastructure\Language;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Roles\Entity\Role;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');


try {

    // calculate default date from which the history should be shown
    $filterDateFrom = ChangelogService::getDefaultFilterDateFrom();


    // Initialize and check the parameters
    $getTable = admFuncVariableIsValid($_GET, 'table','string');
    $getTables = ($getTable !== null && $getTable != "") ? array_map('trim', explode(",", $getTable)) : [];
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getId = admFuncVariableIsValid($_GET, 'id', 'int');
    $getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'string');
    // A checkbox that is not ticked is not submitted at all, so the hidden filter_submitted tells
    // the two cases apart: without it the request does not come from the filter form and grouping
    // is used, with it a missing group_changes means that the user has unticked the checkbox.
    $filterSubmitted = admFuncVariableIsValid($_GET, 'filter_submitted', 'bool', array('defaultValue' => false));
    $getGroupChanges = admFuncVariableIsValid($_GET, 'group_changes', 'bool', array('defaultValue' => !$filterSubmitted));
    $getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

    if ($gSettingsManager->getInt('changelog_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create a user object. Will be filled if the log of one particular user is requested.
    $user = new User($gDb, $gProfileFields);
    // User log contains at most four tables: User, user_data, user_relations and members -> they have many more permissions than other tables!
    $isUserLog = ChangelogService::isUserHistory($getTables);
    if ($isUserLog) {
        if (!empty($getUuid)) {
            $user->readDataByUuid($getUuid);
        } elseif (!empty($getId)) {
            $user->readDataById($getId);
        }
        if (!$user->isNewRecord()) {
            // Address the user by uuid from here on: for the user_data table the record id is the
            // id of the data row and not of the user, so filtering by id would match the log
            // entries of a different user.
            $getUuid = $user->getValue('usr_uuid');
            $getId = 0;
        }
    }

    // All view permissions are evaluated in one place. An empty list means no access at all.
    $subject = $user->isNewRecord() ? null : $user;
    $readableTables = ChangelogService::getReadableTables($gCurrentUser, $getTables, $subject);
    if (count($readableTables) === 0) {
        throw new Exception('SYS_NO_RIGHTS');
    }



    // Page Headline: Depending on the tables and ID/UUID/RelatedIDs, we have different cases:
    //  * Userlog (tables users,user_data,members): Either "Change history of NAME" or "Change history of user data and memberships" (if no ID/UUID)
    //  * No object ID/UUIDs given: "Change history: Table description 1[, Table description 2, ...]" or "Change history"  (if no tables given)
    //  * Only one table (table column will be hidden): "Change history: OBJECTNAME (Table description)"
    //  *
    $tableTitles = array_map([ChangelogService::class, 'getTableLabel'], $getTables);
    // set headline of the script
    if ($isUserLog && (!empty($getId) || !empty($getUuid))) {
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
    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // filter_date_from and filter_date_to can use the internal ISO format
    // or the configured Admidio date format.
    $objDateFrom = DateTimeUtils::parseDate($getDateFrom, '1970-01-01');
    $objDateTo = DateTimeUtils::parseDate($getDateTo, DATE_NOW);

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern = $objDateTo->format('Y-m-d');
    $dateToHtml = $objDateTo->format($gSettingsManager->getString('system_date'));


    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('adm_changelog', $headline);
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
    $form->addInput('filter_submitted', '', '1', array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
    // If the box is unticked, the table shows one row per logged entry instead.
    $form->addCheckbox('group_changes', $gL10n->get('SYS_CHANGELOG_GROUP_CHANGES'), $getGroupChanges);
    $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
    $form->addToHtmlPage();

    // Switching between the grouped and the single entry view reloads the page, so that the table
    // is built for the right kind of rows.
    $page->addJavascript('
        $("#group_changes").change(function() {
            $("#adm_navbar_filter_form").submit();
        });
    ', true);

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

    if ($showTableColumn) {
        $columnHeading[] = $gL10n->get('SYS_DATA_AREA');
    }
    $columnHeading[] = $gL10n->get('SYS_NAME');
    if ($showRelatedColumn) {
        $columnHeading[] = $gL10n->get('SYS_RELATED_TO');
    }
    $columnHeading[] = $gL10n->get('SYS_FIELD');
    // The order of the value columns must match the order in which changelog_data.php writes them.
    $columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
    $columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
    $columnHeading[] = $gL10n->get('SYS_CHANGED_BY');
    $columnHeading[] = $gL10n->get('SYS_DATE_MODIFIED');

    $page->assignSmartyVariable('headers', $columnHeading);

    $filterFields = array(
        'table' => $getTable,
        'uuid' => $getUuid,
        'id' => $getId,
        'related_id' => $getRelatedId,
        'filter_date_from' => $getDateFrom,
        'filter_date_to' => $getDateTo,
        'group_changes' => (int)$getGroupChanges
    );

    $table->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog/changelog_data.php', $filterFields));
//    $table->setColumnAlignByArray($columnAlignment);
    // Only the running number cannot be sorted. All other columns are sorted server-side, see the
    // whitelist $orderColumns in changelog_data.php, which must stay in sync with the columns here.
    $table->disableColumnsSort(array(1));
    // sort by the date of the change (last column), newest first
    $table->setOrderColumns(array(array(count($columnHeading), 'desc')));
    $table->setColumnsNotHideResponsive(array(count($columnHeading)));
    // $table->setDatatablesRowsPerPage($gSettingsManager->getInt('contacts_per_page'));
    // The changelog is not bounded in size, so it must not offer to read all entries at once.
    // The largest selectable page length matches the limit enforced in changelog_data.php.
    $table->setRowsPerPageMenuEntries(array(10 => '10', 25 => '25', 50 => '50', 100 => '100', 500 => '500', 1000 => '1000'));
    $table->disableShowAllEntries();
    $table->setMessageIfNoRowsFound('SYS_CHANGE_HISTORY_NO_ENTRIES');
    $table->createJavascript(0, count($columnHeading));

    // A row that stands for a change of several fields can be expanded. The entries of that change
    // are requested from the same script and are inserted as one additional row below it. They are
    // deliberately not built with the child rows of DataTables, because the responsive extension of
    // the table already uses those for the columns it hides on small screens.
    $detailsUrl = SecurityUtils::encodeUrl(
        ADMIDIO_URL . FOLDER_MODULES . '/changelog/changelog_data.php',
        array_merge($filterFields, array('draw' => 1, 'start' => 0, 'length' => 1000))
    );

    $page->addJavascript('
        $("#adm_history_table tbody").on("click", "a.adm-changelog-details", function(event) {
            event.preventDefault();

            var link = $(this);
            var icon = link.find("i");
            var row = link.closest("tr");

            if (row.next("tr.adm-changelog-detail-row").length > 0) {
                row.next("tr.adm-changelog-detail-row").remove();
                icon.removeClass("bi-chevron-down").addClass("bi-chevron-right");
                return;
            }

            icon.removeClass("bi-chevron-right").addClass("bi-chevron-down");
            row.after("<tr class=\"adm-changelog-detail-row\"><td class=\"p-0\" colspan=\"' . count($columnHeading) . '\"></td></tr>");

            var cell = row.next("tr.adm-changelog-detail-row").children("td");
            cell.html("<div class=\"adm-changelog-details-table\"><i class=\"spinner-border spinner-border-sm\"></i></div>");

            $.getJSON("' . $detailsUrl . '&change_uuid=" + encodeURIComponent(link.data("change-uuid")), function(json) {
                if (json.error) {
                    cell.html("<div class=\"adm-changelog-details-table\"></div>").children("div").text(json.error);
                    return;
                }

                // The script decides which value columns this change needs and sends the headings
                // along with the rows, so there is nothing to interpret here.
                var html = "<div class=\"adm-changelog-details-table table-responsive\">"
                    + "<table class=\"table table-sm mb-0\"><thead><tr>";
                $.each(json.columns, function(index, heading) {
                    html += "<th>" + heading + "</th>";
                });
                html += "</tr></thead><tbody>";

                $.each(json.data, function(index, entry) {
                    html += "<tr>";
                    $.each(entry, function(cellIndex, cell) {
                        html += "<td>" + cell + "</td>";
                    });
                    html += "</tr>";
                });
                cell.html(html + "</tbody></table></div>");
            });
        });
    ', true);




    $page->addHtml('<div class="alert alert-danger form-alert" id="DT_notice" style="display: none;"></div>');
    $page->addHtmlByTemplate('modules/changelog.list.tpl');
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
