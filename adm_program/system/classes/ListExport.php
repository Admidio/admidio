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
class ListExport
{
    /**
     * @var array<string,string> Array with all data that should be handled in this class
     */
    protected $data = array();

    /**
     * Constructor that will create an object to handle the configuration of lists.
     */
    public function __construct()
    {
    }

    public function setColumnHeadlines(array $headlines)
    {
        if (count($this->data) > 0) {
            array_unshift($this->data, $headlines);
        } else {
            $this->data[] = $headlines;
        }
    }

    public function setDataByArray(array $dataArray)
    {
        $this->data = array_merge($this->data, $dataArray);
    }
    public function setDataBySql(string $sql, array $parameters = array())
    {
        global $gDb;

        $listStatement = $gDb->queryPrepared($sql, $parameters);
        $dataSql = $listStatement->fetchAll(\PDO::FETCH_ASSOC);
        $this->data = array_merge($this->data, $dataSql);
    }

    /**
     * Export the data that was added to this class to different file formats. The following file formats
     * are supported: xlsx, csv.
     * @param string $filename The name of the file without file extension that should be exported.
     * @param string $format The following values are allows: "xlsx", "csv"
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export(string $filename, string $format)
    {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->fromArray($this->data);

        switch ($format) {
            case 'xlsx':
                $writer = new Xlsx($spreadsheet);
                $filename .= '.xlsx';
                break;

            case 'csv':
                $writer = new Csv($spreadsheet);
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
