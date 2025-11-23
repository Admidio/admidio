<?php
namespace Admidio\ProfileFields\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\ProfileFields\Entity\ProfileField;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ProfileFieldService
{
    protected ProfileField $profileFieldRessource;
    protected Database $db;
    protected string $UUID;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $profileFieldUUID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $profileFieldUUID = '')
    {
        $this->db = $database;
        $this->profileFieldRessource = new ProfileField($database);
        $this->UUID = $profileFieldUUID;

        if ($profileFieldUUID !== '') {
            $this->profileFieldRessource->readDataByUuid($profileFieldUUID);
        }
    }

    /**
     * Delete the current profile field form into the database.
     * @throws Exception
     */
    public function delete()
    {
        $this->profileFieldRessource->delete();
    }

    /**
     * Save data from the profile field form into the database.
     * @throws Exception
     */
    public function save()
    {
        global $gCurrentSession, $gDb;

        // lastname and firstname must always be mandatory fields and visible in registration dialog
        if ($this->profileFieldRessource->getValue('usf_name_intern') === 'LAST_NAME'
            || $this->profileFieldRessource->getValue('usf_name_intern') === 'FIRST_NAME') {
            $_POST['usf_required_input'] = 1;
            $_POST['usf_registration'] = 1;
        }

        // email must always be visible in registration dialog
        if ($this->profileFieldRessource->getValue('usf_name_intern') === 'EMAIL') {
            $_POST['usf_registration'] = 1;
        }

        // Swap input, because the field name is different from the dialog
        if (isset($_POST['usf_hidden'])) {
            $_POST['usf_hidden'] = 0;
        } else {
            $_POST['usf_hidden'] = 1;
        }

        // check form field input and sanitized it from malicious content
        $profileFieldsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $profileFieldsEditForm->validate($_POST);

        if (isset($_POST['usf_name']) && $this->profileFieldRessource->getValue('usf_name') !== $_POST['usf_name']) {
            // See if the field already exists
            $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_name   = ? -- $_POST[\'usf_name\']
                   AND usf_cat_id = ? -- $_POST[\'usf_cat_id\']
                   AND usf_uuid  <> ? -- $getUsfUUID';
            $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usf_name'], (int)$_POST['usf_cat_id'], $this->UUID));

            if ($pdoStatement->fetchColumn() > 0) {
                throw new Exception('ORG_FIELD_EXIST');
            }
        }

        // write form values in user field object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'usf_')) {
                $this->profileFieldRessource->setValue($key, $value);
            } elseif (str_starts_with($key, 'ufo_')) {
                // if the key starts with 'ufo_' then it is a user field option,
                // and we save it in the user field options table
                $options = array_map(function($item) {
                    if ($item['obsolete'] === '') {
                        $item['obsolete'] = '0';
                    }
                    return $item;
                }, $value);
            }
        }

        $this->profileFieldRessource->save();

        // safe the field options after the new field has been saved
        if (isset($options) && is_array($options)) {
            $this->profileFieldRessource->setSelectOptions($options);
        }
    }
}
