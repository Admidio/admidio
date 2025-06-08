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
 * mem_show_filter - 0  : (Default) Show only active contacts for current or all organizations
 *                   1  : Show only inactive contacts for current or all organizations
 *                   2  : Show active and inactive contacts for current or all organizations
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
    $getMembersShowFiler = admFuncVariableIsValid($_GET, 'mem_show_filter', 'int', array('defaultValue' => 0));

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


        if ($gCurrentUser->isAdministrator()) {
            $selectBoxValues = array(
                '0' => array('0', $gL10n->get('SYS_ACTIVE_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '1' => array('1', $gL10n->get('SYS_FORMER_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '2' => array('2', $gL10n->get('SYS_ALL_CONTACTS'), $gL10n->get('SYS_CURRENT_ORGANIZATION')),
                '3' => array('3', $gL10n->get('SYS_FORMER_CONTACTS'), $gL10n->get('SYS_ALL_ORGANIZATIONS')),
                '4' => array('4', $gL10n->get('SYS_ALL_CONTACTS'), $gL10n->get('SYS_ALL_ORGANIZATIONS'))
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
                'defaultValue' => $getMembersShowFiler,
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
    $contactsTable = new HtmlTable('tbl_contacts', $page, true, true, 'table table-condensed');// create array with all column heading values
    $columnHeading = $contactsListConfig->getColumnNames();

    array_unshift(
        $columnHeading,
        $gL10n->get('SYS_ABR_NO'),
        '<i class="bi bi-person-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', array($orgName)) . '"></i>'
    );
    $columnHeading[] = '&nbsp;';
    $columnAlignment = $contactsListConfig->getColumnAlignments();
    array_unshift($columnAlignment, 'left', 'left');
    $columnAlignment[] = 'right';
    $contactsTable->setServerSideProcessing(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_data.php', array('mem_show_filter' => $getMembersShowFiler)));
    $contactsTable->setColumnAlignByArray($columnAlignment);
    $contactsTable->disableDatatablesColumnsSort(array(1, count($columnHeading)));// disable sort in last column
    $contactsTable->setDatatablesColumnsNotHideResponsive(array(count($columnHeading)));
    $contactsTable->addRowHeadingByArray($columnHeading);
    $contactsTable->setDatatablesRowsPerPage($gSettingsManager->getInt('contacts_per_page'));
    $contactsTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

    $page->addHtml($contactsTable->show());// show html of complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
