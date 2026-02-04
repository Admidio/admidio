<?php

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Roles\ValueObject\ConditionParser;
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
 */
class CategoryReport
{
    public array $headerData = array();          ///< Array mit allen Spaltenueberschriften
    public array $listData = array();          ///< Array mit den Daten für den Report
    public array $headerSelection = array();          ///< Array mit der Auswahlliste für die Spaltenauswahl
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
    public function generate_listData()
    {
        global $gDb, $gProfileFields, $gL10n, $gCurrentOrgId;

        $workArray = array();
        $number_row_pos = -1;
        $number_col = array();

        $columns = explode(',', $this->arrConfiguration[$this->conf]['col_fields']);

        // Optional per-column conditions (aligned with col_fields)
        $conditions = array();
        if (!empty($this->arrConfiguration[$this->conf]['col_conditions'])) {
            $conditions = explode(',', (string) $this->arrConfiguration[$this->conf]['col_conditions']);
        }

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

            $workArray[$key + 1]['type'] = $type;
            $workArray[$key + 1]['id'] = $id;

            // store the optional condition for this column
            $workArray[$key + 1]['condition'] = $conditions[$key] ?? '';

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
             				   AND mem_begin <= ? -- DATE_NOW
           					   AND mem_end    > ? -- DATE_NOW ';
                    $queryParams = array(
                        $id,
                        DATE_NOW,
                        DATE_NOW
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


        // ---------------------------------------------------------------------
        // Apply per-column conditions (similar to myList) by translating them into SQL
        // and pre-filtering the user ids. Conditions are only applied to:
        //  - profile fields (type 'p')
        //  - user fields (type 'u' : uuid, login_name, text, last_login, number_login)
        // ---------------------------------------------------------------------
        $conditionSqlJoins = '';
        $conditionSqlWhere = '';
        $conditionUsesUserTable = false;

        foreach ($workArray as $colKey => $colDef) {
            $rawCond = trim((string)($colDef['condition']));
            if ($rawCond === '') {
                continue;
            }

            $type = $colDef['type'];
            $parser = new ConditionParser();

            if ($type === 'p') {
                $usfId = (int)($colDef['id'] ?? 0);
                if ($usfId <= 0) {
                    continue;
                }

                // add a dedicated LEFT JOIN for this profile field (so empty fields can be tested as well)
                $alias = 'usd' . $colKey;
                $conditionSqlJoins .= ' LEFT JOIN ' . TBL_USER_DATA . ' ' . $alias . '
                                           ON ' . $alias . '.usd_usr_id = usr_id
                                          AND ' . $alias . '.usd_usf_id = ' . $usfId . ' ';

                // detect the value type (same logic as in myList)
                $userFieldType = $gProfileFields->getPropertyById($usfId, 'usf_type');
                $typeHint = 'string';
                $id = (int) $colDef['id'];
                $field = (string) $colDef['field'];
                switch ($userFieldType) {
                    case 'CHECKBOX':
                        $typeHint = 'checkbox';
                        // 'yes'/'no' will be replaced with 1/0 so it can be compared with the database value
                        $arrCheckboxValues = array($gL10n->get('SYS_YES'), $gL10n->get('SYS_NO'), 'true', 'false');
                        $arrCheckboxKeys = array(1, 0, 1, 0);
                        $rawCond = str_replace(
                            array_map(array(StringUtils::class, 'strToLower'), $arrCheckboxValues),
                            $arrCheckboxKeys,
                            StringUtils::strToLower($rawCond)
                        );
                        break;

                    case 'DROPDOWN': // fallthrough
                    case 'RADIO_BUTTON':
                        $typeHint = 'int';
                        // replace all field values with their internal numbers
                        $arrOptions = $gProfileFields->getPropertyById($usfId, 'ufo_usf_options', 'text');
                        $rawCond = array_search(
                            StringUtils::strToLower($rawCond),
                            array_map(array(StringUtils::class, 'strToLower'), $arrOptions),
                            true
                        );
                        break;

                    case 'NUMBER': // fallthrough
                    case 'DECIMAL':
                        $typeHint = 'int';
                        break;

                    case 'DATE':
                        $typeHint = 'date';
                        break;

                    default:
                        $typeHint = 'string';
                }

                // if profile field then add NOT EXISTS statement (same idea as in myList)
                $parser->setNotExistsStatement('SELECT 1
                                                  FROM ' . TBL_USER_DATA . ' ' . $alias . 's
                                                 WHERE ' . $alias . 's.usd_usr_id = usr_id
                                                   AND ' . $alias . 's.usd_usf_id = ' . $usfId);

                $conditionSqlWhere .= $parser->makeSqlStatement(
                    (string)$rawCond,
                    $alias . '.usd_value',
                    $typeHint,
                    $gProfileFields->getPropertyById($usfId, 'usf_name')
                );
                continue;
            }

            if ($type === 'u') {
                // map the supported special user fields to database columns
                $dbCol = '';
                $typeHint = 'string';

                switch ($colDef['field']) {
                    case 'uuid':
                        $dbCol = 'usr_uuid';
                        $typeHint = 'string';
                        break;

                    case 'login_name':
                        $dbCol = 'usr_login_name';
                        $typeHint = 'string';
                        break;

                    case 'text':
                        $dbCol = 'usr_text';
                        $typeHint = 'string';
                        break;

                    case 'last_login':
                        $dbCol = 'usr_last_login';
                        $typeHint = 'date';
                        break;

                    case 'number_login':
                        $dbCol = 'usr_number_login';
                        $typeHint = 'int';
                        break;

                    // photo is not filterable, ignore
                    default:
                        $dbCol = '';
                }

                if ($dbCol === '') {
                    continue;
                }

                $conditionUsesUserTable = true;
                $conditionSqlWhere .= $parser->makeSqlStatement((string)$rawCond, $dbCol, $typeHint, $dbCol);
            }
            // handle all other column types (role/category/membership related and dummy columns)
            // For these types we build an SQL expression (often via EXISTS-subqueries) that can be filtered by ConditionParser.
            // This allows conditions for ALL column types, not only profile/user fields.
            $expr = '';
            $typeHint = 'string';

            // normalize checkbox-like textual values (yes/no/true/false) to 1/0
            $normalizeYesNo = static function ($v) {
                $arrCheckboxValues = array('yes', 'no', 'true', 'false');
                $arrCheckboxKeys   = array(1, 0, 1, 0);
                return str_replace($arrCheckboxValues, $arrCheckboxKeys, StringUtils::strToLower((string) $v));
            };

            switch ($type) {
                // membership boolean flags ---------------------------------------------------------
                case 'c': // current member of any role in category id
                    $typeHint = 'checkbox';
                    $rawCond  = $normalizeYesNo($rawCond);
                    $expr = '(EXISTS (
                                SELECT 1
                                  FROM ' . TBL_CATEGORIES . ' c2
                                 INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_cat_id = c2.cat_id
                                 INNER JOIN ' . TBL_MEMBERS . ' m2 ON m2.mem_rol_id = r2.rol_id
                                 WHERE c2.cat_id = ' . (int) $id . '
                                   AND c2.cat_type = \'ROL\'
                                   AND ( c2.cat_org_id = ' . (int) $gCurrentOrgId . ' OR c2.cat_org_id IS NULL )
                                   AND r2.rol_valid  = true
                                   AND m2.mem_usr_id = usr_id
                                   AND m2.mem_begin <= \'' . $date . '\'
                                   AND m2.mem_end    > \'' . $date . '\'
                            ))';
                    break;

                case 'r': // current member of role id
                    $typeHint = 'checkbox';
                    $rawCond  = $normalizeYesNo($rawCond);
                    $expr = '(EXISTS (
                                SELECT 1
                                  FROM ' . TBL_MEMBERS . ' m2
                                 INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_id = m2.mem_rol_id
                                 INNER JOIN ' . TBL_CATEGORIES . ' c2 ON c2.cat_id = r2.rol_cat_id AND c2.cat_type = \'ROL\'
                                 WHERE r2.rol_id = ' . (int) $id . '
                                   AND r2.rol_valid  = true
                                   AND m2.mem_usr_id = usr_id
                                   AND m2.mem_begin <= \'' . $date . '\'
                                   AND m2.mem_end    > \'' . $date . '\'
                            ))';
                    break;

                case 'l': // current leader of role id
                    $typeHint = 'checkbox';
                    $rawCond  = $normalizeYesNo($rawCond);
                    $expr = '(EXISTS (
                                SELECT 1
                                  FROM ' . TBL_MEMBERS . ' m2
                                 INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_id = m2.mem_rol_id
                                 INNER JOIN ' . TBL_CATEGORIES . ' c2 ON c2.cat_id = r2.rol_cat_id AND c2.cat_type = \'ROL\'
                                 WHERE r2.rol_id = ' . (int) $id . '
                                   AND r2.rol_valid  = true
                                   AND m2.mem_leader = true
                                   AND m2.mem_usr_id = usr_id
                                   AND m2.mem_begin <= \'' . $date . '\'
                                   AND m2.mem_end    > \'' . $date . '\'
                            ))';
                    break;

                case 'w': // current member (not leader) of role id
                    $typeHint = 'checkbox';
                    $rawCond  = $normalizeYesNo($rawCond);
                    $expr = '(EXISTS (
                                SELECT 1
                                  FROM ' . TBL_MEMBERS . ' m2
                                 INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_id = m2.mem_rol_id
                                 INNER JOIN ' . TBL_CATEGORIES . ' c2 ON c2.cat_id = r2.rol_cat_id AND c2.cat_type = \'ROL\'
                                 WHERE r2.rol_id = ' . (int) $id . '
                                   AND r2.rol_valid  = true
                                   AND (m2.mem_leader IS NULL OR m2.mem_leader = false)
                                   AND m2.mem_usr_id = usr_id
                                   AND m2.mem_begin <= \'' . $date . '\'
                                   AND m2.mem_end    > \'' . $date . '\'
                            ))';
                    break;

                case 'f': // former member of role id (as of report date)
                    $typeHint = 'checkbox';
                    $rawCond  = $normalizeYesNo($rawCond);
                    $expr = '(EXISTS (
                                SELECT 1
                                  FROM ' . TBL_MEMBERS . ' m2
                                 INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_id = m2.mem_rol_id
                                 INNER JOIN ' . TBL_CATEGORIES . ' c2 ON c2.cat_id = r2.rol_cat_id AND c2.cat_type = \'ROL\'
                                 WHERE r2.rol_id = ' . (int) $id . '
                                   AND r2.rol_valid  = true
                                   AND m2.mem_usr_id = usr_id
                                   AND m2.mem_begin <  \'' . $date . '\'
                                   AND m2.mem_end   <  \'' . $date . '\'
                            ))';
                    break;

                // membership dates ---------------------------------------------------------------
                case 'b': // membership start date (current membership as of report date)
                    $typeHint = 'date';
                    $expr = '(SELECT MIN(m2.mem_begin)
                                FROM ' . TBL_MEMBERS . ' m2
                               WHERE m2.mem_usr_id = usr_id
                                 AND m2.mem_rol_id = ' . (int) $id . '
                                 AND m2.mem_begin <= \'' . $date . '\'
                                 AND m2.mem_end    > \'' . $date . '\')';
                    break;

                case 'e': // membership end date (current membership as of report date)
                    $typeHint = 'date';
                    $expr = '(SELECT MAX(m2.mem_end)
                                FROM ' . TBL_MEMBERS . ' m2
                               WHERE m2.mem_usr_id = usr_id
                                 AND m2.mem_rol_id = ' . (int) $id . '
                                 AND m2.mem_begin <= \'' . $date . '\'
                                 AND m2.mem_end    > \'' . $date . '\')';
                    break;

                case 'd':
                    // two meanings:
                    //   d#     -> membership duration of role with ID # (in days, as of report date)
                    //   ddummy -> overall membership duration (as of report date)

                    // If condition uses a unit suffix (d/w/m/y) we switch to DATE mode:
                    // We then compare membership START DATE against a threshold date (reportDate - X).
                    $useDateMode = (bool) preg_match('/^\s*([<>]=?|=|[{}]=?)\s*(\d+)\s*[dwmy]\s*$/i', (string) $rawCond);
                    if ($colDef['field'] === 'dummy' || (int) $id === 0) {
                        if ($useDateMode) {
                            // ddummy as DATE: earliest membership start in org
                            $typeHint = 'date';
                            $expr = '(SELECT MIN(m2.mem_begin)
                        FROM ' . TBL_CATEGORIES . ' c2
                       INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_cat_id = c2.cat_id
                       INNER JOIN ' . TBL_MEMBERS . ' m2 ON m2.mem_rol_id = r2.rol_id
                       WHERE c2.cat_type = \'ROL\'
                         AND ( c2.cat_org_id = ' . (int) $gCurrentOrgId . ' OR c2.cat_org_id IS NULL )
                         AND r2.rol_valid  = true
                         AND m2.mem_usr_id = usr_id)';
                        } else {
                            // ddummy as INT: duration in days since earliest membership start in org
                            if (preg_match('/^\s*([<>]=?|=|[{}]=?)\s*(\d+)\s*y\s*$/i', (string) $rawCond, $m)) {
                                $rawCond = $m[1] . ' ' . ((int) $m[2] * 365);
                            }
                            $typeHint = 'int';
                            $expr = '(SELECT DATEDIFF(\'' . $date . '\', MIN(m2.mem_begin))
                        FROM ' . TBL_CATEGORIES . ' c2
                       INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_cat_id = c2.cat_id
                       INNER JOIN ' . TBL_MEMBERS . ' m2 ON m2.mem_rol_id = r2.rol_id
                       WHERE c2.cat_type = \'ROL\'
                         AND ( c2.cat_org_id = ' . (int) $gCurrentOrgId . ' OR c2.cat_org_id IS NULL )
                         AND r2.rol_valid  = true
                         AND m2.mem_usr_id = usr_id)';
                        }
                    } else {
                        if ($useDateMode) {
                            // d# as DATE: membership start (current membership as of report date)
                            $typeHint = 'date';
                            $expr = '(SELECT MIN(m2.mem_begin)
                        FROM ' . TBL_MEMBERS . ' m2
                       WHERE m2.mem_usr_id = usr_id
                         AND m2.mem_rol_id = ' . (int) $id . '
                         AND m2.mem_begin <= \'' . $date . '\'
                         AND m2.mem_end    > \'' . $date . '\')';
                        } else {
                            // d# as INT: duration in days since membership start (current membership as of report date)
                            if (preg_match('/^\s*([<>]=?|=|[{}]=?)\s*(\d+)\s*y\s*$/i', (string) $rawCond, $m)) {
                                $rawCond = $m[1] . ' ' . ((int) $m[2] * 365);
                            }
                            $typeHint = 'int';
                            $expr = '(SELECT DATEDIFF(\'' . $date . '\', MIN(m2.mem_begin))
                        FROM ' . TBL_MEMBERS . ' m2
                       WHERE m2.mem_usr_id = usr_id
                         AND m2.mem_rol_id = ' . (int) $id . '
                         AND m2.mem_begin <= \'' . $date . '\'
                         AND m2.mem_end    > \'' . $date . '\')';
                        }
                    }
                    break;

                // dummy columns -----------------------------------------------------------------
                case 'a': // adummy - all current roles (as of report date)
                    $typeHint = 'string';
                    $expr = '(SELECT GROUP_CONCAT(r2.rol_name ORDER BY r2.rol_name SEPARATOR \', \')
                                FROM ' . TBL_MEMBERS . ' m2
                               INNER JOIN ' . TBL_ROLES . ' r2 ON r2.rol_id = m2.mem_rol_id
                               INNER JOIN ' . TBL_CATEGORIES . ' c2 ON c2.cat_id = r2.rol_cat_id AND c2.cat_type = \'ROL\'
                               WHERE ( c2.cat_org_id = ' . (int) $gCurrentOrgId . ' OR c2.cat_org_id IS NULL )
                                 AND r2.rol_valid  = true
                                 AND m2.mem_usr_id = usr_id
                                 AND m2.mem_begin <= \'' . $date . '\'
                                 AND m2.mem_end    > \'' . $date . '\')';
                    break;

                default:
                    $expr = '';
            }

            if ($expr !== '') {
                // for any condition we rely on usr_id in the SQL, therefore we must join the user table in the main query
                $conditionUsesUserTable = true;
                $conditionSqlWhere .= $parser->makeSqlStatement((string)$rawCond, $expr, $typeHint, $this->headerData[$colKey]['data']);
            }

        }


        // Read in all current members of the current organisation
        // Then add all former members of the groups, where former memberships should be displayed.
        // They will be marked as former members, so the confusion risk is minimized.
        $sql = ' SELECT mem_usr_id
                   FROM ' . TBL_CATEGORIES . '
                  INNER JOIN ' . TBL_ROLES . ' ON rol_cat_id = cat_id
                  INNER JOIN ' . TBL_MEMBERS . ' ON mem_rol_id = rol_id '
                    . ( ($conditionUsesUserTable || $conditionSqlJoins !== '') ? ' INNER JOIN ' . TBL_USERS . ' ON usr_id = mem_usr_id ' : '' )
                    . $conditionSqlJoins . '
                  WHERE cat_type = \'ROL\'
             	    AND ( cat_org_id = ? -- $gCurrentOrgId
               		 OR cat_org_id IS NULL )
             	    AND rol_valid  = true
             	    AND mem_begin <= ? -- $date
           		    AND mem_end    > ? -- $date 
           		    ' . $conditionSqlWhere;
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
            if ((string)$this->arrConfiguration[$this->conf]['selection_role'] !== '') {
                $roleCategoryMarker = false;
                foreach (explode(',', $this->arrConfiguration[$this->conf]['selection_role']) as $rol) {
                    if ($user->isMemberOfRole((int)$rol)) {
                        $roleCategoryMarker = true;
                    }
                }
            }

            if ((string)$this->arrConfiguration[$this->conf]['selection_cat'] !== '') {
                foreach (explode(',', $this->arrConfiguration[$this->conf]['selection_cat']) as $cat) {
                    if ($this->isMemberOfCategory((int)$cat, $member)) {
                        $roleCategoryMarker = true;
                    }
                }
            }
            if (!$roleCategoryMarker) {
                unset($this->listData[$member]);
                continue;
            }

            foreach ($workArray as $key => $data) {
                if ($data['type'] == 'p') {
                    $this->listData[$member][$key] = $user->getValue($gProfileFields->getPropertyById($data['id'], 'usf_name_intern'), 'database');
                } elseif ($data['type'] == 'a') {              //Sonderfall: Rollengesamtuebersicht erstellen
                    $role = new Role($gDb);

                    $this->listData[$member][$key] = '';
                    foreach ($memberShips as $rol_id) {
                        $role->readDataById($rol_id);
                        $this->listData[$member][$key] .= $role->getValue('rol_name') . '; ';
                    }
                    $this->listData[$member][$key] = trim($this->listData[$member][$key], '; ');
                } elseif ($data['type'] == 'd') {              //Sonderfall: Mitgliedschaftsdauer
                    // Get membership durations for all roles
                    $this->listData[$member][$key] = '';
                    $membership = new Membership($gDb);
                    
                    foreach ($memberShips as $rol_id) {
                        $role = new Role($gDb);
                        $role->readDataById($rol_id);
                        
                        // Get membership data for this role
                        $membershipData = $membership->readDataByColumns(array('mem_rol_id' => $rol_id, 'mem_usr_id' => $member));
                        
                        if ($membershipData) {
                            $duration = $membership->calculateDuration();
                            $this->listData[$member][$key] .= $role->getValue('rol_name') . ': ' . $duration['formatted'] . '; ';
                        }
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
                $values['col_conditions'] = $row['crt_col_conditions'];
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
                $categoryReport->setValue('crt_col_conditions', $values['col_conditions']);
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
