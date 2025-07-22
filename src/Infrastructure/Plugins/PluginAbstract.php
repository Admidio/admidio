<?php
namespace Admidio\Infrastructure\Plugins;

use Admidio\Preferences\Service\PreferencesService;
use Admidio\Components\Entity\Component;
use Admidio\Components\Entity\ComponentUpdate;

use InvalidArgumentException;
use Exception;

/**
 * Class PluginAbstract
 */
abstract class PluginAbstract implements PluginInterface
{
    private static $instances = array();
    private static $pluginComId = 0;
    
    protected static $pluginPath = '';
    protected static $name = '';
    protected static $version = '0.0.0';
    protected static $dependencies = array();
    protected static $metadata = array();
    protected static $defaultConfig = array();

    /**
     *
     */
    protected function __construct()
    {
        // set the plugin path to the folder of this class
        $reflection = new \ReflectionClass($this);
        self::$pluginPath = dirname($reflection->getFileName(), 2);
    }

    /**
     * Singleton Class! Stop cloning this class!
     */
    private function __clone()
    {

    }

    /**
     * Singleton Class! Stop unserializing this class!
     */
    public function __wakeup()
    {

    }

    public static function initPreferencePanelCallback(): void
    {
        // find a preference panel for this plugin
        $preferencesFile =  self::getPluginPath() . '/classes/Presenter/' . basename(self::getPluginPath()) . 'PreferencesPresenter.php';
        $preferencesClass = is_file($preferencesFile) ? self::getClassNameFromFile($preferencesFile) : null;
        if (isset($preferencesClass) && class_exists($preferencesClass)) {
            // get the function name for the preferences panel
            $functionName = 'create' . basename(self::getPluginPath()) . 'Form';
            if (!method_exists($preferencesClass, $functionName)) {
                throw new Exception('The preferences class ' . $preferencesClass . ' does not have a method ' . $functionName . '().');
            }
            // register the preferences presenter for this plugin
            PreferencesService::addPluginPreferencesPresenter(
                self::getComponentId(),
                [ $preferencesClass, $functionName ]
            );
        }
    }

    /**
     * Parse a PHP file and return the first class name found.
     */
    private static function getClassNameFromFile(string $file): ?string
    {
        $src = file_get_contents($file);
        $tokens = token_get_all($src);
        $namespace = '';
        $class = null;
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i++;
                while ($tokens[$i][0] === T_WHITESPACE) $i++;
                while (in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR])) {
                    $namespace .= $tokens[$i++][1];
                }
            }
            if ($tokens[$i][0] === T_CLASS) {
                // skip whitespace
                $i++;
                while ($tokens[$i][0] === T_WHITESPACE) $i++;
                $class = $tokens[$i][1];
                break;
            }
        }
        if ($class) {
            return $namespace ? "$namespace\\$class" : $class;
        }
        return null;
    }

    /**
     * Reads the plugin metadata from the plugin file.
     *
     * @param string $class
     * @throws Exception
     */
    private function readPluginMetadata() : void
    {
        // get the plugin name, version and metadata from the plugin file
        $configFiles = self::getStaticFiles('json');
        if (!isset($configFiles) || count($configFiles) === 0) {
            //throw new Exception('Plugin configuration file not found.');
            return;
        } else {
            $configFile = $configFiles[0];
        }
        $configData = json_decode(file_get_contents($configFile), true);
        if ($configData === null) {
            throw new Exception('Plugin configuration file is not valid JSON.');
        } else {
            self::$name = $configData['name'] ?? '';
            self::$dependencies = $configData['dependencies'] ?? array();
            self::$defaultConfig = $configData['defaultConfig'] ?? array();
            self::$metadata = $configData;
        }
    }

    /**
     * @return PluginAbstract
     */
    public static function getInstance() : PluginAbstract
    {
        $class = get_called_class();
        if (!array_key_exists($class, self::$instances))
        {
            self::$instances[$class] = new $class();
            self::$instances[$class]->doClassAutoload();
            self::$instances[$class]->readPluginMetadata();

            // check if the plugin is installed
            if (self::$instances[$class]->isInstalled()) {
                global $gDb;
                // get the component id of the plugin
                $sql = 'SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND (com_type = ? OR com_type = ?)';
                $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN', 'ADM_PLUGIN'));
                self::$pluginComId = (int)$statement->fetchColumn();

                // get the installed version of the plugin
                $sql = 'SELECT com_version FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND (com_type = ? OR com_type = ?)';
                $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN', 'ADM_PLUGIN'));
                self::$version = (string)$statement->fetchColumn();
            }
        }

        return self::$instances[$class];
    }

    /**
     * @return string
     */
    public static function getName() : string
    {
        return self::$name;
    }

    /**
     * @return string
     */
    public static function getVersion() : string
    {
        return self::$version;
    }

    /**
     * @return array
     */
    public static function getMetadata() : array
    {

        return self::$metadata;
    }

    /**
     * @return array
     */
    public static function getDependencies() : array
    {
        return self::$dependencies;
    }

    /**
     * @return array
     */
    public static function getSupportedLanguages() : array
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'languages';

        $langFiles = array();
        foreach (scandir($dir) as $entry)
        {
            $entryPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $entryInfo = pathinfo($entryPath);
            if (is_file($entryPath) && $entryInfo['extension'] === 'xml')
            {
                $langFiles[] = $entryInfo['filename'];
            }
        }

        return $langFiles;
    }

    /**
     * @param string $type
     * @throws InvalidArgumentException
     * @throws Exception
     * @return array
     */
    public static function getStaticFiles(?string $type = null) : array
    {
        if ($type !== null && !is_string($type))
        {
            throw new InvalidArgumentException('Type must be "null" or a "string".');
        }

        if (!is_dir(self::$pluginPath))
        {
            throw new Exception('Plugin path does not exist: ' . self::$pluginPath);
        }

        $files = array();
        foreach (scandir(self::$pluginPath) as $entry)
        {
            $entryPath = self::$pluginPath . DIRECTORY_SEPARATOR . $entry;
            if (is_file($entryPath))
            {
                $entryInfo = pathinfo($entryPath);

                if (!array_key_exists($entryInfo['extension'], $files))
                {
                    $files[$entryInfo['extension']] = array();
                }

                $files[$entryInfo['extension']][] = $entryPath;
            }
        }

        if ($type === null)
        {
            return $files;
        }
        else
        {
            return $files[$type];
        }
    }

    public static function getPluginConfigValues() : array
    {
        global $gSettingsManager;
        $config = array();

        // loop over all default config keys and get their values from the database if the key exists
        foreach (self::$defaultConfig as $key => $value) {
            if ($gSettingsManager->has($key)) {
                switch ($value['type']) {
                    case 'integer':
                        $config[$key] = $gSettingsManager->getInt($key);
                        break;
                    case 'boolean':
                        $config[$key] = $gSettingsManager->getBool($key);
                        break;
                    case 'array':
                        $valueString = $gSettingsManager->get($key);
                        $config[$key] = $valueString === "" ? array() : explode(',', $valueString);
                        break;
                    case 'string':
                    default:
                        $config[$key] = $gSettingsManager->get($key);
                        break;
                }
            } else {
                $config[$key] = $value['value'];
            }
        }

        return $config;
    }

    /**
     * Get the plugin configuration
     * @return array Returns the plugin configuration
     */
    public static function getPluginConfig() : array
    {
        $config = self::$defaultConfig;
        // get the plugin config values from the database
        $values = self::getPluginConfigValues();
        // loop over all default config keys and set their current values
        foreach ($config as $key => $value) {
            $config[$key]['value'] = $values[$key];
        }
        return $config;
    }

    public static function getPluginPath() : string
    {
        return self::$pluginPath;
    }

    /**
     * @throws Exception
     * @return int
     */
    public static function getComponentId() : int
    {
        return self::$pluginComId;
    }

    /**
     * @throws Exception
     * @return string
     */
    public static function getComponentName() : string
    {
        return basename(self::$pluginPath);
    }
    
    /**
     * @throws Exception
     * @return bool
     */
    public static function isInstalled() : bool
    {
        global $gDb;
        // check if the plugin exists in components database table
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND (com_type = ? OR com_type = ?)';
        $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN', 'ADM_PLUGIN'));
        $columns = (int)$statement->fetchColumn();

        return $columns > 0;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function isActivated() : bool
    {
        return self::isInstalled() && (self::getComponentId() > 0);
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function isOverviewPlugin() : bool
    {
        global $gDb;
        // check if the plugin exists in components database table and is of type 'ADM_PLUGIN'
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND com_type = ?';
        $statement = $gDb->queryPrepared($sql, array(self::getName(), 'ADM_PLUGIN'));
        $columns = (int)$statement->fetchColumn();

        return $columns > 0;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function isUpdateAvailable() : bool
    {
        global $gDb;
        // check if the plugin exists in components database table
        $sql = 'SELECT com_version FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND (com_type = ? OR com_type = ?)';
        $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN', 'ADM_PLUGIN'));
        $currentVersion = $statement->fetchColumn();

        return version_compare($currentVersion, self::getVersion(), '<');
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function doClassAutoload() : bool
    {
        $autoloadPath = ADMIDIO_PATH . '/vendor/autoload.php';

        if (is_file($autoloadPath))
        {
            require_once($autoloadPath);

            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function doInstall() : bool
    {
        global $gDb, $gSettingsManager;

        // check if the plugin is already installed
        if (self::isInstalled()) {
            return false;
        }

        // insert default plugin config values into the database
        $gSettingsManager->setMulti(self::getPluginConfigValues());

        // install the plugin
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'PLUGIN', 'com_name' => self::getName(), 'com_name_intern' => basename(self::$pluginPath)));
        $componentUpdateHandle->updatePlugin(self::$metadata['version']);

        // set the new component id of the plugin
        self::$pluginComId = $componentUpdateHandle->getValue('com_id');

        // set the installed version of the plugin
        self::$version = self::$metadata['version'];

        // perform additional installation tasks
        // TODO: implement function to perform updateSteps for the plugin
        // e.g.: $componentUpdateHandle->doUpdateSteps();

        return true;
    }

    /**
     * @param array $options
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doUninstall(array $options = array()) : bool
    {
        if (!is_array($options))
        {
            throw new InvalidArgumentException('Options must be an "array".');
        }

        // check if the plugin is installed
        if (!self::isInstalled()) {
            return false;
        }

        global $gDb, $gSettingsManager;

        // delete the plugin config values from the database
        foreach (self::getPluginConfigValues() as $key => $value) {
            if ($gSettingsManager->has($key)) {
                $gSettingsManager->del($key);
            }
        }

        // update $gSettingsManager to remove the plugin config values
        $gSettingsManager->resetAll();

        // delete the plugin from the components table
        $plugin = new Component($gDb, self::$pluginComId);
        $plugin->delete();

        // reset the plugin component id
        self::$pluginComId = 0;

        // reset the installed version of the plugin
        self::$version = '0.0.0';

        // perform additional uninstallation tasks
        // TODO: implement function to perform additional uninstallation tasks for the plugin
        return true;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public static function doUpdate() : bool
    {
        global $gDb, $gSettingsManager;

        // check if the plugin is installed
        if (!self::isInstalled()) {
            return false;
        }

        // add new plugin config values to the database
        $gSettingsManager->setMulti(self::getPluginConfigValues(), false);

        // update the plugin 
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_name' => self::getName(), 'com_name_intern' => basename(self::$pluginPath)));
        $componentUpdateHandle->updatePlugin(self::$metadata['version']);

        // set the installed version of the plugin
        self::$version = self::$metadata['version'];

        // perform additional update tasks
        // TODO: implement function to perform updateSteps for the plugin
        // e.g.: $componentUpdateHandle->doUpdateSteps();
        return true;
    }
}