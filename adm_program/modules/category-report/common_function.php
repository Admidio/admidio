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

/**
 * Funktion prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
 *
 * @param   int  $cat_id    ID der zu pruefenden Kategorie
 * @param   int  $user_id   ID des Users, fuer den die Mitgliedschaft geprueft werden soll
 * @return  bool
 */
function isMemberOfCategorie($cat_id, $user_id = 0)
{
    global $gCurrentUser, $gDb, $gCurrentOrganization;

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
                  AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL ) ';

    $queryParams = array(
        $user_id,
        DATE_NOW,
        DATE_NOW,
        $cat_id,
        $gCurrentOrganization->getValue('org_id')
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

    while (in_array($name, $config['name']))
    {
        $name .= ' - '.$gL10n->get('SYS_CARBON_COPY');
    }

    return $name;
}

/**
 * Funktion speichert das Konfigurationsarray
 * @param   none
 */
function saveConfigArray(array $arrConfiguration)
{
    global  $gDb, $gCurrentOrganization, $gSettingsManager;

    $crtDb = array();
    $defaultConfiguration = 0;

    $gDb->startTransaction();

    // delete all existing configurations from the current organization
    $sql = 'DELETE FROM '.TBL_CATEGORY_REPORT.'
             WHERE crt_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\') ';
    $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id')));

    // write all existing configurations
    foreach ($arrConfiguration as $key => $values)
    {
        $categoryReport = new TableAccess($gDb, TBL_CATEGORY_REPORT, 'crt');
        $categoryReport->setValue('crt_org_id', $gCurrentOrganization->getValue('org_id'));
        $categoryReport->setValue('crt_name',       $values['name']);
        $categoryReport->setValue('crt_col_fields', $values['col_fields']);
        $categoryReport->setValue('crt_selection_role', $values['selection_role']);
        $categoryReport->setValue('crt_selection_cat', $values['selection_cat']);
        $categoryReport->setValue('crt_number_col', $values['number_col']);
        $categoryReport->save();

        if($values['default_conf'] === true || $defaultConfiguration === 0)
        {
            $defaultConfiguration = $categoryReport->getValue('crt_id');
        }
    }

    // set default configuration
    $gSettingsManager->set('category_report_default_configuration', $defaultConfiguration);

    $gDb->endTransaction();

    return true;
}


