<?php
/*****************************************************************************/
/** @class Component
 *  @brief Handle different components of Admidio (e.g. system, plugins or modules) and manage them in the database
 *
 *  The class search in the database table @b adm_components for a specific component
 *  and loads the data into this object. A component could be per default the @b SYSTEM
 *  itself, a module or a plugin. There are methods to check the version of the system.
 *  @par Examples
 *  @code // check if database and filesystem have same version
 *  try
 *  {
 *      $systemComponent = new Component($gDb);
 *      $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 *      $systemComponent->checkDatabaseVersion(true, 'webmaster@example.com');
 *  }
 *  catch(AdmException $e)
 *  {
        $e->showHtml();
 *  }@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Component extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_component. 
	 *  If the id is set than the specific component will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $com_id The recordset of the component with this id will be loaded. If com_id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $com_id = 0)
    {
        parent::__construct($db, TBL_COMPONENTS, 'com', $com_id);
    }

    /** Check version of component in database against the version of the file system.
     *  There will be different messages shown if versions aren't equal. If user has a current
     *  login and is webmaster than there will be links to the next step to do.
     *  @param $webmaster          Flag if the current user is a webmaster. This should be 0 or 1
     *  @param $emailAdministrator The email address of the administrator.
     *  @return Nothing will be returned. If the versions aren't equal a message will be shown.
     */
    public function checkDatabaseVersion($webmaster, $emailAdministrator)
    {
        global $g_root_path;

        if(version_compare($this->getValue('com_version'), ADMIDIO_VERSION) != 0 || version_compare($this->getValue('com_beta'), BETA_VERSION) != 0)
        {
            $arrDbVersion         = explode('.', $this->getValue('com_version').'.'.$this->getValue('com_beta'));
            $arrFileSystemVersion = explode('.', ADMIDIO_VERSION.'.'.BETA_VERSION);
            
            if($webmaster == true)
            {
                // if webmaster and db version is less than file system version then show notice
                if($arrDbVersion[0] < $arrFileSystemVersion[0]
                || $arrDbVersion[1] < $arrFileSystemVersion[1]
                || $arrDbVersion[2] < $arrFileSystemVersion[2]
                || $arrDbVersion[3] < $arrFileSystemVersion[3])
                {
                    throw new AdmException('SYS_WEBMASTER_DATABASE_INVALID', $this->getValue('com_version'), ADMIDIO_VERSION, '<a href="'.$g_root_path.'/adm_program/installation/update.php">', '</a>');
                }
                // if webmaster and file system version is less than db version then show notice
                elseif($arrDbVersion[0] > $arrFileSystemVersion[0]
                    || $arrDbVersion[1] > $arrFileSystemVersion[1]
                    || $arrDbVersion[2] > $arrFileSystemVersion[2]
                    || $arrDbVersion[3] > $arrFileSystemVersion[3])
                {
                    throw new AdmException('SYS_WEBMASTER_FILESYSTEM_INVALID', $this->getValue('com_version'), ADMIDIO_VERSION, '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
                }
            }
            else
            {
                // if main version and subversion not equal then show notice
                if($arrDbVersion[0] != $arrFileSystemVersion[0]
                || $arrDbVersion[1] != $arrFileSystemVersion[1])
                {
                    throw new AdmException('SYS_DATABASE_INVALID', $this->getValue('com_version'), ADMIDIO_VERSION, '<a href="mailto:'.$emailAdministrator.'">', '</a>');
                }
                // if main version and subversion are equal 
                // but subsub db version is less then subsub file version show notice
                elseif($arrDbVersion[0] == $arrFileSystemVersion[0]
                &&     $arrDbVersion[1] == $arrFileSystemVersion[1]
                &&     $arrDbVersion[2]  < $arrFileSystemVersion[2])
                {
                    throw new AdmException('SYS_DATABASE_INVALID', $this->getValue('com_version'), ADMIDIO_VERSION, '<a href="mailto:'.$emailAdministrator.'">', '</a>');
                }
            }
        }
    }
}
?>