<?php
/******************************************************************************
 * Factory class that creates the relevant forum object
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Forum
{
    public static function includeForumScript($db)
    {
        global $g_organization;
        $forumEnable  = 0;
        $forumVersion = 0;
        
        $sql    = 'SELECT prf_name, prf_value 
                     FROM '. TBL_PREFERENCES. ', '. TBL_ORGANIZATIONS. '
                    WHERE org_shortname = \''.$g_organization.'\'
                      AND prf_org_id = org_id 
                      AND prf_name IN (\'forumVersion\',\'enable_forum_interface\')';
        $result = $db->query($sql);
        
        while($row = $db->fetch_array($result))
        {
            if($row['prf_name'] == 'forumVersion')
            {
                $forumVersion = $row['prf_value'];
            }
            else
            {
                $forumEnable = $row['prf_value'];
            }
        }
        
        if($forumEnable)
        {
            switch ($forumVersion)
            {
                case 'phpBB2':
                    require_once(SERVER_PATH. '/adm_program/system/forum/phpbb2.php');
                    
                default:
                    return false;
            }    
        }
    }

	// method creates the interface to the relevant forum
    public static function createForumObject($forumType)
    {
        switch ($forumType)
        {
            case "phpBB2":
                require_once(SERVER_PATH. "/adm_program/system/forum/phpbb2.php");
                return new PhpBB2;
                
            default:
                return false;
        }
    }
}

?>