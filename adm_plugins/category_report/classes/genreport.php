<?php
/**
 ***********************************************************************************************
 * Class manages the data for the report
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Klasse verwaltet die Daten für den Report
 *
 * Folgende Methoden stehen zur Verfuegung:
 *
 * generate_listData()					-	erzeugt die Arrays listData und headerData für den Report
 * generate_headerSelection() 			- 	erzeugt die Auswahlliste für die Spaltenauswahl
 * isInheaderSelection($search_value)	-	liest die Konfigurationsdaten aus der Datenbank
 *
 *****************************************************************************/

class GenReport
{    
    public	  $headerData      = array();          ///< Array mit allen Spaltenueberschriften
    public	  $listData        = array();          ///< Array mit den Daten für den Report
    public	  $headerSelection = array();          ///< Array mit der Auswahlliste für die Spaltenauswahl
    public	  $conf;							   ///< die gewaehlte Konfiguration

    /**
     * GenReport constructor
     */
    public function __construct()
    {   	
		// die HeaderSelection-Daten werden bei jedem Aufruf der Klasse benoetigt
		$this->generate_headerSelection();
    }

    /**
     * Erzeugt die Arrays listData und headerData fuer den Report
     * @return void
     */
	public function generate_listData()
	{
		global $gDb, $gProfileFields, $pPreferences, $gL10n;
		
		$workarray      = array();
		$number_row_pos = -1;
		$number_col     = array();		
		
		$colfields = explode(',', $pPreferences->config['Konfigurationen']['col_fields'][$this->conf]);
		// die gespeicherten Konfigurationen durchlaufen
		foreach ($colfields as $key => $data)
        {
        	// das ist nur zur Ueberpruefung, ob diese Freigabe noch existent ist
            // es koennte u.U. ja sein, dass ein Profilfeld oder eine Rolle seit der letzten Speicherung geloescht wurde
        	$found = $this->isInHeaderSelection($data);
            if ($found == 0)
            {
            	continue;	
            }
            else 
            {
            	$workarray[$key+1] = array();
            }
            
        	//$data splitten in Typ und ID
        	$type = substr($data, 0, 1);
        	$id = (int) substr($data, 1);
        	
        	$workarray[$key+1]['type'] = $type;
        	$workarray[$key+1]['id'] = $id;
        	
        	$this->headerData[$key+1]['id'] = 0;
        	$this->headerData[$key+1]['data'] = $this->headerSelection[$found]['data'];
        	
        	switch ($type)
        	{
        		case 'p':                    //p=profileField
        			// nur bei Profilfeldern wird 'id' mit der 'usf_id' ueberschrieben
        			$this->headerData[$key+1]['id'] = $id;
        			$number_col[$key+1] = '';
        			break;
        		case 'c':                    //c=categorie
        			
        			$sql = 'SELECT DISTINCT mem_usr_id
             				           FROM '.TBL_MEMBERS.', '.TBL_CATEGORIES.', '.TBL_ROLES.' 
             				          WHERE cat_type = \'ROL\' 
             				            AND cat_id = rol_cat_id
             				            AND mem_rol_id = rol_id
             				            AND mem_begin <= ? -- DATE_NOW
           					            AND mem_end    > ? -- DATE_NOW
             				            AND cat_id = ? -- $id
             				            AND ( cat_org_id = ? -- ORG_ID
               				             OR cat_org_id IS NULL )';
					$queryParams = array(
					    DATE_NOW,
					    DATE_NOW,
					    $id,
					    ORG_ID
					);
					$statement = $gDb->queryPrepared($sql, $queryParams);

					while ($row = $statement->fetch())
					{
						$workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
					}
					$number_col[$key+1] = 0;
        			break;
        		case 'r':                    //r=role

                    $sql = 'SELECT mem_usr_id
             				  FROM '.TBL_MEMBERS.', '.TBL_ROLES.' 
             				 WHERE mem_rol_id = rol_id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW
             				   AND rol_id = ? -- $id ';
					$queryParams = array(
					    DATE_NOW,
					    DATE_NOW,
					    $id
					);
					$statement = $gDb->queryPrepared($sql, $queryParams);

					while ($row = $statement->fetch())
					{
						$workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
					}
					$number_col[$key+1] = 0;
        			break;
        		case 'w':                    //w=without (Leader)

        			$sql = 'SELECT mem_usr_id
             				  FROM '.TBL_MEMBERS.', '.TBL_ROLES.' 
             				 WHERE mem_rol_id = rol_id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW
             				   AND rol_id = ? -- $id 
             				   AND mem_leader = 0 ';
					$queryParams = array(
					    DATE_NOW,
					    DATE_NOW,
					    $id
					);
					$statement = $gDb->queryPrepared($sql, $queryParams);

					while ($row = $statement->fetch())
					{
						$workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
					}
					$number_col[$key+1] = 0;
        			break;        			
        		case 'l':                    //l=leader
        			
        			$sql = 'SELECT mem_usr_id
             				  FROM '.TBL_MEMBERS.', '.TBL_ROLES.' 
             				 WHERE mem_rol_id = rol_id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW
             				   AND rol_id = ? -- $id 
             				   AND mem_leader = 1 ';
					$queryParams = array(
					    DATE_NOW,
					    DATE_NOW,
					    $id
					);
					$statement = $gDb->queryPrepared($sql, $queryParams);

					while ($row = $statement->fetch())
					{
						$workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
					}
					$number_col[$key+1] = 0;
        			break;
				case 'n':                    //n=number
        			// eine oder mehrere Zaehlspalten wurden definiert
        			// die Position der letzten Spalte zwischenspeichern
        			// Werte werden aber nur in der letzten Zaehlspalte angezeigt
        			// alles andere ist Unsinn (warum soll derselbe Wert mehrfach angezeigt werden)
        			$number_row_pos = $key+1;
        			$number_col[$key+1] = '';
        			break;
        		case 'a':                    //a=additional
        			$number_col[$key+1] = '';
        			break;
        	}
        }  

        $number_col[1] = $gL10n->get('PLG_KATEGORIEREPORT_NUMBER_COL');
        
		// alle Mitglieder der aktuellen Organisation einlesen
        $sql = ' SELECT mem_usr_id
             	   FROM '.TBL_MEMBERS.', '.TBL_ROLES.', '.TBL_CATEGORIES. ' 
             	  WHERE mem_rol_id = rol_id
             	    AND rol_valid  = 1   
             	    AND rol_cat_id = cat_id
             	    AND ( cat_org_id = ? -- ORG_ID
               		 OR cat_org_id IS NULL )
             	    AND mem_begin <= ? -- DATE_NOW
           		    AND mem_end    > ? -- DATE_NOW ';
		$queryParams = array(
		    ORG_ID,
		    DATE_NOW,
		    DATE_NOW
		);
		$statement = $gDb->queryPrepared($sql, $queryParams);
        
		while ($row = $statement->fetch())
		{
			$this->listData[$row['mem_usr_id']] = array();
		}
		
		$user = new User($gDb, $gProfileFields);
		
		// alle Mitlieder durchlaufen   ...
    	foreach ($this->listData as $member => $dummy)
		{     	
			$user->readDataById($member);
			$memberShips = $user->getRoleMemberships();
			$number_row_count = 0;
	   		
			// bestehen Rollen- und/oder Kategorieeinschraenkungen?
        	$rolecatmarker = true;
        	if ($pPreferences->config['Konfigurationen']['selection_role'][$this->conf] <> ' '
        	 || $pPreferences->config['Konfigurationen']['selection_cat'][$this->conf] <> ' ')
        	{
        		$rolecatmarker = false;	
        		foreach (explode(',', $pPreferences->config['Konfigurationen']['selection_role'][$this->conf]) as $rol)
        		{
        			if ($user->isMemberOfRole((int) $rol))
        			{
        				$rolecatmarker = true;
        			}
        		}	
				foreach (explode(',', $pPreferences->config['Konfigurationen']['selection_cat'][$this->conf]) as $cat)
        		{
        			if (isMemberOfCategorie($cat, $member))
        			{
        				$rolecatmarker = true;
        			}
        		}
        	} 			
			if (!$rolecatmarker)
        	{
        		unset($this->listData[$member]);
        		continue;
        	}
        	
			foreach ($workarray as $key => $data)
			{
				if ($data['type'] == 'p')
				{				
                    if ( ($gProfileFields->getPropertyById($data['id'], 'usf_type') == 'DROPDOWN'
                       	|| $gProfileFields->getPropertyById($data['id'], 'usf_type') == 'RADIO_BUTTON') )
    				{
    					$this->listData[$member][$key] = $user->getValue($gProfileFields->getPropertyById($data['id'], 'usf_name_intern'), 'database');
    				}
    				else 
    				{
    					$this->listData[$member][$key] = $user->getValue($gProfileFields->getPropertyById($data['id'], 'usf_name_intern'));
    				}
				}
				elseif ($data['type'] == 'a')              //Sonderfall: Rollengesamtuebersicht erstellen
				{
					$role = new TableRoles($gDb);
					
					$this->listData[$member][$key] = '';
					foreach ($memberShips as $rol_id)
					{
						$role->readDataById($rol_id);
						$this->listData[$member][$key] .= $role->getValue('rol_name').'; ';
					}
					$this->listData[$member][$key] = trim($this->listData[$member][$key],'; ');
				}
				elseif ($data['type'] == 'n')              //Sonderfall: Anzahlspalte
				{
					$this->listData[$member][$key] = '';
				}
				else 
				{
					if (isset($data['usr_id']) AND in_array($member,$data['usr_id']))
                	{
                    	$this->listData[$member][$key] = $pPreferences->config['Konfigurationen']['col_yes'][$this->conf];
                    	$number_row_count++;
                    	$number_col[$key]++;
            		}
                	else
                	{
                    	$this->listData[$member][$key] = $pPreferences->config['Konfigurationen']['col_no'][$this->conf];
                	}
				}
			}
			if ($number_row_pos > -1)
			{
				$this->listData[$member][$number_row_pos]=$number_row_count;
			}
		}

		if ($pPreferences->config['Konfigurationen']['number_col'][$this->conf] == 1)
		{
			$this->listData[max(array_keys($this->listData))+1] = $number_col;
		}
	}	
		
    /**
     * Erzeugt die Auswahlliste fuer die Spaltenauswahl
     * @return void
     */
	private function generate_headerSelection()
	{
		global $gDb, $gL10n, $gProfileFields, $gCurrentUser;
	    
        $categories = array();   
        
        $i 	= 1;
        foreach ($gProfileFields->getProfileFields() as $field)
        {               
            if ($field->getValue('usf_hidden') == 0 || $gCurrentUser->editUsers())
            {   
                $this->headerSelection[$i]['id']       = 'p'.$field->getValue('usf_id');
                $this->headerSelection[$i]['cat_name'] = $field->getValue('cat_name');
                $this->headerSelection[$i]['data']     = addslashes($field->getValue('usf_name'));
                $i++;
            }
        }
        
		// alle (Rollen-)Kategorien der aktuellen Organisation einlesen
        $sql = ' SELECT DISTINCT cat.cat_name, cat.cat_id
             	            FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
             	           WHERE cat.cat_type = \'ROL\' 
             	             AND cat.cat_id = rol.rol_cat_id
             	             AND ( cat.cat_org_id = ? 
               	              OR cat.cat_org_id IS NULL )';
		$statement = $gDb->queryPrepared($sql, array(ORG_ID));

		$k = 0;
		while ($row = $statement->fetch())
		{
			// ueberpruefen, ob der Kategoriename mittels der Sprachdatei uebersetzt werden kann
        	if (check_languagePKR($row['cat_name']))
        	{
        		$row['cat_name'] = $gL10n->get($row['cat_name']);
        	}
			$categories[$k]['cat_id']   = $row['cat_id'];
			$categories[$k]['cat_name'] = $row['cat_name'];
			$categories[$k]['data'] 	= $gL10n->get('SYS_CATEGORY').': '.$row['cat_name'];
			$k++;
		}
 
		// alle eingelesenen Kategorien durchlaufen und die Rollen dazu einlesen
  		foreach ($categories as $data)
		{
			$this->headerSelection[$i]['id']   	   = 'c'.$data['cat_id'];
			$this->headerSelection[$i]['cat_name'] = $data['cat_name'];
			$this->headerSelection[$i]['data']	   = $data['data'];
			$i++;

       	    $sql = 'SELECT DISTINCT rol.rol_name, rol.rol_id, rol.rol_valid
                	           FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
                	          WHERE cat.cat_id = ? 
                	            AND cat.cat_id = rol.rol_cat_id';
    		$statement = $gDb->queryPrepared($sql, array($data['cat_id']));
    		
        	while ($row = $statement->fetch())
        	{
        		$marker = '';
        		if ($row['rol_valid'] == 0 )
        		{
        			$marker = ' (' .  ($row['rol_valid'] == 0 ? '*' : '') . ')';
        		}
        			
        		$this->headerSelection[$i]['id']   	   = 'r'.$row['rol_id'];       //r wie role
        		$this->headerSelection[$i]['cat_name'] = $data['cat_name'];
				$this->headerSelection[$i]['data']	   = $gL10n->get('SYS_ROLE').': '.$row['rol_name'].$marker;
				$i++;
				
       			$this->headerSelection[$i]['id']   	   = 'w'.$row['rol_id'];		//w wie without (Leader)
        		$this->headerSelection[$i]['cat_name'] = $data['cat_name'];
				$this->headerSelection[$i]['data']	   = $gL10n->get('SYS_ROLE').' '.$gL10n->get('PLG_KATEGORIEREPORT_WITHOUT').' '.$gL10n->get('SYS_LEADER').': '.$row['rol_name'].$marker;
				$i++;				
        		
				$this->headerSelection[$i]['id']   	   = 'l'.$row['rol_id'];		//l wie leader
        		$this->headerSelection[$i]['cat_name'] = $data['cat_name'];
				$this->headerSelection[$i]['data']	   = $gL10n->get('SYS_LEADER').': '.$row['rol_name'].$marker;
				$i++;
        	}	
    	}
    	//Zusatzspalte fuer die Gesamtrollenuebersicht erzeugen
    	$this->headerSelection[$i]['id']   	   = 'adummy';          //a wie additional
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('PLG_KATEGORIEREPORT_ADDITIONAL_COLS');
		$this->headerSelection[$i]['data']	   = $gL10n->get('PLG_KATEGORIEREPORT_ROLEMEMBERSHIPS');
		$i++;
		
		//Zusatzspalte fuer die Anzahl erzeugen
    	$this->headerSelection[$i]['id']   	   = 'ndummy';          //n wie number 
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('PLG_KATEGORIEREPORT_ADDITIONAL_COLS');
		$this->headerSelection[$i]['data']	   = $gL10n->get('PLG_KATEGORIEREPORT_NUMBER_ROW');
	}
	
    /**
     * Prueft, ob es den uebergebenen Wert in der Spaltenauswahlliste gibt
     * Hinweis: die Spaltenauswahlliste ist immer aktuell, da sie neu generiert wird,
     * der zu pruefende Wert koennte jedoch veraltet sein, da er aus der Konfigurationstabelle stammt
     * @param 	string $search_value
     * @return 	int
     */
	public function isInheaderSelection($search_value)
	{
		$ret = 0;
		foreach ($this->headerSelection as $key => $data)
		{
			if ($data['id'] == $search_value)
			{
				$ret = $key;
				break;
			}
		}
		return $ret;
	}
}
