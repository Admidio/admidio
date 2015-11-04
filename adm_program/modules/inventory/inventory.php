<?php
/******************************************************************************
 * Show and manage all items of inventory
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 *****************************************************************************/

require_once('../../system/common.php');

// only users with the right to edit inventory could use this script
if ($gCurrentUser->editInventory() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
$headline = $gL10n->get('INV_ITEM_MANAGEMENT');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));

// alle Items zur Auswahl selektieren
$sql    = 'SELECT inv_id, item_name.ind_value as item_name, room_id.ind_value as room_id,
                  COALESCE(inv_timestamp_change, inv_timestamp_create) as timestamp
             FROM '. TBL_INVENT. '
             JOIN '. TBL_INVENT_DATA. ' as item_name
               ON item_name.ind_itm_id = inv_id
              AND item_name.ind_inf_id = '. $gInventoryFields->getProperty('ITEM_NAME', 'inf_id'). '
             JOIN '. TBL_INVENT_DATA. ' as room_id
               ON room_id.ind_itm_id = inv_id
              AND room_id.ind_inf_id = '. $gInventoryFields->getProperty('ROOM_ID', 'inf_id'). '
            WHERE inv_valid = 1
            ORDER BY item_name.ind_value, room_id.ind_value ';
$result_mgl  = $gDb->query($sql);

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
        $(".admidio-icon-link-popup").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        ', true);

// get module menu
$itemsAdministrationMenu = $page->getMenu();

$itemsAdministrationMenu->addItem('menu_item_create_user', $g_root_path.'/adm_program/modules/inventory/item_new.php', $gL10n->get('INV_CREATE_ITEM'), 'add.png');

// show link to room management
$itemsAdministrationMenu->addItem('menu_item_manage_rooms', $g_root_path.'/adm_program/modules/rooms/rooms.php', $gL10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION'), 'home.png');

if($gCurrentUser->isWebmaster())
{
    // show link to maintain profile fields
    $itemsAdministrationMenu->addItem('menu_item_maintain_inventory_fields', $g_root_path. '/adm_program/modules/inventory/fields.php', $gL10n->get('PRO_MAINTAIN_ITEM_FIELDS'), 'application_form_edit.png');
}

//Create table object
$itemsTable = new HtmlTable('tbl_invent', $page, true, true, 'table table-condensed');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_ABR_NO'),
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_ROOM'),
    $gL10n->get('MEM_UPDATED_ON'),
    $gL10n->get('SYS_FEATURES')
);

$itemsTable->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$itemsTable->disableDatatablesColumnsSort(5);
$itemsTable->addRowHeadingByArray($columnHeading);
$itemsTable->setDatatablesRowsPerPage($gPreferences['members_users_per_page']);
$itemsTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');

$irow = 1;  // count for line in table

while($row = $gDb->fetch_array($result_mgl))
{
    $timestampChange = new DateTimeExtended($row['timestamp'], 'Y-m-d H:i:s');

    $room = new TableRooms($gDb, $row['room_id']);
    $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$row['room_id'].'&amp;inline=true';

    // create array with all column values
    $columnValues = array(
        $irow,
        '<a href="'.$g_root_path.'/adm_program/modules/inventory/item.php?item_id='. $row['inv_id']. '">'. $row['item_name']. '</a>',
        '<a class="admidio-icon-link-popup" href="'.$roomLink.'">' . $room->getValue('room_name') . '</a>',
    );

    $columnValues[] = $timestampChange->format($gPreferences['system_date'].' '.$gPreferences['system_time']);

    $itemAdministration = '';

    // Link to modify Item
    $itemAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/inventory/item_new.php?item_id='. $row['inv_id']. '"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('MEM_EDIT_USER').'" title="'.$gL10n->get('MEM_EDIT_USER').'" /></a>';

    // remove Item
    if($gCurrentUser->isWebmaster()) // just Webmaster can remove items
    {
        $itemAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/inventory/items_function.php?item_id='.$row['inv_id'].'&amp;mode=6"><img
                                    src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" title="'.$gL10n->get('MEM_REMOVE_USER').'" /></a>';
    }
    else
    {
        $itemAdministration .= '&nbsp;<img class="admidio-icon-link" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
    }

    $columnValues[] = $itemAdministration;

    $itemsTable->addRowByArray($columnValues);

    $irow++;
}

$page->addHtml($itemsTable->show(false));

// show html of complete page
$page->show();
