<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Preferences\Service\PreferencesService;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Components\Entity\Component;
use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;

use Composer\Autoload\ClassLoader;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Class PluginAbstract
 */
abstract class PluginAbstract implements PluginInterface
{
    private static array $instances = array();
    private static int $pluginComId = 0;

    protected static string $pluginPath = '';
    protected static string $name = '';
    protected static string $version = '0.0.0';
    protected static array $dependencies = array();
    protected static array $metadata = array();
    protected static array $defaultConfig = array();

    /**
     *
     */
    protected function __construct()
    {

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

    /**
     * Add the plugin menu entry to the database.
     * This method is called during the installation of the plugin.
     * @throws Exception
     */
    public static function addMenuEntry(): void
    {
        global $gDb;

        $className = basename(self::$pluginPath);

        $pluginMenuEntry = new MenuEntry($gDb);
        $pluginMenuEntry->setValue('men_men_id_parent', 3); // extensions node has the id  of 3 by default
        $pluginMenuEntry->setValue('men_name', self::$name);
        $pluginMenuEntry->setValue('men_com_id', self::$pluginComId);
        $pluginMenuEntry->setValue('men_description', self::$metadata['description']);
        $pluginMenuEntry->setValue('men_url', FOLDER_PLUGINS . '/' . $className . '/' . (self::$metadata['mainFile'] ?? $className . '.php'));
        $pluginMenuEntry->setValue('men_icon', self::$metadata['icon']);
        $pluginMenuEntry->save();

        // rename the menu entry internal name to the class name
        $pluginMenuEntry->readDataById($pluginMenuEntry->getValue('men_id'));
        $pluginMenuEntry->setValue('men_name_intern', $className);
        $pluginMenuEntry->save();
    }

    /**
     * Remove the plugin menu entry from the database.
     * This method is called during the uninstallation of the plugin.
     * @throws Exception
     */
    public static function removeMenuEntry(): void
    {
        global $gDb;

        $className = basename(self::$pluginPath);

        // delete the plugin menu entry
        $pluginMenuEntry = new MenuEntry($gDb);
        if ($pluginMenuEntry->readDataByColumns(array('men_name_intern' => $className))) {
            $pluginMenuEntry->delete();
        }
    }

    /**
     * Initialize the preferences panel for this plugin.
     * This method will be called automatically when the plugin is activated.
     * It will register the preferences panel for this plugin in the PreferencesService.
     */
    public static function initPreferencePanelCallback(): void
    {
        // get the calling plugin name in lowercase and without underscores, hyphens, spaces or other special characters
        $callingPlugin = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', self::getComponentName()));

        // get psr4 autoload mappings and preferences file from metadata
        $psr4 = self::$metadata['autoload']['psr-4'] ?? null;
        $preferencesFile = self::$metadata['preferencesFile'] ?? null;

        // if a preferences file is defined in the metadata, the plugin handles the preferences itself
        if (isset($preferencesFile)) {
            return;
        }

        // check if psr4 autoload mappings are defined, otherwise we cannot find the presenter class
        if (!is_array($psr4)) {
            return;
        }

        // look for presenter class path in the psr4 autoload mappings
        $preferencesClass = null;

        foreach ($psr4 as $prefix => $relativePath) {
            if (str_contains(strtolower($prefix), $callingPlugin)) {
                $presenterBasePath = self::getPluginPath() . DIRECTORY_SEPARATOR . $relativePath;
                // now loop over all files in the presenter path to find a preferences presenter
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($presenterBasePath));
                foreach ($it as $file) {
                    if (!$file->isFile() || !str_contains($file->getFilename(), 'PreferencesPresenter.php')) {
                        continue;
                    }
                    $preferencesFile = $file->getPathname();
                    $preferencesClass = self::getClassNameFromFile($preferencesFile);
                    break;
                }
            }
        }

        if (isset($preferencesClass) && class_exists($preferencesClass)) {

            // get the function name for the preferences panel form
            $className = str_replace('PreferencesPresenter', '', (new ReflectionClass($preferencesClass))->getShortName());
            $functionName = 'create' . $className . 'Form';
            if (!method_exists($preferencesClass, $functionName)) {
                throw new Exception('The preferences class ' . $preferencesClass . ' does not have a method ' . $functionName . '().');
            }
            if (self::isOverviewPlugin()) {
                // register the overview preferences presenter for this plugin
                PreferencesService::addOverviewPluginPreferencesPresenter(self::getComponentId(), [$preferencesClass, $functionName]);
            } else {
                // register the preferences presenter for this plugin
                PreferencesService::addPluginPreferencesPresenter(self::getComponentId(), [$preferencesClass, $functionName]);
            }
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
     * @throws Exception
     */
    private function readPluginMetadata(): void
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
            throw new Exception('Plugin configuration file ' . $configFile . ' is not valid JSON.');
        } else {
            self::$name = $configData['name'] ?? '';
            self::$dependencies = $configData['dependencies'] ?? array();
            self::$defaultConfig = $configData['defaultConfig'] ?? array();
            self::$metadata = $configData;

            self::registerDefaultConfig();
        }
    }

    /**
     * Add the preferences of the plugin to the canonical preference registry.
     *
     * A plugin cannot write its preferences into install/db_scripts/preferences.php, so it
     * describes them in the defaultConfig of its JSON file and registers them here. From that
     * point on they are ordinary Admidio preferences: PreferencesService seeds them into every
     * organization, validates them and removes them again when the plugin is uninstalled.
     *
     * @return void
     * @throws Exception
     */
    /**
     * The preferences this plugin owns, including the companions that hold the keys of an
     * associative value.
     *
     * @return array<int,string>
     */
    private static function preferenceNames(): array
    {
        $names = array();

        foreach (self::$defaultConfig as $key => $configuration) {
            if (!array_key_exists('value', $configuration)) {
                continue;
            }
            $names[] = $key;
            $value = $configuration['value'];
            if (is_array($value) && $value !== array()
                && array_keys($value) !== range(0, count($value) - 1)) {
                $names[] = $key . '_keys';
            }
        }

        return $names;
    }

    private static function registerDefaultConfig(): void
    {
        $defaults = array();

        foreach (self::$defaultConfig as $key => $configuration) {
            if (!array_key_exists('value', $configuration)) {
                continue;
            }

            $type = match ((string)($configuration['type'] ?? 'string')) {
                'boolean' => 'bool',
                'integer' => 'int',
                default => 'string'
            };
            $value = $configuration['value'];

            // Preferences are stored as strings, so the default has to be converted the same way
            // SettingsManager::set() would store it.
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                /*
                 * An associative value keeps its keys in a preference of its own, because a
                 * preference stores one string and the order of the keys would be lost otherwise.
                 */
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $defaults[$key . '_keys'] = array('default' => implode(',', array_keys($value)));
                }
                $value = implode(',', $value);
            }

            $defaults[$key] = array('default' => (string)$value, 'type' => $type);
        }

        foreach ($defaults as $key => $definition) {
            PreferenceDefinitions::register($key, $definition);
        }

        /*
         * TODO remove once every organization is seeded on install: SettingsManager still answers
         * a preference that has no row from this list, see SettingsManager::registerDefaults().
         */
        SettingsManager::registerDefaults(array_map(
            static fn (array $definition): string => (string)$definition['default'],
            $defaults
        ));
    }

    /**
     * @return PluginAbstract
     * @throws Exception
     * @throws ReflectionException
     */
    public static function getInstance(): PluginAbstract
    {
        // reset global variables
        self::$pluginComId = 0;
        self::$pluginPath = '';
        self::$name = '';
        self::$version = '0.0.0';
        self::$dependencies = array();
        self::$metadata = array();
        self::$defaultConfig = array();

        // get the class name of the called class
        $class = get_called_class();
        if (!array_key_exists($class, self::$instances)) {
            self::$instances[$class] = new $class();
        }

        // set the plugin path to the folder of this class
        $reflection = new ReflectionClass(self::$instances[$class]);
        self::$pluginPath = dirname($reflection->getFileName(), 2);

        // read the plugin metadata
        self::$instances[$class]->readPluginMetadata();

        // after we have the plugin path and metadata, we can do the class autoload
        self::$instances[$class]->doClassAutoload($class);

        // check if the plugin is installed
        if (self::$instances[$class]->isInstalled()) {
            global $gDb;
            // get the component id of the plugin
            $sql = 'SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND com_type = ?';
            $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN'));
            self::$pluginComId = (int)$statement->fetchColumn();

            // get the installed version of the plugin
            $sql = 'SELECT com_version FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND com_type = ?';
            $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN'));
            self::$version = (string)$statement->fetchColumn();
        }

        return self::$instances[$class];
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return self::$name;
    }

    /**
     * @return string
     */
    public static function getIcon(): string
    {
        return self::$metadata['icon'] ?? '';
    }

    /**
     * @return string
     */
    public static function getVersion(): string
    {
        return self::$version;
    }

    /**
     * @return array
     */
    public static function getMetadata(): array
    {

        return self::$metadata;
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return self::$dependencies;
    }

    /**
     * @return array
     */
    public static function getSupportedLanguages(): array
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'languages';

        $langFiles = array();
        foreach (scandir($dir) as $entry) {
            $entryPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $entryInfo = pathinfo($entryPath);
            if (is_file($entryPath) && $entryInfo['extension'] === 'xml') {
                $langFiles[] = $entryInfo['filename'];
            }
        }

        return $langFiles;
    }

    /**
     * @param string|null $type
     * @param string $path
     * @return array
     * @throws Exception
     */
    public static function getStaticFiles(?string $type = null, string $path = '' /* self::$pluginPath */): array
    {
        if ($path === '') {
            $path = self::$pluginPath;
        }

        if ($type !== null && !is_string($type)) {
            throw new InvalidArgumentException('Type must be "null" or a "string".');
        }

        if (!is_dir($path)) {
            throw new Exception('Plugin path does not exist: ' . $path);
        }

        $files = array();
        foreach (scandir($path) as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_file($entryPath)) {
                $entryInfo = pathinfo($entryPath);

                if (!isset($entryInfo['extension'])) {
                    $entryInfo['extension'] = '';
                }
                if (!array_key_exists($entryInfo['extension'], $files)) {
                    $files[$entryInfo['extension']] = array();
                }

                $files[$entryInfo['extension']][] = $entryPath;
            }
        }

        if ($type === null) {
            return $files;
        } else {
            return (array_key_exists($type, $files)) ? $files[$type] : array();
        }
    }

    public static function getPluginConfigValues(): array
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
                        if ($gSettingsManager->has($key . '_keys')) {
                            // if the keys are stored separately, use them to create the array
                            $keyString = $gSettingsManager->get($key . '_keys');
                            $config[$key] = $valueString === "" ? array() : array_combine(explode(',', $keyString), explode(',', $valueString));
                        } else {
                            // if no keys are stored, use the value string as the value
                            $config[$key] = $valueString === "" ? array() : explode(',', $valueString);
                        }
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
    public static function getPluginConfig(): array
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

    /**
     * @return string
     */
    public static function getPluginPath(): string
    {
        return self::$pluginPath;
    }

    /**
     * @return int
     */
    public static function getComponentId(): int
    {
        return self::$pluginComId;
    }

    /**
     * @return string
     */
    public static function getComponentName(): string
    {
        return basename(self::$pluginPath);
    }

    /**
     * Get the sequence of the plugin in the components table.
     * @return int Returns the sequence of the plugin.
     * @throws Exception
     */
    public static function getPluginSequence(): int
    {
        $pluginConfig = self::getPluginConfig();
        $sequenceSuffix = '_overview_sequence';
        // find the sequence key in the plugin config
        $sequenceKeys = array_filter(array_keys($pluginConfig), function($k) use ($sequenceSuffix) {
            return substr($k, -strlen($sequenceSuffix)) === $sequenceSuffix;
        });

        if (!empty($sequenceKeys)) {
            // get the first matching key
            $sequenceKey = array_values($sequenceKeys)[0];

            // return the value from the config if available
            return $pluginConfig[$sequenceKey]['value'] ?? 0;
        }
        return 0;
    }

    /**
     * Set the sequence of the plugin in the overview.
     *
     * @return bool Returns true if the plugin defines an overview sequence setting.
     */
    public static function setPluginSequence(int $sequence): bool
    {
        global $gSettingsManager;

        $sequenceSuffix = '_overview_sequence';
        $sequenceKeys = array_filter(array_keys(self::$defaultConfig), function($key) use ($sequenceSuffix) {
            return substr($key, -strlen($sequenceSuffix)) === $sequenceSuffix;
        });

        if (empty($sequenceKeys)) {
            return false;
        }

        $sequenceKey = array_values($sequenceKeys)[0];
        $gSettingsManager->set($sequenceKey, $sequence);

        return true;
    }

    /**
     * Check if the plugin has all dependencies installed.
     * @return bool Returns true if all dependencies are installed, false otherwise.
     * @throws Exception
     */
    public static function checkDependencies(): bool
    {
        // check if the plugin has dependencies
        if (empty(self::$dependencies)) {
            return true;
        }

        $missing = array();

        // loop over all dependencies and check if they are available
        foreach (self::$dependencies as $dependency) {
            // dependencies should be a class name of the admidio core or the final namespace of the class

            // check if the dependency is a fully qualified class name or only a short name
            if (class_exists($dependency, true)) {
                // if the class exists, continue to the next dependency
                continue;
            }

            // if the class does not exist, try to find it in the Admidio namespace
            if (self::findAdmidioClass($dependency) === null) {
                $missing[] = $dependency;
            }
        }

        if (!empty($missing)) {
            // not all dependencies are met
            return false;
        }

        return true;
    }

    /**
     * Scan src/ under the Admidio\ namespace for a class named $shortName.
     * Returns the fully qualified class name if found, or null otherwise.
     *
     * @param string $shortName
     * @return string|null
     */
    private static function findAdmidioClass(string $shortName): ?string
    {
        // define the path to the src directory
        $srcDir = ADMIDIO_PATH . '/src';
        $prefix = 'Admidio\\';

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

        // iterate through the directory structure to find the class file
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getFilename() !== $shortName . '.php') {
                continue;
            }

            // if the file is found, construct the full class name
            $relPath = substr($file->getPathname(), strlen($srcDir) + 1, -4);
            $subNamespaces = str_replace(DIRECTORY_SEPARATOR, '\\', $relPath);
            $fullName = $prefix . ($subNamespaces !== '' ? $subNamespaces : $shortName);


            if (class_exists($fullName, true)) {
                return $fullName;
            }
        }

        return null;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function isInstalled(): bool
    {
        global $gDb;
        // check if the plugin exists in components database table
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND com_type = ?';
        $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN'));
        $columns = (int)$statement->fetchColumn();

        return $columns > 0;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function isActivated(): bool
    {
        return self::isInstalled() && (self::getComponentId() > 0);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function isVisible(): bool
    {
        global $gValidLogin;

        // check if the plugin is activated
        if (!self::isActivated()) {
            return false;
        }

        // check if the plugin has setting ending with '_enabled'
        $pluginConfig = self::getPluginConfig();
        foreach ($pluginConfig as $key => $value) {
            if (str_ends_with($key, '_enabled') && isset($value['value'])) {
                if (($value['value'] === 1 || ($value['value'] === 2 && $gValidLogin))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function isOverviewPlugin(): bool
    {
        global $gDb;
        // check if the plugin exists in components database table and is of type 'PLUGIN' and has overview flag
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_COMPONENTS . ' WHERE com_name = ? AND com_type = ? AND com_overview_plugin = ?';
        $statement = $gDb->queryPrepared($sql, array(self::getName(), 'PLUGIN', true));
        $columns = (int)$statement->fetchColumn();

        return $columns > 0;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function isAdmidioPlugin(): bool
    {
        $admidioOverviewPlugins = array('announcement-list', 'birthday', 'calendar', 'event-list', 'latest-documents-files', 'login-form', 'random-photo', 'who-is-online');

            if (in_array(strtolower(self::getComponentName()), $admidioOverviewPlugins)) {
                return true;
            }
            return false;
    }
    /**
     * @return bool
     * @throws Exception
     */
    public static function isUpdateAvailable(): bool
    {
        return version_compare(self::$version, self::$metadata['version'], '<');
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function doClassAutoload(string $class): bool
    {
        // Register plugin PSR-4 autoload mappings from plugin metadata (no DB required).
        // Each plugin can define in its JSON:
        //   {
        //     "autoload": { "psr-4": { "Vendor\\Plugin\\": "classes/" } }
        //   }
        try {
            if (class_exists(ClassLoader::class)) {
                $loaders = ClassLoader::getRegisteredLoaders();
                $composerLoader = reset($loaders);

                if ($composerLoader) {
                    $existingPrefixes = $composerLoader->getPrefixesPsr4();

                    $instance = self::$instances[$class];

                    // check if the instance is valid
                    if (!$instance instanceof PluginAbstract) {
                        return false;
                    }

                    // read the plugin metadata if not already done
                    if (empty($instance::$metadata)) {
                        $instance->readPluginMetadata();
                    }

                    // check if psr4 autoload mappings are defined
                    $psr4 = $instance::$metadata['autoload']['psr-4'] ?? null;
                    if (!is_array($psr4)) {
                        return false;
                    }

                    // register each PSR-4 mapping for this plugin
                    foreach ($psr4 as $prefix => $relativePath) {
                        if (!is_string($prefix) || !is_string($relativePath)) {
                            continue;
                        }

                        $prefix = rtrim($prefix, '\\') . '\\';
                        $absolutePath = $instance::$pluginPath . DIRECTORY_SEPARATOR . trim($relativePath, "/\\");

                        if (!is_dir($absolutePath)) {
                            continue;
                        }

                        // already registered?
                        if (isset($existingPrefixes[$prefix]) && in_array($absolutePath, $existingPrefixes[$prefix], true)) {
                            continue;
                        }

                        // register the PSR-4 mapping for this plugin
                        $composerLoader->addPsr4($prefix, $absolutePath, true);
                    }
                }
            }
        } catch (\Throwable $e) {
            throw new Exception('Error during plugin class autoload registration: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @param bool $enable
     * @throws Exception
     */
    private static function toggleForeignKeyChecks(bool $enable): void
    {
        global $gDb;

        if (DB_TYPE !== Database::PDO_ENGINE_PGSQL) {
            // disable foreign key checks for mysql, so tables can easily be deleted
            $sql = 'SET foreign_key_checks = ' . (int)$enable;
            $gDb->queryPrepared($sql);
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function doInstall(bool $addMenuEntry = true): bool
    {
        global $gDb;

        // check if the plugin is already installed
        if (self::isInstalled()) {
            return false;
        }

        /*
         * Give the preferences of the plugin a row in every organization, the way the installer
         * does it for the core preferences. Without that an organization other than the current
         * one would have no value at all for them.
         */
        PreferencesService::seedDefaults(self::preferenceNames());

        // check if the db_scripts folder exists
        if (is_dir(self::$pluginPath . DIRECTORY_SEPARATOR . 'db_scripts')) {
            // check if the plugin has a .sql file to create the database tables
            $sqlFiles = self::getStaticFiles('sql', self::$pluginPath . DIRECTORY_SEPARATOR . 'db_scripts');
            if (isset($sqlFiles) && count($sqlFiles) > 0) {
                $sqlFile = null;
                if (count($sqlFiles) === 1) {
                    // if there is only one sql file, take it
                    $sqlFile = $sqlFiles[0];
                } else {
                    // if there are multiple sql files, we need to find the installation file
                    // the installation file needs to be named *install.sql
                    foreach ($sqlFiles as $file) {
                        if (str_contains($file, 'install')) {
                            $sqlFile = $file;
                            break;
                        }
                    }
                }
                if ($sqlFile !== null) {
                    // read data from sql install script and execute all statements to the current database
                    if (!is_file($sqlFile)) {
                        throw new Exception('INS_DATABASE_FILE_NOT_FOUND', array(basename($sqlFile), dirname($sqlFile)));
                    }

                    try {
                        $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFile);
                    } catch (RuntimeException) {
                        throw new Exception('INS_ERROR_OPEN_FILE', array($sqlFile));
                    }

                    self::toggleForeignKeyChecks(false);
                    foreach ($sqlStatements as $sqlStatement) {
                        $gDb->queryPrepared($sqlStatement);
                    }
                    self::toggleForeignKeyChecks(true);
                }
            }
        }

        // install the plugin
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(
            array(
                'com_type' => 'PLUGIN',
                'com_name' => self::getName(),
                'com_name_intern' => strtoupper(basename(self::$pluginPath)),
                'com_overview_plugin' => self::$metadata['overviewPlugin']
            )
        );

        // after we have the plugin path and metadata, we can do the class autoload
        self::doClassAutoload(get_called_class());
        // check if psr4 autoload mappings are defined
        $psr4 = self::$metadata['autoload']['psr-4'] ?? null;
        if (is_array($psr4) && count($psr4) > 0) {
            // define the update class namespace for the plugin
            foreach ($psr4 as $prefix => $relativePath) {
                if (!is_string($prefix) || !is_string($relativePath)) {
                    continue;
                }

                // if the class does not exist, it will be ignored when performing updatePlugin()
                $updateStepCodeNamespace = $prefix . 'Service\\';
                $componentUpdateHandle->updatePlugin(self::$metadata['version'], $updateStepCodeNamespace);
            }
        }

        // set the new component id of the plugin
        self::$pluginComId = $componentUpdateHandle->getValue('com_id');

        // set the installed version of the plugin
        self::$version = self::$metadata['version'];

        // add the plugin menu entry to the database
        if (!self::isOverviewPlugin() && $addMenuEntry) {
            self::addMenuEntry();
        }

        // perform additional installation tasks
        // TODO: implement function to perform updateSteps for the plugin
        // e.g.: $componentUpdateHandle->doUpdateSteps();

        return true;
    }

    /**
     * @param array $options
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function doUninstall(bool $removeMenuEntry = true, array $options = array()): bool
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException('Options must be an "array".');
        }

        // check if the plugin is installed
        if (!self::isInstalled()) {
            return false;
        }

        global $gDb;

        // check if the db_scripts folder exists
        if (is_dir(self::$pluginPath . DIRECTORY_SEPARATOR . 'db_scripts')) {
            // check if the plugin has a .sql file to delete the database tables
            $sqlFiles = self::getStaticFiles('sql', self::$pluginPath . DIRECTORY_SEPARATOR . 'db_scripts');
            if (isset($sqlFiles) && count($sqlFiles) > 0) {
                $sqlFile = null;
                // if there is only a db.sql file, no uninstall script is needed
                if (count($sqlFiles) > 1) {
                    // if there are multiple sql files, we need to find the uninstallation file
                    // the file needs to be named *uninstall.sql
                    foreach ($sqlFiles as $file) {
                        if (str_contains($file, 'uninstall')) {
                            $sqlFile = $file;
                            break;
                        }
                    }
                }
                if ($sqlFile !== null) {
                    // read data from sql install script and execute all statements to the current database
                    if (!is_file($sqlFile)) {
                        throw new Exception('INS_DATABASE_FILE_NOT_FOUND', array(basename($sqlFile), dirname($sqlFile)));
                    }

                    try {
                        $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFile);
                    } catch (RuntimeException) {
                        throw new Exception('INS_ERROR_OPEN_FILE', array($sqlFile));
                    }

                    self::toggleForeignKeyChecks(false);
                    foreach ($sqlStatements as $sqlStatement) {
                        $gDb->queryPrepared($sqlStatement);
                    }
                    self::toggleForeignKeyChecks(true);
                }
            }
        }

        // delete the plugin config values from every organization
        PreferencesService::removePreferences(self::preferenceNames());

        // remove the plugin menu entry
        if ($removeMenuEntry) {
            self::removeMenuEntry();
        }

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
     * @return bool
     * @throws Exception
     */
    public static function doUpdate(): bool
    {
        global $gDb;

        // check if the plugin is installed
        if (!self::isInstalled()) {
            return false;
        }

        // a new version may bring new preferences; the ones an organization already has are kept
        PreferencesService::seedDefaults(self::preferenceNames());

        // update the plugin
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_name' => self::getName(), 'com_name_intern' => basename(self::$pluginPath)));
        // define the update class namespace for the plugin
        // if the update class does not exist, it will be ignored when performing updatePlugin()
        $updateStepCodeNamespace = 'Plugins\\' . basename(self::$pluginPath) . '\\classes\\Service\\';
        $componentUpdateHandle->updatePlugin(self::$metadata['version'], $updateStepCodeNamespace);

        // set the installed version of the plugin
        self::$version = self::$metadata['version'];

        // perform additional update tasks
        // TODO: implement function to perform updateSteps for the plugin
        // e.g.: $componentUpdateHandle->doUpdateSteps();
        return true;
    }

    public static function initParams(array $params = array()): bool
    {
        if (!is_array($params)) {
            throw new InvalidArgumentException('Params must be an "array".');
        }

        return true;
    }
}
