<?php
namespace Admidio\Preferences\Entity;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Preferences\Service\PreferenceDefinitions;

/**
 * @brief Class manages access to database table adm_preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Preferences extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_preferences.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $name The recordset of the text with this name will be loaded.
     *                           If name isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database)
    {
        parent::__construct($database, TBL_PREFERENCES, 'prf');
    }
    /**
     * Logs creation of the DB record -> For preferences, no need to log anything as
     * the actual value change from NULL to something will be logged as a modification
     * immediately after creation, anyway.
     * 
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logCreation(): bool { return true; }
    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['prf_name', 'prf_org_id']);
    }
    /**
     * Mask sensitive values of certain preference in the ChangeLog, and name the plugin that a
     * preference belongs to, so that the history of a single plugin can be read on its own.
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        parent::adjustLogEntry($logEntry);

        $preferenceName = (string)$this->getValue('prf_name');

        /*
         * The settings of a plugin are ordinary preferences, so a change to one was always written
         * to the changelog - with nothing to say which plugin it belonged to. Naming the plugin as
         * the related object is what makes an entry readable: the changelog says which plugin a setting
         * belongs to instead of showing a bare preference name.
         */
        $plugin = PluginRegistry::getOwnerOfSetting($preferenceName);
        if ($plugin !== null) {
            /*
             * log_related_id holds the UUID of the related record, so it is the component that
             * records the installed plugin - not the plugin directory, which is not a UUID. A
             * plugin whose preferences change before it has a component record simply gets its
             * name and no link.
             */
            $logEntry->setLogRelated(
                PluginRegistry::getComponentUuid($plugin->id),
                Language::translateIfTranslationStrId($plugin->name)
            );
        }

        if ($logEntry->getValue('log_field') !== 'prf_value') {
            return;
        }

        // Sensitivity is part of the canonical core preference definition. Unknown/plugin
        // preferences keep their existing changelog behavior because their contract is not owned here.
        if (PreferenceDefinitions::exists($preferenceName)
            && PreferenceDefinitions::isSensitive($preferenceName)) {
            $logEntry->setValue('log_value_old', '********');
            $logEntry->setValue('log_value_new', '********');
        }
    }

}
