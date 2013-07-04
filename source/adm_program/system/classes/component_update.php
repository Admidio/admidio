<?php
/*****************************************************************************/
/** @class ComponentUpdate
 *  @brief Manage the update of a component from the actual version to the target version
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class ComponentUpdate
{
	private $updateFinished;    			///< Flag that will store if the update prozess of this version was successfully finished
	private $xmlObject;			            ///< The SimpleXML object with all the update steps
    private $currentVersionArray;           ///< This is the version the component has actually before update. Each array element contains one part of the version.
    private $targetVersionArray;            ///< This is the version that is stored in the files of the component. Each array element contains one part of the version.
    private $component;                     ///< Database object of the component
    private $db;					        ///< Database object to handle the communication with the database.

	/** Constuctor that will create an object of ComponentUpdate and save a database handle. 
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 */
    public function __construct(&$db)
    {
        $this->db =& $db;
    }
    
    /** Will open a XML file of a specific version that contains all the update steps that
     *  must be passed to successfully update Admidio to this version
     *  @param $version Contains a string with the main version number e.g. 2.4 or 3.0 .
     *                  The version should NOT contain the minor version numbers e.g. 2.4.2
     */
    private function createXmlObject($mainVersion, $subVersion)
    {
        // update of Admidio core has another path for the xml files as plugins
        if($this->component->getValue('com_type') == 'SYSTEM')
        {
            $updateFile = SERVER_PATH. '/adm_install/db_scripts/update_'.$mainVersion.'_'.$subVersion.'.xml';
            
            if(file_exists($updateFile))
            {
                $this->xmlObject = new SimpleXMLElement($updateFile, 0, true);
                return true;
            }
        }
        return false;
    }
    
     
    /** Will execute the specific update step that is set through the parameter $xmlNode.
     *  If the step was successfully done the id will be stored in the component recordset
     *  so if the whole update crashs later we know that this step was successfully executed.
     *  @param $xmlNode A SimpleXML node of the current update step.
     */
    private function executeStep($xmlNode)
    {
        global $g_tbl_praefix;
        
        if(strlen(trim($xmlNode[0])) > 0)
        {
            // replace prefix with installation specific table prefix
            $sql = str_replace('%PREFIX%', $g_tbl_praefix, $xmlNode[0]);
            
            $this->db->query($sql);

            // set the type if the id to integer because otherwise the system thinks it's not numeric !!!
            $stepId = $xmlNode['id'];
            settype($stepId, 'integer');
            
            // save the successful executed update step in database
            $this->component->setValue('com_update_step', $stepId);
            $this->component->save();
        }
    }
    
    /** Set the component for which the update should be done. The method 
     *  will then read the recordset of this component from the database.
     *  @param $type Component type e.g. @b SYSTEM or @b PLUGIN
     *  @param $nameIntern Internal unique name of the component e.g. @b CORE
     */
    public function setComponent($type, $nameIntern)
    {
        $this->component = new TableAccess($this->db, TBL_COMPONENTS, 'com');
        $this->component->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
        $this->currentVersion    = explode('.', $this->component->getValue('com_version'));
    }

    /** Set the target version for the component after update. This information should be
     *  read from the files of the component.
     *  @param $version Target version of the component after update
     */
    public function setTargetVersion($version)
    {
        $this->targetVersion = explode('.', $version);
    }
    
    
    /** Do a loop through all versions start with the current version and end with the target version.
     *  Within every subversion the method will search for an update xml file and execute all steps 
     *  in this file until the end of file is reached. If an error occured then the update will be stopped.
     *  @return Return @b true if the update was successfull.
     */
    public function update()
    {
        global $gDebug;
    
        $this->updateFinished = false;
        $initialSubVersion    = $this->currentVersion[1];
    
        for($mainVersion = $this->currentVersion[0]; $mainVersion <= $this->targetVersion[0]; $mainVersion++)
        {
            // Set max subversion for iteration. If we are in the loop of the target main version 
            // then set target subversion to the max version
            if($mainVersion == $this->targetVersion[0])
            {
                $maxSubVersion = $this->targetVersion[1];
            }
            else
            {
                $maxSubVersion = 20;
            }
        
            for($subVersion = $initialSubVersion; $subVersion <= $maxSubVersion; $subVersion++)
            {
                // if version is not equal to current version then start update step with 0
                if($mainVersion != $this->currentVersion[0]
                || $subVersion  != $this->currentVersion[1])
                {
                    $this->component->setValue('com_update_step', 0);
                    $this->component->save();
                }
                
                // output of the version number for better debugging
                if($gDebug)
                {
                    error_log('Update to version '.$mainVersion.'.'.$subVersion);
                }
                
                // open xml file for this version
                if($this->createXmlObject($mainVersion, $subVersion))
                {
                    // go step by step through the SQL statements and execute them
                    foreach($this->xmlObject->children() as $updateStep)
                    {
                        if($updateStep['id'] > $this->component->getValue('com_update_step'))
                        {
                            $this->executeStep($updateStep);
                        }
                        elseif($updateStep[0] == 'stop')
                        {
                            $this->updateFinished = true;
                        }
                    }
                }
                
                // save current version to component
                $this->component->setValue('com_version', $mainVersion.'.'.$subVersion.'.0');
                $this->component->save();
            }
            
            // reset subversion because we want to start update for next main version with subversion 0
            $initialSubVersion = 0;
        }
    }
}
?>