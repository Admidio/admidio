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
            array('validValues' => array('', 'AUTO', '"', '\|'))
        );

        $postWorksheet = admFuncVariableIsValid($formValues, 'import_sheet', 'string');

        $importfile = $_FILES['userfile']['tmp_name'][0];
        if (strlen($importfile) === 0) {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FILE'))));
            // => EXIT
        } elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
            // check the filesize against the server settings
            $gMessage->show($gL10n->get('SYS_FILE_TO_LARGE_SERVER', array(ini_get('upload_max_filesize'))));
            // => EXIT
        } elseif (!file_exists($importfile) || !is_uploaded_file($importfile)) {
            // check if a file was really uploaded
            $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
            // => EXIT
        }

        switch ($postImportFormat) {
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
                if ($postImportCoding === 'GUESS') {
                    $postImportCoding = Csv::guessEncoding($importfile);
                } elseif ($postImportCoding === '') {
                    $postImportCoding = 'UTF-8';
                }
                $reader->setInputEncoding($postImportCoding);

                if ($postSeparator != '') {
                    $reader->setDelimiter($postSeparator);
                }

                if ($postEnclosure != 'AUTO') {
                    $reader->setEnclosure($postEnclosure);
                }
                break;

            case 'HTML':
                $reader = new Html();
                break;

            case 'AUTO':
            default:
                $reader = IOFactory::createReaderForFile($importfile);
                break;
        }

        // TODO: Better error handling if file cannot be loaded (phpSpreadsheet apparently does not always use exceptions)
        if (isset($reader) and !is_null($reader)) {
            try {
                $spreadsheet = $reader->load($importfile);
                // Read specified sheet (passed as argument/param)
                if (is_numeric($postWorksheet)) {
                    $sheet = $spreadsheet->getSheet($postWorksheet);
                } elseif (!empty($postWorksheet)) {
                    $sheet = $spreadsheet->getSheetByName($postWorksheet);
                } else {
                    $sheet = $spreadsheet->getActiveSheet();
                }

                if (empty($sheet)) {
                    $gMessage->show($gL10n->get('SYS_IMPORT_SHEET_NOT_EXISTS', array($postWorksheet)));
                    // => EXIT
                } else {
                    // read data to array without any format
                    $_SESSION['import_data'] = $sheet->toArray(null, true, false);
                }
            } catch (\PhpOffice\PhpSpreadsheet\Exception|Exception $e) {
                $gMessage->show($e->getMessage());
                // => EXIT
            }
        }
    }

    /**
     * Imports items from the previously read import file into the database.
     *
     * @return array An array containing the success status and message of the import operation.
     * @throws Exception
     */
    public function importItems(): array
    {
        global $gL10n, $gDb, $gCurrentOrgId, $gSettingsManager, $gCurrentSession;
        // check form field input and sanitized it from malicious content
        $itemFieldsImportForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $itemFieldsImportForm->validate($_POST);

        $_SESSION['import_csv_request'] = $formValues;

        $returnMessage = array();

        // go through each line from the file one by one and create the user in the DB
        $line = reset($_SESSION['import_data']);
        $firstRowTitle = array_key_exists('first_row', $formValues);
        $startRow = 0;
        $importItemFields = array();

        // create array with all profile fields that where assigned to columns of the import file
        foreach ($formValues as $formFieldId => $importFileColumn) {
            if ($importFileColumn !== '' && $formFieldId !== 'adm_csrf_token' && $formFieldId !== 'first_row') {
                $importItemFields[$formFieldId] = (int)$importFileColumn;
            }
        }

        if ($firstRowTitle) {
            // skip first line, because here are the column names
            $line = next($_SESSION['import_data']);
            $startRow = 1;
        }

        $assignedFieldColumn = array();

        for ($i = $startRow, $iMax = count($_SESSION['import_data']); $i < $iMax; ++$i) {
            $row = array();
            foreach ($line as $columnKey => $columnValue) {
                // get usf id or database column name
                $fieldId = array_search($columnKey, $importItemFields);
                if ($fieldId !== false) {
                    // CSV readers may return numeric zero or null; preserve zero as text.
                    $row[$fieldId] = trim(strip_tags((string)$columnValue));
                }
            }
            $assignedFieldColumn[] = $row;
            $line = next($_SESSION['import_data']);
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
        $comparisonFieldIds = array();
        foreach ($items->getItemFields() as $itemField) {
            $name = $itemField->getValue('inf_name_intern');
            if (!in_array($name, array_merge(['KEEPER', 'STATUS'], $items->borrowFieldNames), true)) {
                $comparisonFieldIds[] = $itemField->getValue('inf_id');
            }
        }

        // Reuse the list preloader in bounded batches, including its category cache.
        foreach (array_chunk($items->getItems(), 200) as $batch) {
            if (count($assignedFieldColumn) === 0) {
                break;
            }
            $items = new ItemsData($gDb, $gCurrentOrgId);
            $items->preloadItemData(array_column($batch, 'ini_uuid'));
            foreach ($batch as $value) {
                $items->readItemData($value['ini_uuid']);
                $itemValues = array_fill_keys($comparisonFieldIds, '');
                foreach ($items->getItemData() as $key => $itemData) {
                    if ($itemData->getValue('inf_name_intern') === 'KEEPER'
                        || in_array($itemData->getValue('inf_name_intern'), $items->borrowFieldNames, true)) {
                        continue;
                    }
                    $itemValues[$key] = $itemData->getValue('ind_value');
                }
                $categoryName = $items->getValue('CATEGORY');
                if ($categoryName !== '') {
                    $itemValues[$items->getProperty('CATEGORY', 'inf_id')] = Language::translateIfTranslationStrId($categoryName);
                }

                foreach ($assignedFieldColumn as $key => $colValue) {
                    if (!$this->compareArrays($itemValues, $colValue)) {
                        unset($assignedFieldColumn[$key]);
                    }
                }
                if (count($assignedFieldColumn) === 0) {
                    break;
                }
            }
        }

        // get all values of the item fields
        $importedItemData = array();
        //array with the internal field names of the borrowing fields

        foreach ($assignedFieldColumn as $row => $values) {
            $itemData = array();
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
                $_POST['INF-' . $infNameIntern] = $val;
                $itemData[] = array($items->getItemFields()[$infNameIntern]->getValue('inf_name') => array('oldValue' => "", 'newValue' => $val));
            }

            $importedItemData[] = $itemData;
            unset($itemData);
            if (count($assignedFieldColumn) > 0) {

                $itemModule = new ItemService($gDb, '', 0, 1, true);
                $itemModule->save();

                $importSuccess = true;
            }
        }

        // cleanup the post data after the import
        unset($_POST);

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
     * @param array<int,string> $array1 Existing comparable values keyed by inventory field ID, including empty fields.
     * @param array<int,string> $array2 Imported values keyed by the same field IDs.
     * @return bool             true if the arrays are different based on the criteria, otherwise false
     */
    private function compareArrays(array $array1, array $array2): bool
    {
        foreach ($array1 as $fieldId => $value) {
            if ($value === '' && !array_key_exists($fieldId, $array2)) {
                continue;
            }

            if (!array_key_exists($fieldId, $array2) || $array2[$fieldId] !== $value) {
                return true;
            }
        }
        return false;
    }
}
