<?php

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\Membership;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Users\Entity\User;

/**
 * @brief Class manages the data for the report of module CategoryReport
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * The column definitions use a shorthand code of the form Xnn, where X describes the type of object/relation and nn is the ID of the object (profile field, role, etc.)
 * Possible values for "X" are:
 *   p# ... Profile field with ID #
 *   u# ... User field # (uuid, login, photo, text)
 *   c# ... Current member of a role of category with ID #
 *   r# ... Current member of role with ID #
 *   l# ... Current leader of role with ID #
 *   w# ... Current member (NOT leader) of role with ID # (*w*ithout leader)
 *   f# ... Former member of role with ID #
 *   b# ... Membership start of role with ID #
 *   e# ... Membership end of role with ID #
 *   d# ... Membership duration of role with ID #
 *   ndummy ...  Number (running counter)
 *   adummy ...  All roles
 *   ddummy ... Duration of membership
 * 
 */
class CategoryReport
{
    public array $headerData = array();          ///< Array mit allen Spaltenueberschriften
    public array $listData = array();          ///< Array mit den Daten für den Report
    public array $headerSelection = array();          ///< Array mit der Auswahlliste für die Spaltenauswahl
    public array $headerRolePropSelection = array();          ///< Array mit der Auswahlliste für die Spaltenauswahl
    protected int $conf;                               ///< die gewaehlte Konfiguration
    protected array $arrConfiguration = array();         ///< Array with the all configurations from the database

    /**
     * CategoryReport constructor
     * @throws Exception
     */
    public function __construct()
    {
        $this->generate_headerSelection();
    }

    /**
     * Method checks whether a configuration with the transferred name already exists.
     * If this is the case, "- copy" is appended.
     * @param string $name Name that should be checked.
     * @return  string
     * @throws Exception
     */
    function createName(string $name): string
    {
        global $gDb, $gL10n, $gCurrentOrgId;

        $sql = ' SELECT crt_name
                   FROM ' . TBL_CATEGORY_REPORT . '
                  WHERE (  crt_org_id = ? -- $gCurrentOrgId
                        OR crt_org_id IS NULL ) ';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        while ($row = $statement->fetch()) {
            if ($row['crt_name'] === $name) {
                $name .= ' - ' . $gL10n->get('SYS_CARBON_COPY');
            }
        }

        return $name;
    }

    /**
     * Erzeugt die Arrays listData und headerData fuer den Report
     * @return void
     * @throws Exception
     */
    public function generate_listData(string $date = DATE_NOW)
    {
        global $gDb, $gProfileFields, $gL10n, $gCurrentOrgId;

        $workArray = array();
        $number_row_pos = -1;
        $number_col = array();

        $columns = explode(',', $this->arrConfiguration[$this->conf]['col_fields']);
        // run through the saved configurations
        foreach ($columns as $key => $data) {
            // This is only to check whether this release still exists.
            // It could be that a profile field or role has been deleted since the last save
            $found = $this->isInHeaderSelection($data);
            if ($found == 0) {
                continue;
            } else {
                $workArray[$key + 1] = array();
            }

            //$data splitten in Typ und ID
            $type = substr($data, 0, 1);
            $id = (int)substr($data, 1);
            $field = substr($data, 1); // some types like 'u' use a non-numeric identifier

            $workArray[$key + 1]['type'] = $type;
            $workArray[$key + 1]['id'] = $id;
            $workArray[$key + 1]['field'] = $field;

            $this->headerData[$key + 1]['id'] = 0;
            $this->headerData[$key + 1]['data'] = $this->headerSelection[$found]['data'];

            switch ($type) {
                case 'p':                    //p=profileField
                    // nur bei Profilfeldern wird 'id' mit der 'usf_id' ueberschrieben
                    $this->headerData[$key + 1]['id'] = $id;
                    $number_col[$key + 1] = '';
                    break;
                case 'c':                    //c=categorie

                    $sql = 'SELECT DISTINCT mem_usr_id
                              FROM ' . TBL_CATEGORIES . '
                             INNER JOIN ' . TBL_ROLES . ' ON rol_cat_id = cat_id
                             INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                             WHERE cat_id = ? -- $id
                               AND cat_type = \'ROL\'
                               AND mem_begin <= ? -- $date
                               AND mem_end    > ? -- $date
                               AND ( cat_org_id = ? -- $gCurrentOrgId
                                   OR cat_org_id IS NULL )';
                    $queryParams = array(
                        $id,
                        $date,
                        $date,
                        $gCurrentOrgId
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workArray[$key + 1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key + 1] = 0;
                    break;
                case 'r':                    //r=role

                    $sql = 'SELECT mem_usr_id
             				  FROM ' . TBL_ROLES . '
                             INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- $date
           					   AND mem_end    > ? -- $date ';
                    $queryParams = array(
                        $id,
                        $date,
                        $date
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workArray[$key + 1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key + 1] = 0;
                    break;
                case 'f':                    //f=former role

                    $sql = 'SELECT mem_usr_id
             				  FROM ' . TBL_ROLES . '
                             INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin < ? -- $date
           					   AND mem_end    < ? -- $date ';
                    $queryParams = array(
                        $id,
                        $date,
                        $date
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workArray[$key + 1]['usr_id'][] = $row['mem_usr_id'];
                        // NOTE: By default, all current members as of the given date are included. However, if 
                        // a column has the "former members" type, then we need to include all former members
                        // of that role, too (it will be marked as former members, so no risk of confusion)
                            $this->listData[$row['mem_usr_id']] = array();
                    }
                    $number_col[$key + 1] = 0;
                    break;
                case 'w':                    //w=without (Leader)

                    $sql = 'SELECT mem_usr_id
             				  FROM ' . TBL_ROLES . '
                             INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- $date
           					   AND mem_end    > ? -- $date
             				   AND mem_leader = false ';
                    $queryParams = array(
                        $id,
                        $date,
                        $date
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workArray[$key + 1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key + 1] = 0;
                    break;
                case 'l':                    //l=leader

                    $sql = 'SELECT mem_usr_id
             				  FROM ' . TBL_ROLES . '
                             INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                               AND cat_type = \'ROL\'
             				 WHERE rol_id = ? -- $id
             				   AND mem_begin <= ? -- $date
           					   AND mem_end    > ? -- $date
             				   AND mem_leader = true ';
                    $queryParams = array(
                        $id,
                        $date,
                        $date
                    );
                    $statement = $gDb->queryPrepared($sql, $queryParams);

                    while ($row = $statement->fetch()) {
                        $workArray[$key + 1]['usr_id'][] = $row['mem_usr_id'];
                    }
                    $number_col[$key + 1] = 0;
                    break;
                case 'n':                    //n=number
                    // eine oder mehrere Zaehlspalten wurden definiert
                    // die Position der letzten Spalte zwischenspeichern
                    // Werte werden aber nur in der letzten Zaehlspalte angezeigt
                    // alles andere ist Unsinn (warum soll derselbe Wert mehrfach angezeigt werden)
                    $number_row_pos = $key + 1;
                    $number_col[$key + 1] = '';
                    break;
                case 'a':                    //a=additional
                case 'b':                    //b=membership begin
                case 'e':                    //e=membership end
                case 'd':                    //d=membership duration
                    case 'u':                    //u=user profile fields
                    $number_col[$key + 1] = '';
                    break;
            }
        }

        $number_col[1] = $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')';

        // Read in all current members of the current organisation
        // Then add all former members of the groups, where former memberships should be displayed. 
        // They will be marked as former members, so the confusion risk is minimized.
        $sql = ' SELECT mem_usr_id
                   FROM ' . TBL_CATEGORIES . '
                  INNER JOIN ' . TBL_ROLES . ' ON rol_cat_id = cat_id
                  INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id
                  WHERE cat_type = \'ROL\'
             	    AND ( cat_org_id = ? -- $gCurrentOrgId
               		 OR cat_org_id IS NULL )
             	    AND rol_valid  = true
             	    AND mem_begin <= ? -- $date
           		    AND mem_end    > ? -- $date ';
        $queryParams = array(
            $gCurrentOrgId,
            $date,
            $date
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
            $roleSel = trim((string)($this->arrConfiguration[$this->conf]['selection_role'] ?? ''));
            $catSel  = trim((string)($this->arrConfiguration[$this->conf]['selection_cat']  ?? ''));

            $roleMarker = true;
            if ($roleSel !== '') {
                $roleMarker = false;
                foreach (explode(',', $roleSel) as $rol) {
                    if ($user->isMemberOfRole((int)$rol)) {
                        $roleMarker = true;
                    }
                }
            }
            
            $categoryMarker = true;
            if ($catSel !== '') {
                $categoryMarker = false;
                foreach (explode(',', $catSel) as $cat) {
                    if ($this->isMemberOfCategory((int)$cat, $member)) {
                        $categoryMarker = true;
                    }
                }
            }
            // If any of the role or category restrictions fails to match, exclude this member
            if (!$roleMarker || !$categoryMarker) {
                unset($this->listData[$member]);
                continue;
            }

            foreach ($workArray as $key => $data) {
                if ($data['type'] == 'p') {
                    $this->listData[$member][$key] = $user->getValue($gProfileFields->getPropertyById($data['id'], 'usf_name_intern'), 'database');

                } elseif ($data['type'] == 'u') {       // User profile fields (UUID, login, photo, text, ...)
                    $fieldId = null;
                    switch ($data['field']) {
                        case 'uuid':
                        case 'login_name':
                        case 'photo':
                        case 'text':
                        case 'last_login':
                        case 'number_login':
                            $fieldId = 'usr_' . $data['field'];
                            break;
                    }
                    if (!empty($fieldId)) {
                        if ($data['field'] == 'last_login') {
                            $logindate = $user->getValue($fieldId);
                            $this->listData[$member][$key] = $logindate;
                        } elseif ($data['field'] == 'photo') {
                            $this->listData[$member][$key] = $user->getValue($fieldId, 'database') ?? '';
                        } else {
                            $this->listData[$member][$key] = $user->getValue($fieldId, 'database') ?? '';
                        }
                    } else {
                        $this->listData[$member][$key] = '';
                    }

                } elseif ($data['type'] == 'a') {              // Sonderfall: Rollengesamtuebersicht erstellen
                    $role = new Role($gDb);

                    $this->listData[$member][$key] = '';
                    foreach ($memberShips as $rol_id) {
                        $role->readDataById($rol_id);
                        $this->listData[$member][$key] .= $role->getValue('rol_name') . '; ';
                    }
                    $this->listData[$member][$key] = trim($this->listData[$member][$key], '; ');

                } elseif ($data['type'] == 'd' && $data['field'] == 'dummy') {              //Sonderfall: Mitgliedschaftsdauern aller Rollen

                    // Get membership durations for all roles
                    $this->listData[$member][$key] = '';
                    $membership = new Membership($gDb);
                    
                    foreach ($memberShips as $rol_id) {
                        $role = new Role($gDb);
                        $role->readDataById($rol_id);
                        
                        // Get membership data for this role
                        // TODO_RK: readDataByColumns returns false for multiple DB entries!!!
                        $membershipData = $membership->readDataByColumns(array('mem_rol_id' => $rol_id, 'mem_usr_id' => $member));
                        
                        if ($membershipData) {
                              $duration = $membership->calculateDuration();
                            $this->listData[$member][$key] .= $role->getValue('rol_name') . ': ' . $duration['formatted'] . '; ';
                        }
                    }
                    $this->listData[$member][$key] = trim($this->listData[$member][$key], '; ');

                } elseif ($data['type'] === 'b') {      // Membership begin
                    // TODO_RK: readDataByColumns returns false for multiple DB entries!!!
                    $this->listData[$member][$key] = '';
                    $membership = new Membership($gDb);
                    if ($membership->readDataByColumns(array('mem_rol_id' => $data['id'], 'mem_usr_id' => $member))) {
                        $this->listData[$member][$key] = $membership->getValue('mem_begin', 'Y-m-d');
                    }

                } elseif ($data['type'] === 'e') {      // Membership end
                    // TODO_RK: readDataByColumns returns false for multiple DB entries!!!
                    $this->listData[$member][$key] = '';
                    $membership = new Membership($gDb);
                    if ($membership->readDataByColumns(array('mem_rol_id' => $data['id'], 'mem_usr_id' => $member))) {
                        $this->listData[$member][$key] = $membership->getValue('mem_end', 'Y-m-d');
                    }

                } elseif ($data['type'] === 'd') {      // Membership duration
                    // TODO_RK: readDataByColumns returns false for multiple DB entries!!!
                    $this->listData[$member][$key] = '';
                    $membership = new Membership($gDb);
                    if ($membership->readDataByColumns(array('mem_rol_id' => $data['id'], 'mem_usr_id' => $member))) {
                        $duration = $membership->calculateDuration();
                        if (isset($duration['formatted'])) {
                            $this->listData[$member][$key] = $duration['formatted'];
                        }
                    }

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
                $this->listData[$member][$number_row_pos] = $number_row_count;
            }
        }

        if ($this->arrConfiguration[$this->conf]['number_col'] == 1) {
            $this->listData[] = $number_col;
        }
    }

    /**
     * Erzeugt die Auswahlliste fuer die Spaltenauswahl
     * @return void
     * @throws Exception
     */
    private function generate_headerSelection()
    {
        global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gCurrentOrgId;

        $categories = array();

        $i = 1;
        foreach ($gProfileFields->getProfileFields() as $field) {
            if ($field->getValue('usf_hidden') == 0 || $gCurrentUser->isAdministratorUsers()) {
                $this->headerSelection[$i]['id'] = 'p' . $field->getValue('usf_id');
                $this->headerSelection[$i]['cat_name'] = $field->getValue('cat_name');
                $this->headerSelection[$i]['data'] = addslashes($field->getValue('usf_name'));
                $i++;
            }
        }

        // User fields (uuid, login_name, number_login, last_login)
        $this->headerSelection[$i]['id'] = 'uuuid';       // u wie User profile
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_PROFILE_DATA');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_UUID');
        $i++;

        $this->headerSelection[$i]['id'] = 'ulogin_name';       // u wie User profile
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_PROFILE_DATA');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_USERNAME');
        $i++;

        $this->headerSelection[$i]['id'] = 'unumber_login';       // u wie User profile
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_PROFILE_DATA');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_NUMBER_OF_LOGINS');
        $i++;

        $this->headerSelection[$i]['id'] = 'ulast_login';       // u wie User profile
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_PROFILE_DATA');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_LAST_LOGIN');
        $i++;

        // alle (Rollen-)Kategorien der aktuellen Organisation einlesen
        $sql = ' SELECT cat_name, cat_id
             	   FROM ' . TBL_CATEGORIES . '
             	  WHERE cat_type = \'ROL\'
             	    AND ( cat_org_id = ? -- $gCurrentOrgId
               	        OR cat_org_id IS NULL )';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        $k = 0;
        while ($row = $statement->fetch()) {
            // check if the category name must be translated
            if (Admidio\Infrastructure\Language::isTranslationStringId($row['cat_name'])) {
                $row['cat_name'] = $gL10n->get($row['cat_name']);
            }
            $categories[$k]['cat_id'] = $row['cat_id'];
            $categories[$k]['cat_name'] = $row['cat_name'];
            $categories[$k]['data'] = $gL10n->get('SYS_CATEGORY') . ': ' . $row['cat_name'];
            $k++;
        }

        // alle eingelesenen Kategorien durchlaufen und die Rollen dazu einlesen
        foreach ($categories as $data) {
            $this->headerSelection[$i]['id'] = 'c' . $data['cat_id'];
            $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
            $this->headerSelection[$i]['data'] = $data['data'];
            $i++;

            $sql = 'SELECT DISTINCT rol_name, rol_id, rol_valid
                	           FROM ' . TBL_CATEGORIES . '
                	          INNER JOIN ' . TBL_ROLES . ' ON rol_cat_id = cat_id
                	          WHERE cat_id = ? ';
            $statement = $gDb->queryPrepared($sql, array($data['cat_id']));

            while ($row = $statement->fetch()) {
                $marker = '';
                if ($row['rol_valid'] == 0) {
                    $marker = ' (*)';
                }

                $this->headerSelection[$i]['id'] = 'r' . $row['rol_id'];       //r wie role
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_ROLE') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'w' . $row['rol_id'];        //w wie without (Leader)
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_ROLE_WITHOUT_LEADER') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'l' . $row['rol_id'];        //l wie leader
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_LEADER') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'f' . $row['rol_id'];        //f wie former member
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_ROLE_PAST') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'b' . $row['rol_id'];        //b wie begin of membership
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_MEMBERSHIP_START') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'e' . $row['rol_id'];        //e wie end of membership
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_MEMBERSHIP_END') . ': ' . $row['rol_name'] . $marker;
                $i++;

                $this->headerSelection[$i]['id'] = 'd' . $row['rol_id'];        //d wie duration of membership
                $this->headerSelection[$i]['cat_name'] = $data['cat_name'];
                $this->headerSelection[$i]['data'] = $gL10n->get('SYS_MEMBERSHIP_DURATION') . ': ' . $row['rol_name'] . $marker;
                $i++;

            }
        }
        //Zusatzspalte fuer die Gesamtrollenuebersicht erzeugen
        $this->headerSelection[$i]['id'] = 'adummy';          //a wie additional
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_ADDITIONAL_COLUMNS');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_ROLE_MEMBERSHIPS');
        $i++;
        
        //Custom column for membership duration
        $this->headerSelection[$i]['id'] = 'ddummy';          //d wie duration
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_ADDITIONAL_COLUMNS');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_MEMBERSHIP_DURATION');
        $i++;

        //Zusatzspalte fuer die Anzahl erzeugen
        $this->headerSelection[$i]['id'] = 'ndummy';          //n wie number
        $this->headerSelection[$i]['cat_name'] = $gL10n->get('SYS_ADDITIONAL_COLUMNS');
        $this->headerSelection[$i]['data'] = $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_ROW') . ')';


        $this->headerRolePropSelection = array(
            array('id' => "r", 'data' => $gL10n->get('SYS_GROUP_ROLE_MEMBERSHIP')),
            array('id' => "w", 'data' => $gL10n->get('SYS_ROLE_WITHOUT_LEADER')),
            array('id' => "l", 'data' => $gL10n->get('SYS_LEADER')),
            array('id' => "f", 'data' => $gL10n->get('SYS_ROLE_PAST')),
            array('id' => "b", 'data' => $gL10n->get('SYS_MEMBERSHIP_START')),
            array('id' => "e", 'data' => $gL10n->get('SYS_MEMBERSHIP_END')),
            array('id' => "d", 'data' => $gL10n->get('SYS_MEMBERSHIP_DURATION')),
        );
    }

    /**
     * Funktion liest das Konfigurationsarray ein
     * @return  array $config  das Konfigurationsarray
     * @throws Exception
     */
    public function getConfigArray(): array
    {
        global $gDb, $gSettingsManager, $gCurrentOrgId;

        if (count($this->arrConfiguration) === 0) {
            $sql = ' SELECT *
                       FROM ' . TBL_CATEGORY_REPORT . '
                      WHERE ( crt_org_id = ? -- $gCurrentOrgId
                         OR crt_org_id IS NULL ) ';
            $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

            while ($row = $statement->fetch()) {
                $values = array();
                $values['id'] = $row['crt_id'];
                $values['name'] = SecurityUtils::encodeHTML($row['crt_name']);
                $values['col_fields'] = $row['crt_col_fields'];
                $values['selection_role'] = $row['crt_selection_role'];
                $values['selection_cat'] = $row['crt_selection_cat'];
                $values['number_col'] = $row['crt_number_col'];
                $values['default_conf'] = false;
                if ($gSettingsManager->getInt('category_report_default_configuration') == $row['crt_id']) {
                    $values['default_conf'] = true;
                }
                $this->arrConfiguration[] = $values;
            }
        }

        return $this->arrConfiguration;
    }

    /**
     * get the active configuration
     */
    public function getConfiguration(): int
    {
        return $this->conf;
    }

    /**
     * Checks whether the transferred value exists in the column selection list.
     * Note: the column selection list is always up-to-date as it is newly generated,
     * but the value to be checked may be out of date as it comes from the configuration table
     * @param string $search_value
     * @return int
     */
    public function isInHeaderSelection(string $search_value): int
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
     * Funktion prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
     *
     * @param int $cat_id ID der zu pruefenden Kategorie
     * @param int $user_id ID des Users, fuer den die Mitgliedschaft geprueft werden soll
     * @return  bool
     * @throws Exception
     */
    private function isMemberOfCategory(int $cat_id, int $user_id = 0): bool
    {
        global $gCurrentUserId, $gDb, $gCurrentOrgId;

        if ($user_id == 0) {
            $user_id = $gCurrentUserId;
        } elseif (is_numeric($user_id) === false) {
            return false;
        }

        $sql = 'SELECT mem_id
                  FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . '
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
            return true;
        } else {
            return false;
        }
    }

    /**
     * Funktion speichert das Konfigurationsarray
     * @param array $arrConfiguration
     * @return  array das Konfigurationsarray
     * @throws Exception
     */
    public function saveConfigArray(array $arrConfiguration): array
    {
        global $gDb, $gCurrentOrgId, $gSettingsManager;

        $defaultConfiguration = 0;

        $gDb->startTransaction();

        foreach ($arrConfiguration as $values) {
            if ($values['id'] === '' || $values['id'] > 0) {                  // id > 0 (=edit a configuration) or '' (=append a configuration)
                $categoryReport = new Entity($gDb, TBL_CATEGORY_REPORT, 'crt', $values['id']);
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
                $values['id'] = $values['id'] * (-1);
                $categoryReport = new Entity($gDb, TBL_CATEGORY_REPORT, 'crt', $values['id']);
                $categoryReport->delete();
            }
        }

        $gDb->endTransaction();

        $this->arrConfiguration = array();

        return $this->getConfigArray();
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
}
