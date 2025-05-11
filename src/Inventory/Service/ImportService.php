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
use Admidio\Inventory\Service\ItemService;
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
    public function readImportFile(): void
    {
        global $gL10n, $gMessage, $gCurrentSession;
        
        // check the CSRF token of the form against the session token
        $inventoryImportFileForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $inventoryImportFileForm->validate($_POST);
        
        // Initialize and check the parameters
        $postImportFormat   = admFuncVariableIsValid(
            $formValues,
            'format',
            'string',
            array('requireValue' => true,
                'validValues' => array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'))
        );
        $postImportCoding   = admFuncVariableIsValid(
            $formValues,
            'import_encoding',
            'string',
            array('validValues' => array('', 'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'))
        );
        $postSeparator      = admFuncVariableIsValid(
            $formValues,
            'import_separator',
            'string',
            array('validValues' => array('', ',', ';', '\t', '|'))
        );
        $postEnclosure      = admFuncVariableIsValid(
            $formValues,
            'import_enclosure',
            'string',
            array('validValues' => array('', 'AUTO', '"', '\|'))
        );

        $postWorksheet      = admFuncVariableIsValid($formValues, 'import_sheet', 'string');

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
            } catch (\PhpOffice\PhpSpreadsheet\Exception |Exception $e) {
                $gMessage->show($e->getMessage());
                // => EXIT
            }
        }
    }

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
            $line = next($_SESSION['import_data']);
        }
        
        // cleanup the assigned field column array
        $assignedFieldColumn = array_filter($assignedFieldColumn, function($row) {
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
                        $itemData->getValue('inf_name_intern') === 'IN_INVENTORY' || $itemData->getValue('inf_name_intern') === 'RECEIVED_ON' ||
                        $itemData->getValue('inf_name_intern') === 'RECEIVED_BACK_ON') {
                    continue;
                }
                
                if ($itemData->getValue('inf_name_intern') === 'CATEGORY') {
                    $category = new Category($gDb);
                    if ($category->readDataByUuid($itemValue));
                        $itemValues[] = array($itemData->getValue('inf_name_intern') => $category->getValue('cat_name'));
                    continue;
                }
        
                $itemValues[] = array($itemData->getValue('inf_name_intern') => $itemValue);
            }
            $itemValues = array_merge_recursive(...$itemValues);
        
            if (count($assignedFieldColumn) === 0) {
                break;
            }
        
            foreach($assignedFieldColumn as $key => $value) {
                $ret = $this->compareArrays($itemValues, $value);
                if (!$ret) {
                    unset($assignedFieldColumn[$key]);
                    continue;
                }
            }
        }
        
        // get all values of the item fields
        $importedItemData = array();
        
        foreach ($assignedFieldColumn as $row => $values) {
            foreach ($items->getItemFields() as $fields){
                $infId = $fields->getValue('inf_id');
                $imfNameIntern = $fields->getValue('inf_name_intern');
        
                if (isset($values[$infId]))
                {
                    if ($fields->getValue('inf_type')=='CHECKBOX') {
                        if ($values[$infId] === $gL10n->get('SYS_YES')) {
                            $values[$infId] = 1;
                        }
                        else {
                            $values[$infId] = 0;
                        }
                    }
        
                    if($imfNameIntern === 'ITEMNAME') {
                        if ($values[$infId] === '') {
                            break;
                        }
                        $val = $values[$infId];
                    }
                    elseif($imfNameIntern === 'KEEPER') {
                        if (substr_count($values[$infId], ',') === 1) {
                            $sql = $items->getSqlOrganizationsUsersShort();
                        }
                        else {
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
                    }
                    elseif($imfNameIntern === 'LAST_RECEIVER') {
                        if (substr_count($values[$infId], ',') === 1) {
                            $sql = $items->getSqlOrganizationsUsersShort();
                        }
                        else {
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
                    }
                    elseif($imfNameIntern === 'CATEGORY') {
                        $catName = $values[$infId];
                        $val = '';

                        if ($catName !== '') {
                            $categoryService = new CategoryService($gDb, 'IVT');
                            $allCategories = $categoryService->getVisibleCategories();
                            foreach ($allCategories as $key => $category) {
                                if ($category['cat_name'] === $catName) {
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
    
                    }
                    elseif($imfNameIntern === 'RECEIVED_ON' || $imfNameIntern === 'RECEIVED_BACK_ON') {
                        $val = $values[$infId];
                        if ($val !== '') {
                            // date must be formatted
                            if ($gSettingsManager->get('inventory_field_date_time_format')  === 'datetime') {
                                //check if date is datetime or only date
                                if (strpos($val, ' ') === false) {
                                    $val .=  '00:00';
                                }
                                // check if date is wrong formatted
                                $dateObject = DateTime::createFromFormat('d.m.Y H:i', $val);
                                if ($dateObject instanceof DateTime) {
                                    // convert date to correct format
                                    $val = $dateObject->format('Y-m-d H:i');
                                }
                                // chDateTimeeck if date is right formatted
                                $date = DateTime::createFromFormat('Y-m-d H:i', $val);
                                if ($date instanceof DateTime) {
                                    $val = $date->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
                                }
                            }
                            else {
                                // check if date is date or datetime
                                if (strpos($val, ' ') !== false) {
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
                    }
                    else {
                        $val = $values[$infId];
                    }
                }
                else {
                    $val = '';
                }
                $formValues['INF-' . $imfNameIntern] = '' . $val . '';
                $ItemData[] = array($items->getItemFields()[$imfNameIntern]->getValue('inf_name') => array('oldValue' => "", 'newValue' => $val));
            }
        
            $importedItemData[] = $ItemData;
            $ItemData = array();
            if (count($assignedFieldColumn) > 0) {
         
                $itemModule = new ItemService($gDb, '', 0, 1, 1);
                $itemModule->save();
        
                $importSuccess = true;
                unset($_POST);
            }   
        }
        
        // Send notification to all users
        $items->sendNotification($importedItemData);
                
         if ($importSuccess) {
            $returnMessage['success'] = 'success';
            $returnMessage['message'] = $gL10n->get('SYS_SAVE_DATA');
        }
        else {
            $returnMessage['success'] = 'success';
            $returnMessage['message'] = $gL10n->get('SYS_INVENTORY_NO_NEW_IMPORT_DATA');

        }

        return $returnMessage;
    }

    /**
     * Compares two arrays to determine if they are different based on specific criteria
     *
     * @param array             $array1 The first array to compare
     * @param array             $array2 The second array to compare
     * @return bool             true if the arrays are different based on the criteria, otherwise false
     */
    private function compareArrays(array $array1, array $array2) : bool
    {
        $array1 = array_filter($array1, function($key) {
            return $key !== 'KEEPER' && $key !== 'LAST_RECEIVER' && $key !== 'IN_INVENTORY' && $key !== 'RECEIVED_ON' && $key !== 'RECEIVED_BACK_ON';
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