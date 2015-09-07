<?php

/**
 * Class DB
 */
class Database2
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
    protected $minRequiredVersion;  ///< The minimun required version of this database that is necessary to run Admidio.
    protected $databaseName;        ///< The name of the database e.g. 'MySQL'

    /** The constructor will check if a valid engine was set and try to connect to the database.
     *  If the engine is invalid or the connection not possible an exception will be thrown.
     * @param string $engine   The database type that is supported from Admidio. @b mysql and @b postgresql are valid values.
     * @param string $host     The hostname or server where the database is running. e.g. localhost or 127.0.0.1
     * @param int    $port     If you don't use the default port of the database then set your port here.
     * @param string $dbName   Name of the database you want to connect.
     * @param string $username Username to connect to database
     * @param string $password Password to connect to database
     * @param array  $options
     */
    public function __construct($engine, $host, $port = null, $dbName, $username = null, $password = null, $options = array())
    {
        try
        {
            if (!$engine || !$host || !$dbName)
            {
                throw new Exception('Engine, Host and DB-Name are required Parameters');
            }
        }
        catch (Exception $e)
        {
            print 'Error .. Details :<br /> {'.$e->getMessage().'}';
            exit();
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
        }
        catch (PDOException $e)
        {
            print 'PDO Driver Error .. Details :<br /> {'.$e->getMessage().'}';
            exit();
        }

        $this->buildDSNString();

        // needed to avoid leaking username, password,... if a PDOException is thrown
        try
        {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);

            $this->setConnectionOptions();
        }
        catch (PDOException $e)
        {
            print 'Error!: ' . $e->getMessage() . '<br/>';
            exit();
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->pdo = null;
    }

    /** Create a valid DSN string for the engine that was set through the constructor.
     *  If no valid engine is set than an exception is thrown.
     */
    private function buildDSNString()
    {
        try
        {
            switch ($this->engine)
            {
                case 'mysql':
                    if (!$this->port)
                    {
                        $this->port = 3307;
                    }

                    $this->dsn = 'mysql:host='.$this->host.';port='.$this->port.';dbname='.$this->dbName;
                    break;

                case 'pgsql':
                case 'postgresql':
                    if (!$this->port)
                    {
                        $this->port = 5432;
                    }

                    $this->dsn = 'pgsql:host='.$this->host.';port='.$this->port.';dbname='.$this->dbName;
                    break;

                default:
                    throw new Exception('Engine is not supported by Admidio');
            }
        }
        catch (Exception $e)
        {
            print "Error!: " . $e->getMessage() . "<br/>";
            exit();
        }
    }

    /** The method will commit an open transaction to the database. If the
     *  transaction counter is greater 1 than only the counter will be
     *  decreased and no commit will performed.
     */
    public function endTransaction()
    {
        // if there is no open transaction then do nothing and return
        if($this->transactions === 0)
        {
            return true;
        }

        // If there was a previously opened transaction we do not commit yet...
        // but count back the number of inner transactions
        if ($this->transactions > 1)
        {
            $this->transactions--;
            return true;
        }

        $result = $this->pdo->commit();

        if (!$result)
        {
            $this->showError();
        }

        $this->transactions = 0;
        return $result;
    }

    /** Fetch a result row as an associative array, a numeric array, or both.
     *  @param object $pdoStatement An object of the class PDOStatement. This should be set if multiple
     *                              rows where selected and other sql statements are also send to the database.
     *  @param int    $fetchType    Set the result type. Can contain @b PDO::FECTH_ASSOC for an associative array,
     *                              @b PDO::FETCH_NUM for a numeric array or @b PDO::FETCH_BOTH (Default).
     *  @return Returns an array that corresponds to the fetched row and moves the internal data pointer ahead.
     */
    public function fetch_array($pdoStatement = null, $fetchType = PDO::FETCH_BOTH)
    {
        // if pdo statement is committed then fetch this object
        if(is_object($pdoStatement))
        {
            return $pdoStatement->fetch($fetchType);
        }
        // if no pdo statement was committed then take the one from the last query
        elseif(is_object($this->pdoStatement))
        {
            return $this->pdoStatement->fetch($fetchType);
        }

        return null;
    }

    public function fetch_object()
    {
        if(is_object($this->pdoStatement))
        {
            return $this->pdoStatement->fetchObject();
        }

        return null;
    }

    /** Fetch from the sql the next row into an array. The array will contain each column.
     *  This row array contains as default the column name as key. If you set the $fetchType than you can change
     *  this behavior.
     *  @param object $pdoStatement An object of the class PDOStatement. This should be set if multiple
     *                              rows where selected and other sql statements are also send to the database.
     *  @param int    $fetchType    Set the result type. Can contain @b PDO::FECTH_ASSOC for an associative array,
     *                              @b PDO::FETCH_NUM for a numeric array or @b PDO::FETCH_BOTH (Default).
     *  @return Returns an array with each column of the current row. If no rows were found an empty array is returned.
     *
     *  @par Examples
     *  @code   $this->query('SELECT cat_id, cat_name, cat_sequence FROM adm_categories ');
     *  $records = $this->fetchAll();
     *
     *  // Array with the results:
     *     $record = array(
     *              [cat_id]       => 1
     *              [cat_name]     => 'Common'
     *              [cat_sequence] => 4
     *              )
     *          ... @endcode
     */
    public function fetch($pdoStatement = null, $fetchType = PDO::FETCH_ASSOC)
    {
        // if pdo statement is committed then fetch this object
        if(is_object($pdoStatement))
        {
            return $pdoStatement->fetch($fetchType);
        }
        // if no pdo statement was committed then take the one from the last query
        elseif(is_object($this->pdoStatement))
        {
            return $this->pdoStatement->fetch($fetchType);
        }

        return null;
    }

    /** Fetch from the sql all rows into an array. The array will contain each row which is also an array.
     *  This row array contains as default the column name as key. If you set the $fetchType than you can change
     *  this behavior.
     *  @param $fetchType Set the result type. Can contain @b PDO::FETCH_ASSOC (default) for an associative array,
     *                     @b PDO::FETCH_NUM for a numeric array or @b PDO::FETCH_BOTH (Default).
     *  @return Returns an array with arrays of each fetched row. If no rows were found an empty array is returned.
     *
     *  @par Examples
     *  @code   $this->query('SELECT cat_id, cat_name, cat_sequence FROM adm_categories ');
     *  $records = $this->fetchAll();
     *
     *  // Array with the results:
     *     $records = array(
     *          [0] => array(
     *              [cat_id]       => 1
     *              [cat_name]     => 'Common'
     *              [cat_sequence] => 4
     *              )
     *          [1] => array(
     *              [cat_id]       => 2
     *              [cat_name]     => 'Teams'
     *              [cat_sequence] => 2
     *              )
     *          ... @endcode
     */
    public function fetchAll($fetchType = PDO::FETCH_ASSOC)
    {
        return $this->pdoStatement->fetchAll($fetchType);
    }

    /**
     * @return array
     */
    public static function getAvailableDBs()
    {
        return PDO::getAvailableDrivers();
    }

    // Teile dieser Funktion sind von get_backtrace aus phpBB3
    // Return a nicely formatted backtrace (parts from the php manual by diz at ysagoon dot com)
    protected function getBacktrace()
    {
        $output = '<div style="font-family: monospace;">';
        $backtrace = debug_backtrace();
        $path = SERVER_PATH;

        foreach ($backtrace as $number => $trace)
        {
            // We skip the first one, because it only shows this file/function
            if ($number == 0)
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
            $trace['type'] = (!isset($trace['type'])) ? '' : $trace['type'];

            $output .= '<br />';
            $output .= '<b>FILE:</b> ' . htmlentities($trace['file']) . '<br />';
            $output .= '<b>LINE:</b> ' . ((!empty($trace['line'])) ? $trace['line'] : '') . '<br />';

            $output .= '<b>CALL:</b> ' . htmlentities($trace['class'] . $trace['type'] . $trace['function']) . '(' . ((count($args)) ? implode(', ', $args) : '') . ')<br />';
        }
        $output .= '</div>';
        return $output;
    }

    /** Get the minimum required version of the database that is necessary to run Admidio.
     *  @return Returns a string with the minimum required database version e.g. '5.0.1'
     */
    public function getMinimumRequiredVersion()
    {
        if($this->minRequiredVersion === '')
        {
            $xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/databases.xml', 0, true);
            $node = $xmlDatabases->xpath("/databases/database[@id='".$this->engine."']/minversion");
            $this->minRequiredVersion = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
        }
        return $this->minRequiredVersion;
    }

    /** Get the name of the database that is running Admidio.
     *  @return Returns a string with the name of the databse e.g. 'MySQL' or 'PostgreSQL'
     */
    public function getName()
    {
        if($this->databaseName === '')
        {
            $xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/databases.xml', 0, true);
            $node = $xmlDatabases->xpath("/databases/database[@id='".$this->engine."']/name");
            $this->databaseName = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
        }
        return $this->databaseName;
    }

    /** Get the version of the connected database.
     *  @return Returns a string with the database version e.g. '5.5.8'
     */
    public function getVersion()
    {
        $this->query('SELECT version()');
        $row = $this->fetch_array();

        return $row[0];
    }

    // Liefert die ID einer vorherigen INSERT-Operation
    public function insert_id()
    {
        return $this->pdo->lastInsertId();
    }

    /** Returns the number of rows of the last executed statement.
     *  Therefore a valid result must exists or set as parameter.
     *  If no valid result exists the method will return 0.
     *  @param $result Optional a valid result of a executed sql statement. If no result is set
     *                 then the method will look for a result within the database object.
     *  @return Return the number of rows of the result of the sql statement.
     */
    public function num_rows()
    {
        $rowCount = 0;

        if(is_object($this->pdoStatement))
        {
            $rowCount = $this->pdoStatement->rowCount();
        }

        return $rowCount;
    }

    /** Send a sql statement to the database that will be executed. If debug mode is set
     *  then this statement will be written to the error log. If it's a @b SELECT statement
     *  then also the number of rows will be logged. If an error occurred the script will
     *  be terminated and the error with a backtrace will be send to the browser.
     *  @param string $sql A string with the sql statement that should be executed in database.
     *  @param bool $throwError Default will be @b true and if an error the script will be terminated and
     *                          occurred the error with a backtrace will be send to the browser. If set to
     *                          @b false no error will be shown and the script will be continued.
     *  @return For @b SELECT statements a result resource will be returned and otherwise @b true.
     *          If an error occurred then @b false will be returned of the script was not terminated.
     */
    public function query($sql, $throwError = true)
    {
        global $gDebug;

        // if debug mode then log all sql statements
        if($gDebug === 1)
        {
            error_log($sql);
        }

        $this->fetchArray   = array();
        $this->pdoStatement = $this->pdo->query($sql);

        // if we got an db error then show this error
        if($this->pdo->errorCode() !== null && $this->pdo->errorCode() !== '00000')
        {
            if($throwError)
            {
                return $this->showError();
            }
        }
        elseif($gDebug === 1 && strpos(strtoupper($sql), 'SELECT') === 0)
        {
            // if debug modus then show number of selected rows
            error_log('Found rows: '.$this->num_rows());
        }

        return $this->pdoStatement;
    }

    /** Escapes special characters within the input string. 
     *  The returned string has no quotes around the input string!
     *  @param string $string The string to be quoted.
     *  @return Returns a quoted string that is theoretically safe to pass into an SQL statement.
     */
    public function quote($string)
    {
        return trim($this->pdo->quote($string),"'");
    }

    /** If there is a open transaction than this method sends a rollback to the database
     *  and will set the transaction counter to zero.
     */
    public function rollback()
    {
        if($this->transactions > 0)
        {
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

    /** Set connection specific options like UTF8 connection. These options
     *  should always be set if Admidio connect to a database.
     */
    private function setConnectionOptions()
    {
        // Connect to database with UTF8
        $this->query('SET NAMES \'UTF8\'');

        if($this->engine === 'mysql')
        {
            // ANSI Modus setzen, damit SQL kompatibler zu anderen DBs werden kann
            $this->query('SET SQL_MODE = \'ANSI\'');
            // falls der Server die Joins begrenzt hat, kann dies mit diesem Statement aufgehoben werden
            $this->query('SET SQL_BIG_SELECTS = 1');
        }
    }

    /** Methods reads all columns and their properties from the database table.
     *  @param string  $table                Name of the database table for which the columns should be shown.
     *  @param boolean $showColumnProperties If this is set to @b false only the column names were returned.
     *  @return Returns an array with each column and their properties if $showColumnProperties is set to @b true.
     *          The array has the following format:
     *          array (
     *          'column1' => array (
     *                       'serial' => '1',
     *                       'null'   => '0',
     *                       'key'    => '0',
     *                       'type'   => 'integer')
     *          'column2' => array (...)
     *          ...
     */
    public function showColumns($table, $showColumnProperties = true)
    {
        if(!isset($this->dbStructure[$table]))
        {
            $columnProperties = array();

            $sql = 'SHOW COLUMNS FROM '.$table;
            $this->query($sql);

            while ($row = $this->fetch_array())
            {
                $columnProperties[$row['Field']]['serial'] = 0;
                $columnProperties[$row['Field']]['null']   = 0;
                $columnProperties[$row['Field']]['key']    = 0;

                if($row['Extra'] === 'auto_increment')
                {
                    $columnProperties[$row['Field']]['serial'] = 1;
                }
                if($row['Null'] === 'YES')
                {
                    $columnProperties[$row['Field']]['null'] = 1;
                }
                if($row['Key'] === 'PRI' || $row['Key'] === 'MUL')
                {
                    $columnProperties[$row['Field']]['key'] = 1;
                }

                if(strpos($row['Type'], 'tinyint(1)') !== false)
                {
                    $columnProperties[$row['Field']]['type'] = 'boolean';
                }
                elseif(strpos($row['Type'], 'smallint') !== false)
                {
                    $columnProperties[$row['Field']]['type'] = 'smallint';
                }
                elseif(strpos($row['Type'], 'int') !== false)
                {
                    $columnProperties[$row['Field']]['type'] = 'integer';
                }
                else
                {
                    $columnProperties[$row['Field']]['type'] = $row['Type'];
                }
            }

            // safe array with table structure in class array
            $this->dbStructure[$table] = $columnProperties;
        }

        if($showColumnProperties)
        {
            // returns all columns with their properties of the table
            return $this->dbStructure[$table];
        }
        else
        {
            // returns only the column names of the table.
            $tableColumns = array();

            foreach($this->dbStructure[$table] as $columnName => $columnProperties)
            {
                $tableColumns[] = $columnName;
            }

            return $tableColumns;
        }
    }

    /** Display the error code and error message to the user if a database error occurred.
     *  The error must be read by the child method. This method will call a backtrace so
     *  you see the script and specific line in which the error occurred.
     *  @param $code    The database error code that will be displayed.
     *  @param $message The database error message that will be displayed.
     *  @return Will exit the script and returns a html output with the error informations.
     */
    public function showError($code = 0, $message = '')
    {
        global $g_root_path, $gMessage, $gPreferences, $gCurrentOrganization, $gDebug, $gL10n;

        $htmlOutput = '';
        $backtrace  = $this->getBacktrace();

        // Rollback on open transaction
        if($this->transactions > 0)
        {
            $this->pdo->rollBack();
        }

        if(!headers_sent() && isset($gPreferences) && defined('THEME_SERVER_PATH'))
        {
            // create html page object
            $page = new HtmlPage($gL10n->get('SYS_DATABASE_ERROR'));
        }

        // transform the database error to html
        $errorInfo   = $this->pdo->errorInfo();

        $errorString = '<div style="font-family: monospace;">
                         <p><b>S Q L - E R R O R</b></p>
                         <p><b>CODE:</b> '.$this->pdo->errorCode().'</p>
                         '.$errorInfo[1].'<br /><br />
                         '.$errorInfo[2].'<br /><br />
                         <b>B A C K T R A C E</b><br />
                         '.$backtrace.'
                         </div>';
        $htmlOutput = $errorString;

        // in debug mode show error in log file
        if($gDebug === 1)
        {
             error_log($this->pdo->errorCode(). ': '. $errorInfo[1]."\n".$errorInfo[2]);
        }

        // display database error to user
        if(!headers_sent() && isset($gPreferences) && defined('THEME_SERVER_PATH'))
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

    /** Checks if an open transaction exists. If there is no open transaction than
     *  start one otherwise increase the internal transaction counter.
     */
    public function startTransaction()
    {
        // If we are within a transaction we will not open another one,
        // but enclose the current one to not loose data (prevening auto commit)
        if ($this->transactions > 0)
        {
            $this->transactions++;
            return true;
        }

        $result = $this->pdo->beginTransaction();

        if (!$result)
        {
            $this->showError();
        }

        $this->transactions = 1;
        return $result;
    }
}

?>