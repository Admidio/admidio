<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Ifsnop\Mysqldump as IMysqldump;

class DatabaseDump
{
    /**
     * @var Database An object with the current database connection.
     */
    protected Database $dbHandler;
    /**
     * @var IMysqldump\Mysqldump An object that will create the database dump.
     */
    protected IMysqldump\Mysqldump $mysqldump;
    /**
     * @var string $dumpFile Name with path of the dump file.
     */
    protected string $dumpFilename;

    /**
     * @throws Exception
     */
    public function __construct(Database $dbHandler)
    {
        $this->dbHandler = $dbHandler;
    }

    /**
     * @param string $filename
     * @throws Exception
     */
    public function create(string $filename)
    {
        global $gDbType, $g_adm_srv, $g_adm_db, $g_adm_usr, $g_adm_pw;

        $dumpSettings = array(
            'include-tables' => $this->getAdmidioTables(),
            'compress' => IMysqldump\Mysqldump::GZIP
        );

        $this->dumpFilename = $filename;

        $this->mysqldump = new IMysqldump\Mysqldump($gDbType . ':host=' . $g_adm_srv . ';dbname=' . $g_adm_db, $g_adm_usr, $g_adm_pw, $dumpSettings);
        $this->mysqldump->start(ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $this->dumpFilename);

    }

    public function export()
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->dumpFilename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize(ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $this->dumpFilename));
        readfile(ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $this->dumpFilename);
        exit();
    }

    public function deleteDumpFile()
    {
        unlink(ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $this->dumpFilename);
    }

    protected function getAdmidioTables(): array
    {
        // create a list with all tables with configured table prefix
        $sql = 'SELECT table_name
          FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name LIKE ?
           AND table_type = \'BASE TABLE\'';
        $statement = $this->dbHandler->queryPrepared($sql, array(DB_NAME, TABLE_PREFIX . '_%'));
        $tables = array();

        while ($tableName = $statement->fetchColumn()) {
            $tables[] = $tableName;
        }

        return $tables;
    }
}
