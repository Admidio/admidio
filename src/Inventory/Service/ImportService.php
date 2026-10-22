<?php

namespace Admidio\Inventory\Service;

// PhpSpreadsheet namespaces
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Html;

// Admidio namespaces
use Admidio\Categories\Service\CategoryService;
use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\SelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;

// PHP namespaces
use DateTime;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ImportService
{
    /**
     * Reads the uploaded import file and saves the data in the session.
     *
     * @return void
     * @throws Exception
     */
    public function readImportFile(): void
    {
        global $gL10n, $gMessage, $gCurrentSession;

        // check the CSRF token of the form against the session token
        $inventoryImportFileForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $inventoryImportFileForm->validate($_POST);

        // Initialize and check the parameters
        $postImportFormat = admFuncVariableIsValid(
            $formValues,
            'format',
            'string',
            array('requireValue' => true,
                'validValues' => array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'))
        );
        $postImportCoding = admFuncVariableIsValid(
            $formValues,
            'import_encoding',
            'string',
            array('validValues' => array('', 'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'))
        );
        $postSeparator = admFuncVariableIsValid(
            $formValues,
            'import_separator',
            'string',
            array('validValues' => array('', ',', ';', '\t', '|'))
        );
        $postEnclosure = admFuncVariableIsValid(
            $formValues,
            'import_enclosure',
            'string',
            array('validValues' => array('', 'AUTO', '"', '\''))
        );
        $postWorksheet = admFuncVariableIsValid($formValues, 'import_sheet', 'string');

        $importFile = $_FILES['userfile']['tmp_name'][0];
        if (strlen($importFile) === 0) {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FILE'))));
            // => EXIT
        } elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
            $gMessage->show($gL10n->get('SYS_FILE_TO_LARGE_SERVER', array(ini_get('upload_max_filesize'))));
            // => EXIT
        } elseif (!file_exists($importFile) || !is_uploaded_file($importFile)) {
            $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
            // => EXIT
        }

        try {
            $_SESSION['import_data'] = $this->readImportFileData(
                $importFile,
                $postImportFormat,
                $postImportCoding,
                $postSeparator,
                $postEnclosure,
                $postWorksheet
            );
        } catch (\Throwable $exception) {
            $gMessage->show($exception->getMessage());
            // => EXIT
        }
    }

    /**
     * Read an inventory import file without relying on HTTP upload or session state.
     *
     * @param string $importFile Path to the import file.
     * @param string $format AUTO, XLSX, XLS, ODS, CSV or HTML.
     * @param string $encoding CSV input encoding. Empty means UTF-8.
     * @param string $separator CSV delimiter. Empty means reader default.
     * @param string $enclosure CSV enclosure. AUTO means reader default.
     * @param string $worksheet Worksheet name or zero-based worksheet index.
     * @return array<int,array<int,mixed>>
     * @throws Exception
     */
    public function readImportFileData(
        string $importFile,
        string $format = 'AUTO',
        string $encoding = '',
        string $separator = '',
        string $enclosure = 'AUTO',
        string $worksheet = ''
    ): array {
        if (!is_file($importFile) || !is_readable($importFile)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        if (!in_array($format, array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'), true)) {
            throw new \InvalidArgumentException('Invalid inventory import format.');
        }

        switch ($format) {
            case 'XLSX':
                $reader = new Xlsx();
                break;

            case 'XLS':
                $reader = new Xls();
                break;

            case 'ODS':
                $reader = new Ods();
                break;

            case 'CSV':
                $reader = new Csv();
                if ($encoding === 'GUESS') {
                    $encoding = Csv::guessEncoding($importFile);
                } elseif ($encoding === '') {
                    $encoding = 'UTF-8';
                }
                $reader->setInputEncoding($encoding);

                if ($separator !== '') {
                    if ($separator === '\t') {
                        $separator = "\t";
                    }
                    $reader->setDelimiter($separator);
                }

                if ($enclosure !== 'AUTO') {
                    $reader->setEnclosure($enclosure);
                }
                break;

            case 'HTML':
                $reader = new Html();
                break;

            case 'AUTO':
            default:
                $reader = IOFactory::createReaderForFile($importFile);
                break;
        }

        $spreadsheet = $reader->load($importFile);
        if ($worksheet !== '' && is_numeric($worksheet)) {
            $sheet = $spreadsheet->getSheet((int)$worksheet);
        } elseif ($worksheet !== '') {
            $sheet = $spreadsheet->getSheetByName($worksheet);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }

        if ($sheet === null) {
            throw new Exception('SYS_IMPORT_SHEET_NOT_EXISTS', array($worksheet));
        }

        return $sheet->toArray(null, true, false);
    }

    /**
     * Imports items from the previously read import file into the database.
     *
     * @return array An array containing the success status and message of the import operation.
     * @throws Exception
     */
    public function importItems(): array
    {
        global $gCurrentSession;

        // check form field input and sanitize it from malicious content
        $itemFieldsImportForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $itemFieldsImportForm->validate($_POST);

        $_SESSION['import_csv_request'] = $formValues;

        return $this->importData($_SESSION['import_data'], $formValues);
    }

    /**
     * Import inventory data that has already been read and mapped.
     *
     * The mapping array uses inventory field IDs as keys and zero-based source
     * column indexes as values. Set first_row to skip a header row.
     *
     * @param array<int,array<int,mixed>> $importData
     * @param array<int|string,mixed> $formValues
     * @return array{success:string,message:string}
     * @throws Exception
     */
    public function importData(array $importData, array $formValues): array
    {
        global $gL10n, $gDb, $gCurrentOrgId, $gSettingsManager;

        $returnMessage = array();

        // go through each line from the file one by one and create the user in the DB
        $line = reset($importData);
        $firstRowTitle = array_key_exists('first_row', $formValues);
        $startRow = 0;
        $importItemFields = array();

        // create array with all profile fields that where assigned to columns of the import file
        foreach ($formValues as $formFieldId => $importFileColumn) {
            if ($importFileColumn !== '' && $formFieldId !== 'adm_csrf_token' && $formFieldId !== 'first_row') {
                $importItemFields[$formFieldId] = (int)$importFileColumn;
            }
        }

        $itemDefinitions = new ItemsData($gDb, $gCurrentOrgId);
        foreach ($itemDefinitions->getItemFields() as $itemField) {
            $internalName = (string)$itemField->getValue('inf_name_intern');
            if ($gSettingsManager->getBool('inventory_items_disable_borrowing')
                && in_array($internalName, $itemDefinitions->borrowFieldNames, true)) {
                continue;
            }

            if ((int)$itemField->getValue('inf_required_input') === 1
                && !array_key_exists((int)$itemField->getValue('inf_id'), $importItemFields)
                && !array_key_exists((string)$itemField->getValue('inf_id'), $importItemFields)) {
                throw new Exception('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name')));
            }
        }

        if ($firstRowTitle) {
            // skip first line, because here are the column names
            $line = next($importData);
            $startRow = 1;
        }

        $assignedFieldColumn = array();

        for ($i = $startRow, $iMax = count($importData); $i < $iMax; ++$i) {
            $row = array();
            foreach ($line as $columnKey => $columnValue) {
                if (empty($columnValue)) {
                    $columnValue = '';
                }

                // get usf id or database column name
                $fieldId = array_search($columnKey, $importItemFields);
                if ($fieldId !== false) {
                    $row[$fieldId] = trim(strip_tags($columnValue));
                }
            }
            $assignedFieldColumn[] = $row;
            $line = next($importData);
        }

        // cleanup the assigned field column array
        $assignedFieldColumn = array_filter($assignedFieldColumn, function ($row) {
            foreach ($row as $value) {
                if (trim($value) !== '') {
                    return true;
                }
            }
            return false;
        });

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $items->readItems();
        $importSuccess = false;

        // check if the item already exists
        foreach ($items->getItems() as $fieldId => $value) {
            $items->readItemData($value['ini_uuid']);
            $itemValues = array();
            foreach ($items->getItemData() as $key => $itemData) {
                $itemValue = $itemData->getValue('ind_value');
                if ($itemData->getValue('inf_name_intern') === 'KEEPER' || $itemData->getValue('inf_name_intern') === 'LAST_RECEIVER' ||
                    $itemData->getValue('inf_name_intern') === 'BORROW_DATE' || $itemData->getValue('inf_name_intern') === 'RETURN_DATE') {
                    continue;
                }

                $itemValues[] = array($itemData->getValue('inf_name_intern') => $itemValue);
            }
            // also add a column with the category if it exists
            $item = new Item($gDb, $items, $items->getItemId());
            $catID = $item->getValue('ini_cat_id');
            $category = new Category($gDb);
            if ($category->readDataById($catID)) {
                $itemValues[] = array('CATEGORY' => $category->getValue('cat_name'));
            }

            $itemValues = array_merge_recursive(...$itemValues);

            if (count($assignedFieldColumn) === 0) {
                break;
            }

            foreach ($assignedFieldColumn as $key => $colValue) {
                $ret = $this->compareArrays($itemValues, $colValue);
                if (!$ret) {
                    unset($assignedFieldColumn[$key]);
                }
            }
        }

        // get all values of the item fields
        $importedItemData = array();
        //array with the internal field names of the borrowing fields

        foreach ($assignedFieldColumn as $row => $values) {
            $itemData = array();
            $itemFormValues = array();
            foreach ($items->getItemFields() as $fields) {
                $val = '';
                $infId = $fields->getValue('inf_id');
                $infNameIntern = $fields->getValue('inf_name_intern');
                $infType = $fields->getValue('inf_type');
                if ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($infNameIntern, $items->borrowFieldNames)) {
                    continue; // skip borrowing fields if borrowing is disabled
                }
                if (isset($values[$infId])) {
                    if ($fields->getValue('inf_type') == 'CHECKBOX') {
                        if ($values[$infId] === $gL10n->get('SYS_YES')) {
                            $values[$infId] = 1;
                        } else {
                            $values[$infId] = 0;
                        }
                    }

                    if ($infNameIntern === 'ITEMNAME') {
                        if ($values[$infId] === '') {
                            break;
                        }
                        $val = $values[$infId];
                    } elseif ($infNameIntern === 'KEEPER') {
                        if (substr_count($values[$infId], ',') === 1) {
                            $sql = $items->getSqlOrganizationsUsersShort();
                        } else {
                            $sql = $items->getSqlOrganizationsUsersComplete();
                        }

                        $result = $gDb->queryPrepared($sql);

                        while ($row = $result->fetch()) {
                            if ($row['name'] == $values[$infId]) {
                                $val = $row['usr_id'];
                                break;
                            }
                            $val = '-1';
                        }
                    } elseif ($infNameIntern === 'LAST_RECEIVER') {
                        if (substr_count($values[$infId], ',') === 1) {
                            $sql = $items->getSqlOrganizationsUsersShort();
                        } else {
                            $sql = $items->getSqlOrganizationsUsersComplete();
                        }

                        $result = $gDb->queryPrepared($sql);

                        while ($row = $result->fetch()) {
                            if ($row['name'] == $values[$infId]) {
                                $val = $row['usr_id'];
                                break;
                            }
                            $val = $values[$infId];
                        }
                    } elseif ($infNameIntern === 'CATEGORY') {
                        $catName = $values[$infId];
                        if ($catName !== '') {
                            $categoryService = new CategoryService($gDb, 'IVT');
                            $allCategories = $categoryService->getVisibleCategories();
                            foreach ($allCategories as $key => $category) {
                                if (Language::translateIfTranslationStrId($category['cat_name']) === $catName) {
                                    $val = $category['cat_uuid'];
                                    break;
                                }
                            }
                            if ($val === '') {
                                $category = new Category($gDb);
                                $category->setValue('cat_name', $catName);
                                $category->setValue('cat_org_id', $gCurrentOrgId);
                                $category->setValue('cat_type', 'IVT');
                                $category->save();

                                // get the uuid of the new category
                                $val = $category->getValue('cat_uuid');
                            }
                        }
                    } elseif ($infNameIntern === 'BORROW_DATE' || $infNameIntern === 'RETURN_DATE') {
                        $val = $values[$infId];
                        if ($val !== '') {
                            // date must be formatted
                            if ($gSettingsManager->get('inventory_field_date_time_format') === 'datetime') {
                                //check if date is datetime or only date
                                if (!str_contains($val, ' ')) {
                                    $val .= '00:00';
                                }
                                // check if date is wrong formatted
                                $dateObject = DateTime::createFromFormat('d.m.Y H:i', $val);
                                if ($dateObject instanceof DateTime) {
                                    // convert date to correct format
                                    $val = $dateObject->format('Y-m-d H:i');
                                }
                                // check DateTime if date is right formatted
                                $date = DateTime::createFromFormat('Y-m-d H:i', $val);
                                if ($date instanceof DateTime) {
                                    $val = $date->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                                }
                            } else {
                                // check if date is date or datetime
                                if (str_contains($val, ' ')) {
                                    $val = substr($val, 0, 10);
                                }
                                // check if date is wrong formatted
                                $dateObject = DateTime::createFromFormat('d.m.Y', $val);
                                if ($dateObject instanceof DateTime) {
                                    // convert date to correct format
                                    $val = $dateObject->format('Y-m-d');
                                }
                                // check if date is right formatted
                                $date = DateTime::createFromFormat('Y-m-d', $val);
                                if ($date instanceof DateTime) {
                                    $val = $date->format($gSettingsManager->getString('system_date'));
                                }
                            }
                        }
                    } elseif ($infNameIntern === 'STATUS' || in_array($infType, array('DROPDOWN', 'DROPDOWN_MULTISELECT', 'RADIOBUTTON'))) {
                        $optionValue = $values[$infId];
                        if ($optionValue !== '') {
                            $option = new SelectOptions($gDb, $fields->getValue('inf_id'));
                            $optionValues = $option->getAllOptions();
                            foreach ($optionValues as $optionData) {
                                if (Language::translateIfTranslationStrId($optionData['value']) === $optionValue) {
                                    $val = $optionData['id'];
                                    break;
                                }
                            }
                            if ($val === '') {
                                $option = new SelectOptions($gDb, $fields->getValue('inf_id'));
                                $options = $option->getAllOptions();
                                $maxId = 0;
                                foreach ($options as $optionData) {
                                    if ($optionData['id'] > $maxId) {
                                        $maxId = $optionData['id'];
                                    }
                                }
                                $newOption[$maxId + 1] = array('value' => $optionValue);
                                $option->setOptionValues($newOption);
                                // reload all options to get the id of the new option
                                $options = $option->getAllOptions();
                                foreach ($options as $optionData) {
                                    if (Language::translateIfTranslationStrId($optionData['value']) === $optionValue) {
                                        $val = $optionData['id'];
                                        break;
                                    } else {
                                        $val = $values[$infId];
                                    }
                                }
                            }
                        }
                    } elseif ($infType === 'DROPDOWN_DATE_INTERVAL') {
                        $optionValue = $values[$infId];
                        if ($optionValue !== '') {
                            // check, if the option value is given in [] brackets
                            if (preg_match('/\[(.*?)]/', $optionValue, $matches)) {
                                $optionValue = $matches[1];
                            }
                            $option = new SelectOptions($gDb, $fields->getValue('inf_id'));
                            $optionValues = $option->getAllOptions();
                            foreach ($optionValues as $optionData) {
                                if (Language::translateIfTranslationStrId($optionData['value']) === $optionValue) {
                                    $val = $optionData['id'];
                                    break;
                                } else {
                                    $val = $values[$infId];
                                }
                            }
                        }
                    } else {
                        $val = $values[$infId];
                    }
                }
                $itemFormValues['INF-' . $infNameIntern] = $val;
                $itemData[] = array($items->getItemFields()[$infNameIntern]->getValue('inf_name') => array('oldValue' => "", 'newValue' => $val));
            }

            $importedItemData[] = $itemData;
            unset($itemData);
            if (count($assignedFieldColumn) > 0) {

                $itemModule = new ItemService($gDb, '', 0, 1, true);
                $itemModule->saveData($itemFormValues);

                $importSuccess = true;
            }
        }


        // Send notification to all users
        $items->sendNotification($importedItemData);

        $returnMessage['success'] = 'success';
        if ($importSuccess) {
            $returnMessage['message'] = $gL10n->get('SYS_SAVE_DATA');
        } else {
            $returnMessage['message'] = $gL10n->get('SYS_INVENTORY_NO_NEW_IMPORT_DATA');
        }

        return $returnMessage;
    }

    /**
     * Compares two arrays to determine if they are different based on specific criteria
     *
     * @param array $array1 The first array to compare
     * @param array $array2 The second array to compare
     * @return bool             true if the arrays are different based on the criteria, otherwise false
     */
    private function compareArrays(array $array1, array $array2): bool
    {
        $array1 = array_filter($array1, function ($key) {
            return $key !== 'KEEPER' && $key !== 'LAST_RECEIVER' && $key !== 'BORROW_DATE' && $key !== 'RETURN_DATE';
        }, ARRAY_FILTER_USE_KEY);

        foreach ($array1 as $value) {
            if ($value === '') {
                continue;
            }

            if (!in_array($value, $array2, true)) {
                return true;
            }
        }
        return false;
    }
}
