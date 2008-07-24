<?php
/******************************************************************************
 * Factory-Klasse welches das relevante Forumobjekt erstellt
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Forum
{
    function includeForumScript($db)
    {
        global $g_organization;
        $forum_enable  = 0;
        $forum_version = 0;
        
        $sql    = "SELECT prf_name, prf_value 
                     FROM ". TBL_PREFERENCES. ", ". TBL_ORGANIZATIONS. "
                    WHERE org_shortname = '". $g_organization. "'
                      AND prf_org_id = org_id 
                      AND prf_name IN ('forum_version','enable_forum_interface')";
        $result = $db->query($sql);
        
        while($row = $db->fetch_array($result))
        {
            if($row['prf_name'] == 'forum_version')
            {
                $forum_version = $row['prf_value'];
            }
            else
            {
                $forum_enable = $row['prf_value'];
            }
        }
        
        if($forum_enable)
        {
            switch ($forum_version)
            {
                case "phpBB2":
                    require_once(SERVER_PATH. "/adm_program/system/forum/phpbb2.php");
                    
                default:
                    return false;
            }    
        }
    }


    // Funktion erstellt die Schnittstelle zum entsprechenden Forum

    function createForumObject($forum_type)
    {
        switch ($forum_type)
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