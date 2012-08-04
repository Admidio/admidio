<?php
/******************************************************************************
 * Show participients of dates
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Author       : Thomas-RCV
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

/// This class counts participients, checks the limit for signin
/// or returns an array with participients of date.

/**
 *  This function is designed to read the participients of dates from database.
 *  Participients can be counted or be written in an array.
 *  Also the limit of participation can be got from database.
 *  @param $rolId The ID of role participients.
 *  @param $count Number of patricipients found to a date.
 *  @param $order Ordering participients when written in array Default: 'ASC'
 *  @param $limit The limit for assignment of a date.
 *  @param $memberDate The Array contains surname and firstname of all participients
 */
class Participients
{   
    private $rolId;
    private $count;
    private $order;
    private $limit;
    public  $memberDate = array();
    
    /**
     *  Initialize all parameters 
     */
    public function __construct()
    {
        $this->rolId      = '';
        $this->count      = '';
        $this->order      = '';
        $this->limit      = '';
        $this->memberDate = '';
    }
    
    public function __destruct ()
    {
        unset ($this);
    }
    
    /**
     *  Count participients of date.
     *  Check if $rolId is set an is numeric else return 'FALSE'.
     *  @param $rolId Role ID to be counted.
     *  @return $count The result of counting.
     */
    public function getCount($rolId)
    {
        if(!isset($rolId) || !is_numeric($rolId))
        {
            return FALSE;
        }
        else
        {
            $this->rolId = $rolId;
            $this->setCount($this->rolId);
            return $this->count;
        }
    }
    
    /**
     *  Count participients according to rolId in database
     *  @return TRUE 
     */
    private function setCount()
    {
        global $gDb;

        $sql = 'SELECT DISTINCT mem_usr_id
                FROM '.TBL_MEMBERS.'
                WHERE mem_rol_id = '.$this->rolId.'
                AND mem_begin <= \''.DATE_NOW.'\'
                AND mem_end    > \''.DATE_NOW.'\'';
                
                $result = $gDb->query($sql);
                $row_count = $gDb->num_rows($result);
                
        $this->count = $row_count;
        return True;
    }

    /**
     *  Get the limit of participients.
     *  Check if $rolId is set an is numeric else return FALSE.
     *  @param $rolId Role ID to be checked.
     *  @return Limit of participients.
     */
    public function getLimit($rolId)
    {
        if(!isset($rolId) || !is_numeric($rolId))
        {
            return FALSE;
        }
        else
        {
            $this->rolId = $rolId;
            $this->setLimit($this->rolId);
            return $this->limit;
        }
    }
    
    /**
     *  Check limit of participients
     *  @return TRUE
     */
    private function setLimit()
    {
        global $gDb;

        $sql = 'SELECT DISTINCT mem_usr_id FROM '.TBL_MEMBERS.'
                WHERE mem_rol_id = '.$this->rolId.'
                AND mem_leader = 0';
                
                $result = $gDb->query($sql);
                $row_count = $gDb->num_rows($result);

        $this->limit = $row_count;
        return True;
    }
    
    /**
     *  Return all participients with surname,firstname as array
     *  @param $rolId The Id of role participients.
     *  @param $order Values ASC/DESC Default: 'ASC'
     *  @return $memberDate The result of all participients as array 
     */
    public function getParticipientsArray($rolId, $order = 'ASC')
    {
        if(!isset($rolId) || !is_numeric($rolId) ||
           !in_array($order, array('ASC', 'DESC')))
        {
            return FALSE;
        }
        else
        {
            $this->rolId = $rolId;
            $this->order = $order;
            $this->readData($this->rolId, $this->order);
            return $this->memberDate;
        }
    }
    
    /**
     *  Read the participients from database an write them to array
     */
     private function readData ()
     {
        global $gDb;
        global $gProfileFields;
        
        $sql = 'SELECT DISTINCT
                    surname.usd_value as surname, firstname.usd_value as firstname
                FROM '.TBL_MEMBERS.'
                    LEFT JOIN '. TBL_USER_DATA .' surname
                        ON surname.usd_usr_id = mem_usr_id
                        AND surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                    LEFT JOIN '. TBL_USER_DATA .' firstname
                        ON firstname.usd_usr_id = mem_usr_id
                        AND firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                WHERE mem_rol_id = '.$this->rolId.'
                ORDER BY surname '.$this->order.' ';

        $result = $gDb->query($sql);
                
        while ($row = $gDb->fetch_array($result))
        {
            $participients[] = $row['surname'].', '.$row['firstname'];
        }
                
        $this->memberDate = $participients;
        return TRUE;
     }       
}      
?>