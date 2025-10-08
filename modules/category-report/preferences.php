<?php
/**
 ***********************************************************************************************
 * Modul Preferences of the admidio module CategoryReport
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * add     : add a configuration
 * delete  : delete a configuration
 * copy    : copy a configuration
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/../../system/login_valid.php');

    // only authorized user are allowed to start this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Initialize and check the parameters
    $getAdd = admFuncVariableIsValid($_GET, 'add', 'bool');
    $getDelete = admFuncVariableIsValid($_GET, 'delete', 'numeric', array('defaultValue' => 0));
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'numeric', array('defaultValue' => 0));

    $report = new CategoryReport();
    $config = $report->getConfigArray();
    $catReportConfigs = array();

    $headline = $gL10n->get('SYS_CATEGORY_REPORT') . ' - ' . $gL10n->get('SYS_CONFIGURATIONS');

    if ($getAdd) {
        $config[] = array('id' => '', 'name' => '', 'col_fields' => '', 'selection_role' => '', 'selection_cat' => '', 'number_col' => '', 'default_conf' => false);
        // ohne $report->saveConfigArray(); ansonsten würden 'name' und 'col_fields' ohne Daten gespeichert sein
    }

    if ($getDelete > 0 && isset($config[$getDelete - 1])) {
        $config[$getDelete - 1]['id'] = $config[$getDelete - 1]['id'] * (-1);                   // id negieren, als Kennzeichen für "Deleted"
        $config = $report->saveConfigArray($config);
    }

    if ($getCopy > 0) {
        $config[] = array('id' => '',
            'name' => $report->createName($config[$getCopy - 1]['name']),
            'col_fields' => $config[$getCopy - 1]['col_fields'],
            'selection_role' => $config[$getCopy - 1]['selection_role'],
            'selection_cat' => $config[$getCopy - 1]['selection_cat'],
            'number_col' => $config[$getCopy - 1]['number_col'],
            'default_conf' => false);
        $config = $report->saveConfigArray($config);
    }

    $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_CONFIGURATIONS'));

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('plg-category-report-preferences', $headline);
    ChangelogService::displayHistoryButton($page, 'categoryreport', 'category_report');

    $javascriptCode = '';
    $javascriptCodeExecute = '';


    $javascriptCode = 'var arr_user_fields = createProfileFieldsArray();
        function createUserFieldSelect(config, val = null)
        {
        	var category = "";
            var htmlCboFields = "<select class=\"form-control\"  size=\"1\" name=\"columns" + config + "[]\" class=\"ListProfileField\" >" +
                "<option value=\"\"></option>";
        	for (const field of arr_user_fields) {
            	if (category != field.cat_name) {
                	if (category.length > 0) {
                    	htmlCboFields += "</optgroup>";
                	}    
                	htmlCboFields += "<optgroup label=\"" + field.cat_name + "\">";
                	category = field.cat_name;
            	}    
            	var selected = ((val != null) && (field.id == val)) ? " selected=\"selected\" " : "";
             	htmlCboFields += "<option value=\"" + field.id + "\" " + selected + ">" + field.data + "</option>";
        	}
            if (category.length > 0) {
                htmlCboFields += "</optgroup>";
            }
        	htmlCboFields += "</select>";
            return htmlCboFields;
        };
    ';
    $javascriptCode .= '
        function addColumnToConfiguration(config, val = null)
        {
        	var table = document.getElementById("mylist_fields_tbody" + config);
        	var newTableRow = table.insertRow();
        	newTableRow.setAttribute("class", "CategoryReportColumnDefinition");
        	var newCellCount = newTableRow.insertCell();
            newCellCount.setAttribute("class", "CategoryReportColumnNumber");
        	var newCellField = newTableRow.insertCell(-1);
        	newCellField.innerHTML = createUserFieldSelect(config, val);
            var newCellButtons = newTableRow.insertCell(-1);
            newCellButtons.innerHTML = "    <a class=\"admidio-icon-link admidio-move-row\" style=\"padding-left: 0pt; padding-right: 0pt;\">" + 
                        "        <i class=\"bi bi-arrows-move handle\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_MOVE_VAR') . '\"></i></a>" +
                        "    <a class=\"admidio-icon-link admidio-delete\" style=\"padding-left: 0pt; padding-right: 0pt;\">" + 
                        "        <i class=\"bi bi-trash\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_DELETE') . '\"></i></a>";

        	$(newTableRow).fadeIn("slow");
            updateNumbering();
        };
    ';

    // create an array with the necessary data
    foreach ($config as $key => $value) {
        $catReportConfigs[$key] = $value['name'];

        // Function to generate the list of selected fields
        $fields = explode(',', $value['col_fields']);
        $validFieldIds = array_filter($fields, function ($fieldId) use ($report) {
                return $report->isInHeaderSelection($fieldId) > 0;
            });
        $jsArray = json_encode(array_values($validFieldIds));
        $javascriptCode .= "
        function createColumnsArray{$key}() {
            return {$jsArray};
        }";

        $javascriptCodeExecute .= "
        createColumnsArray{$key}().forEach(item => addColumnToConfiguration({$key}, item));
        $(\"#mylist_fields_tbody{$key}\").sortable({
            handle: \".admidio-move-row\", 
            items: \"tr\",
            update: updateNumbering
        });
    	";
    }

    // $report->headerSelection has integer indices starting at 1. We don't need them anyway, so ignore them
    // before converting the whole data structure to a JSON object.
    $javascriptCode .= '
    function createProfileFieldsArray()
    {
        return ' . json_encode(array_values($report->headerSelection), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ';
    };
    function updateNumbering() {
        $(".catreport-columns-table").each(function() {
            $(this).find("tbody tr").each(function(index) {
                $(this).find("td:first").text((index + 1) + ". ' . $gL10n->get('SYS_COLUMN') . ':");
            });
        });
    }
    ';
    // Delete button handler, automatically number rows on load;
    $javascriptCodeExecute .= '
    $(document).on("click", ".admidio-delete", function(){
        let row = $(this).closest("tr").fadeOut(300, function() {
            $(this).remove();
            updateNumbering();
        });
    });
    updateNumbering();
    ';

    $page->addJavascript($javascriptCode);
    $page->addJavascript($javascriptCodeExecute, true);


    $formConfigurations = new FormPresenter(
        'adm_configurations_preferences_form',
        'modules/category-report.config.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences_function.php', array('form' => 'configurations')),
        $page,
        array('class' => 'form-preferences')
    );

    $currentNumberConf = 0;
    $categoryReports = array();
    $key_to_open = ($getAdd) ? array_key_last($config) : array_key_first($config);
    


    foreach ($config as $key => $value) {
        $categoryReport = array(
            'key' => $key,
            'name' => 'name' . $key,
            'selection_role' => 'selection_role' . $key,
            'selection_cat' => 'selection_cat' . $key,
            'number_col' => 'number_col' . $key,
            'id' => 'id' . $key,
            'default_conf' => 'default_conf' . $key,
            'open' => ($key == $key_to_open),
        );
        $formConfigurations->addInput('name' . $key, $gL10n->get('SYS_DESIGNATION'), $value['name'],
            array('property' => FormPresenter::FIELD_REQUIRED));

        $sql = 'SELECT rol_id, rol_name, cat_name
              FROM ' . TBL_CATEGORIES . ' , ' . TBL_ROLES . '
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = ' . $gCurrentOrgId . '
                OR cat_org_id IS NULL )';
        $formConfigurations->addSelectBoxFromSql('selection_role' . $key, $gL10n->get('SYS_ROLE_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$value['selection_role']), 'multiselect' => true, 'helpTextId' => 'SYS_ROLE_SELECTION_CONF_DESC'));

        $sql = 'SELECT cat_id, cat_name
              FROM ' . TBL_CATEGORIES . ' , ' . TBL_ROLES . '
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = ' . $gCurrentOrgId . '
                OR cat_org_id IS NULL )';
        $formConfigurations->addSelectBoxFromSql('selection_cat' . $key, $gL10n->get('SYS_CAT_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$value['selection_cat']), 'multiselect' => true, 'helpTextId' => 'SYS_CAT_SELECTION_CONF_DESC'));
        $formConfigurations->addCheckbox('number_col' . $key, $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')', $value['number_col'], array('helpTextId' => 'SYS_NUMBER_COL_DESC'));
        $formConfigurations->addInput('id' . $key, '', $value['id'], array('property' => FormPresenter::FIELD_HIDDEN));
        $formConfigurations->addInput('default_conf' . $key, '', $value['default_conf'], array('property' => FormPresenter::FIELD_HIDDEN));

        if (count($config) > 1 && $value['default_conf'] == false) {
            $categoryReport['urlConfigDelete'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('delete' => $key + 1));
        }
        if (!empty($value['name'])) {
            $categoryReport['urlConfigCopy'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('copy' => $key + 1));
        }
        $categoryReports[] = $categoryReport;
    }
    $page->assignSmartyVariable('categoryReports', $categoryReports);
    $page->assignSmartyVariable('urlConfigNew', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('add' => 1)));
    $formConfigurations->addSubmitButton('adm_button_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $formConfigurations->addToHtmlPage();
    $gCurrentSession->addFormObject($formConfigurations);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
