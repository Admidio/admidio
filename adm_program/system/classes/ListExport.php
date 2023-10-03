<?php
/**
 ***********************************************************************************************
 * Class manages a data array
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class creates a list configuration object. With this object it's possible
 * to manage the configuration in the database. You can easily create new lists,
 * add new columns or remove columns. The object will only list columns of the configuration
 * which the current user is allowed to view.
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;

class ListExport
{
    /**
     * @var array<int,array> Array with all data that should be handled in this class
     */
    protected $data = array();
    /**
     * @var Spreadsheet An object of the PhpSpreadsheet which will handle the export
     */
    protected $spreadsheet;
    /**
     * @var boolean Flag if the spreadsheet contains a headline for each column.
     */
    protected $containsHeadline = false;

    /**
     * Constructor that will create an object to handle the configuration of lists.
     */
    public function __construct()
    {
    }

    protected function format()
    {
        $alphabet = range('A', 'Z');
        $column = $alphabet[count($this->data[0])-1];

        if ($this->containsHeadline) {
            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle('A1:'.$column.'1')
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('ffdddddd');
            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle('A1:'.$column.'1')
                ->getFont()
                ->setBold(true);
        }

        for($number = 0; $number < count($this->data[0]); $number++) {
            $this->spreadsheet->getActiveSheet()->getColumnDimension($alphabet[$number])->setAutoSize(true);
        }
    }

    /**
     * Set the column headline for each column of the data array.
     * @param array $headlines Array with the column headline for each column.
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function setColumnHeadlines(array $headlines)
    {
        if (count($this->data) > 0) {
            array_unshift($this->data, $headlines);
            $this->spreadsheet->getActiveSheet()->insertNewRowBefore(1);
            $this->spreadsheet->getActiveSheet()->fromArray($headlines);
        } else {
            $this->data[] = $headlines;

            $this->spreadsheet = new Spreadsheet();
            $this->spreadsheet->getActiveSheet()->fromArray($this->data);
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

        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getActiveSheet()->fromArray($this->data);
    }

    /**
     * The data array will be filled from the result of a sql statement. Each row of the sql statement will be a
     * sub array where each column of the sql statement will be an array value.
     * @param string $sql Sql statement that will return the content for the export.
     * @param array $parameters Parameters for the sql statement.
     * @return void
     */
    public function setDataBySql(string $sql, array $parameters = array())
    {
        global $gDb;

        $listStatement = $gDb->queryPrepared($sql, $parameters);
        $dataSql = $listStatement->fetchAll(\PDO::FETCH_ASSOC);
        $this->data = array_merge($this->data, $dataSql);

        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getActiveSheet()->fromArray($this->data);
    }

    /**
     * Export the data that was added to this class to different file formats. The following file formats
     * are supported: xlsx, csv. The default export will be a csv file.
     * @param string $filename The name of the file without file extension that should be exported.
     * @param string $format The following values are allows: "xlsx", "csv"
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws AdmException
     */
    public function export(string $filename, string $format = 'csv')
    {
        if (count($this->data) === 0) {
            throw new AdmException('The export file will contain no data.');
        }

        switch ($format) {
            case 'xlsx':
                $this->format();
                $writer = new Xlsx($this->spreadsheet);
                $filename .= '.xlsx';
                break;
            case 'ods':
                $this->format();
                $writer = new Ods($this->spreadsheet);
                $filename .= '.ods';
                break;
            case 'pdf':
                $this->format();
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf($this->spreadsheet);
                $filename .= '.pdf';
                break;
            default:
                $writer = new Csv($this->spreadsheet);
                $filename .= '.csv';
                break;
        }

        // save file to server folder because we need the content length otherwise the Excel file is corrupt
        $tempFileFolderName = ADMIDIO_PATH . FOLDER_DATA . '/' . $filename;
        $writer->save($tempFileFolderName);

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($tempFileFolderName));
        ob_clean();
        flush();
        $writer->save('php://output');
        unlink($tempFileFolderName);
        exit();
    }
}
