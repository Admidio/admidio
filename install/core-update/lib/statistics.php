<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/** Build and retain the exact preview without loading files that the updater replaces. */
function statisticsPreview(array $ctx, string $updateId): array
{
    return updateState($ctx['config']['rootPath'], $updateId, static function (array $state) use ($ctx): array {
        if (isset($state['statisticsData']) || $state['state'] !== 'review') {
            return $state;
        }
        $prefix = $ctx['config']['tablePrefix'];
        $preference = static function (string $name) use ($ctx, $prefix): ?string {
            $row = dbRow($ctx['pdo'], 'SELECT prf_value FROM ' . $prefix . '_preferences WHERE prf_name = ?'
                . ' AND prf_org_id = (SELECT MIN(org_id) FROM ' . $prefix . '_organizations)', array($name));
            return isset($row['prf_value']) ? (string)$row['prf_value'] : null;
        };
        $identity = dbRow($ctx['pdo'], 'SELECT prf_value FROM ' . $prefix . '_preferences'
            . ' WHERE prf_name = ? AND prf_value <> ?'
            . ' ORDER BY CASE WHEN prf_org_id IS NULL THEN 0 ELSE 1 END, prf_org_id LIMIT 1', array('system_tenant_id', ''));
        $id = $identity['prf_value'] ?? null;
        if ($id === null || $id === '') {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
            $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
            $hex = bin2hex($bytes);
            $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
        }
        $extensions = array();
        $names = get_loaded_extensions();
        asort($names);
        foreach ($names as $name) {
            $version = phpversion($name);
            $extensions[$name] = $version === false ? null : $version;
        }
        $plugins = array();
        // Package manifests override the installed versions of bundled plugins.
        $root = $ctx['config']['rootPath'];
        foreach (array($root . '/plugins', $root . '/adm_plugins', workspace($root, (string)$state['updateId']) . '/extracted/plugins') as $directory) {
            foreach (glob($directory . '/*/plugin.json') ?: array() as $file) {
                $manifest = json_decode((string)file_get_contents($file), true);
                if (is_array($manifest) && is_string($manifest['version'] ?? null)) {
                    $pluginId = basename(dirname($file));
                    $installed = dbRow($ctx['pdo'], 'SELECT com_id FROM ' . $prefix . '_components'
                        . ' WHERE com_type = ? AND com_name_intern = ?', array('PLUGIN', $pluginId));
                    $setting = strtolower(str_replace('-', '_', $pluginId));
                    $enabled = dbRow($ctx['pdo'], 'SELECT prf_value FROM ' . $prefix . '_preferences'
                        . ' WHERE prf_name = ? AND prf_value = ?', array('plugin_' . $setting . '_enabled', '1'));
                    if ($installed !== null && $enabled !== null) {
                        $plugins[$pluginId] = $manifest['version'];
                    }
                }
            }
        }
        ksort($plugins);
        $driver = $ctx['pdo']->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $state['statisticsEnabled'] = $preference('system_statistics_enabled') === '1';
        $state['statisticsData'] = array(
            'id' => $id,
            'timestamp' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'database' => array('type' => $ctx['config']['dbType'] ?? $driver, 'version' => $ctx['pdo']->getAttribute(\PDO::ATTR_SERVER_VERSION)),
            'admidio' => array('version' => $state['targetVersionText'] ?? $state['targetVersion'],
                'previous_version' => $state['installedVersion'], 'language' => $preference('system_language') ?? 'en', 'plugins' => $plugins),
            'php' => array('version' => PHP_VERSION, 'extensions' => $extensions)
        );
        return $state;
    });
}
