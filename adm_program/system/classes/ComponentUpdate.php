<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Manage the update of a component from the actual version to the target version
 *
 * The class is an extension to the component class and will handle the update of a
 * component. It will read the database version from the component and set this as
 * source version. Then you should set the target version. The class will then search
 * search for specific update xml files in special directories. For the system this should be
 * **adm_program/installation/db_scripts** and for plugins there should be an install folder within the
 * plugin directory. The xml files should have the prefix update and than the main und subversion
 * within their filename e.g. **update_3_0.xml**.
 *
 * **Code example:**
 * ```
 * // update the system module to the actual filesystem version
 * $componentUpdateHandle = new ComponentUpdate($gDb);
 * $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 * $componentUpdateHandle->update(ADMIDIO_VERSION);
 * ```
 */
class ComponentUpdate extends Component
{
    const UPDATE_STEP_STOP = 'stop';

    /**
     * Constructor that will create an object for component updating.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(Database $database)
    {
        parent::__construct($database);

        ComponentUpdateSteps::setDatabase($database);
    }

    /**
     * Gets the version parts of a version string
     * @param string $versionString A version string
     * @return array<int,int> Returns an array with the version parts
     */
    private static function getVersionArrayFromVersion($versionString)
    {
        return array_map('intval', explode('.', $versionString));
    }

    /**
     * Will open a XML file of a specific version that contains all the update steps that
     * must be passed to successfully update Admidio to this version
     * @param int $mainVersion  Contains a string with the main version number e.g. 2 or 3 from 2.x or 3.x.
     * @param int $minorVersion Contains a string with the main version number e.g. 1 or 2 from x.1 or x.2.
     * @throws \UnexpectedValueException
     * @return \SimpleXMLElement
     */
    private function getXmlObject($mainVersion, $minorVersion)
    {
        global $gLogger;

        // update of Admidio core has another path for the xml files as plugins
        if ($this->getValue('com_type') === 'SYSTEM')
        {
            $updateFile = ADMIDIO_PATH.'/adm_program/installation/db_scripts/update_'.$mainVersion.'_'.$minorVersion.'.xml';

            if (is_file($updateFile))
            {
                return new \SimpleXMLElement($updateFile, 0, true);
            }

            $message = 'XML-Update file not found!';
            $gLogger->warning($message, array('filePath' => $updateFile));

            throw new \UnexpectedValueException($message);
        }

        throw new \UnexpectedValueException('No System update!');
    }

    /**
     * Goes step by step through the update xml file of the current database version and search for the maximum step.
     * If the last step is found than the id of this step will be returned.
     * @return int Return the number of the last update step that was found in xml file of the current version.
     */
    public function getMaxUpdateStep()
    {
        $maxUpdateStep = 0;
        $currentVersionArray = self::getVersionArrayFromVersion($this->getValue('com_version'));

        try
        {
            // open xml file for this version
            $xmlObject = $this->getXmlObject($currentVersionArray[0], $currentVersionArray[1]);
        }
        catch (\UnexpectedValueException $exception)
        {
            return 0;
        }

        // go step by step through the SQL statements until the last one is found
        foreach ($xmlObject->children() as $updateStep)
        {
            if ((string) $updateStep === self::UPDATE_STEP_STOP)
            {
                break;
            }

            $maxUpdateStep = (int) $updateStep['id'];
        }

        return $maxUpdateStep;
    }

    /**
     * Get method name and execute this method
     * @param string $updateStepContent
     */
    private static function executeUpdateMethod($updateStepContent)
    {
        // get the method name (remove "ComponentUpdateSteps::")
        $methodName = substr($updateStepContent, 22);
        // now call the method
        ComponentUpdateSteps::{$methodName}();
    }

    /**
     * Prepares and execute a sql statement
     * @param string $sql
     * @param bool   $showError
     */
    private function executeUpdateSql($sql, $showError)
    {
        $this->db->queryPrepared(Database::prepareSqlTablePrefix($sql), array(), $showError);
    }

    /**
     * Will execute the specific update step that is set through the parameter $xmlNode.
     * If the step was successfully done the id will be stored in the component recordset
     * so if the whole update crashs later we know that this step was successfully executed.
     * When the node has an attribute **database** than this sql statement will only executed
     * if the value of the attribute is equal to your current **DB_ENGINE**. If the node has
     * an attribute **error** and this is set to **ignore** than an sql error will not stop
     * the update script.
     * @param \SimpleXMLElement $xmlNode A SimpleXML node of the current update step.
     */
    private function executeStep(\SimpleXMLElement $xmlNode)
    {
        global $gLogger;

        $updateStepContent = trim((string) $xmlNode);

        $startTime = microtime(true);

        // if a method of this class was set in the update step
        // then call this function and don't execute a SQL statement
        if (StringUtils::strStartsWith($updateStepContent, 'ComponentUpdateSteps::'))
        {
            $gLogger->info('UPDATE: Execute update step Nr: ' . (int) $xmlNode['id']);

            self::executeUpdateMethod($updateStepContent);

            $gLogger->debug('UPDATE: Execution time ' . getExecutionTime($startTime));
        }
        // only execute if sql statement is for all databases or for the used database
        elseif (!isset($xmlNode['database']) || (string) $xmlNode['database'] === DB_ENGINE)
        {
            $showError = true;
            // if the attribute error was set to "ignore" then don't show errors that occurs on sql execution
            if (isset($xmlNode['error']) && (string) $xmlNode['error'] === 'ignore')
            {
                $showError = false;
            }

            $gLogger->info('UPDATE: Execute update step Nr: ' . (int) $xmlNode['id']);

            $this->executeUpdateSql($updateStepContent, $showError);

            $gLogger->debug('UPDATE: Execution time ' . getExecutionTime($startTime));
        }
        elseif ((string) $xmlNode['database'] !== DB_ENGINE)
        {
            $gLogger->info(
                'UPDATE: Update step is for another database!',
                array('database' => (string) $xmlNode['database'], 'step' => (int) $xmlNode['id'])
            );
        }
        else
        {
            $gLogger->warning(
                'UPDATE: Unexpected update step!',
                array('content' => (string) $xmlNode, 'attributes' => (array) $xmlNode->attributes())
            );
        }

        // save the successful executed update step in database
        $this->setValue('com_update_step', (int) $xmlNode['id']);
        $this->save();
    }

    /**
     * Do a loop through all versions start with the current version and end with the target version.
     * Within every subversion the method will search for an update xml file and execute all steps
     * in this file until the end of file is reached. If an error occurred then the update will be stopped.
     * @param string $targetVersion The target version to update.
     */
    public function update($targetVersion)
    {
        global $gLogger;

        $currentVersionArray = self::getVersionArrayFromVersion($this->getValue('com_version'));
        $targetVersionArray  = self::getVersionArrayFromVersion($targetVersion);
        $initialMinorVersion = $currentVersionArray[1];

        for ($mainVersion = $currentVersionArray[0]; $mainVersion <= $targetVersionArray[0]; ++$mainVersion)
        {
            // Set max subversion for iteration. If we are in the loop of the target main version
            // then set target minor-version to the max version
            $maxMinorVersion = 20;
            if ($mainVersion === $targetVersionArray[0])
            {
                $maxMinorVersion = $targetVersionArray[1];
            }

            for ($minorVersion = $initialMinorVersion; $minorVersion <= $maxMinorVersion; ++$minorVersion)
            {
                // if version is not equal to current version then start update step with 0
                if ($mainVersion !== $currentVersionArray[0] || $minorVersion !== $currentVersionArray[1])
                {
                    $this->setValue('com_update_step', 0);
                    $this->save();
                }

                // output of the version number for better debugging
                $gLogger->notice('UPDATE: Start executing update steps to version '.$mainVersion.'.'.$minorVersion);

                // open xml file for this version
                try
                {
                    $xmlObject = $this->getXmlObject($mainVersion, $minorVersion);

                    // go step by step through the SQL statements and execute them
                    foreach ($xmlObject->children() as $updateStep)
                    {
                        if ((string) $updateStep === self::UPDATE_STEP_STOP)
                        {
                            break;
                        }
                        if ((int) $updateStep['id'] > (int) $this->getValue('com_update_step'))
                        {
                            $this->executeStep($updateStep);
                        }
                        else
                        {
                            $gLogger->info('UPDATE: Skip update step Nr: ' . (int) $updateStep['id']);
                        }
                    }
                }
                catch (\UnexpectedValueException $exception)
                {
                    // TODO
                }

                $gLogger->notice('UPDATE: Finish executing update steps to version '.$mainVersion.'.'.$minorVersion);

                // save current version to system component
                $this->setValue('com_version', ADMIDIO_VERSION);
                $this->setValue('com_beta', ADMIDIO_VERSION_BETA);
                $this->save();

                // save current version to all modules
                $sql = 'UPDATE '.TBL_COMPONENTS.'
                           SET com_version = ? -- ADMIDIO_VERSION
                             , com_beta    = ? -- ADMIDIO_VERSION_BETA
                         WHERE com_type = \'MODULE\'';
                $this->db->queryPrepared($sql, array(ADMIDIO_VERSION, ADMIDIO_VERSION_BETA));
            }

            // reset subversion because we want to start update for next main version with subversion 0
            $initialMinorVersion = 0;
        }
    }
}
