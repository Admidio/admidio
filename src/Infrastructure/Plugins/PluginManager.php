<?php

namespace Admidio\Infrastructure\Plugins;

/**
 * Class PluginManager
 */
class PluginManager
{
    protected $pluginsPath = '';
    protected $pluginMainFile = '';

    /**
     *
     */
    public function __construct()
    {
        $this->pluginsPath = realpath(ADMIDIO_PATH . FOLDER_PLUGINS);
    }

    /**
     *
     */
    public function getAvailablePlugins() : array|object
    {
        $plugins = array();
        foreach (scandir($this->pluginsPath) as $entry) {
            // skip dot and dotdot entries
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $pluginFolder = $this->pluginsPath . DIRECTORY_SEPARATOR . $entry;
            $className = null;
            $pluginClassFile = null;

            if (is_dir($pluginFolder)) {
                // loop over all class files to find the main plugin class file in the classes folder
                foreach (scandir($pluginFolder . DIRECTORY_SEPARATOR . 'classes') as $classFileEntry) {
                    if ($classFileEntry === '.' || $classFileEntry === '..' || !is_file($pluginFolder . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $classFileEntry)) {
                        continue;
                    }

                    $pluginClassFile = $pluginFolder . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $classFileEntry;
                    $className = is_file($pluginClassFile) ? $this->getClassNameFromFile($pluginClassFile) : null;

                    // The classname only contains the namespace of the plugin itself, so we need a way to find the class to get an instance of it.
                    // The problem is, that if the plugin isn't installed yet, we cannot use autoloading to find the class.
                    // Therefore, we check if the class exists first. If not, we include the file manually.
                    if ($className !== null && !class_exists($className)) {
                        include_once $pluginClassFile;

                        if (is_subclass_of($className, PluginAbstract::class)) {
                            break;
                        }
                    }
                }

                $instance = $className != null ? $className::getInstance() : null;

                // find the main plugin file
                $this->getMainPluginFile($pluginFolder, $entry, $instance);
                $plugins[$entry] = array(
                    'fullPath' => $this->pluginMainFile,
                    'relativePath' => str_replace(realpath(ADMIDIO_PATH), '', $this->pluginMainFile),
                    'interface' => $instance
                );
            }
        }

        return $plugins;
    }

    public function getPluginById(int $pluginId) : ?PluginAbstract
    {
        $plugins = $this->getAvailablePlugins();
        foreach ($plugins as $plugin) {
            $plugin['interface'] = $plugin['interface'] !== null ? $plugin['interface']::getInstance() : null;
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->getComponentId() === $pluginId) {
                return $plugin['interface'];
            }
        }
        return null;
    }

    public function getPluginByName(string $pluginName) : ?PluginAbstract
    {
        $plugins = $this->getAvailablePlugins();
        if (isset($plugins[$pluginName])) {
            $plugin = $plugins[$pluginName]['interface'] !== null ? $plugins[$pluginName]['interface']::getInstance() : null;
            if ($plugin instanceof PluginAbstract) {
                return $plugin;
            }
        }
        return null;
    }

    public function getMetadataByComponentId(int $componentId) : ?array
    {
        $plugin = $this->getPluginById($componentId);
        return $plugin ? $plugin->getMetadata() : null;
    }

    private function getMainPluginFile(string $pluginFolder, string $pluginName, ?PluginAbstract $instance) : void
    {
        $pluginFileName = $instance != null ? $instance->getMetadata()['mainFile'] : 'index.php';
        $pluginFile = $pluginFolder . DIRECTORY_SEPARATOR . $pluginFileName;
        if (is_file($pluginFile)) {
            $this->pluginMainFile = $pluginFile;
        } else {
            $pluginFileName = $pluginName . '.php';
            $pluginFile = $pluginFolder . DIRECTORY_SEPARATOR . $pluginFileName;
            if (is_file($pluginFile)) {
            $this->pluginMainFile = $pluginFile;
            }
        }
    }

    /**
     * Parse a PHP file and return the first class name found.
     */
    private function getClassNameFromFile(string $file) : ?string
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
     *
     */
    public function getInstalledPlugins() : array
    {
        $availablePlugins = $this->getAvailablePlugins();
        $installedPlugins = array();
        foreach ($availablePlugins as $plugin) {
            $plugin['interface'] = $plugin['interface'] !== null ? $plugin['interface']::getInstance() : null;
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->isInstalled()) {
                $installedPlugins[] = $plugin['interface'];
            }
        }
        return $installedPlugins;
    }

    /**
     * Get all plugins that are used on the overview page.
     * @return array
     */
    public function getOverviewPlugins() : array
    {
        global $gValidLogin;
        
        $availablePlugins = $this->getAvailablePlugins();
        $overviewPlugins = array();
        foreach ($availablePlugins as $plugin) {
            $plugin['interface'] = $plugin['interface'] !== null ? $plugin['interface']::getInstance() : null;
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->isInstalled() && $plugin['interface']->isOverviewPlugin()) {
                $configValues = $plugin['interface']->getPluginConfigValues();
                $enabled = false;
                foreach ($configValues as $key => $value) {
                    if (str_ends_with($key, '_plugin_enabled') && ($value === 1 || ($value === 2 && $gValidLogin))) {
                        $enabled = true;
                        break;
                    }
                }
                if (!$enabled) {
                    continue;
                }

                $templateRow = array(
                    'id' => $plugin['interface']->getComponentId(),
                    'name' => $plugin['interface']->getComponentName(),
                    'file' => basename($plugin['relativePath']),
                    'interface' => $plugin['interface']
                );

                // Get the sequence of the plugin
                $sequence = $plugin['interface']->getPluginSequence();
                $desiredSequence = $sequence;
                if (isset($overviewPlugins[$desiredSequence])) {
                    $desiredSequence++;
                    while (isset($overviewPlugins[$desiredSequence])) {
                        $desiredSequence++;
                    }
                }
                $overviewPlugins[$desiredSequence] = $templateRow;
                ksort($overviewPlugins);
            }
        }
        return $overviewPlugins;
    }

    /**
     * Get all active plugins.
     * @return array
     */
    public function getActivePlugins() : array
    {
        $availablePlugins = $this->getAvailablePlugins();
        $activePlugins = array();
        // TODO: Check if the plugin is activated
        // For now, we assume all installed plugins are active.
        foreach ($availablePlugins as $plugin) {
            $plugin['interface'] = $plugin['interface'] !== null ? $plugin['interface']::getInstance() : null;
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->isActivated()) {
                $activePlugins[] = $plugin['interface'];
            }
        }
        return $activePlugins;
    }
}