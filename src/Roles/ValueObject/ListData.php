<?php
namespace Admidio\Roles\ValueObject;

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\ListConfiguration;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @brief Class manages a data array
 *
 * This class handle the data of a list. Therefore, the data can be added via several methods.
 * The preferred method is based on the ListConfiguration class and will use their configuration
 * to handle the data and the output. It's also possible to add data via an individual sql or
 * just set a custom array. The class delivers several export possibilities such as Excel,
 * ODF-Spreadsheet or CSV file.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ListData
{
    /**
     * @var array<int,array> Array with all data that should be handled in this class
     */
    protected array $data = array();
    /**
     * @var ListConfiguration An object of the ListConfiguration that could be used to read data
     * and to format data to different output formats.
     */
    protected ListConfiguration $listConfiguration;
    /**
     * @var Spreadsheet An object of the PhpSpreadsheet which will handle the export
     */
    protected Spreadsheet $spreadsheet;
    /**
     * @var boolean Flag if the spreadsheet contains a headline for each column.
     */
    protected bool $containsHeadline = false;

    /**
     * Constructor that will create an object to handle the configuration of lists.
     */
    public function __construct()
    {
    }

    /**
     * Return the number of rows of the data in this object.
     * @return int Return the number of rows of the data in this object.
     */
    public function rowCount(): int
    {
        return count($this->data);
    }

    /**
     * Returns an array with all the data prepared for the destination output format.
     * @param string $outputFormat Optional output format. The following formats are possible 'html', 'print', 'csv', 'xlsx', 'ods' or 'pdf'
     * @return array[]
     * @throws Exception
     */
    public function getData(string $outputFormat = ''): array
    {
        if ($outputFormat !== '') {
            return $this->prepareOutputFormat($outputFormat);
        } else {
            return $this->data;
        }
    }

    /**
     * Prepares the internal data array for the submitted output format and returns that formatted array.
     * @param string $outputFormat The following formats are possible 'html', 'print', 'csv', 'xlsx', 'ods' or 'pdf'
     * @return array Returns copy of the data array with formatted data.
     * @throws Exception
     */
    protected function prepareOutputFormat(string $outputFormat): array
    {
        global $gL10n;

        $outputData = array();
        $startRow = 0;

        if($this->containsHeadline) {
            $startRow = 1;
            $outputData[0] = $this->data[0];
        }

        for($rowNumber = $startRow; $rowNumber < count($this->data); $rowNumber++) {
            $columnNumber = 1;

            foreach($this->data[$rowNumber] as $columnValueKey => $columnValue) {
                if (in_array($columnValueKey, array('mem_leader', 'usr_uuid'))) {
                    $outputData[$rowNumber][$columnValueKey] = $columnValue;
                } elseif ($columnValueKey === 'mem_former') {
                    if ($outputFormat === 'html' || $outputFormat === 'print' || $outputFormat === 'pdf') {
                        // For HTML, print, and pdf formats, we keep the boolean value as is
                        $outputData[$rowNumber][$columnValueKey] = (bool)$columnValue;
                    } else {
                        // For all other formats, we convert the boolean value to a string
                        $outputData[$rowNumber][$columnValueKey] = (bool)$columnValue ? $gL10n->get('SYS_FORMER_MEMBER') : $gL10n->get('SYS_MEMBER');
                    }
                } else {
                    $outputData[$rowNumber][$columnValueKey] =
                        $this->listConfiguration->convertColumnContentForOutput(
                            $columnNumber,
                            $outputFormat,
                            (string) $columnValue,
                            ($this->data[$rowNumber]['usr_uuid'] ?? '')
                        );
                    $columnNumber++;
                }
            }
        }

        return $outputData;
    }

    /**
     * @throws Exception
     */
    protected function format()
    {
        $activeSheet = $this->spreadsheet->getActiveSheet();
        $columnCount = count($this->data[0]);
        $lastColumn  = Coordinate::stringFromColumnIndex($columnCount);
    
        if ($this->containsHeadline) {
            $range = "A1:{$lastColumn}1";
            $style = $activeSheet->getStyle($range);
    
            $style->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFDDDDDD');
            $style->getFont()
                ->setBold(true);
        }
    
        for ($i = 1; $i <= $columnCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $activeSheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    
        try {
            $this->spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new Exception($e);
        }
    }
    
    /**
     * Set the column headline for each column of the data array.
     * @param array $headlines Array with the column headline for each column.
     * @return void
     */
    public function setColumnHeadlines(array $headlines)
    {
        if (count($this->data) > 0) {
            array_unshift($this->data, $headlines);
        } else {
            $this->data[] = $headlines;
        }
        $this->containsHeadline = true;
    }

    /**
     * Set an array filled with data that should be exported.
     * @param array $dataArray The array with the data that should be exported.
     * @return void
     */
    public function setDataByArray(array $dataArray)
    {
        $this->data = array_merge($this->data, $dataArray);
    }

    /**
     * Read the data by a configuration of the table **adm_lists**. With this method it's possible
     * to format the output for visual html presentation or for the different export formats.
     * @param ListConfiguration $listConfiguration A configuration object with all necessary information.
     * @param array $options (optional) An array with the following possible entries:
     *                                  - **showAllMembersThisOrga** : Set to true all users with an active membership
     *                                    to at least one role of the current organization will be shown.
     *                                    This setting could be combined with **showFormerMembers** or **showRelationTypes**.
     *                                  - **showAllMembersDatabase** : Set to true all users of the database will be shown
     *                                    independent of the membership to roles or organizations
     *                                  - **showRolesMembers** : An array with all roles ids could be set and only members
     *                                    of this roles will be shown.
     *                                    This setting could be combined with **showFormerMembers** or **showRelationTypes**.
     *                                  - **showFormerMembers** : Set to true if roles members or members of the organization
     *                                    should be shown and also former members should be listed
     *                                  - **showRelationTypes** : An array with relation types. The sql will be expanded with
     *                                    all users who are in such a relationship to the selected role users.
     *                                  - **showUserUUID** : If set to true the first column of the SQL will be the usr_uuid.
     *                                  - **showLeaderFlag** : If set to true the first columns of the SQL will be
     *                                    the flag if a user is a leader in the role or not.
     *                                  - **useConditions** : false - Don't add additional conditions to the SQL
     *                                                        true  - Conditions will be added as stored in the settings
     *                                  - **useOrderBy** : false - Don't add the sorting to the SQL
     *                                                  true  - Sorting is added as stored in the settings
     *                                  - **startDate** : The start date if memberships that should be considered. The time period of
     *                                    the membership must be at least one day after this date.
     *                                  - **endDate** : The end date if memberships that should be considered.The time period of
     *                                    the membership must be at least one day before this date.
     * @return void
     * @throws Exception
     */
    public function setDataByConfiguration(ListConfiguration $listConfiguration, array $options)
    {
        $this->listConfiguration = $listConfiguration;
        $this->setDataBySql($this->listConfiguration->getSQL($options));
    }

    /**
     * The data array will be filled from the result of a sql statement. Each row of the sql statement will be a
     * sub array where each column of the sql statement will be an array value.
     * @param string $sql Sql statement that will return the content for the export.
     * @param array $parameters Parameters for the sql statement.
     * @return void
     * @throws Exception
     */
    public function setDataBySql(string $sql, array $parameters = array())
    {
        global $gDb;

        $listStatement = $gDb->queryPrepared($sql, $parameters);
        $dataSql = $listStatement->fetchAll(\PDO::FETCH_ASSOC);
        $this->data = array_merge($this->data, $dataSql);
    }

    /**
     * Export the data that was added to this class to different file formats. The following file formats
     * are supported: xlsx, csv. The default export will be a csv file.
     * @param string $filename The name of the file without file extension that should be exported.
     * @param string $format The following values are allows: "xlsx", "csv"
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws Exception
     */
    public function export(string $filename, string $format = 'csv')
    {
        if (count($this->data) === 0) {
            throw new Exception('The export file will contain no data.');
        }

        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getActiveSheet()->fromArray($this->prepareOutputFormat($format));

        switch ($format) {
            case 'xlsx':
                $this->format();
                $writer = new Xlsx($this->spreadsheet);
                $filename .= '.xlsx';
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'ods':
                $this->format();
                $writer = new Ods($this->spreadsheet);
                $filename .= '.ods';
                $contentType = 'application/vnd.oasis.opendocument.spreadsheet';
                break;
            case 'pdf':
                $this->format();
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf($this->spreadsheet);
                $filename .= '.pdf';
                $contentType = 'application/pdf';
                break;
            default:
                $writer = new Csv($this->spreadsheet);
                $filename .= '.csv';
                $contentType = 'text/csv';
                break;
        }

        // save file to server folder because we need the content length otherwise the Excel file is corrupt
        $tempFileFolderName = ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $filename;
        $writer->save($tempFileFolderName);

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($tempFileFolderName));
        if(ob_get_length() > 0) { // Issue 1607 Fix
            ob_end_clean();
        }
        $writer->save('php://output');
        unlink($tempFileFolderName);
        exit();
    }
}
