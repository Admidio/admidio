<?php
/**
 ***********************************************************************************************
 * Create a custom list
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * list_uuid    : UUID of the list configuration that should be shown
 * rol_ids      : (Optional) ID of the role or an integer array of all role ids whose members should be shown
 * active_role  : true  - (Default) List only active roles
 *                false - List only deactivated roles
 * show_members : 0 - (Default) show active members of role
 *                1 - show former members of role
 *                2 - show active and former members of role
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getListUuid    = admFuncVariableIsValid($_GET, 'list_uuid', 'string');
$getRoleIds     = admFuncVariableIsValid($_GET, 'rol_ids', 'string'); // could be int or int[], so string is necessary
$getActiveRole  = admFuncVariableIsValid($_GET, 'active_role', 'bool', array('defaultValue' => true));
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'int');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('groups_roles_enable_module')
    || ($gSettingsManager->getInt('groups_roles_edit_lists') === 2 && !$gCurrentUser->checkRolesRight('rol_edit_user')) // users with the right to edit all profiles
    || ($gSettingsManager->getInt('groups_roles_edit_lists') === 3 && !$gCurrentUser->isAdministrator())) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// only users with the right to assign roles can view inactive roles
if (!$gCurrentUser->checkRolesRight('rol_assign_roles')) {
    $getActiveRole = true;
}

// set headline of the script
$headline = $gL10n->get('SYS_CONFIGURATION_LIST');

// add current url to navigation stack if last url was not the same page
if (!str_contains($gNavigation->getUrl(), 'mylist.php')) {
    $gNavigation->addUrl(CURRENT_URL, $headline);
}

$defaultColumnRows   = 6;    // number of columns that should be shown
$mySqlMaxColumnAlert = '';

// Listenobjekt anlegen
$list = new ListConfiguration($gDb);
$list->readDataByUuid($getListUuid);

if (isset($_SESSION['mylist_request'])) {
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['mylist_request']));
    unset($_SESSION['mylist_request']);

    if (!isset($formValues['cbx_global_configuration'])) {
        $formValues['cbx_global_configuration'] = 0;
    }

    if (!isset($formValues['sel_roles_ids'])) {
        $formValues['sel_roles_ids'] = 0;
    }

    // if rows for columns have been added manually before, they must now be created directly
    for ($i = $defaultColumnRows + 1; $i > 0; ++$i) {
        if (isset($formValues['column'.$i])) {
            ++$defaultColumnRows;
        } else {
            $i = -1;
        }
    }
} else {
    $formValues['sel_select_configuration'] = $getListUuid;
    $formValues['cbx_global_configuration'] = $list->getValue('lst_global');
    $formValues['sel_roles_ids']            = $getRoleIds;

    // if a saved configuration was loaded then add columns to formValues array
    if ($getListUuid !== '') {
        $defaultColumnRows = $list->countColumns();

        for ($number = 1, $max = $list->countColumns(); $number <= $max; ++$number) {
            $column = $list->getColumnObject($number);
            $userField = new TableUserField($gDb, $column->getValue('lsc_usf_id'));

            if ($column->getValue('lsc_usf_id') > 0) {
                $formValues['column'. $number] = $userField->getValue('usf_name_intern');
            } else {
                $formValues['column'. $number] = $column->getValue('lsc_special_field');
            }

            $formValues['sort'. $number]      = $column->getValue('lsc_sort');
            $formValues['condition'. $number] = $column->getValue('lsc_filter');
        }
    }
}

// create html page object
$page = new HtmlPage('admidio-mylist', $headline);

// within MySql it's only possible to join 61 tables therefore show a message if user
// want's to join more than 57 columns
if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
    $mySqlMaxColumnAlert = '
    if (fieldNumberIntern >= 57) {
        alert("'.$gL10n->get('SYS_NO_MORE_COLUMN').'");
        return;
    }';
}

$javascriptCode = '
    var listUuid          = "'.$getListUuid.'";
    var fieldNumberIntern = 0;
    var arrUserFields     = createProfileFieldsArray();
    var arrDefaultFields  = createColumnsArray();

    /**
     * Function adds a new line to assign columns for the list
     */
    function addColumn() {
        '.$mySqlMaxColumnAlert.'

        var category = "";
        var fieldNumberShow = fieldNumberIntern + 1;
        var table = document.getElementById("mylist_fields_tbody");
        var newTableRow = table.insertRow(fieldNumberIntern);
        newTableRow.setAttribute("id", "row" + fieldNumberShow)
        $(newTableRow).css("display", "none");
        var newCellCount = newTableRow.insertCell(-1);
        newCellCount.textContent = (fieldNumberShow) + ". '.$gL10n->get('SYS_COLUMN').' :";

        // neue Spalte zur Auswahl des Profilfeldes
        var newCellField = newTableRow.insertCell(-1);
        htmlCboFields = "<select class=\"form-control\" onchange=\"getConditionField(" + fieldNumberShow + ", this.options[this.selectedIndex].text)\" size=\"1\" id=\"column" + fieldNumberShow + "\" class=\"ListProfileField\" name=\"column" + fieldNumberShow + "\">" +
                "<option value=\"\"></option>";
        for (var counter = 1; counter < arrUserFields.length; counter++) {
            if (category !== arrUserFields[counter]["cat_name"]) {
                if (category.length > 0) {
                    htmlCboFields += "</optgroup>";
                }
                htmlCboFields += "<optgroup label=\"" + arrUserFields[counter]["cat_name"] + "\">";
                category = arrUserFields[counter]["cat_name"];
            }

            var selected = "";
            // bei einer neuen Liste sind Vorname und Nachname in den ersten Spalten vorbelegt
            if (( (fieldNumberIntern === 0 && arrUserFields[counter]["usf_name_intern"] === "LAST_NAME")
               || (fieldNumberIntern === 1 && arrUserFields[counter]["usf_name_intern"] === "FIRST_NAME"))
            && listUuid === "") {
                selected = " selected=\"selected\" ";
            }

            // bei gespeicherten Listen das entsprechende Profilfeld selektieren
            // und den Feldnamen dem Listenarray hinzufügen
            if (arrDefaultFields[fieldNumberShow]) {
                if (arrUserFields[counter]["usf_name_intern"] === arrDefaultFields[fieldNumberShow]["usf_name_intern"]) {
                    selected = " selected=\"selected\" ";
                    arrDefaultFields[fieldNumberShow]["usf_name"] = arrUserFields[counter]["usf_name"];
                }
            }
            htmlCboFields += "<option value=\"" + arrUserFields[counter]["usf_name_intern"] + "\" " + selected + ">" + arrUserFields[counter]["usf_name"] + "</option>";
        }
        htmlCboFields += "</select>";
        newCellField.innerHTML = htmlCboFields;

        // new column for setting the sorting
        var selectAsc  = "";
        var selectDesc = "";

        if (arrDefaultFields[fieldNumberShow]) {
            if (arrDefaultFields[fieldNumberShow]["sort"] === "ASC") {
                selectAsc = " selected=\"selected\" ";
            }
            if (arrDefaultFields[fieldNumberShow]["sort"] === "DESC") {
                selectDesc = " selected=\"selected\" ";
            }
        } else if (fieldNumberIntern === 0) {
            selectAsc = " selected=\"selected\" ";
        }

        var newCellOrder = newTableRow.insertCell(-1);
        newCellOrder.innerHTML = "<select class=\"form-control\" size=\"1\" id=\"sort" + fieldNumberShow + "\" name=\"sort" + fieldNumberShow + "\">" +
                "<option value=\"\">&nbsp;</option>" +
                "<option value=\"ASC\" " + selectAsc + ">'.$gL10n->get('SYS_A_TO_Z').'</option>" +
                "<option value=\"DESC\" " + selectDesc + ">'.$gL10n->get('SYS_Z_TO_A').'</option>" +
            "</select>";

        // new column for conditions
        condition = "";
        if (arrDefaultFields[fieldNumberShow]) {
            var fieldName = arrDefaultFields[fieldNumberShow]["usf_name"];

            if (arrDefaultFields[fieldNumberShow]["condition"]) {
                condition = arrDefaultFields[fieldNumberShow]["condition"];
                condition = condition.replace(/{/g, "<");
                condition = condition.replace(/}/g, ">");
            }
        } else {
            var fieldName = "";
        }

        htmlFormCondition = setConditionField(fieldNumberShow, fieldName);
        var newCellConditions = newTableRow.insertCell(-1);
        newCellConditions.setAttribute("id", "td_condition" + fieldNumberShow);
        newCellConditions.innerHTML = htmlFormCondition;

        $(newTableRow).fadeIn("slow");
        fieldNumberIntern++;
    }

    function createProfileFieldsArray() {
        var userFields = [];';

// create a multidimensional array for all columns with the necessary data
$i = 0;
$arrParticipantsInformation = array(
    'mem_approved'         => $gL10n->get('SYS_PARTICIPATION_STATUS'),
    'mem_usr_id_change'    => $gL10n->get('SYS_CHANGED_BY'),
    'mem_timestamp_change' => $gL10n->get('SYS_CHANGED_AT'),
    'mem_comment'          => $gL10n->get('SYS_COMMENT'),
    'mem_count_guests'     => $gL10n->get('SYS_SEAT_AMOUNT')
);

foreach ($gProfileFields->getProfileFields() as $field) {
    // add profile field to user field array
    if ($gProfileFields->isVisible($field->getValue('usf_name_intern'), $gCurrentUser->editUsers())) {
        $javascriptCode .= '
            userFields[' . ++$i . '] = {
                "cat_name": "'. str_replace('"', '\'', $field->getValue('cat_name')). '",
                "usf_name": "'. addslashes($field->getValue('usf_name')). '",
                "usf_name_intern": "'. addslashes($field->getValue('usf_name_intern')). '",
                "usf_type": "'. $field->getValue('usf_type'). '",
                "usf_value_list": {}
            };';

        // get available values for current field type and push to array
        if ($field->getValue('usf_type') === 'DROPDOWN' || $field->getValue('usf_type') === 'RADIO_BUTTON') {
            foreach ($field->getValue('usf_value_list', 'text') as $key => $value) {
                $javascriptCode .= '
                    userFields[' . $i . ']["usf_value_list"]["'. $key .'"] = "'. $value .'";';
            }
        } else {
            $javascriptCode .= '
                userFields[' . $i . ']["usf_value_list"] = "";';
        }
    }
}

// after the profile fields add add some special profile informations e.g. username, photo, created at ...
$javascriptCode .= '
        userFields[' . ++$i . '] = {
            "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
            "usf_name": "' . $gL10n->get('SYS_PHOTO') . '",
            "usf_name_intern": "usr_photo"
        };';

// administrator could export the uuid of each user to identify the user later at the import
if ($gCurrentUser->editUsers()) {
    $javascriptCode .= '
            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_USERNAME') . '",
                "usf_name_intern": "usr_login_name"
            };

            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_CREATED_BY') . '",
                "usf_name_intern": "usr_usr_id_create"
                };

            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_CREATED_AT') . '",
                "usf_name_intern": "usr_timestamp_create"
                };

            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_CHANGED_BY') . '",
                "usf_name_intern": "usr_usr_id_change"
                };

            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_CHANGED_AT') . '",
                "usf_name_intern": "usr_timestamp_change"
                };

            userFields[' . ++$i . '] = {
                "cat_name": "' . $gL10n->get('SYS_PROFILE_INFORMATION') . '",
                "usf_name": "' . $gL10n->get('SYS_UNIQUE_ID') . '",
                "usf_name_intern": "usr_uuid"
                };';
}

$javascriptCode .= '
        userFields[' . ++$i . '] = {
            "cat_name": "'.$gL10n->get('SYS_ROLE_INFORMATION').'",
            "usf_name": "'.$gL10n->get('SYS_MEMBERSHIP_START').'",
            "usf_name_intern": "mem_begin"
        };

        userFields[' . ++$i . '] = {
            "cat_name": "'.$gL10n->get('SYS_ROLE_INFORMATION').'",
            "usf_name": "'.$gL10n->get('SYS_MEMBERSHIP_END').'",
            "usf_name_intern": "mem_end"
        };';

    // add new category with participant information of events
    foreach ($arrParticipantsInformation as $memberStatus => $columnName) {
        $javascriptCode .= '
            userFields['. ++$i . '] = {
                "cat_name" : "'.$gL10n->get('SYS_PARTICIPATION_INFORMATION').'",
                "usf_name" : "'.$columnName.'",
                "usf_name_intern" : "'.$memberStatus.'",
            };';
    }

$javascriptCode .= '
        return userFields;
}

    function createColumnsArray()
    {
        var defaultFields = [];';

// now add all columns to the javascript row objects
$actualColumnNumber = 1;
while (isset($formValues['column' . $actualColumnNumber])) {
    $sortValue      = '';
    $conditionValue = '';

    if (isset($formValues['sort' . $actualColumnNumber])) {
        $sortValue = $formValues['sort' . $actualColumnNumber];
    }
    if (isset($formValues['condition' . $actualColumnNumber])) {
        $conditionValue = $formValues['condition' . $actualColumnNumber];
    }

    $javascriptCode .= '
        defaultFields[' . $actualColumnNumber . '] = {
            "usf_name_intern": "' . $formValues['column' . $actualColumnNumber] . '",
            "sort": "' . $sortValue . '",
            "condition": "' . $conditionValue . '"
        };';

    ++$actualColumnNumber;
}

$javascriptCode .= '
        return defaultFields;
    }

    /**
     * @param {int}    columnNumber
     * @param {string} columnName
     */
    function getConditionField(columnNumber, columnName) {
        htmlFormCondition = setConditionField(columnNumber, columnName);
        $("#td_condition" + columnNumber).html(htmlFormCondition);
    }

    /**
     * @param {int}    columnNumber
     * @param {string} columnName
     */
    function setConditionField(fieldNumberShow, columnName) {
        html = "<input type=\"text\" class=\"form-control\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\" maxlength=\"50\" value=\"" + condition + "\" />";
        var key;

        for (key in arrUserFields) {
            if (arrUserFields[key]["usf_name"] === columnName) {
                if (arrUserFields[key]["usf_type"] === "DROPDOWN"
                ||  arrUserFields[key]["usf_type"] === "RADIO_BUTTON") {
                    html = "<select class=\"form-control\" size=\"1\" id=\"condition" + fieldNumberShow + "\" class=\"ListConditionField\" name=\"condition" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>";

                    for (selectValue in arrUserFields[key]["usf_value_list"]) {
                        selected = "";

                        if (arrDefaultFields[fieldNumberShow]) {
                            if (arrUserFields[key]["usf_name_intern"] === arrDefaultFields[fieldNumberShow]["usf_name_intern"]
                            &&  arrUserFields[key]["usf_value_list"][selectValue] == arrDefaultFields[fieldNumberShow]["condition"]) {
                                selected = " selected=\"selected\" ";
                            }
                        }
                        html += "<option value=\"" + arrUserFields[key]["usf_value_list"][selectValue] + "\" " + selected + ">" + arrUserFields[key]["usf_value_list"][selectValue] + "</option>";
                        "</select>";
                    }
                }

                if (arrUserFields[key]["usf_type"] === "CHECKBOX") {
                    html = "<select class=\"form-control\" size=\"1\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>";

                    selected = "";

                    if (arrDefaultFields[fieldNumberShow]) {
                        if (arrUserFields[key]["usf_name_intern"] === arrDefaultFields[fieldNumberShow]["usf_name_intern"]
                            && arrDefaultFields[fieldNumberShow]["condition"] == "1") {
                            selected = " selected=\"selected\" ";
                        }
                            html += "<option value=\"1\" " + selected + ">'.$gL10n->get('SYS_YES').'</option>";
                        selected = "";

                        if (arrUserFields[key]["usf_name_intern"] === arrDefaultFields[fieldNumberShow]["usf_name_intern"]
                            && arrDefaultFields[fieldNumberShow]["condition"] == "0") {
                            selected = " selected=\"selected\" ";
                        }
                            html += "<option value=\"0\" " + selected + ">'.$gL10n->get('SYS_NO').'</option>" +
                            "</select>";
                    } else {
                        html += "<option value=\"1\">'.$gL10n->get('SYS_YES').'</option>" +
                                "<option value=\"0\">'.$gL10n->get('SYS_NO').'</option>" +
                                "</select>";
                    }
                }
            }
        }
        return html;
    }

    function loadList() {
        var listUuid = $("#sel_select_configuration").val();
        self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php', array('active_role' => (int) $getActiveRole)) . '&list_uuid=" + listUuid;
    }

    /**
     * @param {string} mode
     */
    function send(mode) {
        for (var i = 1; i <= fieldNumberIntern; i++) {
            if (document.getElementById("condition" + i)) {
                var condition = document.getElementById("condition" + i);
                condition.value = condition.value.replace(/</g, "{");
                condition.value = condition.value.replace(/>/g, "}");
            }
        }

        var myListConfigForm = document.getElementById("mylist_configuration_form");

        switch (mode) {
            case "show":
                myListConfigForm.action = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist_function.php', array('mode' => 2)).'";
                myListConfigForm.submit();
                break;

            case "save":
                myListConfigForm.action = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist_function.php', array('list_uuid' => $getListUuid, 'mode' => 1)).'";
                myListConfigForm.submit();
                break;

            case "save_as":
                var listName = "";
                listName = prompt("'.$gL10n->get('SYS_CONFIGURATION_SAVE').'");
                if (listName !== null) {
                    myListConfigForm.action = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist_function.php', array('mode' => 1)) . '&name=" + listName;
                    myListConfigForm.submit();
                }
                break;

            case "delete":
                var msg_result = confirm("'.$gL10n->get('SYS_CONFIGURATION_DELETE').'");
                if (msg_result) {
                    myListConfigForm.action = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist_function.php', array('list_uuid' => $getListUuid, 'mode' => 3)).'";
                    myListConfigForm.submit();
                }
                break;
        }
    }';
$page->addJavascript($javascriptCode);
$page->addJavascript('$(function() {
    $("#sel_select_configuration").change(function() { loadList(); });
    $("#btn_show_list").click(function() { send("show"); });
    $("#btn_add_column").click(function() { addColumn(); });
    $("#btn_save").click(function() { send("save_as"); });
    $("#btn_save_changes").click(function() { send("save"); });
    $("#btn_delete").click(function() { send("delete"); });
    $("#btn_copy").click(function() { send("save_as"); });

    for (var counter = 0; counter < '. $defaultColumnRows. '; counter++) {
        addColumn();
    }
});', true);

// show form
$form = new HtmlForm('mylist_configuration_form', '#', $page);
$form->openGroupBox('gb_configuration_list', $gL10n->get('SYS_CONFIGURATION'));

// read all relevant configurations from database and create an array
$yourLastConfigurationsGroup = false;
$yourConfigurationsGroup     = false;
$presetConfigurationsGroup   = false;
$actualGroup                 = '';
$configurationsArray[]       = array('', $gL10n->get('SYS_CREATE_NEW_CONFIGURATION'), null);
$numberLastConfigurations    = 0;

$sql = 'SELECT lst_id, lst_uuid, lst_name, lst_global, lst_timestamp
          FROM '.TBL_LISTS.'
         WHERE lst_org_id = ? -- $gCurrentOrgId
           AND (  lst_usr_id = ? -- $gCurrentUserId
               OR lst_global = true)
      ORDER BY lst_global ASC, lst_name ASC, lst_timestamp DESC';
$configurationsStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $gCurrentUserId));

$configurations = $configurationsStatement->fetchAll();

foreach ($configurations as $configuration) {
    if ($configuration['lst_global'] == 0 && !$yourLastConfigurationsGroup && (string) $configuration['lst_name'] === '') {
        $actualGroup = $gL10n->get('SYS_YOUR_LAST_CONFIGURATION');
        $yourLastConfigurationsGroup = true;
    } elseif ($configuration['lst_global'] == 0 && !$yourConfigurationsGroup && (string) $configuration['lst_name'] !== '') {
        $actualGroup = $gL10n->get('SYS_YOUR_CONFIGURATION');
        $yourConfigurationsGroup = true;
    } elseif ($configuration['lst_global'] == 1 && !$presetConfigurationsGroup) {
        $actualGroup = $gL10n->get('SYS_PRESET_CONFIGURATION');
        $presetConfigurationsGroup = true;
    }

    try {// if it's a temporary saved configuration than show timestamp of creating as name
        if ((string)$configuration['lst_name'] === '') {
            $objListTimestamp = new DateTime($configuration['lst_timestamp']);
            ++$numberLastConfigurations;

            // only 5 configurations without a name should be saved for each user
            if ($numberLastConfigurations > 5) {
                // delete all other configurations
                $delList = new ListConfiguration($gDb, $configuration['lst_id']);
                $delList->delete();
            } else {
                // now add configuration to array
                $configurationsArray[] = array($configuration['lst_uuid'], $objListTimestamp->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')), $actualGroup);
            }
        } else {
            // now add configuration to array
            $configurationsArray[] = array($configuration['lst_uuid'], $configuration['lst_name'], $actualGroup);
        }
    } catch (AdmException $e) {
        $e->showHtml();
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

$form->addSelectBox(
    'sel_select_configuration',
    $gL10n->get('SYS_SELECT_CONFIGURATION'),
    $configurationsArray,
    array('defaultValue' => $formValues['sel_select_configuration'], 'showContextDependentFirstEntry' => false)
);

// Administrators could upgrade a configuration to a global configuration that is visible to all users
if ($gCurrentUser->isAdministrator()) {
    $form->addCheckbox(
        'cbx_global_configuration',
        $gL10n->get('SYS_CONFIGURATION_ALL_USERS'),
        (bool) $list->getValue('lst_global'),
        array('defaultValue' => $formValues['cbx_global_configuration'], 'helpTextIdLabel' => 'SYS_PRESET_CONFIGURATION_DESC')
    );
}

    $form->addDescription($gL10n->get('SYS_ADD_COLUMNS_DESC'));
    $form->addHtml('
    <div class="table-responsive">
    <table class="table table-condensed" id="mylist_fields_table">
        <thead>
            <tr>
                <th style="width: 20%;">'.$gL10n->get('SYS_ABR_NO').'</th>
                <th style="width: 37%;">'.$gL10n->get('SYS_CONTENT').'</th>
                <th style="width: 18%;">'.$gL10n->get('SYS_ORDER').'</th>
                <th style="width: 25%;">'.$gL10n->get('SYS_CONDITION').'
                    <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-class="modal-lg"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/msg_window.php', array('message_id' => 'mylist_condition', 'inline' => 'true')).'">
                        <i class="fas fa-info-circle admidio-info-icon"></i>
                    </a>
                </th>
            </tr>
        </thead>
        <tbody id="mylist_fields_tbody">
        </tbody>
    </table>
    </div>');

$form->openButtonGroup();
$form->addButton('btn_add_column', $gL10n->get('SYS_ADD_COLUMN'), array('icon' => 'fa-plus-circle'));
if ($getListUuid !== '' && $list->getValue('lst_name') !== '') {
    $form->addButton('btn_save_changes', $gL10n->get('SYS_SAVE_CHANGES'), array('icon' => 'fa-check'));
} else {
    $form->addButton('btn_save', $gL10n->get('SYS_SAVE_CONFIGURATION'), array('icon' => 'fa-check'));
}
// your lists could be deleted, administrators are allowed to delete system configurations
if (($gCurrentUser->isAdministrator() && $list->getValue('lst_global') == 1)
|| ($gCurrentUserId === (int) $list->getValue('lst_usr_id') && strlen($list->getValue('lst_name')) > 0)) {
    $form->addButton('btn_delete', $gL10n->get('SYS_DELETE_CONFIGURATION'), array('icon' => 'fa-trash-alt'));
}
// current configuration can be duplicated and saved with another name
if (strlen($list->getValue('lst_name')) > 0) {
    $form->addButton(
        'btn_copy',
        $gL10n->get('SYS_COPY_VAR', array($gL10n->get('SYS_CONFIGURATION'))),
        array('icon' => 'fa-clone')
    );
}
$form->closeButtonGroup();

$form->closeGroupBox();

$form->openGroupBox('gb_select_members', $gL10n->get('SYS_SELECT_MEMBERS'));

// show all roles where the user has the right to view them
$sqlData = array();
if ($getActiveRole) {
    $allVisibleRoles = $gCurrentUser->getRolesViewMemberships();

    // check if there are roles that the current user could view
    if (count($allVisibleRoles) === 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS_VIEW_LIST'));
        // => EXIT
    }

    $sqlData['query'] = 'SELECT rol_id, rol_name, cat_name
                           FROM '.TBL_ROLES.'
                     INNER JOIN '.TBL_CATEGORIES.'
                             ON cat_id = rol_cat_id
                          WHERE rol_id IN (' . Database::getQmForValues($allVisibleRoles) . ')
                       ORDER BY cat_sequence, rol_name';
    $sqlData['params'] = $allVisibleRoles;
} else {
    $sqlData['query'] = 'SELECT rol_id, rol_name, cat_name
                           FROM '.TBL_ROLES.'
                     INNER JOIN '.TBL_CATEGORIES.'
                             ON cat_id = rol_cat_id
                            AND cat_name_intern <> \'EVENTS\'
                          WHERE rol_valid  = false
                            AND (  cat_org_id  = ? -- $gCurrentOrgId
                                OR cat_org_id IS NULL )
                       ORDER BY cat_sequence, rol_name';
    $sqlData['params'] = array($gCurrentOrgId);

    // check if there are roles that the current user could view
    $inactiveRolesStatement = $gDb->queryPrepared($sqlData['query'], $sqlData['params']);
    if ($inactiveRolesStatement->rowCount() === 0) {
        $gMessage->show($gL10n->get('PRO_NO_ROLES_VISIBLE'));
        // => EXIT
    }
}
$form->addSelectBoxFromSql(
    'sel_roles_ids',
    $gL10n->get('SYS_ROLE'),
    $gDb,
    $sqlData,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['sel_roles_ids'], 'multiselect' => true)
);

if ($gSettingsManager->getBool('members_enable_user_relations')) {
    // select box showing all relation types
    $sql = 'SELECT urt_id, urt_name, urt_name
              FROM '.TBL_USER_RELATION_TYPES.'
          ORDER BY urt_name';
    $form->addSelectBoxFromSql(
        'sel_relationtype_ids',
        $gL10n->get('SYS_USER_RELATION'),
        $gDb,
        $sql,
        array('showContextDependentFirstEntry' => false, 'multiselect' => true, 'defaultValue' => isset($formValues['sel_relationtype_ids']) ? $formValues['sel_relationtype_ids'] : '')
    );
}

$form->closeGroupBox();

$form->addButton(
    'btn_show_list',
    $gL10n->get('SYS_SHOW_LIST'),
    array('icon' => 'fa-list-alt', 'class' => 'btn-primary admidio-margin-bottom')
);

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
