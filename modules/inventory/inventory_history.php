<?php
/**
 ***********************************************************************************************
 * Show history of item field changes in the InventoryManager plugin
 * 
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * 
 * Parameters:
 * item_id          : If set, only show the item field history of that item
 * filter_date_from : Set to the actual date if no date information is delivered
 * filter_date_to   : Set to the actual date if no date information is delivered
 ***********************************************************************************************
 */
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

use Admidio\Inventory\ValueObjects\ItemsData;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/../../system/login_valid.php');

    // calculate default date from which the item fields history should be shown
    $filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
    $filterDateFrom->modify('-'.$gSettingsManager->getInt('inventory_field_history_days').' day');

    $getItemId    = admFuncVariableIsValid($_GET, 'item_id', 'int', array('defaultValue' => 0));
    $getDateFrom  = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo    = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));

    $items = new ItemsData($gDb, $gCurrentOrgId);
    $items->readItemData($getItemId, $gCurrentOrgId);

    $user = new User($gDb, $gProfileFields);

    $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($items->getValue('ITEMNAME')));

    // if profile log is activated then the item field history will be shown otherwise show error
    if (!$gSettingsManager->getBool('changelog_module_enabled')) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // filter_date_from and filter_date_to can have different formats
    // now we try to get a default format for intern use and html output
    $objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom) ?: DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom) ?: DateTime::createFromFormat('Y-m-d', '1970-01-01');
    $objDateTo   = DateTime::createFromFormat('Y-m-d', $getDateTo) ?: DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo) ?: DateTime::createFromFormat('Y-m-d', '1970-01-01');

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml   = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern   = $objDateTo->format('Y-m-d');
    $dateToHtml     = $objDateTo->format($gSettingsManager->getString('system_date'));

    // create select statement with all necessary data
    if ($getItemId === 0) {
        $sql = 'SELECT inl_ini_id, inl_inf_id, inl_usr_id_create, inl_timestamp_create, inl_value_old, inl_value_new 
            FROM '.TBL_INVENTORY_LOG.'
            WHERE inl_timestamp_create BETWEEN ? AND ? 
            AND inl_org_id = ?
            ORDER BY inl_timestamp_create DESC;';
        $fieldHistoryStatement = $gDb->queryPrepared($sql, array($dateFromIntern.' 00:00:00', $dateToIntern.' 23:59:59', $gCurrentOrgId));
    }
    else {
        $sql = 'SELECT inl_ini_id, inl_inf_id, inl_usr_id_create, inl_timestamp_create, inl_value_old, inl_value_new 
            FROM '.TBL_INVENTORY_LOG.'
            WHERE inl_timestamp_create BETWEEN ? AND ? 
            AND inl_org_id = ?
            AND inl_ini_id = ?
            ORDER BY inl_timestamp_create DESC;';
        $fieldHistoryStatement = $gDb->queryPrepared($sql, array($dateFromIntern.' 00:00:00', $dateToIntern.' 23:59:59', $gCurrentOrgId, $getItemId));
    }

    if ($fieldHistoryStatement->rowCount() === 0) {
        // message is shown, so delete this page from navigation stack
        $gNavigation->deleteLastUrl();
        $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
        $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED_PROFIL', array($items->getValue('ITEMNAME'))));
        // => EXIT
    }

    // create html page object
    $page = new PagePresenter('plg-inventory-manager-items-history', $headline);

    // create filter menu with input elements for Startdate and Enddate
    //$FilterNavbar = new HtmlNavbar('menu_profile_field_history_filter', '', null, 'filter');
    $form = new FormPresenter(
        'adm_navbar_filter_form',
        'sys-template-parts/form.filter.tpl',
        '',
        $page,
        array('type' => 'navbar', 'setFocus' => false)
    );

    //$form = new FormPresenter('navbar_filter_form', ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory_history.php', $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('item_id', '', $getItemId, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
    //$FilterNavbar->addForm($form->show());
    //$page->addHtml($FilterNavbar->show());
    $form->addToHtmlPage();

    $table = new HtmlTable('profile_field_history_table', $page, true, true);

    $columnHeading = array(
        $gL10n->get('SYS_FIELD'),
        $gL10n->get('SYS_NEW_VALUE'),
        $gL10n->get('SYS_PREVIOUS_VALUE'),
        $gL10n->get('SYS_EDITED_BY'),
        $gL10n->get('SYS_CHANGED_AT')
    );

    $table->setDatatablesOrderColumns(array(array(5, 'desc')));
    $table->addRowHeadingByArray($columnHeading);

    while ($row = $fieldHistoryStatement->fetch()) {
        $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['inl_timestamp_create']);
        $columnValues    = array();
        $columnValues[]  = $items->getPropertyById((int) $row['inl_inf_id'], 'inf_name');

        $imlValueNew = $items->getHtmlValue($items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern'), $row['inl_value_new']);
        if ($imlValueNew !== '') {
            if ($items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern') === 'KEEPER' || $items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern') === 'LAST_RECEIVER') {
                if (is_numeric($imlValueNew)) {
                    $found = $user->readDataById($imlValueNew);
                    if ($found) {
                        $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
                    }
                    else {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname'). '"';
                        $columnValues[] = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION',array($orgName));
                    }
                }
                else {
                    $columnValues[] = $imlValueNew;
                }
            }
            else {
                $columnValues[] = $imlValueNew;
            }
        }
        else {
            $columnValues[] = '&nbsp;';
        }
        
        $imlValueOld = $items->getHtmlValue($items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern'), $row['inl_value_old']);
        if ($imlValueOld !== '') {
            if ($items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern') === 'KEEPER' || $items->getPropertyById((int) $row['inl_inf_id'], 'inf_name_intern') === 'LAST_RECEIVER') {
                if (is_numeric($imlValueOld)) {
                    $found = $user->readDataById($imlValueOld);
                    if ($found) {
                        $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
                    }
                    else {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname'). '"';
                        $columnValues[] = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION',array($orgName));
                    }
                }
                else {
                    $columnValues[] = $imlValueOld;
                }
            }
            else {
                $columnValues[] = $imlValueOld;
            }
        }
        else {
            $columnValues[] = '&nbsp;';
        }
    
        $user->readDataById($row['inl_usr_id_create']);
        $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
        $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
        $table->addRowByArray($columnValues);
    }

    $page->addHtml($table->show());
    $page->show();
}
catch (Exception $e) {
    $gMessage->show($e->getMessage());
}