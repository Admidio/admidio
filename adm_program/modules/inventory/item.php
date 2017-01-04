<?php
/**
 ***********************************************************************************************
 * Show item profile
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * item_id : Show profile of the item with this is. If this parameter is not set then
 *           an error will be shown.
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create item object
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

// Testen ob Recht besteht Profil einzusehn
if($gPreferences['enable_inventory_module'] >= 0 && !$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($fieldNameIntern, $item)
{
    global $gPreferences, $inventory, $gL10n, $gInventoryFields;
    $html      = array('label' => '', 'value' => '');
    $value     = '';
    $msg_image = '';

    if($gInventoryFields->getProperty($fieldNameIntern, 'inf_hidden') == 1)
    {
        return '';
    }

    // get value of field in html format
    $value = $inventory->getValue($fieldNameIntern, 'html');

    // Icons anzeigen
    if(strlen($gInventoryFields->getProperty($fieldNameIntern, 'inf_icon')) > 0)
    {
        $value = $gInventoryFields->getProperty($fieldNameIntern, 'inf_icon').'&nbsp;&nbsp;'. $value;
    }

    // show html of field, if user has a value for that field or it's a checkbox field
    if(strlen($inventory->getValue($fieldNameIntern)) > 0 || $gInventoryFields->getProperty($fieldNameIntern, 'inf_type') === 'CHECKBOX')
    {
        $html['label'] = $gInventoryFields->getProperty($fieldNameIntern, 'inf_name');
        $html['value'] = $value;
    }

    return $html;
}

unset($_SESSION['profile_request']);

// set headline
$headline = $gL10n->get('PRO_PROFILE_FROM', $inventory->getValue('ITEM_NAME'));

// if user id was not set and own profile should be shown then initialize navigation
if(!isset($_GET['item_id']))
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

$page->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/dist/css/datepicker3.css');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/js/bootstrap-datepicker.js');
$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap-datepicker/js/locales/bootstrap-datepicker.'.$gL10n->getLanguage().'.js');

$page->addJavascript('
    var profileJS = new ProfileJS(gRootPath);
    profileJS.deleteRole_ConfirmText  = "'.$gL10n->get('ROL_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.deleteFRole_ConfirmText = "'.$gL10n->get('ROL_LINK_MEMBERSHIP_DEL', '[rol_name]').'";
    profileJS.setBy_Text              = "'.$gL10n->get('SYS_SET_BY').'";
    profileJS.inv_id                  = '.$inventory->getValue('inv_id').';

    function showHideMembershipInformation(element) {
        $("#" + element.attr("id") + "_Content").toggle("fast");
    }');
$page->addJavascript('
    profileJS.init();
    $(".admidio-icon-link-popup").colorbox({
        rel: "nofollow",
        scrolling: false,
        onComplete: function() {
            $("#admButtonNo").focus();
        }
    });
    $(".admMemberInfo").click(function () { showHideMembershipInformation($(this)); });
    $("#profile_authorizations_box_body").mouseout(function () { profileJS.deleteShowInfo(); });

    $(".admidio-form-membership-period").submit(function(event) {
        var id = $(this).attr("id");
        var parentId = $("#" + id).parent().parent().attr("id");
        var parent = $("#" + parentId);
        var action = $(this).attr("action");

        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.get({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {
                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");

                    parent.animate({opacity: 1.0}, 2500);
                    parent.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                }
            }
        });
    });', true);

// get module menu
$profileMenu = $page->getMenu();

// show back link
if($gNavigation->count() > 1)
{
    $profileMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

// show link to edit profile
$profileMenu->addItem('menu_item_new_entry', ADMIDIO_URL. FOLDER_MODULES.'/inventory/item_new.php?item_id='.$inventory->getValue('inv_id'),
                $gL10n->get('MEM_EDIT_USER'), 'edit.png');

$profileMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');

if($gCurrentUser->isAdministrator())
{
    // show link to maintain profile fields
    $profileMenu->addItem('menu_item_maintain_profile_fields', ADMIDIO_URL. FOLDER_MODULES.'/preferences/fields.php',
                                $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras');

    // show link to system preferences of weblinks
    $profileMenu->addItem('menu_item_preferences_links', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=profile',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
}

// *******************************************************************************
// User data block
// *******************************************************************************

$page->addHtml('
<div class="panel panel-default" id="user_data_panel">
    <div class="panel-heading">'.$gL10n->get('SYS_MASTER_DATA').'</div>
    <div class="panel-body row">
        <div class="col-sm-8">');
            // create a static form
            $form = new HtmlForm('profile_user_data_form', null);

            $bAddressOutput = false;    // Merker, ob die Adresse schon angezeigt wurde

            // Schleife ueber alle Felder der Stammdaten

            foreach($gInventoryFields->mInventoryFields as $field)
            {
                // nur Felder der Stammdaten anzeigen
                if($field->getValue('cat_name_intern') === 'MASTER_DATA' && $field->getValue('inv_hidden') == 0)
                {
                    switch($field->getValue('inf_name_intern'))
                    {
                        case 'ROOM_ID':
                            $field = getFieldCode($field->getValue('inf_name_intern'), $getItemId);
                            if($gDbType === 'mysql')
                            {
                                $sql = 'SELECT CONCAT(room_name, \' (\', room_capacity, \'+\', IFNULL(room_overhang, \'0\'), \')\') AS name
                                          FROM '.TBL_ROOMS.'
                                         WHERE room_id = ' . $field['value'];
                            }
                            else
                            {
                                $sql = 'SELECT room_name || \' (\' || room_capacity || \'+\' || COALESCE(room_overhang, \'0\') || \')\' AS name
                                          FROM '.TBL_ROOMS.'
                                         WHERE room_id = ' . $field['value'];
                            }
                            $pdoStatement = $gDb->query($sql);

                            if($pdoStatement->rowCount() > 0)
                            {
                                $form->addStaticControl('address', $field['label'], $pdoStatement->fetchColumn());
                            }
                            else
                            {
                                $form->addStaticControl('address', $field['label'], 'room_id ' . $field['value'] . ' not found');
                            }
                            break;

                        case 'FIRST_NAME':
                            break;

                        default:
                            $field = getFieldCode($field->getValue('inf_name_intern'), $getItemId);
                            if(strlen($field['value']) > 0)
                            {
                                $form->addStaticControl('address', $field['label'], $field['value']);
                            }
                            break;
                    }
                }
            }
            $page->addHtml($form->show(false));
        $page->addHtml('</div>
        <div class="col-sm-4 text-right">');

            // *******************************************************************************
            // Profile photo
            // *******************************************************************************

            $page->addHtml('<img id="profile_photo" class="thumbnail" src="item_photo_show.php?inv_id='.$inventory->getValue('inv_id').'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');

            // Nur berechtigte User duerfen das Profilfoto editieren
            if($gCurrentUser->editInventory($inventory))
            {
                $page->addHtml('<div id="profile_picture_links" class="btn-group-vertical" role="group">
                    <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/inventory/item_photo_edit.php?inv_id='.$inventory->getValue('inv_id').'"><img
                            src="'.THEME_URL.'/icons/photo_upload.png" alt="'.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_CHANGE_PROFILE_PICTURE').'</a>');
                // Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
                if((strlen($inventory->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
                    || is_file(ADMIDIO_PATH . FOLDER_DATA . '/item_photos/'.$inventory->getValue('inv_id').'.jpg') && $gPreferences['profile_photo_storage'] == 1)
                {
                    $page->addHtml('<a class="btn" href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=pro_pho&amp;element_id=no_element'.
                                    '&amp;database_id='.$inventory->getValue('inv_id').'"><img src="'. THEME_URL. '/icons/delete.png"
                                    alt="'.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'" /> '.$gL10n->get('PRO_DELETE_PROFILE_PICTURE').'</a>');
                }
                $page->addHtml('</div>');
            }
        $page->addHtml('</div>
    </div>
</div>');

// *******************************************************************************
// Loop over all categories and profile fields except the master data
// *******************************************************************************

$category = '';
foreach($gProfileFields->mProfileFields as $field)
{
    // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
    // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
    if($field->getValue('cat_name_intern') !== 'MASTER_DATA'
    && ($gCurrentUser->editInventory($inventory)
        || (!$gCurrentUser->editInventory($inventory) && $field->getValue('inf_hidden') == 0)))
    {
        // show new category header if new category and field has value or is a checkbox field
        if($category !== $field->getValue('cat_name')
        && (strlen($inventory->getValue($field->getValue('inf_name_intern'))) > 0 || $field->getValue('inf_type') === 'CHECKBOX'))
        {
            if($category !== '')
            {
                // new category then show last form and close div container
                $page->addHtml($form->show(false));
                $page->addHtml('</div></div>');
            }
            $category = $field->getValue('cat_name');

            $page->addHtml('
            <div class="panel panel-default" id="'.$field->getValue('cat_name').'_data_panel">
                <div class="panel-heading">'.$field->getValue('cat_name').'</div>
                <div class="panel-body">');

            // create a static form
            $form = new HtmlForm('profile_user_data_form', null);
        }

        // show html of field, if user has a value for that field or it's a checkbox field
        if(strlen($inventory->getValue($field->getValue('inf_name_intern'))) > 0 || $field->getValue('inf_type') === 'CHECKBOX')
        {
            $field = getFieldCode($field->getValue('inf_name_intern'), $inventory);
            if(strlen($field['value']) > 0)
            {
                $form->addStaticControl('address', $field['label'], $field['value']);
            }
        }
    }
}

if($category !== '')
{
    // new category then show last form and close div container
    $page->addHtml($form->show(false));
    $page->addHtml('</div></div>');
}

// show information about user who creates the recordset and changed it
$page->addHtml(admFuncShowCreateChangeInfoById($inventory->getValue('inv_usr_id_create'), $inventory->getValue('inv_timestamp_create'), $inventory->getValue('inv_usr_id_change'), $inventory->getValue('inv_timestamp_change')));

$page->show();
