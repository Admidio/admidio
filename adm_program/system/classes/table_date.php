<?php
/******************************************************************************
 * Class manages access to database table adm_dates
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getIcal($domain)  - gibt String mit dem Termin im iCal-Format zurueck
 * editRight()       - prueft, ob der Termin von der aktuellen Orga bearbeitet werden darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableDate extends TableAccess
{
    protected $visibleRoles = array();
    protected $changeVisibleRoles;
    
	/** Constuctor that will create an object of a recordset of the table adm_dates. 
	 *  If the id is set than the specific date will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $dat_id The recordset of the date with this id will be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $dat_id = 0)
    {
		// read also data of assigned category
		$this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');

        parent::__construct($db, TBL_DATES, 'dat', $dat_id);
    }

    /** Additional to the parent method visible roles array and flag will be initialized.
	 */
    public function clear()
    {
        parent::clear();

        $this->visibleRoles = array();
        $this->changeVisibleRoles = false;
    }
        
	/** Deletes the selected record of the table and all references in other tables. 
	 *  After that the class will be initialize.
	 *  @return @b true if no error occured
	 */
    public function delete()
    {
		$this->db->startTransaction();

        $sql = 'DELETE FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id = '.$this->getValue('dat_id');
        $result = $this->db->query($sql);

        parent::delete();

        // haben diesem Termin Mitglieder zugesagt, so muessen diese Zusagen noch geloescht werden
        if($this->getValue('dat_rol_id') > 0)
        {
            $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_rol_id = '.$this->getValue('dat_rol_id');
            $this->db->query($sql);
            
            $sql = 'DELETE FROM '.TBL_ROLES.' WHERE rol_id = '.$this->getValue('dat_rol_id');
            $this->db->query($sql);
        }

		$this->db->endTransaction();
	}    
    
    // prueft, ob der Termin von der aktuellen Orga bearbeitet werden darf
    public function editRight()
    {
        global $gCurrentOrganization;
        
        // Termine der eigenen Orga darf bearbeitet werden
        if($this->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
        {
            return true;
        }
        // Termine von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->getValue('dat_global') == true
        && $gCurrentOrganization->isChildOrganization($this->getValue('cat_org_id')))
        {
            return true;
        }
    
        return false;
    }

    //gibt einen Termin im iCal-Format zurueck
    public function getIcal($domain)
    {
        $prodid = '-//www.admidio.org//Admidio' . ADMIDIO_VERSION . '//DE';
        
        $ical = $this->getIcalHeader().
                $this->getIcalVEvent($domain).
                $this->getIcalFooter();
        return $ical;
    }
    
    //gibt den Kopf eines iCalCalenders aus
    public function getIcalHeader()
    {
        $prodid = '-//www.admidio.org//Admidio' . ADMIDIO_VERSION . '//DE';
        
        $icalHeader =   "BEGIN:VCALENDAR\n".
                        "METHOD:PUBLISH\n".
                        "PRODID:". $prodid. "\n".
                        "VERSION:2.0\n";
        return $icalHeader;
    }
    
    //gibt den Fuß eines iCalCalenders aus
    public function getIcalFooter()
    {      
        $icalFooter = "END:VCALENDAR";
        
        return $icalFooter;
    }
    
    //gibt einen einzelnen Termin im iCal-Format zurück 
    public function getIcalVEvent($domain)
    {
        $uid = $this->getValue('dat_timestamp_create', 'ymdThis') . '+' . $this->getValue('dat_usr_id_create') . '@' . $domain;
        
        $icalVEevent =  "BEGIN:VEVENT\n".
                        "CREATED:". $this->getValue('dat_timestamp_create', 'Ymd').'T'.$this->getValue('dat_timestamp_create', 'His')."\n";
        if($this->getValue('dat_timestamp_change') != NULL)
        {
            $icalVEevent .= "LAST-MODIFIED:". $this->getValue('dat_timestamp_change', 'Ymd').'T'.$this->getValue('dat_timestamp_change', 'His')."\n";
        }
                    
        //Semicolons herausfiltern             
        $icalVEevent .=  "UID:". $uid. "\n".
                        "SUMMARY:". str_replace(';', '.', $this->getValue('dat_headline')). "\n".
                        "DESCRIPTION:". str_replace("\r\n", "\n", str_replace(';', '.', $this->getValue('dat_description', 'plain'))). "\n".
                        "DTSTAMP:".date('Ymd').'T'.date('His')."\n".
                        "LOCATION:". str_replace(';', '.',$this->getValue('dat_location')). "\n";
        if($this->getValue('dat_all_day') == 1)
        {
            // das Ende-Datum bei mehrtaegigen Terminen muss im iCal auch + 1 Tag sein
            // Outlook und Co. zeigen es erst dann korrekt an
            $icalVEevent .= "DTSTART;VALUE=DATE:". $this->getValue('dat_begin', 'Ymd'). "\n".
                     "DTEND;VALUE=DATE:". $this->getValue('dat_end', 'Ymd'). "\n";
        }
        else
        {
            $icalVEevent .= "DTSTART:". $this->getValue('dat_begin', 'Ymd')."T".$this->getValue('dat_begin', 'His')."\n".
                     "DTEND:". $this->getValue('dat_end', 'Ymd')."T".$this->getValue('dat_end', 'His')."\n";
        }
        $icalVEevent .= "END:VEVENT\n";
        
        return $icalVEevent;
    }

    /** Get the value of a column of the database table.
     *  If the value was manipulated before with @b setValue than the manipulated value is returned.
     *  @param $columnName The name of the database column whose value should be read
     *  @param $format For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                 For text columns the format can be @b plain that would return the original database value without any transformations
     *  @return Returns the value of the database column.
     *          If the value was manipulated before with @b setValue than the manipulated value is returned.
     */ 
    public function getValue($columnName, $format = '')
    {
		global $gL10n;

        if($columnName == 'dat_end' && $this->dbColumns['dat_all_day'] == 1)
        {
            // bei ganztaegigen Terminen wird das Enddatum immer 1 Tag zurueckgesetzt
            list($year, $month, $day, $hour, $minute, $second) = preg_split('/[- :]/', $this->dbColumns['dat_end']);
            $value = date($format, mktime($hour, $minute, $second, $month, $day, $year) - 86400);
        }
        elseif($columnName == 'dat_description')
        {
			if(isset($this->dbColumns['dat_description']) == false)
			{
				$value = '';
			}
			elseif($format == 'plain')
			{
				$value = html_entity_decode(strStripTags($this->dbColumns['dat_description']), ENT_QUOTES, 'UTF-8');
			}
			else
			{
				$value = $this->dbColumns['dat_description'];
			}
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        if($columnName == 'dat_country' && strlen($value) > 0)
        {
            // beim Land die sprachabhaengige Bezeichnung auslesen
            global $gL10n;
            $value = $gL10n->getCountryByCode($value);
        }
		elseif($columnName == 'cat_name' && $format != 'plain')
		{
			// if text is a translation-id then translate it
			if(strpos($value, '_') == 3)
			{
				$value = $gL10n->get(admStrToUpper($value));
			}
		}

        return $value;
    }

    // die Methode gibt ein Array  mit den fuer den Termin sichtbaren Rollen-IDs zurueck
    public function getVisibleRoles()
    {
        if(count($this->visibleRoles) == 0)
        {
            // alle Rollen-IDs einlesen, die diesen Termin sehen duerfen
            $this->visibleRoles = array();
            $sql = 'SELECT dtr_rol_id FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id = '.$this->getValue('dat_id');
            $this->db->query($sql);

            while($row = $this->db->fetch_array())
            {
                if($row['dtr_rol_id'] == null)
                {
                    $this->visibleRoles[] = -1;
                }
                else
                {
                    $this->visibleRoles[] = $row['dtr_rol_id'];
                }
            }
        }
        return $this->visibleRoles;
    }

	/** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
	 *  a new record or if only an update is neccessary. The update statement will only update
	 *  the changed columns. If the table has columns for creator or editor than these column
	 *  with their timestamp will be updated.
	 *  Saves also all roles that could see this date.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
	 */
    public function save($updateFingerPrint = true)
    {
		$this->db->startTransaction();

        parent::save($updateFingerPrint);

        if($this->changeVisibleRoles == true)
        {
            // Sichbarkeit der Rollen wegschreiben
            if($this->new_record == false)
            {
                // erst einmal alle bisherigen Rollenzuordnungen loeschen, damit alles neu aufgebaut werden kann
                $sql='DELETE FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id = '.$this->getValue('dat_id');
                $this->db->query($sql);
            }

            // nun alle Rollenzuordnungen wegschreiben
            $date_role = new TableAccess($this->db, TBL_DATE_ROLE, 'dtr');

            foreach($this->visibleRoles as $key => $roleID)
            {
                if($roleID != 0)
                {
                    if($roleID > 0)
                    {
                        $date_role->setValue('dtr_rol_id', $roleID);
                    }
                    $date_role->setValue('dtr_dat_id', $this->getValue('dat_id'));
                    $date_role->save();
                    $date_role->clear();
                }
            }
        }

        $this->changeVisibleRoles = false;
		$this->db->endTransaction();
    }

    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue The new value that should be stored in the database field
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if($columnName == 'dat_end' && $this->getValue('dat_all_day') == 1)
        {
            // hier muss bei ganztaegigen Terminen das bis-Datum um einen Tag hochgesetzt werden
            // damit der Termin bei SQL-Abfragen richtig beruecksichtigt wird
            list($year, $month, $day, $hour, $minute, $second) = preg_split('/[- :]/', $newValue);
            $newValue = date('Y-m-d H:i:s', mktime($hour, $minute, $second, $month, $day, $year) + 86400);
        }
        elseif($columnName == 'dat_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }

    // die Methode erwartet ein Array mit den fuer den Termin sichtbaren Rollen-IDs
    public function setVisibleRoles($arrVisibleRoles)
    {
        if(count(array_diff($arrVisibleRoles, $this->visibleRoles)) > 0)
        {
            $this->changeVisibleRoles = true;
        }
        $this->visibleRoles = $arrVisibleRoles;
    }
}
?>