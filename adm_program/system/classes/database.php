<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Database
 * @brief Handle the connection to the database, send all sql statements and handle the returned rows.
 *
 * This class creates a connection to the database and provides several methods
 * to communicate with the database. There are methods to send sql statements
 * and to handle the response of the database. This class also supports transactions.
 * Just call Database#startTransaction and finish it with Database#endTransaction. If
 * you call this multiple times only 1 transaction will be open and it will be closed
 * after the last endTransaction was send.
 * @par Examples
 * To open a connection you can use the settings of the config.php of Admidio.
 * @code
 * // create object and open connection to database
 * try
 * {
 *     $gDb = new Database($gDbType, $g_adm_srv, null, $g_adm_db, $g_adm_usr, $g_adm_pw);
 * }
 * catch (AdmException $e)
 * {
 *     $e->showText();
 * }
 * @endcode
 * Now you can use the new object @b $gDb to send a query to the database
 * @code
 * // send sql to database and assign the returned PDOStatement
 * $organizationsStatement = $gDb->query('SELECT org_shortname, org_longname FROM adm_organizations');
 *
 * // now fetch all rows of the returned PDOStatement within one array
 * $organizationsList = $organizationsStatement->fetchAll();
 *
 * // Array with the results:
 * //    $organizationsList = array(
 * //         [0] => array(
 * //             [org_shortname] => 'DEMO'
 * //             [org_longname]  => 'Demo-Organization'
 * //             )
 * //         [1] => array(
 * //             [org_shortname] => 'TEST'
 * //             [org_longname]  => 'Test-Organization'
 * //             )
 *
 * // you can also go step by step through the returned PDOStatement
 * while ($organizationNames = $organizationsStatement->fetch())
 * {
 *     echo $organizationNames['shortname'].' '.$organizationNames['longname'];
 * }
 * @endcode
 */
class Database
{
    protected $engine;
    protected $host;
    protected $port;
    protected $dbName;
    protected $username;
    protected $password;
    protected $options;

    protected $dsn;
    protected $pdo;                 ///< The PDO object that handles the communication with the database.
    protected $transactions;        ///< The transaction marker. If this is > 0 than a transaction is open.
    protected $pdoStatement;        ///< The PdoStatement object which is needed to handle the return of a query.
    protected $dbStructure;         ///< array with arrays of every table with their structure
    protected $fetchArray;
    protected $minRequiredVersion;  ///< The minimum required version of this database that is necessary to run Admidio.
    protected $databaseName;        ///< The name of the database e.g. 'MySQL'

    /**
     * The constructor will check if a valid engine was set and try to connect to the database.
     * If the engine is invalid or the connection not possible an exception will be thrown.
     * @param string $engine   The database type that is supported from Admidio. @b mysql and @b postgresql are valid values.
     * @param string $host     The hostname or server where the database is running. e.g. localhost or 127.0.0.1
     * @param int    $port     If you don't use the default port of the database then set your port here.
     * @param string $dbName   Name of the database you want to connect.
     * @param string $username Username to connect to database
     * @param string $password Password to connect to database
     * @param array  $options
     * @throws AdmException
     */
    public function __construct($engine, $host, $port = null, $dbName, $username = null, $password = null, $options = array())
    {
        // for compatibility to old versions accept the string postgresql
        if ($engine === 'postgresql')
        {
            $engine = 'pgsql';
        }

        $this->engine   = $engine;
        $this->host     = $host;
        $this->port     = $port;
        $this->dbName   = $dbName;
        $this->username = $username;
        $this->password = $password;
        $this->options  = $options;

        $this->transactions = 0;
        $this->sqlStatement = null;
        $this->fetchArray   = array();
        $this->minRequiredVersion = '';
        $this->databaseName       = '';

        try
        {
            $availableDrivers = PDO::getAvailableDrivers();

            if (empty($availableDrivers))
            {
                throw new PDOException('PDO does not support any drivers');
            }
            if (!in_array($this->engine, $availableDrivers, true))
            {
                throw new PDOException('The requested PDO driver '.$this->engine.' is not supported');
            }

            $this->buildDSNString();

            // needed to avoid leaking username, password, ... if a PDOException is thrown
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);

            $this->setConnectionOptions();
        }
        catch (PDOException $e)
        {
            throw new AdmException($e->getMessage());
        }
    }

    /**
     * Create a valid DSN string for the engine that was set through the constructor.
     * If no valid engine is set than an exception is thrown.
     * @throws PDOException
     */
    private function buildDSNString()
    {
        switch ($this->engine)
        {
            case 'mysql':
                if (!$this->port)
                {
                    $this->dsn = 'mysql:host='.$this->host.';dbname='.$this->dbName;
                }
                else
                {
                    $this->dsn = 'mysql:host='.$this->host.';port='.$this->port.';dbname='.$this->dbName;
                }
                break;

            case 'pgsql':
                if (!$this->port)
                {
                    $this->dsn = 'pgsql:host='.$this->host.';dbname='.$this->dbName;
                }
                else
                {
                    $this->dsn = 'pgsql:host='.$this->host.';port='.$this->port.';dbname='.$this->dbName;
                }
                break;

            default:
                throw new PDOException('Engine is not supported by Admidio');
        }
    }

    /**
     * The method will commit an open transaction to the database. If the
     * transaction counter is greater 1 than only the counter will be
     * decreased and no commit will performed.
     * @return bool Returns @b true if the commit was successful otherwise @b false
     * @see Database#startTransaction
     * @see Database#rollback
     */
    public function endTransaction()
    {
        global $gDebug;

        // if there is no open transaction then do nothing and return
        if ($this->transactions === 0)
        {
            return true;
        }

        // If there was a previously opened transaction we do not commit yet...
        // but count back the number of inner transactions
        if ($this->transactions > 1)
        {
            --$this->transactions;
            return true;
        }

        // if debug mode then log all sql statements
        if ($gDebug === 1)
        {
            error_log('COMMIT');
        }

        $result = $this->pdo->commit();

        if (!$result)
        {
            $this->showError();
        }

        $this->transactions = 0;
        return $result;
    }

    /**
     * Escapes special characters within the input string.
     * In contrast to the <a href="https://secure.php.net/manual/en/pdo.quote.php">quote</a> method,
     * the returned string has no quotes around the input string!
     * @param string $string The string to be quoted.
     * @return string Returns a quoted string that is theoretically safe to pass into an SQL statement.
     * @see <a href="https://secure.php.net/manual/en/pdo.quote.php">PDO::quote</a>
     */
    public function escapeString($string)
    {
        return trim($this->pdo->quote($string), "'");
    }

    /**
     * Returns an array with all available PDO database drivers of the server.
     * @return array Returns an array with all available PDO database drivers of the server.
     */
    public static function getAvailableDBs()
    {
        return PDO::getAvailableDrivers();
    }

    /**
     * This method will create an backtrace of the current position in the script. If several
     * scripts were called than each script with their position will be listed in the backtrace.
     * @return string Returns a string with the backtrace of all called scripts.
     */
    protected function getBacktrace()
    {
        $output = '<div style="font-family: monospace;">';
        $backtrace = debug_backtrace();
        $path = SERVER_PATH;

        foreach ($backtrace as $number => $trace)
        {
            // We skip the first one, because it only shows this file/function
            if ($number === 0)
            {
                continue;
            }

            // Strip the current directory from path
            if (empty($trace['file']))
            {
                $trace['file'] = '';
            }
            else
            {
                $trace['file'] = str_replace(array($path, '\\'), array('', '/'), $trace['file']);
                $trace['file'] = substr($trace['file'], 1);
            }
            $args = array();

            // If include/require/include_once is not called, do not show arguments - they may contain sensible information
            if (!in_array($trace['function'], array('include', 'require', 'include_once'), true))
            {
                unset($trace['args']);
            }
            else
            {
                // Path...
                if (!empty($trace['args'][0]))
                {
                    $argument = htmlentities($trace['args'][0]);
                    $argument = str_replace(array($path, '\\'), array('', '/'), $argument);
                    $argument = substr($argument, 1);
                    $args[] = "'{$argument}'";
                }
            }

            $trace['class'] = (!isset($trace['class'])) ? '' : $trace['class'];
            $trace['type']  = (!isset($trace['type']))  ? '' : $trace['type'];

            $output .= '<br />';
            $output .= '<strong>FILE:</strong> '.htmlentities($trace['file']).'<br />';
            $output .= '<strong>LINE:</strong> '.((!empty($trace['line'])) ? $trace['line'] : '').'<br />';

            $output .= '<strong>CALL:</strong> '.htmlentities($trace['class'].$trace['type'].$trace['function']).
                       '('.((count($args)) ? implode(', ', $args) : '').')<br />';
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Get the minimum required version of the database that is necessary to run Admidio.
     * @return string Returns a string with the minimum required database version e.g. '5.0.1'
     */
    public function getMinimumRequiredVersion()
    {
        if ($this->minRequiredVersion === '')
        {
            $xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/databases.xml', null, true);
            $node = $xmlDatabases->xpath('/databases/database[@id="'.$this->engine.'"]/minversion');
            $this->minRequiredVersion = (string) $node[0]; // explicit typcasting because of problem with simplexml and sessions
        }
        return $this->minRequiredVersion;
    }

    /**
     * Get the name of the database that is running Admidio.
     * @return string Returns a string with the name of the database e.g. 'MySQL' or 'PostgreSQL'
     */
    public function getName()
    {
        if ($this->databaseName === '')
        {
            $xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/databases.xml', null, true);
            $node = $xmlDatabases->xpath('/databases/database[@id="'.$this->engine.'"]/name');
            $this->databaseName = (string) $node[0]; // explicit typcasting because of problem with simplexml and sessions
        }
        return $this->databaseName;
    }

    /**
     * Get the version of the connected database.
     * @return string Returns a string with the database version e.g. '5.5.8'
     */
    public function getVersion()
    {
        $versionStatement = $this->query('SELECT version()');
        $row = $versionStatement->fetch(PDO::FETCH_NUM);

        if ($this->engine === 'pgsql')
        {
            // the string (PostgreSQL 9.0.4, compiled by Visual C++ build 1500, 64-bit) must be separated
            $versionArray  = explode(',', $row[0]);
            $versionArray2 = explode(' ', $versionArray[0]);
            return $versionArray2[1];
        }

        return $row[0];
    }

    /**
     * Returns the ID of the unique id column of the last INSERT operation.
     * This method replace the old method Database#insert_id.
     * @return string Return ID value of the last INSERT operation.
     * @see Database#insert_id
     */
    public function lastInsertId()
    {
        if ($this->engine === 'pgsql')
        {
            $lastValStatement = $this->query('SELECT lastval()');
            $insertRow = $lastValStatement->fetch(PDO::FETCH_NUM);
            return $insertRow[0];
        }
        else
        {
            return $this->pdo->lastInsertId();
        }
    }

    /**
     * Send a sql statement to the database that will be executed. If debug mode is set
     * then this statement will be written to the error log. If it's a @b SELECT statement
     * then also the number of rows will be logged. If an error occurred the script will
     * be terminated and the error with a backtrace will be send to the browser.
     * @param string $sql A string with the sql statement that should be executed in database.
     * @param bool $throwError Default will be @b true and if an error the script will be terminated and
     *                         occurred the error with a backtrace will be send to the browser. If set to
     *                         @b false no error will be shown and the script will be continued.
     * @return object For @b SELECT statements an object of <a href="https://secure.php.net/manual/en/class.pdostatement.php">PDOStatement</a> will be returned.
     *                This should be used to fetch the returned rows. If an error occurred then @b false will be returned.
     */
    public function query($sql, $throwError = true)
    {
        global $gDebug;

        if ($this->engine === 'pgsql')
        {
            $sqlCompare = strtolower($sql);

            // prepare the sql statement to be compatible with PostgreSQL
            if (strpos($sqlCompare, 'create table') !== false || strpos($sqlCompare, 'alter table') !== false)
            {
                if (strpos($sqlCompare, 'create table') !== false)
                {
                    // on a create-table-statement if necessary cut existing MySQL table options
                    $sql = substr($sql, 0, strrpos($sql, ')') + 1);
                }

                // PostgreSQL doesn't know unsigned
                $sql = str_replace('unsigned', '', $sql);

                // PostgreSQL interprets a boolean as string so transform it to a smallint
                $sql = str_replace('boolean', 'smallint', $sql);

                // A blob is in PostgreSQL a bytea datatype
                $sql = str_replace('blob', 'bytea', $sql);

                // Auto_Increment must be replaced with Serial
                $posAutoIncrement = strpos($sql, 'AUTO_INCREMENT');
                if ($posAutoIncrement > 0)
                {
                    $posInteger = strrpos(substr($sql, 0, $posAutoIncrement), 'integer');
                    $sql = substr($sql, 0, $posInteger).' serial '.substr($sql, $posAutoIncrement + 14);
                }
            }
        }

        // if debug mode then log all sql statements
        if ($gDebug === 1)
        {
            error_log($sql);
        }

        $this->fetchArray   = array();
        $this->pdoStatement = $this->pdo->query($sql);

        // if we got an db error then show this error
        if ($this->pdo->errorCode() !== null && $this->pdo->errorCode() !== '00000')
        {
            if ($throwError)
            {
                return $this->showError();
            }
        }
        elseif ($gDebug === 1 && strpos(strtoupper($sql), 'SELECT') === 0)
        {
            // if debug modus then show number of selected rows
            error_log('Found rows: '.$this->pdoStatement->rowCount());
        }

        return $this->pdoStatement;
    }

    /**
     * If there is a open transaction than this method sends a rollback
     * to the database and will set the transaction counter to zero.
     * @return bool
     * @see Database#startTransaction
     * @see Database#endTransaction
     */
    public function rollback()
    {
        global $gDebug;

        if ($this->transactions > 0)
        {
            // if debug mode then log all sql statements
            if ($gDebug === 1)
            {
                error_log('ROLLBACK');
            }

            $result = $this->pdo->rollBack();

            if (!$result)
            {
                $this->showError();
            }

            $this->transactions = 0;
            return true;
        }
        return false;
    }

    /**
     * Set connection specific options like UTF8 connection.
     * These options should always be set if Admidio connect to a database.
     */
    private function setConnectionOptions()
    {
        // Connect to database with UTF8
        $this->query('SET NAMES \'UTF8\'');

        if ($this->engine === 'mysql')
        {
            // set ANSI mode, that SQL could be more compatible with other DBs
            $this->query('SET SQL_MODE = \'ANSI\'');
            // if the server has limited the joins, it can be canceled with this statement
            $this->query('SET SQL_BIG_SELECTS = 1');
        }
    }

    /**
     * Methods reads all columns and their properties from the database table.
     * @param string $table                Name of the database table for which the columns should be shown.
     * @param bool   $showColumnProperties If this is set to @b false only the column names were returned.
     * @return array Returns an array with each column and their properties if $showColumnProperties is set to @b true.
     *               The array has the following format:
     *               array (
     *               'column1' => array (
     *                            'serial' => '1',
     *                            'null'   => '0',
     *                            'key'    => '0',
     *                            'type'   => 'integer')
     *               'column2' => array (...)
     *               ...
     */
    public function showColumns($table, $showColumnProperties = true)
    {
        if (!isset($this->dbStructure[$table]))
        {
            $columnProperties = array();

            if ($this->engine === 'mysql')
            {
                $sql = 'SHOW COLUMNS FROM '.$table;
                $columnsStatement = $this->query($sql);
                $columnsList      = $columnsStatement->fetchAll();

                foreach ($columnsList as $properties)
                {
                    $columnProperties[$properties['Field']]['serial'] = 0;
                    $columnProperties[$properties['Field']]['null']   = 0;
                    $columnProperties[$properties['Field']]['key']    = 0;

                    if ($properties['Extra'] === 'auto_increment')
                    {
                        $columnProperties[$properties['Field']]['serial'] = 1;
                    }
                    if ($properties['Null'] === 'YES')
                    {
                        $columnProperties[$properties['Field']]['null'] = 1;
                    }
                    if ($properties['Key'] === 'PRI' || $properties['Key'] === 'MUL')
                    {
                        $columnProperties[$properties['Field']]['key'] = 1;
                    }

                    if (strpos($properties['Type'], 'tinyint(1)') !== false)
                    {
                        $columnProperties[$properties['Field']]['type'] = 'boolean';
                    }
                    elseif (strpos($properties['Type'], 'smallint') !== false)
                    {
                        $columnProperties[$properties['Field']]['type'] = 'smallint';
                    }
                    elseif (strpos($properties['Type'], 'int') !== false)
                    {
                        $columnProperties[$properties['Field']]['type'] = 'integer';
                    }
                    else
                    {
                        $columnProperties[$properties['Field']]['type'] = $properties['Type'];
                    }
                }
            }
            elseif ($this->engine === 'pgsql')
            {
                $sql = 'SELECT column_name, column_default, is_nullable, data_type
                          FROM information_schema.columns
                         WHERE table_name = \''.$table.'\'';
                $columnsStatement = $this->query($sql);
                $columnsList = $columnsStatement->fetchAll();

                foreach ($columnsList as $properties)
                {
                    $columnProperties[$properties['column_name']]['serial'] = 0;
                    $columnProperties[$properties['column_name']]['null']   = 0;
                    $columnProperties[$properties['column_name']]['key']    = 0;

                    if (strpos($properties['column_default'], 'nextval') !== false)
                    {
                        $columnProperties[$properties['column_name']]['serial'] = 1;
                    }
                    if ($properties['is_nullable'] === 'YES')
                    {
                        $columnProperties[$properties['column_name']]['null']   = 1;
                    }
                    /*if ($properties['Key'] === 'PRI' || $properties['Key'] === 'MUL')
                    {
                        $columnProperties[$properties['column_name']]['key'] = 1;
                    }*/

                    if (strpos($properties['data_type'], 'timestamp') !== false)
                    {
                        $columnProperties[$properties['column_name']]['type'] = 'timestamp';
                    }
                    elseif (strpos($properties['data_type'], 'time') !== false)
                    {
                        $columnProperties[$properties['column_name']]['type'] = 'time';
                    }
                    else
                    {
                        $columnProperties[$properties['column_name']]['type'] = $properties['data_type'];
                    }
                }
            }

            // safe array with table structure in class array
            $this->dbStructure[$table] = $columnProperties;
        }

        if ($showColumnProperties)
        {
            // returns all columns with their properties of the table
            return $this->dbStructure[$table];
        }
        else
        {
            // returns only the column names of the table.
            $tableColumns = array();

            foreach ($this->dbStructure[$table] as $columnName => $columnProperties)
            {
                $tableColumns[] = $columnName;
            }

            return $tableColumns;
        }
    }

    /**
     * Display the error code and error message to the user if a database error occurred.
     * The error must be read by the child method. This method will call a backtrace so
     * you see the script and specific line in which the error occurred.
     * @param int    $code    The database error code that will be displayed.
     * @param string $message The database error message that will be displayed.
     * @return void Will exit the script and returns a html output with the error information.
     */
    public function showError($code = 0, $message = '')
    {
        global $g_root_path, $gMessage, $gPreferences, $gCurrentOrganization, $gDebug, $gL10n;

        $backtrace  = $this->getBacktrace();

        // Rollback on open transaction
        if ($this->transactions > 0)
        {
            $this->pdo->rollBack();
        }

        if (!headers_sent() && isset($gPreferences) && defined('THEME_SERVER_PATH'))
        {
            // create html page object
            $page = new HtmlPage($gL10n->get('SYS_DATABASE_ERROR'));
        }

        // transform the database error to html
        $errorInfo = $this->pdo->errorInfo();

        $htmlOutput = '
            <div style="font-family: monospace;">
                 <p><strong>S Q L - E R R O R</strong></p>
                 <p><strong>CODE:</strong> '.$this->pdo->errorCode().'</p>
                 '.$errorInfo[1].'<br /><br />
                 '.$errorInfo[2].'<br /><br />
                 <strong>B A C K T R A C E</strong><br />
                 '.$backtrace.'
             </div>';

        // in debug mode show error in log file
        if ($gDebug === 1)
        {
            error_log($this->pdo->errorCode().': '.$errorInfo[1]."\n".$errorInfo[2]);
        }

        // display database error to user
        if (isset($page) && !headers_sent() && isset($gPreferences) && defined('THEME_SERVER_PATH'))
        {
            $page->addHtml($htmlOutput);
            $page->show();
        }
        else
        {
            echo $htmlOutput;
        }

        exit();
    }

    /**
     * Start a transaction if no open transaction exists. If you call this multiple times
     * only 1 transaction will be open and it will be closed after the last endTransaction was send.
     * @return bool
     * @see Database#endTransaction
     * @see Database#rollback
     */
    public function startTransaction()
    {
        global $gDebug;

        // If we are within a transaction we will not open another one,
        // but enclose the current one to not loose data (prevening auto commit)
        if ($this->transactions > 0)
        {
            ++$this->transactions;
            return true;
        }

        // if debug mode then log all sql statements
        if ($gDebug === 1)
        {
            error_log('START TRANSACTION');
        }

        $result = $this->pdo->beginTransaction();

        if (!$result)
        {
            $this->showError();
        }

        $this->transactions = 1;
        return $result;
    }

    /**
     * Fetch a result row as an associative array, a numeric array, or both.
     * @deprecated 3.1.0:4.0.0 Switched to native PDO method.
     *             Please use the PHP class <a href="https://secure.php.net/manual/en/class.pdostatement.php">PDOStatement</a>
     *             and the method <a href="https://secure.php.net/manual/en/pdostatement.fetch.php">fetch</a> instead.
     * @param object $pdoStatement An object of the class PDOStatement. This should be set if multiple
     *                             rows where selected and other sql statements are also send to the database.
     * @param int    $fetchType    Set the result type. Can contain @b PDO::FECTH_ASSOC for an associative array,
     *                             @b PDO::FETCH_NUM for a numeric array or @b PDO::FETCH_BOTH (Default).
     * @return object|null Returns an array that corresponds to the fetched row and moves the internal data pointer ahead.
     * @see <a href="https://secure.php.net/manual/en/pdostatement.fetch.php">PDOStatement::fetch</a>
     */
    public function fetch_array($pdoStatement = null, $fetchType = PDO::FETCH_BOTH)
    {
        // if pdo statement is committed then fetch this object
        if (is_object($pdoStatement))
        {
            return $pdoStatement->fetch($fetchType);
        }
        // if no pdo statement was committed then take the one from the last query
        elseif (is_object($this->pdoStatement))
        {
            return $this->pdoStatement->fetch($fetchType);
        }

        return null;
    }

    /**
     * Fetch a result row as an object.
     * @deprecated 3.1.0:4.0.0 Switched to native PDO method.
     *             Please use methods Database#fetchAll or Database#fetch instead.
     *             Please use the PHP class <a href="https://secure.php.net/manual/en/class.pdostatement.php">PDOStatement</a>
     *             and the method <a href="https://secure.php.net/manual/en/pdostatement.fetchobject.php">fetchObject</a> instead.
     * @param object $pdoStatement An object of the class PDOStatement. This should be set if multiple
     *                             rows where selected and other sql statements are also send to the database.
     * @return object|null Returns an object that corresponds to the fetched row and moves the internal data pointer ahead.
     * @see <a href="https://secure.php.net/manual/en/pdostatement.fetchobject.php">PDOStatement::fetchObject</a>
     */
    public function fetch_object($pdoStatement = null)
    {
        // if pdo statement is committed then fetch this object
        if (is_object($pdoStatement))
        {
            return $pdoStatement->fetchObject();
        }
        // if no pdo statement was committed then take the one from the last query
        elseif (is_object($this->pdoStatement))
        {
            return $this->pdoStatement->fetchObject();
        }

        return null;
    }

    /**
     * Returns the ID of the unique id column of the last INSERT operation.
     * @deprecated 3.1.0:4.0.0 Renamed method to camelCase style.
     *             Please use methods Database#lastInsertId instead.
     * @return string Return ID value of the last INSERT operation.
     * @see Database#lastInsertId
     */
    public function insert_id()
    {
        return $this->lastInsertId();
    }

    /**
     * Returns the number of rows of the last executed statement.
     * @deprecated 3.1.0:4.0.0 Switched to native PDO method.
     *             Please use the PHP class <a href="https://secure.php.net/manual/en/class.pdostatement.php">PDOStatement</a>
     *             and the method <a href="https://secure.php.net/manual/en/pdostatement.rowcount.php">rowCount</a> instead.
     * @param object $pdoStatement An object of the class PDOStatement. This should be set if multiple
     *                             rows where selected and other sql statements are also send to the database.
     * @return int|null Return the number of rows of the result of the sql statement.
     * @see <a href="https://secure.php.net/manual/en/pdostatement.rowcount.php">PDOStatement::rowCount</a>
     */
    public function num_rows($pdoStatement = null)
    {
        // if pdo statement is committed then fetch this object
        if (is_object($pdoStatement))
        {
            return $pdoStatement->rowCount();
        }
        // if no pdo statement was committed then take the one from the last query
        elseif (is_object($this->pdoStatement))
        {
            return $this->pdoStatement->rowCount();
        }

        return null;
    }
}
