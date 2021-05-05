<?php
/**
 ***********************************************************************************************
 * Various common functions for the admidio module CategoryReport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');

if(!defined('ORG_ID'))
{
	define('ORG_ID', (int) $gCurrentOrganization->getValue('org_id'));
}

/**
 * Funktion überprueft den übergebenen Namen, ob er gemaess den Namenskonventionen für
 * Profilfelder und Kategorien zum Uebersetzen durch eine Sprachdatei geeignet ist
 * Bsp: SYS_COMMON --> Rueckgabe true
 * Bsp: Mitgliedsbeitrag --> Rueckgabe false
 *
 * @param   string  $field_name
 * @return  bool
 */
function check_languagePCR($field_name)
{
    $ret = false;
 
    //pruefen, ob die ersten 3 Zeichen von $field_name Grußbuchstaben sind
    //pruefen, ob das vierte Zeichen von $field_name ein _ ist

    //Prüfung entfaellt: pruefen, ob die restlichen Zeichen von $field_name Grußbuchstaben sind
    //if ((ctype_upper(substr($field_name,0,3))) && ((substr($field_name,3,1))=='_')  && (ctype_upper(substr($field_name,4)))   )

    if ((ctype_upper(substr($field_name,0,3))) && ((substr($field_name,3,1)) == '_')   )
    {
      $ret = true;
    }
    return $ret;
}
 

/**
 * Funktion prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
 *
 * @param   int  $cat_id    ID der zu pruefenden Kategorie
 * @param   int  $user_id   ID des Users, fuer den die Mitgliedschaft geprueft werden soll
 * @return  bool
 */
function isMemberOfCategorie($cat_id, $user_id = 0)
{
    global $gCurrentUser, $gDb;

    if ($user_id == 0)
    {
        $user_id = $gCurrentUser->getValue('usr_id');
    }
    elseif (is_numeric($user_id) == false)
    {
        return -1;
    }

    $sql    = 'SELECT mem_id
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_usr_id = ? -- $user_id
                  AND mem_begin <= ? -- DATE_NOW
                  AND mem_end    > ? -- DATE_NOW
                  AND mem_rol_id = rol_id
                  AND cat_id   = ? -- $cat_id
                  AND rol_valid  = 1
                  AND rol_cat_id = cat_id
                  AND (  cat_org_id = ? -- ORG_ID
                   OR cat_org_id IS NULL ) ';
    
    $queryParams = array(
        $user_id,
        DATE_NOW,
        DATE_NOW,
        $cat_id,
        ORG_ID
    );
    $statement = $gDb->queryPrepared($sql, $queryParams);
    $user_found = $statement->rowCount();

    if ($user_found == 1)
    {
        return 1;
    }
    else
    {
        return 0;
    }   
}

/**
 * Funktion prüft, ob es eine Konfiguration mit dem übergebenen Namen bereits gibt
 * wenn ja: wird "- Kopie" angehängt und rekursiv überprüft
 * @param   string  $name
 * @return  string  
 */
function createColDescConfig($name)
{
    global $config, $gL10n;
    
    while (in_array($name, $config['col_desc']))
    {
        $name .= ' - '.$gL10n->get('MAI_CARBON_COPY');
    }

    return $name;
}

/**
 * Funktion initialisiert das Konfigurationsarray
 * @param   none
 * @return  Array $config  das Konfigurationsarray 
 */
function initConfigArray()
{
    global $gL10n, $gProfileFields;
    
    $config = array('col_desc' 		=> array($gL10n->get('CRT_PATTERN')),
                    'col_fields' 	=> array('p'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('LAST_NAME', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('STREET', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('CITY', 'usf_id')),
                    'col_yes'		=> array('ja'),
                    'col_no'		=> array('nein'),
                    'selection_role'=> array(' '),
                    'selection_cat'	=> array(' '),
                    'number_col'	=> array(0)  );
        
    return $config;
}

/**
 * Funktion liest das Konfigurationsarray ein
 * @param   none
 * @return  Array $config  das Konfigurationsarray
 */
function getConfigArray()
{
    global  $gDb; 

    $config = array();
    $i = 0;
    
    $tableName = TABLE_PREFIX . '_category_report';

    $sql = ' SELECT *
               FROM '.$tableName.'
              WHERE ( crt_org_id = ?
                 OR crt_org_id IS NULL ) ';
    $statement = $gDb->queryPrepared($sql, array( ORG_ID));
        
    while($row = $statement->fetch())
    {
        $config['col_desc'][$i]       = $row['crt_col_desc'];
        $config['col_fields'][$i]     = $row['crt_col_fields'];
        $config['col_yes'][$i]        = $row['crt_col_yes'];
        $config['col_no'][$i]         = $row['crt_col_no'];
        $config['selection_role'][$i] = $row['crt_selection_role'];
        $config['selection_cat'][$i]  = $row['crt_selection_cat'];
        $config['number_col'][$i]     = $row['crt_number_col'];
        ++$i;
    }
    return $config;
}

/**
 * Funktion speichert das Konfigurationsarray
 * @param   none
 */
function saveConfigArray()
{
    global  $config, $gDb;
    
    $tableName = TABLE_PREFIX . '_category_report';
    $numConfig = count($config['col_desc']);
    $crtDb = array();
        
    $sql = ' SELECT crt_id
               FROM '.$tableName.'
              WHERE ( crt_org_id = ?
                 OR crt_org_id IS NULL ) ';
    $statement = $gDb->queryPrepared($sql, array( ORG_ID));
        
    while($row = $statement->fetch())
    {
        $crtDb[] = $row['crt_id'];
    }
    
    $numCrtDb = count($crtDb);
        
    for($i = $numConfig; $i < $numCrtDb; ++$i)
    {
        $categoryReport = new TableAccess($gDb, TABLE_PREFIX . '_category_report', 'crt', $crtDb[$i]);
        $categoryReport->delete();
        unset($crtDb[$i]);
    }
        
    foreach ($config['col_desc'] as $i => $dummy)
    {
        $categoryReport = new TableAccess($gDb, TABLE_PREFIX . '_category_report', 'crt');
        if (isset($crtDb[$i]))
        {
            $categoryReport->readDataById($crtDb[$i]);
        }
            
        $categoryReport->setValue('crt_org_id', ORG_ID);
        $categoryReport->setValue('crt_col_desc', $config['col_desc'][$i]);
        $categoryReport->setValue('crt_col_fields', $config['col_fields'][$i]);
        $categoryReport->setValue('crt_col_yes', $config['col_yes'][$i]);
        $categoryReport->setValue('crt_col_no', $config['col_no'][$i]);
        $categoryReport->setValue('crt_selection_role', $config['selection_role'][$i]);
        $categoryReport->setValue('crt_selection_cat', $config['selection_cat'][$i]);
        $categoryReport->setValue('crt_number_col', $config['number_col'][$i]);
        $categoryReport->save();
    }
    return;
}



