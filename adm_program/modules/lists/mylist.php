<?php
/**
 ***********************************************************************************************
 * Create a custom list
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lst_id : Id of the list configuration that should be shown
 * rol_id : (Optional) If a role id is set then the form field will be preassigned.
 * active_role  : true  - (Default) List only active roles
 *                false - List only deactivated roles
 * show_members : 0 - (Default) show active members of role
 *                1 - show former members of role
 *                2 - show active and former members of role
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getListId      = admFuncVariableIsValid($_GET, 'lst_id',       'int');
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id',       'int');
$getActiveRole  = admFuncVariableIsValid($_GET, 'active_role',  'bool', array('defaultValue' => true));
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'int');

// falls ehemalige Rolle, dann auch nur ehemalige Mitglieder anzeigen
if(!$getActiveRole)
{
    $getShowMembers = 1;
}

// set headline of the script
$headline = $gL10n->get('LST_MY_LIST').' - '.$gL10n->get('LST_CONFIGURATION');

if($getRoleId === 0)
{
    // Navigation faengt hier im Modul an
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL, $headline);

$defaultColumnRows   = 6;    // number of columns that should be shown
$mySqlMaxColumnAlert = '';

// Listenobjekt anlegen
$list = new ListConfiguration($gDb, $getListId);

if(isset($_SESSION['mylist_request']))
{
    $formValues = strStripSlashesDeep($_SESSION['mylist_request']);
    unset($_SESSION['mylist_request']);

    if(!isset($formValues['cbx_global_configuration']))
    {
        $formValues['cbx_global_configuration'] = 0;
    }

    if(!isset($formValues['sel_roles_ids']))
    {
        $formValues['sel_roles_ids'] = 0;
    }

    // falls vorher schon Zeilen fuer Spalten manuell hinzugefuegt wurden,
    // muessen diese nun direkt angelegt werden
    for($i = $defaultColumnRows+1; $i > 0; ++$i)
    {
        if(isset($formValues['column'.$i]))
        {
            ++$defaultColumnRows;
        }
        else
        {
            $i = -1;
        }
    }
}
else
{
    $formValues['sel_select_configuation']  = $getListId;
    $formValues['cbx_global_configuration'] = $list->getValue('lst_global');
    $formValues['sel_roles_ids']            = $getRoleId;
    $formValues['sel_show_members']         = $getShowMembers;

    // if a saved configuration was loaded then add columns to formValues array
    if($getListId > 0)
    {
        $defaultColumnRows = $list->countColumns();

        for($number = 1; $number <= $list->countColumns(); ++$number)
        {
            $column = $list->getColumnObject($number);
            if($column->getValue('lsc_usf_id') > 0)
            {
                $column_content = $column->getValue('lsc_usf_id');
            }
            else
            {
                $column_content = $column->getValue('lsc_special_field');
            }

            $formValues['column'. $number]    = $column_content;
            $formValues['sort'. $number]      = $column->getValue('lsc_sort');
            $formValues['condition'. $number] = $column->getValue('lsc_filter');
        }
    }
}

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// within MySql it's only possible to join 61 tables therefore show a message if user
// want's to join more than 57 columns
if($gDbType === 'mysql')
{
    $mySqlMaxColumnAlert = '
    if(fieldNumberIntern >= 57)
    {
        alert("'.$gL10n->get('LST_NO_MORE_COLUMN').'");
        return;
    }';
}

$javascriptCode = '
    var listId             = '.$getListId.';
    var fieldNumberIntern  = 0;
    var arr_user_fields    = createProfileFieldsArray();
    var arr_default_fields = createColumnsArray();

    // Funktion fuegt eine neue Zeile zum Zuordnen von Spalten fuer die Liste hinzu
    function addColumn()
    {
        '.$mySqlMaxColumnAlert.'

        var category = "";
        var fieldNumberShow  = fieldNumberIntern + 1;
        var table = document.getElementById("mylist_fields_tbody");
        var newTableRow = table.insertRow(fieldNumberIntern);
        newTableRow.setAttribute("id", "row" + fieldNumberShow)
        //$(newTableRow).css("display", "none"); // ausgebaut wg. Kompatibilitaetsproblemen im IE8
        var newCellCount = newTableRow.insertCell(-1);
        newCellCount.innerHTML = (fieldNumberShow) + ".&nbsp;'.$gL10n->get('LST_COLUMN').'&nbsp;:";

        // neue Spalte zur Auswahl des Profilfeldes
        var newCellField = newTableRow.insertCell(-1);
        htmlCboFields = "<select class=\"form-control\" onchange=\"getConditionField(" + fieldNumberShow + ", this.options[this.selectedIndex].text)\" size=\"1\" id=\"column" + fieldNumberShow + "\" class=\"ListProfileField\" name=\"column" + fieldNumberShow + "\">" +
                "<option value=\"\"></option>";
        for(var counter = 1; counter < arr_user_fields.length; counter++)
        {
            if(category != arr_user_fields[counter]["cat_name"])
            {
                if(category.length > 0)
                {
                    htmlCboFields += "</optgroup>";
                }
                htmlCboFields += "<optgroup label=\"" + arr_user_fields[counter]["cat_name"] + "\">";
                category = arr_user_fields[counter]["cat_name"];
            }

            var selected = "";
            // bei einer neuen Liste sind Vorname und Nachname in den ersten Spalten vorbelegt
            if((  (fieldNumberIntern === 0 && arr_user_fields[counter]["usf_name_intern"] === "LAST_NAME")
               || (fieldNumberIntern === 1 && arr_user_fields[counter]["usf_name_intern"] === "FIRST_NAME"))
            && listId == 0)
            {
                selected = " selected=\"selected\" ";
            }

            // bei gespeicherten Listen das entsprechende Profilfeld selektieren
            // und den Feldnamen dem Listenarray hinzufügen
            if(arr_default_fields[fieldNumberShow])
            {
                if(arr_user_fields[counter]["usf_id"] == arr_default_fields[fieldNumberShow]["usf_id"])
                {
                    selected = " selected=\"selected\" ";
                    arr_default_fields[fieldNumberShow]["usf_name"] = arr_user_fields[counter]["usf_name"];
                }
            }
            htmlCboFields += "<option value=\"" + arr_user_fields[counter]["usf_id"] + "\" " + selected + ">" + arr_user_fields[counter]["usf_name"] + "</option>";
        }
        htmlCboFields += "</select>";
        newCellField.innerHTML = htmlCboFields;

        // neue Spalte zur Einstellung der Sortierung
        var selectAsc  = "";
        var selectDesc = "";

        if(arr_default_fields[fieldNumberShow])
        {
            if(arr_default_fields[fieldNumberShow]["sort"] === "ASC")
            {
                selectAsc = " selected=\"selected\" ";
            }
            if(arr_default_fields[fieldNumberShow]["sort"] === "DESC")
            {
                selectDesc = " selected=\"selected\" ";
            }
        }
        else if(fieldNumberIntern === 0)
        {
            selectAsc = " selected=\"selected\" ";
        }

        var newCellOrder = newTableRow.insertCell(-1);
        newCellOrder.innerHTML = "<select class=\"form-control\" size=\"1\" id=\"sort" + fieldNumberShow + "\" name=\"sort" + fieldNumberShow + "\">" +
                "<option value=\"\">&nbsp;</option>" +
                "<option value=\"ASC\" " + selectAsc + ">'.$gL10n->get('LST_A_TO_Z').'</option>" +
                "<option value=\"DESC\" " + selectDesc + ">'.$gL10n->get('LST_Z_TO_A').'</option>" +
            "</select>";

        // neue Spalte fuer Bedingungen
        condition = "";
        if(arr_default_fields[fieldNumberShow])
        {
            var fieldName = arr_default_fields[fieldNumberShow]["usf_name"];

            if(arr_default_fields[fieldNumberShow]["condition"])
            {
                condition = arr_default_fields[fieldNumberShow]["condition"];
                condition = condition.replace(/{/g, "<");
                condition = condition.replace(/}/g, ">");
            }
        }
        else
        {
            var fieldName = "";
        }

        htmlFormCondition = setConditonField(fieldNumberShow, fieldName);
        var newCellConditions = newTableRow.insertCell(-1);
        newCellConditions.setAttribute("id", "td_condition" + fieldNumberShow);
        newCellConditions.innerHTML = htmlFormCondition;

        $(newTableRow).fadeIn("slow");
        fieldNumberIntern++;
    }

    function createProfileFieldsArray()
    {
        var user_fields = new Array(); ';

// create a multidimensional array for all columns with the necessary data
$i = 1;
$oldCategoryNameIntern = '';
$posEndOfMasterData = 0;

foreach($gProfileFields->mProfileFields as $field)
{
    // at the end of category master data save positions for loginname and username
    // they will be added after profile fields loop
    if($oldCategoryNameIntern === 'MASTER_DATA'
        && $field->getValue('cat_name_intern') !== 'MASTER_DATA')
    {
        $posEndOfMasterData = $i;
        $i = $i + 2;
    }

    // add profile field to user field array
    if($field->getValue('usf_hidden') == 0 || $gCurrentUser->editUsers())
    {
        $javascriptCode .= '
                user_fields['. $i. '] = new Object();
                user_fields['. $i. ']["cat_id"]   = "'. $field->getValue('cat_id'). '";
                user_fields['. $i. ']["cat_name"] = "'. strtr($field->getValue('cat_name'), '"', '\''). '";
                user_fields['. $i. ']["usf_id"]   = "'. $field->getValue('usf_id'). '";
                user_fields['. $i. ']["usf_name"] = "'. addslashes($field->getValue('usf_name')). '";
                user_fields['. $i. ']["usf_name_intern"] = "'. addslashes($field->getValue('usf_name_intern')). '";
                user_fields['. $i. ']["usf_type"] = "'. $field->getValue('usf_type'). '";
                user_fields['. $i. ']["usf_value_list"] = new Object();';

        // get avaiable values for current field type and push to array
        if($field->getValue('usf_type') === 'DROPDOWN'
            || $field->getValue('usf_type') === 'RADIO_BUTTON')
        {
            foreach($field->getValue('usf_value_list', 'text') as $key => $value)
            {
                $javascriptCode .= '
                        user_fields['. $i. ']["usf_value_list"]["'. $key .'"] = "'. $value .'";';
            }
        }
        else
        {
            $javascriptCode .= '
                    user_fields['. $i. ']["usf_value_list"] = "";';
        }

        $oldCategoryNameIntern = $field->getValue('cat_name_intern');
        ++$i;
    }
}

// Add loginname and photo at the end of category master data
// add new category with start and end date of role membership
if($posEndOfMasterData == 0)
{
    $posEndOfMasterData = $i;
    $i = $i + 2;
}
$javascriptCode .= '
        user_fields['. $posEndOfMasterData. '] = new Object();
        user_fields['. $posEndOfMasterData. ']["cat_id"]   = user_fields[1]["cat_id"];
        user_fields['. $posEndOfMasterData. ']["cat_name"] = user_fields[1]["cat_name"];
        user_fields['. $posEndOfMasterData. ']["usf_id"]   = "usr_login_name";
        user_fields['. $posEndOfMasterData. ']["usf_name"] = "'.$gL10n->get('SYS_USERNAME').'";
        user_fields['. $posEndOfMasterData. ']["usf_name_intern"] = "'.$gL10n->get('SYS_USERNAME').'";

        user_fields['. ($posEndOfMasterData+1). '] = new Object();
        user_fields['. ($posEndOfMasterData+1). ']["cat_id"]   = user_fields[1]["cat_id"];;
        user_fields['. ($posEndOfMasterData+1). ']["cat_name"] = user_fields[1]["cat_name"];
        user_fields['. ($posEndOfMasterData+1). ']["usf_id"]   = "usr_photo";
        user_fields['. ($posEndOfMasterData+1). ']["usf_name"] = "'.$gL10n->get('PHO_PHOTO').'";
        user_fields['. ($posEndOfMasterData+1). ']["usf_name_intern"] = "'.$gL10n->get('PHO_PHOTO').'";

        user_fields['. $i. '] = new Object();
        user_fields['. $i. ']["cat_id"]   = -1;
        user_fields['. $i. ']["cat_name"] = "'.$gL10n->get('LST_ROLE_INFORMATION').'";
        user_fields['. $i. ']["usf_id"]   = "mem_begin";
        user_fields['. $i. ']["usf_name"] = "'.$gL10n->get('LST_MEMBERSHIP_START').'";
        user_fields['. $i. ']["usf_name_intern"] = "'.$gL10n->get('LST_MEMBERSHIP_START').'";';

++$i;
$javascriptCode .= '
        user_fields['. $i. '] = new Object();
        user_fields['. $i. ']["cat_id"]   = -1;
        user_fields['. $i. ']["cat_name"] = "'.$gL10n->get('LST_ROLE_INFORMATION').'";
        user_fields['. $i. ']["usf_id"]   = "mem_end";
        user_fields['. $i. ']["usf_name"] = "'.$gL10n->get('LST_MEMBERSHIP_END').'";
        user_fields['. $i. ']["usf_name_intern"] = "'.$gL10n->get('LST_MEMBERSHIP_END').'";

        return user_fields;
    }

    function createColumnsArray()
    {
        var default_fields = new Array(); ';

// now add all columns to the javascript row objects
$actualColumnNumber = 1;
while(isset($formValues['column'. $actualColumnNumber]))
{
    $sortValue      = '';
    $conditionValue = '';

    if(isset($formValues['sort'. $actualColumnNumber]))
    {
        $sortValue = $formValues['sort'. $actualColumnNumber];
    }
    if(isset($formValues['condition'. $actualColumnNumber]))
    {
        $conditionValue = $formValues['condition'. $actualColumnNumber];
    }

    $javascriptCode .= '
            default_fields['. $actualColumnNumber. '] = new Object();
            default_fields['. $actualColumnNumber. ']["usf_id"]    = "'. $formValues['column'. $actualColumnNumber]. '";
            default_fields['. $actualColumnNumber. ']["sort"]      = "'. $sortValue. '";
            default_fields['. $actualColumnNumber. ']["condition"] = "'. $conditionValue. '";';

    ++$actualColumnNumber;
}

$javascriptCode .= '
        return default_fields;
    }

    function getConditionField(columnNumber, columnName)
    {
        htmlFormCondition = setConditonField(columnNumber, columnName);
        $("#td_condition" + columnNumber).html(htmlFormCondition);
    }

    function setConditonField(fieldNumberShow, columnName)
    {
        html = "<input type=\"text\" class=\"form-control\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\" maxlength=\"50\" value=\"" + condition + "\" />";
        var key;

        for (key in arr_user_fields)
        {
            if(arr_user_fields[key]["usf_name"] == columnName)
            {
                if(arr_user_fields[key]["usf_type"] === "DROPDOWN"
                  || arr_user_fields[key]["usf_type"] === "RADIO_BUTTON")
                {
                    html = "<select class=\"form-control\" size=\"1\" id=\"condition" + fieldNumberShow + "\" class=\"ListConditionField\" name=\"condition" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>";

                    for (selectValue in arr_user_fields[key]["usf_value_list"])
                    {
                        selected = "";

                        if(arr_default_fields[fieldNumberShow])
                        {
                            if(arr_user_fields[key]["usf_id"] == arr_default_fields[fieldNumberShow]["usf_id"]
                                && arr_user_fields[key]["usf_value_list"][selectValue] == arr_default_fields[fieldNumberShow]["condition"])
                            {
                                selected = " selected=\"selected\" ";
                            }
                        }
                        html += "<option value=\"" + arr_user_fields[key]["usf_value_list"][selectValue] + "\" " + selected + ">" + arr_user_fields[key]["usf_value_list"][selectValue] + "</option>";
                        "</select>";
                    }
                }

                if(arr_user_fields[key]["usf_type"] === "CHECKBOX")
                {
                    html = "<select class=\"form-control\" size=\"1\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>";

                    selected = "";

                    if(arr_default_fields[fieldNumberShow])
                    {

                        if(arr_user_fields[key]["usf_id"] == arr_default_fields[fieldNumberShow]["usf_id"]
                            && arr_default_fields[fieldNumberShow]["condition"] == "1")
                        {
                            selected = " selected=\"selected\" ";
                        }
                            html += "<option value=\"1\" " + selected + ">'.$gL10n->get('SYS_YES').'</option>";
                        selected = "";

                        if(arr_user_fields[key]["usf_id"] == arr_default_fields[fieldNumberShow]["usf_id"]
                            && arr_default_fields[fieldNumberShow]["condition"] == "0")
                        {
                            selected = " selected=\"selected\" ";
                        }
                            html += "<option value=\"0\" " + selected + ">'.$gL10n->get('SYS_NO').'</option>" +
                            "</select>";
                    }
                    else
                    {
                        html += "<option value=\"1\">'.$gL10n->get('SYS_YES').'</option>" +
                                "<option value=\"0\">'.$gL10n->get('SYS_NO').'</option>" +
                                "</select>";
                    }
                }
            }
        }
        return html;
    }

    function loadList()
    {
        var listId = $("#sel_select_configuation").val();
        var show_members = $("#sel_show_members").val();
        self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?lst_id=" + listId + "&active_role='.$getActiveRole.'&show_members=" + show_members;
    }

    function send(mode)
    {
        for(var i = 1; i <= fieldNumberIntern; i++)
        {
            if(document.getElementById("condition" + i))
            {
                var condition = document.getElementById("condition" + i);
                condition.value = condition.value.replace(/</g, "{");
                condition.value = condition.value.replace(/>/g, "}");
            }
        }

        switch (mode)
        {
            case "show":
                document.getElementById("mylist_configuration_form").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?mode=2";
                document.getElementById("mylist_configuration_form").submit();
                break;

            case "save":
                document.getElementById("mylist_configuration_form").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=1";
                document.getElementById("mylist_configuration_form").submit();
                break;

            case "save_as":
                var listName = "";
                listName = prompt("'.$gL10n->get('LST_CONFIGURATION_SAVE').'");
                if(listName != null)
                {
                    document.getElementById("mylist_configuration_form").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?mode=1&name=" + listName;
                    document.getElementById("mylist_configuration_form").submit();
                }
                break;

            case "delete":
                var msg_result = confirm("'.$gL10n->get('LST_CONFIGURATION_DELETE').'");
                if(msg_result)
                {
                    document.getElementById("mylist_configuration_form").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=3";
                    document.getElementById("mylist_configuration_form").submit();
                }
                break;

            case "system":
                var msg_result = confirm("'.$gL10n->get('LST_WANT_CONFIGURATION_FOR_ALL_USERS').'");
                if(msg_result)
                {
                    document.getElementById("mylist_configuration_form").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=4";
                    document.getElementById("mylist_configuration_form").submit();
                }
                break;
        }
    }';
$page->addJavascript($javascriptCode);
$page->addJavascript('$(document).ready(function() {
    $("#sel_select_configuation").change(function() { loadList(); });
    $("#btn_show_list").click(function() { send("show"); });
    $("#btn_add_column").click(function() { addColumn(); });
    $("#btn_save").click(function() { send("save_as"); });
    $("#btn_save_changes").click(function() { send("save"); });
    $("#btn_delete").click(function() { send("delete"); });
    $("#btn_copy").click(function() { send("save_as"); });

    for(var counter = 0; counter < '. $defaultColumnRows. '; counter++) {
        addColumn();
    }
});', true);

// get module menu
$myListMenu = $page->getMenu();

// show link to system preferences of roles
if($gCurrentUser->isWebmaster())
{
    $myListMenu->addItem('admMenuItemPreferencesLists', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=lists',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

// if mylist was not called directly then show link to navigate to previous page
if($gNavigation->count() > 1)
{
    $myListMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

// show form
$form = new HtmlForm('mylist_configuration_form', $g_root_path. '/adm_program/modules/lists/mylist_prepare.php', $page);
$form->openGroupBox('gb_configuration_list', $gL10n->get('LST_CONFIGURATION_LIST'));

// read all relevant configurations from database and create an array
$yourLastConfigurationsGroup = false;
$yourConfigurationsGroup     = false;
$presetConfigurationsGroup   = false;
$actualGroup                 = '';
$configurationsArray[]       = array(0, $gL10n->get('LST_CREATE_NEW_CONFIGURATION'), null);
$numberLastConfigurations    = 0;

$sql = 'SELECT lst_id, lst_name, lst_global, lst_timestamp
          FROM '.TBL_LISTS.'
         WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id') .'
           AND (  lst_usr_id = '. $gCurrentUser->getValue('usr_id'). '
               OR lst_global = 1)
         ORDER BY lst_global ASC, lst_name ASC, lst_timestamp DESC';
$configurationsStatement = $gDb->query($sql);

$configurations = $configurationsStatement->fetchAll();

foreach($configurations as $configuration)
{
    if($configuration['lst_global'] == 0 && !$yourLastConfigurationsGroup && strlen($configuration['lst_name']) === 0)
    {
        $actualGroup = $gL10n->get('LST_YOUR_LAST_CONFIGURATION');
        $yourLastConfigurationsGroup = true;
    }
    elseif($configuration['lst_global'] == 0 && !$yourConfigurationsGroup && strlen($configuration['lst_name']) > 0)
    {
        $actualGroup = $gL10n->get('LST_YOUR_CONFIGURATION');
        $yourConfigurationsGroup = true;
    }
    elseif($configuration['lst_global'] == 1 && !$presetConfigurationsGroup)
    {
        $actualGroup = $gL10n->get('LST_PRESET_CONFIGURATION');
        $presetConfigurationsGroup = true;
    }

    // if its a temporary saved configuration than show timestamp of creating as name
    if(strlen($configuration['lst_name']) === 0)
    {
        $objListTimestamp = new DateTime($configuration['lst_timestamp']);
        ++$numberLastConfigurations;

        // only 5 configurations without a name should be saved for each user
        if($numberLastConfigurations > 5)
        {
            // delete all other configurations
            $del_list = new ListConfiguration($gDb, $configuration['lst_id']);
            $del_list->delete();
        }
        else
        {
            // now add configuration to array
            $configurationsArray[] = array($configuration['lst_id'], $objListTimestamp->format($gPreferences['system_date'].' '.$gPreferences['system_time']), $actualGroup);
        }
    }
    else
    {
        // now add configuration to array
        $configurationsArray[] = array($configuration['lst_id'], $configuration['lst_name'], $actualGroup);
    }

}

$form->addSelectBox('sel_select_configuation', $gL10n->get('LST_SELECT_CONFIGURATION'), $configurationsArray,
    array('defaultValue' => $formValues['sel_select_configuation'], 'showContextDependentFirstEntry' => false));

// Webmasters could upgrade a configuration to a global configuration that is visible to all users
if($gCurrentUser->isWebmaster())
{
    $form->addCheckbox('cbx_global_configuration', $gL10n->get('LST_CONFIGURATION_ALL_USERS'), $list->getValue('lst_global'),
        array('defaultValue' => $formValues['cbx_global_configuration'], 'helpTextIdLabel' => 'LST_PRESET_CONFIGURATION_DESC'));
}

    $form->addDescription($gL10n->get('LST_ADD_COLUMNS_DESC'));
    $form->addHtml('
    <div class="table-responsive">
    <table class="table table-condensed" id="mylist_fields_table">
        <thead>
            <tr>
                <th style="width: 20%;">'.$gL10n->get('SYS_ABR_NO').'</th>
                <th style="width: 37%;">'.$gL10n->get('SYS_CONTENT').'</th>
                <th style="width: 18%;">'.$gL10n->get('SYS_ORDER').'</th>
                <th style="width: 25%;">'.$gL10n->get('SYS_CONDITION').'
                    <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.$g_root_path.'/adm_program/system/msg_window.php?message_id=mylist_condition&amp;inline=true">
                        <img src="'.THEME_PATH.'/icons/help.png" alt="Help" />
                    </a>
                </th>
            </tr>
        </thead>
        <tbody id="mylist_fields_tbody">
        </tbody>
    </table>
    </div>');

$form->openButtonGroup();
$form->addButton('btn_add_column', $gL10n->get('LST_ADD_COLUMN'), array('icon' => THEME_PATH.'/icons/add.png'));
if($getListId > 0 && $list->getValue('lst_name') !== '')
{
    $form->addButton('btn_save_changes', $gL10n->get('LST_SAVE_CHANGES'), array('icon' => THEME_PATH.'/icons/disk.png'));
}
else
{
    $form->addButton('btn_save', $gL10n->get('LST_SAVE_CONFIGURATION'), array('icon' => THEME_PATH.'/icons/disk.png'));
}
// your lists could be deleted, webmasters are allowed to delete system configurations
if(($gCurrentUser->isWebmaster() && $list->getValue('lst_global') == 1)
|| ($gCurrentUser->getValue('usr_id') == $list->getValue('lst_usr_id') && strlen($list->getValue('lst_name')) > 0))
{
    $form->addButton('btn_delete', $gL10n->get('LST_DELETE_CONFIGURATION'), array('icon' => THEME_PATH.'/icons/delete.png'));
}
// current configuration can be duplicated and saved with another name
if(strlen($list->getValue('lst_name')) > 0)
{
    $form->addButton('btn_copy', $gL10n->get('SYS_COPY_VAR', $gL10n->get('LST_CONFIGURATION')), array('icon' => THEME_PATH.'/icons/application_double.png'));
}
$form->closeButtonGroup();

$form->closeGroupBox();

$form->openGroupBox('gb_select_members', $gL10n->get('LST_SELECT_MEMBERS'));
// show all roles where the user has the right to see them
$sql = 'SELECT rol_id, rol_name, cat_name
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_valid   = '.$getActiveRole.'
           AND rol_visible = 1
           AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence, rol_name';
$form->addSelectBoxFromSql('sel_roles_ids', $gL10n->get('SYS_ROLE'), $gDb, $sql,
    array('property' => FIELD_REQUIRED, 'defaultValue' => $formValues['sel_roles_ids'], 'multiselect' => true));
$showMembersSelection = array($gL10n->get('LST_ACTIVE_MEMBERS'), $gL10n->get('LST_FORMER_MEMBERS'), $gL10n->get('LST_ACTIVE_FORMER_MEMBERS'));
$form->addSelectBox('sel_show_members', $gL10n->get('LST_MEMBER_STATUS'), $showMembersSelection,
    array('property' => FIELD_REQUIRED, 'defaultValue' => $formValues['sel_show_members'], 'showContextDependentFirstEntry' => false));
$form->closeGroupBox();

$form->addButton('btn_show_list', $gL10n->get('LST_SHOW_LIST'), array('icon' => THEME_PATH.'/icons/list.png', 'class' => 'btn-primary'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
