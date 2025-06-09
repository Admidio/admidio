<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Inventory\ValueObjects\ItemsData;

/**
 * @brief Class with methods to display the module pages of the registration.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InventoryImportPresenter extends PagePresenter
{
    protected array $formValues = array();
    protected array $formats = array();
    protected array $encoding = array();
    protected array $separator = array();
    protected array $enclosure = array();
    
    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $objectUUID UUID of an object that represents the page. The data shown at the page will belong
     *                           to this object.
     * @throws Exception
     */
    public function __construct(string $objectUUID = 'adm_inventory_import')
    {
        global $gL10n;

        if (isset($_SESSION['import_request'])) {
            // due to incorrect input the user has returned to this form
            // now write the previously entered contents into the object
            $this->formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_request']));
            unset($_SESSION['import_request']);
        }
        
        // Make sure all potential form values have either a value from the previous request or the default
        if (!isset($this->formValues['format'])) {
            $this->formValues['format'] = '';
        }
        if (!isset($this->formValues['import_sheet'])) {
            $this->formValues['import_sheet'] = '';
        }
        if (!isset($this->formValues['import_encoding'])) {
            $this->formValues['import_encoding'] = '';
        }
        if (!isset($this->formValues['import_separator'])) {
            $this->formValues['import_separator'] = '';
        }
        if (!isset($this->formValues['import_enclosure'])) {
            $this->formValues['import_enclosure'] = 'AUTO';
        }

        // Initialize form options
        $this->formats = array(
            'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
            'XLSX' => $gL10n->get('SYS_EXCEL_2007_365'),
            'XLS'  => $gL10n->get('SYS_EXCEL_97_2003'),
            'ODS'  => $gL10n->get('SYS_ODF_SPREADSHEET'),
            'CSV'  => $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
            'HTML' => $gL10n->get('SYS_HTML_TABLE')
        );

        $this->encoding = array(
            '' => $gL10n->get('SYS_DEFAULT_ENCODING_UTF8'),
            'GUESS' => $gL10n->get('SYS_ENCODING_GUESS'),
            'UTF-8' => $gL10n->get('SYS_UTF8'),
            'UTF-16BE' => $gL10n->get('SYS_UTF16BE'),
            'UTF-16LE' => $gL10n->get('SYS_UTF16LE'),
            'UTF-32BE' => $gL10n->get('SYS_UTF32BE'),
            'UTF-32LE' => $gL10n->get('SYS_UTF32LE'),
            'CP1252' => $gL10n->get('SYS_CP1252'),
            'ISO-8859-1' => $gL10n->get('SYS_ISO_8859_1')
        );
        
        $this->separator = array(
            '' => $gL10n->get('SYS_AUTO_DETECT'),
            ',' => $gL10n->get('SYS_COMMA'),
            ';' => $gL10n->get('SYS_SEMICOLON'),
            '\t' => $gL10n->get('SYS_TAB'),
            '|' => $gL10n->get('SYS_PIPE')
        );

        $this->enclosure = array(
            'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
            '' => $gL10n->get('SYS_NO_QUOTATION'),
            '"' => $gL10n->get('SYS_DQUOTE'),
            '\'' => $gL10n->get('SYS_QUOTE')
        );

        parent::__construct($objectUUID);
    }

    public function createImportFileSelectionForm(): void
    {
        global $gL10n, $gCurrentSession;

        // show form
        $form = new FormPresenter(
            'adm_inventory_import_form',
            'modules/inventory.import.file-selection.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'import_read_file')),
            $this,
            array('enableFileUpload' => true)
        );

        $form->addDescription(
            'adm_inventory_import_description',
            $gL10n->get('SYS_INVENTORY_IMPORT_DESC')
        );

        $form->addSelectBox(
            'format',
            $gL10n->get('SYS_FORMAT'),
            $this->formats,
            array('showContextDependentFirstEntry' => false,
            'property' => FormPresenter::FIELD_REQUIRED,
            'defaultValue' => $this->formValues['format'])
        );
        $this->addJavascript(
            '
            $("#format").change(function() {
                const format = $(this).children("option:selected").val();
                $(".import-setting").prop("disabled", true).parents("div.admidio-form-group").hide();
                $(".import-"+format).prop("disabled", false).parents("div.admidio-form-group").show("slow");
            });
            $("#format").trigger("change");',
            true
        );
        
        $form->addFileUpload(
            'userfile',
            $gL10n->get('SYS_CHOOSE_FILE'),
            array('property' => FormPresenter::FIELD_REQUIRED, 'allowedMimeTypes' => array('text/comma-separated-values',
                    'text/csv',
                    'text/html',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'application/vnd.oasis.opendocument.spreadsheet'
                )
            )
        );
        
        // Add format-specific settings (if specific format is selected)
        // o) Worksheet: AUTO, XLSX, XLS, ODS, HTML (not CSV)
        // o) Encoding (Default/Detect/UTF-8/ISO-8859-1/CP1252): CSV, HTML
        // o) Delimiter (Detect/Comma/Tab/Semicolon): CSV
        $form->addInput(
            'import_sheet',
            $gL10n->get('SYS_WORKSHEET_NAMEINDEX'),
            '',
            array('class' => 'import-setting import-XLSX import-XLS import-ODS import-HTML import-AUTO')
        );
        
        $form->addSelectBox(
            'import_encoding',
            $gL10n->get('SYS_CODING'),
            $this->encoding,
            array('showContextDependentFirstEntry' => false, 'defaultValue' => $this->formValues['import_encoding'], 'class' => 'import-setting import-CSV import-HTML')
        );
        
        $form->addSelectBox(
            'import_separator',
            $gL10n->get('SYS_SEPARATOR_FOR_CSV_FILE'),
            $this->separator,
            array('showContextDependentFirstEntry' => false, 'defaultValue' => $this->formValues['import_separator'], 'class' => 'import-setting import-CSV')
        );
        
        $form->addSelectBox(
            'import_enclosure',
            $gL10n->get('SYS_FIELD_ENCLOSURE'),
            $this->enclosure,
            array('showContextDependentFirstEntry' => false, 'defaultValue' => $this->formValues['import_enclosure'], 'class' => 'import-setting import-CSV')
        );
        
        $form->addSubmitButton(
            'btn_forward',
            $gL10n->get('SYS_ASSIGN_FIELDS'),
            array('icon' => 'fa-arrow-circle-right', 'class' => ' offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);    
    }

    public function createAssignFieldsForm(): void
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentOrgId;

        if (isset($_SESSION['import_csv_request'])) {
            // due to incorrect input the user has returned to this form
            // now write the previously entered contents into the object
            $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_csv_request']));
            unset($_SESSION['import_csv_request']);
            if (!isset($formValues['first_row'])) {
                $formValues['first_row'] = false;
            }
        } else {
            $formValues['first_row'] = true;
        }
               
        // show form
        $form = new FormPresenter(
            'adm_inventory_import_assign_fields_form',
            'modules/inventory.import.assign-fields.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'import_items')),
            $this
        );

        $form->addCheckbox(
            'first_row',
            $gL10n->get('SYS_FIRST_LINE_COLUMN_NAME'),
            $formValues['first_row']
        );
        
        $this->addJavascript('
            $(".admidio-import-field").change(function() {
                var available = [];
                $("#adm_inventory_import_assign_fields_form .admidio-import-field").first().children("option").each(function() {
                    if ($(this).val() != "") {
                        available.push($(this).text());
                    }
                });
                var used = [];
                $("#adm_inventory_import_assign_fields_form .admidio-import-field").children("option:selected").each(function() {
                    if ($(this).val() != "") {
                        used.push($(this).text());
                    }
                });               
                var outstr = "";
                $(available).not(used).each(function(index, value) {
                    if (value === "Nr.") {
                    outstr += "<tr><td>" + value + "</td><td></td></tr>";
                    } else {
                    outstr += "<tr><td>" + value + "</td><td><a href=\"' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php?mode=field_edit&field_name=" + encodeURIComponent(value) + "&redirect_to_import=true\" class=\"btn btn-primary btn-sm\">' . $gL10n->get('SYS_INVENTORY_ITEMFIELD_CREATE') . '</a></td></tr>";
                    }
                });
                if (outstr == "") {
                    outstr = "-";
                } else {
                    outstr = "<table class=\"table table-condensed\" style=\"--bs-table-bg: transparent;\"><tbody>" + outstr + "</tbody></table>";
                }
                $("#admidio-import-unused #admidio-import-unused-fields").html(outstr);
            });
            $(".admidio-import-field").trigger("change");',
            true
        );

        $arrayCsvColumns = $_SESSION['import_data'][0];
        // Remove only null values
        $arrayCsvColumns = array_filter($arrayCsvColumns, function ($value) {
            return !is_null($value);
        });
        
        $items = new ItemsData($gDb, $gCurrentOrgId);
        foreach ($items->getItemFields() as $itemField) {
            $fieldDefaultValue = -1;
            if (in_array($itemField->GetValue('inf_name'), $arrayCsvColumns)) {
                $fieldDefaultValue = array_search($itemField->GetValue('inf_name'), $arrayCsvColumns);
                if ($fieldDefaultValue === false) {
                    $fieldDefaultValue = -1;
                }
            }
    
            // set required fields
            $fieldProperty = FormPresenter::FIELD_DEFAULT;
            if ($itemField->GetValue('inf_required_input') === 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            }
    
            $form->addSelectBox(
                $itemField->GetValue('inf_id'),
                $itemField->GetValue('inf_name'),
                $arrayCsvColumns,
                array(
                    'category' => ((bool)$itemField->getValue('inf_system')) ? $gL10n->get('SYS_BASIC_DATA') : $gL10n->get('SYS_INVENTORY_USER_DEFINED_FIELDS'),
                    'property' => $fieldProperty,
                    'defaultValue' => $fieldDefaultValue,
                    'firstEntry' => $gL10n->get('SYS_ASSIGN_FILE_COLUMN'),
                    'class' => 'admidio-import-field'
                )
            );
        }

        $form->addSubmitButton('btn_forward', $gL10n->get('SYS_IMPORT'), array('icon' => 'fa-upload'));

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}