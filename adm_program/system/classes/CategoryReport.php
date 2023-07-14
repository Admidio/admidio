<?php
/**
 ***********************************************************************************************
 * Class manages the data for the report of module CategoryReport
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Klasse verwaltet die Daten für den Report des Moduls CategoryReport
 *
 * Folgende Methoden stehen zur Verfuegung:
 *
 * generate_listData()					-	erzeugt die Arrays listData und headerData für den Report
 * generate_headerSelection() 			- 	erzeugt die Auswahlliste für die Spaltenauswahl
 * isInheaderSelection($search_value)	-	liest die Konfigurationsdaten aus der Datenbank
 * setConfiguration()                   -   set the internal active configuration to the crtId of the parameter
 * isMemberOfCategorie()                -   prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
 *
 *****************************************************************************/

class CategoryReport
{
    public $headerData      = array();          ///< Array mit allen Spaltenueberschriften
    public $listData        = array();          ///< Array mit den Daten für den Report
    public $headerSelection = array();          ///< Array mit der Auswahlliste für die Spaltenauswahl
    protected $conf;							   ///< die gewaehlte Konfiguration
    protected $arrConfiguration = array();         ///< Array with the all configurations from the database

    /**
     * CategoryReport constructor
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
        global $gDb, $gProfileFields, $gL10n, $gCurrentOrgId;

        $workarray      = array();
        $number_row_pos = -1;
        $number_col     = array();

        $colfields = explode(',', $this->arrConfiguration[$this->conf]['col_fields']);
        // die gespeicherten Konfigurationen durchlaufen
        foreach ($colfields as $key => $data) {
            // das ist nur zur Ueberpruefung, ob diese Freigabe noch existent ist
            // es koennte u.U. ja sein, dass ein Profilfeld oder eine Rolle seit der letzten Speicherung geloescht wurde
            $found = $this->isInHeaderSelection($data);
            if ($found == 0) {
                continue;
            } else {
                $workarray[$key+1] = array();
            }

            //$data splitten in Typ und ID
            $type = substr($data, 0, 1);
            $id = (int) substr($data, 1);

            $workarray[$key+1]['type'] = $type;
            $workarray[$key+1]['id'] = $id;

            $this->headerData[$key+1]['id'] = 0;
            $this->headerData[$key+1]['data'] = $this->headerSelection[$found]['data'];

            switch ($type) {
                case 'p':                    //p=profileField
                    // nur bei Profilfeldern wird 'id' mit der 'usf_id' ueberschrieben
                    $this->headerData[$key+1]['id'] = $id;
                    $number_col[$key+1] = '';
                    break;
                case 'c':                    //c=categorie

                    $sql = 'SELECT DISTINCT mem_usr_id
             				           FROM '.TBL_CATEGORIES.'
             				          INNER JOIN '.TBL_ROLES.' ON rol_cat_id = cat_id
             				          INNER JOIN '.TBL_MEMBERS.' ON mem_rol_id = rol_id
             				          WHERE cat_id = ? -- $id
             				            AND cat_type = \'ROL\'
             				            AND mem_begin <= ? -- DATE_NOW
           					            AND mem_end    > ? -- DATE_NOW
             				            AND ( cat_org_id = ? -- $gCurrentOrgId
               				             OR cat_org_id IS NULL )';
                    $queryParams = array(
                        $id,
                        DATE_NOW,
                        DATE_NOW,
                        $gCurrentOrgId
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key+1] = 0;
                    break;
                case 'r':                    //r=role

                    $sql = 'SELECT mem_usr_id
             				  FROM '.TBL_ROLES.'
                             INNER JOIN '.TBL_MEMBERS.' ON mem_rol_id = rol_id
                             INNER JOIN '.TBL_CATEGORIES.' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW ';
                    $queryParams = array(
                        $id,
                        DATE_NOW,
                        DATE_NOW
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key+1] = 0;
                    break;
                case 'w':                    //w=without (Leader)

                    $sql = 'SELECT mem_usr_id
             				  FROM '.TBL_ROLES.'
                             INNER JOIN '.TBL_MEMBERS.' ON mem_rol_id = rol_id
                             INNER JOIN '.TBL_CATEGORIES.' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW
             				   AND mem_leader = false ';
                    $queryParams = array(
                        $id,
                        DATE_NOW,
                        DATE_NOW
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workarray[$key+1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key+1] = 0;
                    break;
                case 'l':                    //l=leader

                    $sql = 'SELECT mem_usr_id
             				  FROM '.TBL_ROLES.'
                             INNER JOIN '.TBL_MEMBERS.' ON mem_rol_id = rol_id
                             INNER JOIN '.TBL_CATEGORIES.' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW
             				   AND mem_leader = true ';
                    $queryParams = array(
                        $id,
                        DATE_NOW,
                        DATE_NOW
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
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

        $number_col[1] = $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')';

        // Read in all members of the current organisation
        $sql = ' SELECT mem_usr_id
                   FROM '.TBL_CATEGORIES.'
                  INNER JOIN '.TBL_ROLES.' ON rol_cat_id = cat_id
                  INNER JOIN '.TBL_MEMBERS.' ON mem_rol_id = rol_id
                  WHERE cat_type = \'ROL\'
             	    AND ( cat_org_id = ? -- $gCurrentOrgId
               		 OR cat_org_id IS NULL )
             	    AND rol_valid  = true
             	    AND mem_begin <= ? -- DATE_NOW
           		    AND mem_end    > ? -- DATE_NOW ';
        $queryParams = array(
            $gCurrentOrgId,
            DATE_NOW,
            DATE_NOW
        );
        $statement = $gDb->queryPrepared($sql, $queryParams);

        while ($row = $statement->fetch()) {
            $this->listData[$row['mem_usr_id']] = array();
        }

        $user = new User($gDb, $gProfileFields);

        // go through all members
        foreach ($this->listData as $member => $dummy) {
            $user->readDataById($member);
            $memberShips = $user->getRoleMemberships();
            $number_row_count = 0;

            // Are there role and/or category restrictions?
            $roleCategoryMarker = true;
            if ((string) $this->arrConfiguration[$this->conf]['selection_role'] !== '') {
                $roleCategoryMarker = false;
                foreach (explode(',', $this->arrConfiguration[$this->conf]['selection_role']) as $rol) {
                    if ($user->isMemberOfRole((int)$rol)) {
                        $roleCategoryMarker = true;
                    }
                }
            }

            if ((string) $this->arrConfiguration[$this->conf]['selection_cat'] !== '') {
                foreach (explode(',', $this->arrConfiguration[$this->conf]['selection_cat']) as $cat) {
                    if ($this->isMemberOfCategorie($cat, $member)) {
                        $roleCategoryMarker = true;
                    }
                }
            }
            if (!$roleCategoryMarker) {
                unset($this->listData[$member]);
                continue;
            }

            foreach ($workarray as $key => $data) {
                if ($data['type'] == 'p') {
                    $this->listData[$member][$key] = $user->getValue($gProfileFields->getPropertyById($data['id'], 'usf_name_intern'), 'database');
                } elseif ($data['type'] == 'a') {              //Sonderfall: Rollengesamtuebersicht erstellen
                    $role = new TableRoles($gDb);

                    $this->listData[$member][$key] = '';
                    foreach ($memberShips as $rol_id) {
                        $role->readDataById($rol_id);
                        $this->listData[$member][$key] .= $role->getValue('rol_name').'; ';
                    }
                    $this->listData[$member][$key] = trim($this->listData[$member][$key], '; ');
                } elseif ($data['type'] == 'n') {              //Sonderfall: Anzahlspalte
                    $this->listData[$member][$key] = '';
                } else {
                    if (isset($data['usr_id']) and in_array($member, $data['usr_id'])) {
                        $this->listData[$member][$key] = true;
                        $number_row_count++;
                        $number_col[$key]++;
                    } else {
                        $this->listData[$member][$key] = '';
                    }
                }
            }
            if ($number_row_pos > -1) {
                $this->listData[$member][$number_row_pos]=$number_row_count;
            }
        }

        if ($this->arrConfiguration[$this->conf]['number_col'] == 1) {
            $this->listData[] = $number_col;
        }
    }

    /**
     * Erzeugt die Auswahlliste fuer die Spaltenauswahl
     * @return void
     */
    private function generate_headerSelection()
    {
        global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gCurrentOrgId;

        $categories = array();

        $i 	= 1;
        foreach ($gProfileFields->getProfileFields() as $field) {
            if ($field->getValue('usf_hidden') == 0 || $gCurrentUser->editUsers()) {
                $this->headerSelection[$i]['id']       = 'p'.$field->getValue('usf_id');
                $this->headerSelection[$i]['cat_name'] = $field->getValue('cat_name');
                $this->headerSelection[$i]['data']     = addslashes($field->getValue('usf_name'));
                $i++;
            }
        }

        // alle (Rollen-)Kategorien der aktuellen Organisation einlesen
        $sql = ' SELECT cat_name, cat_id
             	   FROM '.TBL_CATEGORIES.'
             	  WHERE cat_type = \'ROL\'
             	    AND ( cat_org_id = ? -- $gCurrentOrgId
               	        OR cat_org_id IS NULL )';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        $k = 0;
        while ($row = $statement->fetch()) {
            // check if the category name must be translated
            if (Language::isTranslationStringId($row['cat_name'])) {
                $row['cat_name'] = $gL10n->get($row['cat_name']);
            }
            $categories[$k]['cat_id']   = $row['cat_id'];
            $categories[$k]['cat_name'] = $row['cat_name'];
            $categories[$k]['data'] 	= $gL10n->get('SYS_CATEGORY').': '.$row['cat_name'];
            $k++;
        }

        // alle eingelesenen Kategorien durchlaufen und die Rollen dazu einlesen
        foreach ($categories as $data) {
            $this->headerSelection[$i]['id']   	   = 'c'.$data['cat_id'];
            $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
            $this->headerSelection[$i]['data']	   = $data['data'];
            $i++;

            $sql = 'SELECT DISTINCT rol_name, rol_id, rol_valid
                	           FROM '.TBL_CATEGORIES.'
                	          INNER JOIN '.TBL_ROLES.' ON rol_cat_id = cat_id
                	          WHERE cat_id = ? ';
            $statement = $gDb->queryPrepared($sql, array($data['cat_id']));

            while ($row = $statement->fetch()) {
                $marker = '';
                if ($row['rol_valid'] == 0) {
                    $marker = ' (' .  ($row['rol_valid'] == 0 ? '*' : '') . ')';
                }

                $this->headerSelection[$i]['id']   	   = 'r'.$row['rol_id'];       //r wie role
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data']	   = $gL10n->get('SYS_ROLE').': '.$row['rol_name'].$marker;
                $i++;

                $this->headerSelection[$i]['id']   	   = 'w'.$row['rol_id'];		//w wie without (Leader)
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data']	   = $gL10n->get('SYS_ROLE_WITHOUT_LEADER').': '.$row['rol_name'].$marker;
                $i++;

                $this->headerSelection[$i]['id']   	   = 'l'.$row['rol_id'];		//l wie leader
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data']	   = $gL10n->get('SYS_LEADER').': '.$row['rol_name'].$marker;
                $i++;
            }
        }
        //Zusatzspalte fuer die Gesamtrollenuebersicht erzeugen
        $this->headerSelection[$i]['id']   	   = 'adummy';          //a wie additional
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_ADDITIONAL_COLUMNS');
        $this->headerSelection[$i]['data']	   = $gL10n->get('SYS_ROLE_MEMBERSHIPS');
        $i++;

        //Zusatzspalte fuer die Anzahl erzeugen
        $this->headerSelection[$i]['id']   	   = 'ndummy';          //n wie number
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_ADDITIONAL_COLUMNS');
        $this->headerSelection[$i]['data']	   = $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_ROW') . ')';
    }

    /**
     * Funktion liest das Konfigurationsarray ein
     * @param   none
     * @return  array $config  das Konfigurationsarray
     */
    public function getConfigArray()
    {
        global  $gDb, $gSettingsManager, $gCurrentOrgId;

        if (count($this->arrConfiguration) === 0) {
            $sql = ' SELECT *
                       FROM '. TBL_CATEGORY_REPORT .'
                      WHERE ( crt_org_id = ? -- $gCurrentOrgId
                         OR crt_org_id IS NULL ) ';
            $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

            while ($row = $statement->fetch()) {
                $values = array();
                $values['id']             = $row['crt_id'];
                $values['name']           = SecurityUtils::encodeHTML($row['crt_name']);
                $values['col_fields']     = $row['crt_col_fields'];
                $values['selection_role'] = $row['crt_selection_role'];
                $values['selection_cat']  = $row['crt_selection_cat'];
                $values['number_col']     = $row['crt_number_col'];
                $values['default_conf']   = false;
                if ($gSettingsManager->getInt('category_report_default_configuration') == $row['crt_id']) {
                    $values['default_conf']   = true;
                }
                $this->arrConfiguration[] = $values;
            }
        }

        return $this->arrConfiguration;
    }

    /**
     * Funktion speichert das Konfigurationsarray
     * @param   $arrConfiguration
     * @return  array das Konfigurationsarray
     */
    public function saveConfigArray(array $arrConfiguration)
    {
        global  $gDb, $gCurrentOrgId, $gSettingsManager;

        $defaultConfiguration = 0;

        $gDb->startTransaction();

        foreach ($arrConfiguration as $key => $values) {
            if ($values['id'] === '' || $values['id'] > 0) {                  // id > 0 (=edit a configuration) or '' (=append a configuration)
                $categoryReport = new TableAccess($gDb, TBL_CATEGORY_REPORT, 'crt', $values['id']);
                $categoryReport->setValue('crt_org_id', $gCurrentOrgId);
                $categoryReport->setValue('crt_name', $values['name']);
                $categoryReport->setValue('crt_col_fields', $values['col_fields']);
                $categoryReport->setValue('crt_selection_role', $values['selection_role']);
                $categoryReport->setValue('crt_selection_cat', $values['selection_cat']);
                $categoryReport->setValue('crt_number_col', $values['number_col']);
                $categoryReport->save();

                if ($values['default_conf'] === true || $defaultConfiguration === 0) {
                    $defaultConfiguration = $categoryReport->getValue('crt_id');
                }
                // set default configuration
                $gSettingsManager->set('category_report_default_configuration', $defaultConfiguration);
            } else {                                                            // delete
                $values['id'] = $values['id']*(-1);
                $categoryReport = new TableAccess($gDb, TBL_CATEGORY_REPORT, 'crt', $values['id']);
                $categoryReport->delete();
            }
        }

        $gDb->endTransaction();

        $this->arrConfiguration = array();

        return $this->getConfigArray();
    }

    /**
     * get the active configuration
     */
    public function getConfiguration()
    {
        return $this->conf;
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
        foreach ($this->headerSelection as $key => $data) {
            if ($data['id'] == $search_value) {
                $ret = $key;
                break;
            }
        }
        return $ret;
    }

    /**
     * set the internal active configuration to the crtId of the parameter
     */
    public function setConfiguration($crtId)
    {
        foreach ($this->arrConfiguration as $key => $values) {
            if ($values['id'] == $crtId) {
                $this->conf = $key;
            }
        }
    }

    /**
     * Funktion prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
     *
     * @param   int  $cat_id    ID der zu pruefenden Kategorie
     * @param   int  $user_id   ID des Users, fuer den die Mitgliedschaft geprueft werden soll
     * @return  bool
     */
    private function isMemberOfCategorie($cat_id, $user_id = 0)
    {
        global $gCurrentUserId, $gDb, $gCurrentOrgId;

        if ($user_id == 0) {
            $user_id = $gCurrentUserId;
        } elseif (is_numeric($user_id) == false) {
            return -1;
        }

        $sql = 'SELECT mem_id
                  FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                 WHERE mem_usr_id = ? -- $user_id
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND mem_rol_id = rol_id
                   AND cat_id   = ? -- $cat_id
                   AND rol_valid  = true
                   AND rol_cat_id = cat_id
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                    OR cat_org_id IS NULL ) ';

        $queryParams = array(
            $user_id,
            DATE_NOW,
            DATE_NOW,
            $cat_id,
            $gCurrentOrgId
        );
        $statement = $gDb->queryPrepared($sql, $queryParams);
        $user_found = $statement->rowCount();

        if ($user_found == 1) {
            return 1;
        } else {
            return 0;
        }
    }
}
