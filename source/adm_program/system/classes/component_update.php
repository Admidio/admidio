<?php
/*****************************************************************************/
/** @class ComponentUpdate
 *  @brief Manage the update of a component from the actual version to the target version
 *
 *  The class will read a language specific text that is identified with their 
 *  text id out of an language xml file. The access will be manages with the
 *  SimpleXMLElement which search through xml files. An object of this class
 *  can't be stored in a PHP session because it creates PHP core objects which
 *  couldn't be stored in sessions. Therefore an object of @b LanguageData 
 *  should be assigned to this class that stored all neccessary data and can be
 *  stored in a session.
 *  @par Examples
 *  @code // show how to use this class with the language data class and sessions
 *  script_a.php
 *  // create a language data object and assign it to the language object
 *  $language = new Language();
 *  $languageData = new LanguageData('de');
 *  $language->addLanguageData($languageData);
 *  $session->addObject('languageData', $languageData);
 *  
 *  script_b.php
 *  // read language data from session and add it to language object
 *  $language = new Language();
 *  $language->addLanguageData($session->getObject('languageData'));@endcode
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
    private $currentUpdateStep;             ///< Integer value that represents the current update step which was successfully done

    /** Will open a XML file of a specific version that contains all the update steps that
     *  must be passed to successfully update Admidio to this version
     *  @param $version Contains a string with the main version number e.g. 2.4 or 3.0 .
     *                  The version should NOT contain the minor version numbers e.g. 2.4.2
     */
    private function createXmlObject($mainVersion, $subVersion)
    {
        if($this->component->getValue('com_type') == 'CORE')
        {
            $updateFile = SERVER_PATH. '/adm_install/db_scripts/update_'.$mainVersion.'_'.$subVersion'.xml';
            
            if(file_exists($updateFile))
            {
                $this->xmlObject = new SimpleXMLElement($updateFile, 0, true);
                return true;
            }
        }
        return false
    }
    
    public function update()
    {
        $this->updateFinished = false;
        $initialSubVersion    = $this->currentVersion[1];
    
        for($mainVersion = $this->currentVersion[0]; $mainVersion <= $this->targetVersion[0]; $mainVersion++)
        {
            for($subVersion = $initialSubVersion; $subVersion <= 20; $subVersion++)
            {
                if($this->createXmlObject($mainVersion, $subVersion))
                {
                    $test = 1;
                }
            }
            
            // reset subversion because we want to start update for next main version with subversion 0
            $initialSubVersion = 0;
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
        $this->currentUpdateStep = $this->component->getValue('com_update_step');
    }

    /** Set the target version for the component after update. This information should be
     *  read from the files of the component.
     *  @param $version Target version of the component after update
     */
    public function setTargetVersion($version)
    {
        $this->targetVersion = explode('.', $version);
    }
}
?>