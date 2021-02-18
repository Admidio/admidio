<?php
/**
 ***********************************************************************************************
 * Modul Preferences (Einstellungen) fuer das Admidio-Plugin Kategoriereport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Hinweis:  preferences.php ist eine modifizierte Kombination der Dateien
 *           .../modules/lists/mylist.php und .../modules/preferences/preferences.php
 * 
 * Parameters:
 *
 * add_delete : -1 - Erzeugen einer Konfiguration
 * 				>0 - Löschen einer Konfiguration
 * show_option: direktes Öffnen eines Panels des Accordeon-Menüs
 *
 ***********************************************************************************************
 */
 
require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/classes/genreport.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getAddDelete = admFuncVariableIsValid($_GET, 'add_delete', 'numeric', array('defaultValue' => 0));
$showOption   = admFuncVariableIsValid($_GET, 'show_option', 'string');

$pPreferences = new ConfigTablePKR();
$pPreferences->read();

$headline = $gL10n->get('PLG_KATEGORIEREPORT_CATEGORY_REPORT');

if ($getAddDelete === -1)
{
    foreach($pPreferences->config['Konfigurationen'] as $key => $dummy)
    {
        $pPreferences->config['Konfigurationen'][$key][] = $pPreferences->config_default['Konfigurationen'][$key][0];
    }
}
elseif ($getAddDelete > 0)
{
    foreach($pPreferences->config['Konfigurationen'] as $key => $dummy)
    {
        array_splice($pPreferences->config['Konfigurationen'][$key], $getAddDelete-1, 1);
    }
    
    // durch das Loeschen einer Konfiguration kann der Fall eintreten, dass es die eingestellte Standardkonfiguration nicht mehr gibt
    // daher die Standardkonfiguration auf die erste Konfiguration im Array setzen
    $pPreferences->config['Optionen']['config_default'] = 0;
}

$num_configs = count($pPreferences->config['Konfigurationen']['col_desc']);
$pPreferences->save();

$report = new GenReport();

if ($getAddDelete)
{
    $showOption = 'configurations';
}

$gNavigation->clear();
$gNavigation->addUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php');
$gNavigation->addUrl(CURRENT_URL);

// create html page object
$page = new HtmlPage('plg-kategoriereport-preferences', $headline);

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
for ($conf=0;$conf<$num_configs;$conf++)
{
    $javascriptCode .= '
        
        var arr_default_fields'.$conf.' = createColumnsArray'.$conf.'();
        var fieldNumberIntern'.$conf.'  = 0;
            
    	// Funktion fuegt eine neue Zeile zum Zuordnen von Spalten fuer die Liste hinzu
    	function addColumn'.$conf.'()
    	{
        	var category = "";
        	var fieldNumberShow  = fieldNumberIntern'.$conf.' + 1;
        	var table = document.getElementById("mylist_fields_tbody'.$conf.'");
        	var newTableRow = table.insertRow(fieldNumberIntern'.$conf.');
        	newTableRow.setAttribute("id", "row" + (fieldNumberIntern'.$conf.' + 1))
        	//$(newTableRow).css("display", "none"); // ausgebaut wg. Kompatibilitaetsproblemen im IE8
        	var newCellCount = newTableRow.insertCell(-1);
        	newCellCount.innerHTML = (fieldNumberShow) + ".&nbsp;'.$gL10n->get('SYS_COLUMN').':";
        	    
        	// neue Spalte zur Auswahl des Profilfeldes
        	var newCellField = newTableRow.insertCell(-1);
        	htmlCboFields = "<select class=\"form-control\"  size=\"1\" id=\"column" + fieldNumberShow + "\" class=\"ListProfileField\" name=\"column'.$conf.'_" + fieldNumberShow + "\">" +
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
            	if(arr_default_fields'.$conf.'[fieldNumberIntern'.$conf.'])
            	{
                	if(arr_user_fields[counter]["id"] == arr_default_fields'.$conf.'[fieldNumberIntern'.$conf.']["id"])
                	{
                    	selected = " selected=\"selected\" ";
                   	 arr_default_fields'.$conf.'[fieldNumberIntern'.$conf.']["data"] = arr_user_fields[counter]["data"];
                	}
            	}
             	htmlCboFields += "<option value=\"" + arr_user_fields[counter]["id"] + "\" " + selected + ">" + arr_user_fields[counter]["data"] + "</option>";
        	}
        	htmlCboFields += "</select>";
        	newCellField.innerHTML = htmlCboFields;
                   	     
        	$(newTableRow).fadeIn("slow");
        	fieldNumberIntern'.$conf.'++;
    	}
        	    
    	function createColumnsArray'.$conf.'()
    	{
        	var default_fields = new Array(); ';
    $fields = explode(',', $pPreferences->config['Konfigurationen']['col_fields'][$conf]);
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
$i = 1;
for ($i = 1; $i < count($report->headerSelection)+1; $i++)
{
    $javascriptCode .= '
                user_fields['. $i. '] 				= new Object();
                user_fields['. $i. ']["id"]   		= "'. $report->headerSelection[$i]['id'] . '";
                user_fields['. $i. ']["cat_name"] 	= "'. $report->headerSelection[$i]['cat_name']. '";
                user_fields['. $i. ']["data"]   	= "'. $report->headerSelection[$i]['data'] . '";
                ';
}
$javascriptCode .= '
        return user_fields;
    }
';
$page->addJavascript($javascriptCode);
$javascriptCode = '$(document).ready(function() { ';
for ($conf = 0; $conf < $num_configs; $conf++)
{
    $javascriptCode .= '
    	for(var counter = 0; counter < '. count(explode(',', $pPreferences->config['Konfigurationen']['col_fields'][$conf])). '; counter++) {
        	addColumn'. $conf. '();
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
    'configurations_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'configurations')),
    $page, array('class' => 'form-preferences')
);

$html = '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_popup.php').'">'.
                '<i class="fas fa-info" data-toggle="tooltip" title="' . $gL10n->get('SYS_HELP') . '"></i> '.$gL10n->get('SYS_HELP').'</a>';
$formConfigurations->addDescription($gL10n->get('PLG_KATEGORIEREPORT_CONFIGURATIONS_HEADER').' '.$html);
$formConfigurations->addLine();
$formConfigurations->addDescription('<div style="width:100%; height:550px; overflow:auto; border:20px;">');

for ($conf=0;$conf<$num_configs;$conf++)
{
    $formConfigurations->openGroupBox('configurations_group',($conf+1).'. '.$gL10n->get('PLG_KATEGORIEREPORT_CONFIGURATION'));
    $formConfigurations->addInput('col_desc'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_COL_DESC'), $pPreferences->config['Konfigurationen']['col_desc'][$conf], array('property' => HtmlForm::FIELD_REQUIRED));
    $html = '
	   <div class="table-responsive">
    		<table class="table table-condensed" id="mylist_fields_table">
        		<thead>
            		<tr>
                		<th style="width: 20%;">'.$gL10n->get('SYS_ABR_NO').'</th>
                		<th style="width: 37%;">'.$gL10n->get('SYS_CONTENT').'</th>
            		</tr>
        		</thead>
        		<tbody id="mylist_fields_tbody'.$conf.'">
            		<tr id="table_row_button">
                		<td colspan="2">
                    		<a class="icon-text-link" href="javascript:addColumn'.$conf.'()"><i class="fas fa-plus-circle"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_ADD_ANOTHER_COLUMN').'</a>
                		</td>
            		</tr>
        		</tbody>
    		</table>
    	</div>';
    $formConfigurations->addCustomContent($gL10n->get('PLG_KATEGORIEREPORT_COLUMN_SELECTION'), $html);
    $formConfigurations->addInput('col_yes'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_DISPLAY_TEXT_MEMBERSHIP_YES'), $pPreferences->config['Konfigurationen']['col_yes'][$conf], array('maxLength' => 10));
    $formConfigurations->addInput('col_no'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_DISPLAY_TEXT_MEMBERSHIP_NO'), $pPreferences->config['Konfigurationen']['col_no'][$conf], array('maxLength' => 10));
    
    $sql = 'SELECT rol_id, rol_name, cat_name
              FROM '.TBL_CATEGORIES.' , '.TBL_ROLES.'
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = '.ORG_ID.'
                OR cat_org_id IS NULL )';
    $formConfigurations->addSelectBoxFromSql('selection_role'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_ROLE_SELECTION'), $gDb, $sql, array('defaultValue' => explode(',',$pPreferences->config['Konfigurationen']['selection_role'][$conf]),'multiselect' => true));
    
    $sql = 'SELECT cat_id, cat_name
              FROM '.TBL_CATEGORIES.' , '.TBL_ROLES.'
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = '.ORG_ID.'
                OR cat_org_id IS NULL )';
    $formConfigurations->addSelectBoxFromSql('selection_cat'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_CAT_SELECTION'), $gDb, $sql, array('defaultValue' => explode(',',$pPreferences->config['Konfigurationen']['selection_cat'][$conf]),'multiselect' => true));
    $formConfigurations->addCheckbox('number_col'.$conf, $gL10n->get('PLG_KATEGORIEREPORT_NUMBER_COL'), $pPreferences->config['Konfigurationen']['number_col'][$conf]);
    if($num_configs != 1)
    {
        $html = '<a id="delete_config" class="icon-text-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', array('add_delete' => $conf+1)).'">
            <i class="fas fa-trash-alt"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_DELETE_CONFIG').'</a>';
        $formConfigurations->addCustomContent('', $html);
    }
    
    $formConfigurations->closeGroupBox();
}
$formConfigurations->addDescription('</div>');
$formConfigurations->addLine();
$html = '<a id="add_config" class="icon-text-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', array('add_delete' => -1)).'">
            <i class="fas fa-clone"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_ADD_ANOTHER_CONFIG').'
        </a>';
$htmlDesc = '<div class="alert alert-warning alert-small" role="alert">
                <i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST').'
            </div>';
$formConfigurations->addCustomContent('', $html, array('helpTextIdInline' => $htmlDesc));   
$formConfigurations->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('common', 'configurations', $gL10n->get('PLG_KATEGORIEREPORT_CONFIGURATIONS'), 'fas fa-cogs', $formConfigurations->show()));

// PANEL: OPTIONS

$formOptions = new HtmlForm(
    'options_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('form' => 'options')),
    $page, array('class' => 'form-preferences')
);
$formOptions->addSelectBox('config_default', $gL10n->get('PLG_KATEGORIEREPORT_CONFIGURATION'),$pPreferences->config['Konfigurationen']['col_desc'], array('defaultValue' => $pPreferences->config['Optionen']['config_default'], 'showContextDependentFirstEntry' => false, 'helpTextIdInline' => 'PLG_KATEGORIEREPORT_CONFIGURATION_DEFAULT_DESC'));
$html = '<a class="btn" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/export_import.php', array('mode' => 1)).'">
    <i class="fas fa-exchange-alt"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_LINK_TO_EXPORT_IMPORT').'</a>';
$formOptions->addCustomContent($gL10n->get('PLG_KATEGORIEREPORT_EXPORT_IMPORT'), $html, array('helpTextIdInline' => 'PLG_KATEGORIEREPORT_EXPORT_IMPORT_DESC'));
$html = '<a id="deinstallation" class="icon-text-link" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('mode' => 2)).'">
    <i class="fas fa-trash-alt"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_LINK_TO_DEINSTALLATION').'</a>';
$formOptions->addCustomContent($gL10n->get('PLG_KATEGORIEREPORT_DEINSTALLATION'), $html, array('helpTextIdInline' => 'PLG_KATEGORIEREPORT_DEINSTALLATION_DESC'));
$formOptions->addSubmitButton('btn_save_options', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

$page->addHtml(getPreferencePanel('common', 'options', $gL10n->get('PLG_KATEGORIEREPORT_OPTIONS'), 'fas fa-cog', $formOptions->show()));

// PANEL: PLUGIN INFORMATIONS

$formPluginInformations = new HtmlForm(
    'plugin_informations_preferences_form', null,
    $page, array('class' => 'form-preferences')
);

$formPluginInformations->addStaticControl('plg_name', $gL10n->get('PLG_KATEGORIEREPORT_PLUGIN_NAME'), $gL10n->get('PLG_KATEGORIEREPORT_CATEGORY_REPORT'));
$formPluginInformations->addStaticControl('plg_version', $gL10n->get('PLG_KATEGORIEREPORT_PLUGIN_VERSION'), $pPreferences->config['Plugininformationen']['version']);
$formPluginInformations->addStaticControl('plg_date', $gL10n->get('PLG_KATEGORIEREPORT_PLUGIN_DATE'), $pPreferences->config['Plugininformationen']['stand']);

$html = '<a class="icon-text-link" href="https://www.admidio.org/dokuwiki/doku.php?id=de:plugins:kategoriereport#kategoriereport" target="_blank">
    <i class="fas fa-external-link-square-alt"></i> '.$gL10n->get('PLG_KATEGORIEREPORT_DOCUMENTATION_OPEN').'</a>';
$formPluginInformations->addCustomContent($gL10n->get('PLG_KATEGORIEREPORT_DOCUMENTATION'), $html, array('helpTextIdInline' => 'PLG_KATEGORIEREPORT_DOCUMENTATION_OPEN_DESC'));

$page->addHtml(getPreferencePanel('common', 'plugin_informations', $gL10n->get('PLG_KATEGORIEREPORT_PLUGIN_INFORMATION'), 'fas fa-info-circle', $formPluginInformations->show()));

$page->addHtml('
        </div>
    </div>
</div>');

$page->show();
