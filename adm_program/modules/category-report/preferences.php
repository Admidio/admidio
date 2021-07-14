<?php
/**
 ***********************************************************************************************
 * Modul Preferences of the admidio module CategoryReport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * add_delete : -1 - Generate a configuration
 * 				>0 - Deleting a configuration
 * show_option: direct opening of a panel of the accordion menu
 *
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/../../system/login_valid.php');
require_once(__DIR__ . '/common_function.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getAddDelete = admFuncVariableIsValid($_GET, 'add_delete', 'numeric', array('defaultValue' => 0));
$showOption   = admFuncVariableIsValid($_GET, 'show_option', 'string');

$report = new CategoryReport();
$config = $report->getConfigArray();
$catReportConfigs = array();

$headline = $gL10n->get('SYS_CATEGORY_REPORT');

if ($getAddDelete === -1)
{
    foreach($config as $key => $dummy)
    {
        $config[] = array('name' => '', 'col_fields' => '', 'selection_role' => '', 'selection_cat' => '', 'number_col' => '');
    }
}
elseif ($getAddDelete > 0)
{
    foreach($config as $key => $dummy)
    {
        array_splice($config[$key], $getAddDelete-1, 1);
    }

    // durch das Loeschen einer Konfiguration kann der Fall eintreten, dass es die eingestellte Standardkonfiguration nicht mehr gibt
    // daher die Standardkonfiguration auf die erste Konfiguration im Array setzen
    $gSettingsManager->set('category_report_default_configuration', 0);
    saveConfigArray($config);
}

if ($getAddDelete)
{
    $showOption = 'configurations';
}

$gNavigation->clear();
$gNavigation->addUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/category_report.php');
$gNavigation->addUrl(CURRENT_URL);

// create html page object
$page = new HtmlPage('plg-category-report-preferences', $headline);

// open the module configurations if a new configuration is added or deleted
if ($showOption <> '')
{
    $page->addJavascript('
        $("#tabs_nav_common").attr("class", "nav-link active");
        $("#tabs-common").attr("class", "tab-pane fade show active");
        $("#collapse_'.$showOption.'").attr("class", "collapse show");
        location.hash = "#" + "panel_'.$showOption.'";',
        true
    );
}
else
{
    $page->addJavascript('
        $("#tabs_nav_common").attr("class", "active");
        $("#tabs-common").attr("class", "tab-pane active");',
        true
    );
}

$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {

                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                }
            }
        });
    });',
    true
);

$javascriptCode = 'var arr_user_fields = createProfileFieldsArray();';

// create an array with the necessary data
foreach($config as $key => $value)
{
    $catReportConfigs[$key] = $value['name'];
    $javascriptCode .= '

        var arr_default_fields'.$key.' = createColumnsArray'.$key.'();
        var fieldNumberIntern'.$key.'  = 0;

    	// Funktion fuegt eine neue Zeile zum Zuordnen von Spalten fuer die Liste hinzu
    	function addColumn'.$key.'()
    	{
        	var category = "";
        	var fieldNumberShow  = fieldNumberIntern'.$key.' + 1;
        	var table = document.getElementById("mylist_fields_tbody'.$key.'");
        	var newTableRow = table.insertRow(fieldNumberIntern'.$key.');
        	newTableRow.setAttribute("id", "row" + (fieldNumberIntern'.$key.' + 1))
        	//$(newTableRow).css("display", "none"); // ausgebaut wg. Kompatibilitaetsproblemen im IE8
        	var newCellCount = newTableRow.insertCell(-1);
        	newCellCount.innerHTML = (fieldNumberShow) + ".&nbsp;'.$gL10n->get('SYS_COLUMN').':";

        	// neue Spalte zur Auswahl des Profilfeldes
        	var newCellField = newTableRow.insertCell(-1);
        	htmlCboFields = "<select class=\"form-control\"  size=\"1\" id=\"column" + fieldNumberShow + "\" class=\"ListProfileField\" name=\"column'.$key.'_" + fieldNumberShow + "\">" +
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

            	// bei gespeicherten Listen das entsprechende Profilfeld selektieren
            	// und den Feldnamen dem Listenarray hinzufuegen
            	if(arr_default_fields'.$key.'[fieldNumberIntern'.$key.'])
            	{
                	if(arr_user_fields[counter]["id"] == arr_default_fields'.$key.'[fieldNumberIntern'.$key.']["id"])
                	{
                    	selected = " selected=\"selected\" ";
                   	 arr_default_fields'.$key.'[fieldNumberIntern'.$key.']["data"] = arr_user_fields[counter]["data"];
                	}
            	}
             	htmlCboFields += "<option value=\"" + arr_user_fields[counter]["id"] + "\" " + selected + ">" + arr_user_fields[counter]["data"] + "</option>";
        	}
        	htmlCboFields += "</select>";
        	newCellField.innerHTML = htmlCboFields;

        	$(newTableRow).fadeIn("slow");
        	fieldNumberIntern'.$key.'++;
    	}

    	function createColumnsArray'.$key.'()
    	{
        	var default_fields = new Array(); ';
    $fields = explode(',', $value['col_fields']);
    for ($number = 0; $number < count($fields); $number++)
    {
        // das ist nur zur Überprüfung, ob diese Freigabe noch existent ist
        // es koennte u.U. ja sein, dass ein Profilfeld oder eine Rolle seit der letzten Speicherung geloescht wurde
        $found = $report->isInHeaderSelection($fields[$number]);
        if ($found > 0)
        {
            $javascriptCode .= '
                	default_fields['. $number. '] 		  = new Object();
                	default_fields['. $number. ']["id"]   = "'. $report->headerSelection[$found]["id"]. '";
                	default_fields['. $number. ']["data"] = "'. $report->headerSelection[$found]["data"]. '";
                	';
        }
    }
    $javascriptCode .= '
        	return default_fields;
    	}
    	';
}

$javascriptCode .= '
    function createProfileFieldsArray()
    {
        var user_fields = new Array(); ';

// create an array for all columns with the necessary data
foreach($report->headerSelection as $key => $value)
{
    $javascriptCode .= '
                user_fields['. $key. '] 			= new Object();
                user_fields['. $key. ']["id"]   	= "'. $report->headerSelection[$key]['id'] . '";
                user_fields['. $key. ']["cat_name"] = "'. $report->headerSelection[$key]['cat_name']. '";
                user_fields['. $key. ']["data"]   	= "'. $report->headerSelection[$key]['data'] . '";
                ';
}
$javascriptCode .= '
        return user_fields;
    }
';
$page->addJavascript($javascriptCode);
$javascriptCode = '$(document).ready(function() { ';

foreach($config as $key => $value)
{
    $javascriptCode .= '
    	for(var counter = 0; counter < '. count(explode(',', $value['col_fields'])). '; counter++) {
        	addColumn'. $key. '();
    	}
    	';
}
$javascriptCode .= ' }); ';
$page->addJavascript($javascriptCode, true);

/**
 * @param string $group
 * @param string $id
 * @param string $title
 * @param string $icon
 * @param string $body
 * @return string
 */
function getPreferencePanel($group, $id, $title, $icon, $body)
{
    $html = '
        <div class="card" id="panel_' . $id . '">
            <div class="card-header">
                <a type="button" data-toggle="collapse" data-target="#collapse_' . $id . '">
                    <i class="' . $icon . ' fa-fw"></i>' . $title . '
                </a>
            </div>
            <div id="collapse_' . $id . '" class="collapse" aria-labelledby="headingOne" data-parent="#accordion_preferences">
                <div class="card-body">
                    ' . $body . '
                </div>
            </div>
        </div>
    ';
    return $html;
}

$page->addHtml('
<ul id="preferences_tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="tabs_nav_common" class="nav-link" href="#tabs-common" data-toggle="tab" role="tab">'.$gL10n->get('SYS_SETTINGS').'</a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade" id="tabs-common" role="tabpanel">
        <div class="accordion" id="accordion_preferences">');

// PANEL: CONFIGURATIONS

$formConfigurations = new HtmlForm(
    'configurations_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences_function.php', array('form' => 'configurations')),
    $page, array('class' => 'form-preferences')
);

$formConfigurations->addDescription($gL10n->get('SYS_CONFIGURATIONS_HEADER'));
$formConfigurations->addLine();
$formConfigurations->addDescription('<div style="width:100%; height:550px; overflow:auto; border:20px;">');
$currentNumberConf = 0;

foreach($config as $key => $value)
{
    $formConfigurations->openGroupBox('configurations_group',++$currentNumberConf.'. '.$gL10n->get('SYS_CONFIGURATION'));
    $formConfigurations->addInput('name'.$key, $gL10n->get('SYS_DESIGNATION'), $value['name'],
        array('property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'SYS_CAT_SELECTION_COL_DESC'));
    $html = '
	   <div class="table-responsive">
    		<table class="table table-condensed" id="mylist_fields_table">
        		<thead>
            		<tr>
                		<th style="width: 20%;">'.$gL10n->get('SYS_ABR_NO').'</th>
                		<th style="width: 37%;">'.$gL10n->get('SYS_CONTENT').'</th>
            		</tr>
        		</thead>
        		<tbody id="mylist_fields_tbody'.$key.'">
            		<tr id="table_row_button">
                		<td colspan="2">
                    		<a class="icon-text-link" href="javascript:addColumn'.$key.'()"><i class="fas fa-plus-circle"></i> '.$gL10n->get('SYS_ADD_COLUMN').'</a>
                		</td>
            		</tr>
        		</tbody>
    		</table>
    	</div>';
    $formConfigurations->addCustomContent($gL10n->get('SYS_COLUMN_SELECTION'), $html, array('helpTextIdLabel' => 'SYS_COLUMN_SELECTION_DESC'));

    $sql = 'SELECT rol_id, rol_name, cat_name
              FROM '.TBL_CATEGORIES.' , '.TBL_ROLES.'
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                OR cat_org_id IS NULL )';
    $formConfigurations->addSelectBoxFromSql('selection_role'.$key, $gL10n->get('SYS_ROLE_SELECTION'), $gDb, $sql,
        array('defaultValue' => explode(',',$value['selection_role']),'multiselect' => true, 'helpTextIdLabel' => 'SYS_ROLE_SELECTION_CONF_DESC'));

    $sql = 'SELECT cat_id, cat_name
              FROM '.TBL_CATEGORIES.' , '.TBL_ROLES.'
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                OR cat_org_id IS NULL )';
    $formConfigurations->addSelectBoxFromSql('selection_cat'.$key, $gL10n->get('SYS_CAT_SELECTION'), $gDb, $sql,
        array('defaultValue' => explode(',',$value['selection_cat']),'multiselect' => true, 'helpTextIdLabel' => 'SYS_CAT_SELECTION_CONF_DESC'));
    $formConfigurations->addCheckbox('number_col'.$key, $gL10n->get('SYS_NUMBER_COL'), $value['number_col'], array('helpTextIdLabel' => 'SYS_NUMBER_COL_DESC'));
    if(count($config) > 1)
    {
        $html = '<a id="delete_config" class="icon-text-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences.php', array('add_delete' => $key+1)).'">
            <i class="fas fa-trash-alt"></i> '.$gL10n->get('SYS_DELETE_CONFIGURATION').'</a>';
        $formConfigurations->addCustomContent('', $html);
    }

    $formConfigurations->closeGroupBox();
}
$formConfigurations->addDescription('</div>');
$formConfigurations->addLine();
$html = '<a id="add_config" class="icon-text-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences.php', array('add_delete' => -1)).'">
            <i class="fas fa-clone"></i> '.$gL10n->get('SYS_ADD_ANOTHER_CONFIG').'
        </a>';
$htmlDesc = '<div class="alert alert-warning alert-small" role="alert">
                <i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'
            </div>';
$formConfigurations->addCustomContent('', $html, array('helpTextIdInline' => $htmlDesc));
$formConfigurations->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('common', 'configurations', $gL10n->get('SYS_CONFIGURATIONS'), 'fas fa-cogs', $formConfigurations->show()));

// PANEL: OPTIONS

$formOptions = new HtmlForm(
    'options_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences_function.php', array('form' => 'options')),
    $page, array('class' => 'form-preferences')
);
$formOptions->addSelectBox('default_config', $gL10n->get('SYS_CONFIGURATION'),$catReportConfigs, array('defaultValue' => $gSettingsManager->get('category_report_default_configuration'), 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'SYS_CONFIGURATION_DEFAULT_DESC'));
$html = '<a class="btn" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/category-report/export_import.php', array('mode' => 1)).'">
    <i class="fas fa-exchange-alt"></i> '.$gL10n->get('SYS_LINK_TO_EXPORT_IMPORT').'</a>';
$formOptions->addCustomContent($gL10n->get('SYS_EXPORT_IMPORT'), $html, array('helpTextIdInline' => 'SYS_EXPORT_IMPORT_DESC'));
$formOptions->addSubmitButton('btn_save_options', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('common', 'options', $gL10n->get('SYS_SETTINGS'), 'fas fa-cog', $formOptions->show()));

$page->addHtml('
        </div>
    </div>
</div>');

$page->show();
