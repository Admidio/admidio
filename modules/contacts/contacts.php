<?php
/**
 ***********************************************************************************************
 * Show and manage all members of the organization
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mem_show_filter - 0  : (Default) Show only active contacts for current organization
 *                   1  : Show only inactive contacts for current organization
 *                   2  : Show active and inactive contacts for current organization
 *                   3  : Show active and inactive contacts for all organizations (only Admin)
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMembersShowFilter = admFuncVariableIsValid($_GET, 'mem_show_filter', 'int', array('defaultValue' => 0));

    // set headline of the script
    $headline = $gL10n->get('SYS_CONTACTS');// Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-person-vcard-fill');

    if ($gSettingsManager->getInt('contacts_list_configuration') === 0) {
        throw new Exception('No contact list configuration was set in the preferences.');
    }
    $contactsListConfig = new ListConfiguration($gDb, $gSettingsManager->getInt('contacts_list_configuration'));
    $_SESSION['contacts_list_configuration'] = $contactsListConfig;

    // Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-contacts', $headline);
    $page->setContentFullWidth();

    if ($gCurrentUser->isAdministratorUsers()) {
        $page->addJavascript('
            $("#menu_item_contacts_create_contact").attr("href", "javascript:void(0);");
            $("#menu_item_contacts_create_contact").attr("data-href", "' . ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_new.php");
            $("#menu_item_contacts_create_contact").attr("class", "nav-link btn btn-secondary openPopup");

            // change mode of users that should be shown
            $("#mem_show_filter").on("change", function() {
                var form = $("#adm_navbar_filter_form");
                var contactsSelect = $("#mem_show_filter");
                contactsSelect.attr("name", "mem_show_filter");
                form.submit();
            });', true);

        $page->addPageFunctionsMenuItem(
            'menu_item_contacts_create_contact',
            $gL10n->get('SYS_CREATE_CONTACT'),
            ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_new.php',
            'bi-plus-circle-fill'
        );

        ChangelogService::displayHistoryButton($page, 'contacts', 'users,user_data,members');

        // create filter menu with elements for category
        $form = new FormPresenter(
            'adm_navbar_filter_form',
            'sys-template-parts/form.filter.tpl',
            '',
            $page,
            array('type' => 'navbar', 'setFocus' => false)
        );


        if ($gCurrentUser->isAdministrator() && $gSettingsManager->getBool('contacts_show_all')) {
            $selectBoxValues = array(
                '0' => array('0', $gL10n->get('SYS_ACTIVE_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '1' => array('1', $gL10n->get('SYS_FORMER_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '2' => array('2', $gL10n->get('SYS_ALL_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '3' => array('3', $gL10n->get('SYS_ALL_CONTACTS'), $gL10n->get('SYS_ALL_ORGANIZATIONS'))
            );
        } else {
            $selectBoxValues = array(
                '0' => $gL10n->get('SYS_ACTIVE_CONTACTS'),
                '1' => $gL10n->get('SYS_FORMER_CONTACTS'),
                '2' => $gL10n->get('SYS_ALL_CONTACTS')
            );
        }

        // filter all items
        $form->addSelectBox(
            'mem_show_filter',
            $gL10n->get('SYS_CONTACTS'),
            $selectBoxValues,
            array(
                'defaultValue' => $getMembersShowFilter,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addToHtmlPage();

        // show link to import users
        $page->addPageFunctionsMenuItem(
            'menu_item_contacts_import_users',
            $gL10n->get('SYS_IMPORT_CONTACTS'),
            ADMIDIO_URL . FOLDER_MODULES . '/contacts/import.php',
            'bi-upload'
        );
    } else {
        $contactsListConfig->setModeShowOnlyNames();
    }
    if ($gCurrentUser->isAdministrator()) {
        // show link to maintain profile fields
        $page->addPageFunctionsMenuItem(
            'menu_item_contacts_profile_fields',
            $gL10n->get('SYS_EDIT_PROFILE_FIELDS'),
            ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php',
            'bi-ui-radios'
        );
    }
    $orgName = $gCurrentOrganization->getValue('org_longname');// Create table object
    $contactsTable = new HtmlTable('adm_contacts_table', $page, true, true, 'table table-condensed');// create array with all column heading values
    $columnHeading = $contactsListConfig->getColumnNames();

    if (($getMembersShowFilter === 2) && $gCurrentUser->isAdministratorUsers()) {
        array_unshift(
            $columnHeading,
            '<input type="checkbox" id="select-all" data-bs-toggle="tooltip" data-bs-original-title="' . $gL10n->get('SYS_SELECT_ALL') . '"/>',
            $gL10n->get('SYS_ABR_NO'),
            '<i class="bi bi-person-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ORGANIZATION_AFFILIATION') . '"></i>'
        );
    } else {    
        array_unshift(
            $columnHeading,
            $gL10n->get('SYS_ABR_NO'),
            '<i class="bi bi-person-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ORGANIZATION_AFFILIATION') . '"></i>'
        );
    }

    $columnHeading[] = '&nbsp;';
    $columnAlignment = $contactsListConfig->getColumnAlignments();
    if (($getMembersShowFilter === 2) && $gCurrentUser->isAdministratorUsers()) {
        array_unshift($columnAlignment, 'center', 'left', 'left');
    } else {
        array_unshift($columnAlignment, 'left', 'left');
    }
    
    $columnAlignment[] = 'right';
    $contactsTable->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_data.php', array('mem_show_filter' => $getMembersShowFilter)));
    $contactsTable->setColumnAlignByArray($columnAlignment);
    $contactsTable->disableDatatablesColumnsSort(array(1, 2, count($columnHeading)));// disable sort in last column
    $contactsTable->setDatatablesColumnsNotHideResponsive(array(count($columnHeading)));
    $contactsTable->addRowHeadingByArray($columnHeading);
    $contactsTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

    if (($getMembersShowFilter === 2) && $gCurrentUser->isAdministratorUsers()) {
        // add the checkbox for selecting items and action buttons
        $page->addJavascript('
            $(document).ready(function() {
                var $table = $("#adm_contacts_table");

                $table.one("init.dt", function() {
                    var tableApi = $table.DataTable();

                    // base URLs
                    var editUrlBase = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('mode' => 'html_selection')) . '";
                    var explainDeleteUrlBase = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'delete_explain_msg')) . '";

                    // cache jQuery objects
                    var $editButton = $("#edit-selected").css("display", "block");
                    var $deleteButon = $("#delete-selected").css("display", "block");
                    var $headChk = $table.find("thead input[type=checkbox]");
                    var $rowChks = function() { return $table.find("tbody input[type=checkbox]"); };
                    var $actions = $("#adm_contacts_table_select_actions");

                    // master list of selected IDs
                    var selectedIds = [];

                    function anySelected() {
                        return selectedIds.length > 0;
                    }

                    function refreshActions() {
                        $editButton.prop("disabled", !anySelected());
                        $deleteButon.prop("disabled", !anySelected());
                    }

                    function updateHeaderState() {
                        var total = $rowChks().length;
                        var checked = $rowChks().filter(":checked").length;
                        if (checked === 0) {
                            $headChk.prop({ checked: false, indeterminate: false });
                        } else if (checked === total) {
                            $headChk.prop({ checked: true, indeterminate: false });
                        } else {
                            $headChk.prop({ checked: false, indeterminate: true });
                        }
                    }

                    // header-checkbox → select/unselect *all* rows
                    $headChk.on("change", function() {
                        var checkAll = this.checked;
                        selectedIds = [];

                        if (checkAll) {
                            // grab every row (even on other pages)
                            tableApi.rows().every(function() {
                                if ($(this.node()).is(":visible")){
                                    selectedIds.push(this.node().id.replace(/^row_members_/, ""));
                                }
                            });
                        }

                        // toggle checked state of all row checkboxes
                        $rowChks().prop("checked", checkAll);

                        updateHeaderState();
                        refreshActions();
                    });

                    // individual row-checkbox → toggle just that ID
                    $table.on("change", "tbody input[type=checkbox]", function() {
                        var id = this.closest("tr").id.replace(/^row_members_/, "");
                        var idx = selectedIds.indexOf(id);
                        if (this.checked && idx === -1) {
                            selectedIds.push(id);
                        } else if (!this.checked && idx !== -1) {
                            selectedIds.splice(idx, 1);
                        }

                        updateHeaderState();
                        refreshActions();
                    });

                    // bulk-delete button → fire Admidio’s openPopup against explain_msg URL
                    $actions.off("click", "#delete-selected").on("click", "#delete-selected", function() {
                        // build uuids[] querystring
                        var qs = selectedIds.map(function(id) {
                            return "user_uuids[]=" + encodeURIComponent(id);
                        }).join("&");

                        // full URL to your explain_msg endpoint
                        var popupUrl = explainDeleteUrlBase + "&" + qs;

                        // create a temporary <a class="openPopup"> to invoke Admidio’s AJAX popup loader
                        $("<a>", {
                            href: "javascript:void(0);",
                            class: "admidio-icon-link openPopup",
                            "data-href": popupUrl
                        }).appendTo("body")
                          .click()    // trigger the built-in openPopup handler
                          .remove();

                        // when the popup closes, unselect all items
                        $(document).one("hidden.bs.modal", function() {
                            selectedIds = [];
                            $headChk.prop({ checked: false, indeterminate: false });
                            $rowChks().prop("checked", false);
                            
                            // initialize button states
                            updateHeaderState();
                            refreshActions();
                        });
                    });

                    // bulk-edit button → fire Admidio’s openPopup against item_edit URL
                    $actions.off("click", "#edit-selected").on("click", "#edit-selected", function() {
                        // build uuids[] querystring
                        var qs = selectedIds.map(function(id) {
                            return "user_uuids[]=" + encodeURIComponent(id);
                        }).join("&");

                        // full URL to the edit endpoint
                        var editUrl = editUrlBase + "&" + qs;

                        // open the editUrl directly in the current window
                        window.location.href = editUrl;
                        
                        // initialize button states
                        updateHeaderState();
                        refreshActions();
                    });

                    // initialize button states
                    refreshActions();
                });
            });'
            , true
        );

        $contactsTable->setDatatablesRowsPerPage(-1); // show all rows in the table

        $page->addHtml('
            <div class="table-responsive">
                <div id="adm_contacts_table_select_actions" class="mb-3">
                    <ul class="nav admidio-menu-function-node">
                        <li class="nav-item">
                            <button id="edit-selected" class="btn nav-link btn-primary" style="display: none;">
                                <i class="bi bi-pencil-square me-1"></i>' . $gL10n->get('SYS_EDIT_SELECTION') . '
                            </button>
                        </li>
                        <li class="nav-item">
                            <button id="delete-selected" class="btn nav-link btn-primary" style="display: none;">
                                <i class="bi bi-trash me-1"></i>' . $gL10n->get('SYS_DELETE_SELECTION') . '
                            </button>
                        </li>
                    </ul>
                </div>
        ');
        $page->addHtml($contactsTable->show());// show html of complete page
        $page->addHtml('</div>');// close table responsive div
    } else {
        $contactsTable->setDatatablesRowsPerPage($gSettingsManager->getInt('contacts_per_page'));
        $page->addHtml($contactsTable->show());// show html of complete page
    }

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
