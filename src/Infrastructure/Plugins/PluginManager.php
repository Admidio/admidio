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
            if (is_dir($pluginFolder)) {
                $pluginClassFile = $pluginFolder . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $entry . '.php';
                $className = is_file($pluginClassFile) ? $this->getClassNameFromFile($pluginClassFile) : null;
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
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->getComponentId() === $pluginId) {
                return $plugin['interface'];
            }
        }
        return null;
    }

    public function getPluginByName(string $pluginName) : ?PluginAbstract
    {
        $plugins = $this->getAvailablePlugins();
        if (isset($plugins[$pluginName]) && $plugins[$pluginName]['interface'] instanceof PluginAbstract) {
            return $plugins[$pluginName]['interface'];
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
        $availablePlugins = $this->getAvailablePlugins();
        $overviewPlugins = array();
        foreach ($availablePlugins as $plugin) {
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->isInstalled() && $plugin['interface']->isOverviewPlugin()) {
                $configValues = $plugin['interface']->getPluginConfigValues();
                $enabled = false;
                foreach ($configValues as $key => $value) {
                    if (preg_match('/_enabled$/', $key) && $value > 0) {
                        $enabled = true;
                        break;
                    }
                }
                if (!$enabled) {
                    continue;
                }
                $overviewPlugins[] = array(
                    'id' => $plugin['interface']->getComponentId(),
                    'name' => $plugin['interface']->getComponentName(),
                    'file' => basename($plugin['relativePath']),
                    'interface' => $plugin['interface']
                );
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
            if ($plugin['interface'] instanceof PluginAbstract && $plugin['interface']->isActivated()) {
                $activePlugins[] = $plugin['interface'];
            }
        }
        return $activePlugins;
    }
}