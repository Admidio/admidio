<?php
/**
 * The built-in plugins that have been converted to the plugin runtime.
 *
 * The conversions land one at a time, and every one of them has to keep the preferences of an
 * existing installation. That is what this test is for: a renamed preference here means an
 * administrator loses a setting on update, and nothing else would notice.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class BuiltInPluginsTest extends PluginTestCase
{
    /**
     * One entry per converted plugin: its directory, the namespace it owns and every preference it
     * has to keep. A plugin is added here by the commit that converts it.
     *
     * @return array<string,array{0: string, 1: string, 2: array<int,string>}>
     */
    public static function convertedPlugins(): array
    {
        return array(
            'announcement-list' => array(
                'announcement-list',
                'AdmidioPlugin\\AnnouncementList\\',
                array(
                    'announcement_list_plugin_enabled',
                    'announcement_list_overview_sequence',
                    'announcement_list_announcements_count',
                    'announcement_list_show_preview_chars',
                    'announcement_list_show_full_description',
                    'announcement_list_chars_before_linebreak',
                    'announcement_list_displayed_categories'
                )
            ),
            'birthday' => array(
                'birthday',
                'AdmidioPlugin\\Birthday\\',
                array(
                    'birthday_plugin_enabled',
                    'birthday_overview_sequence',
                    'birthday_show_names_extern',
                    'birthday_show_names',
                    'birthday_show_age',
                    'birthday_show_age_salutation',
                    'birthday_show_notice_none',
                    'birthday_show_past',
                    'birthday_show_future',
                    'birthday_show_display_limit',
                    'birthday_show_email_extern',
                    'birthday_roles_view_plugin',
                    'birthday_roles_sql',
                    'birthday_sort_sql'
                )
            ),
            'event-list' => array(
                'event-list',
                'AdmidioPlugin\\EventList\\',
                array(
                    'event_list_plugin_enabled',
                    'event_list_overview_sequence',
                    'event_list_events_count',
                    'event_list_show_event_date_end',
                    'event_list_show_preview_chars',
                    'event_list_show_full_description',
                    'event_list_chars_before_linebreak',
                    'event_list_displayed_categories'
                )
            ),
            'latest-documents-files' => array(
                'latest-documents-files',
                'AdmidioPlugin\\LatestDocumentsFiles\\',
                array(
                    'latest_documents_files_plugin_enabled',
                    // Not latest_documents_files_overview_sequence: the name the plugin has always
                    // stored its position under is kept, so no installation loses it.
                    'latest_documents_overview_sequence',
                    'latest_documents_files_files_count',
                    'latest_documents_files_show_upload_timestamp',
                    'latest_documents_files_max_chars_filename'
                )
            ),
            'random-photo' => array(
                'random-photo',
                'AdmidioPlugin\\RandomPhoto\\',
                array(
                    'random_photo_plugin_enabled',
                    'random_photo_overview_sequence',
                    'random_photo_max_char_per_word',
                    'random_photo_max_width',
                    'random_photo_max_height',
                    'random_photo_albums',
                    'random_photo_album_photo_number',
                    'random_photo_show_album_link'
                )
            ),
            'who-is-online' => array(
                'who-is-online',
                'AdmidioPlugin\\WhoIsOnline\\',
                array(
                    'who_is_online_plugin_enabled',
                    'who_is_online_overview_sequence',
                    'who_is_online_time_still_active',
                    'who_is_online_show_visitors',
                    'who_is_online_show_members_to_visitors',
                    'who_is_online_show_self',
                    'who_is_online_show_users_side_by_side'
                )
            )
        );
    }

    /**
     * @testdox The converted plugin is a valid plugin of the current format
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testPluginIsValid(string $id, string $namespace, array $preferences): void
    {
        $plugin = $this->builtIn($id);

        $this->assertNull($plugin->error);
        $this->assertSame($id, $plugin->id);
        $this->assertSame(array(), $plugin->checkRequirements());
        $this->assertFileExists($plugin->getEntryFile());
        $this->assertSame(array($namespace), array_keys($plugin->autoload));
        $this->assertDirectoryExists($plugin->autoload[$namespace]);
    }

    /**
     * @testdox Nothing of the previous plugin format is left behind
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testPreviousFormatIsGone(string $id, string $namespace, array $preferences): void
    {
        $path = ADMIDIO_PATH . '/plugins/' . $id;

        $this->assertFileDoesNotExist($path . '/' . $id . '.json', 'the manifest of the previous format');
        $this->assertFileDoesNotExist($path . '/index.php', 'the widget replaces the included file');
        $this->assertDirectoryDoesNotExist($path . '/classes', 'the classes moved to src/');
    }

    /**
     * @testdox Every preference keeps the name it had, so no installation loses a setting
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testPreferenceNamesArePreserved(string $id, string $namespace, array $preferences): void
    {
        $plugin = $this->builtIn($id);

        $this->assertSame($preferences, array_keys($plugin->settings));

        foreach (array_merge(array($plugin->getEnabledSettingName()), $preferences) as $name) {
            $this->assertMatchesRegularExpression('/^[a-z0-9](_?[a-z0-9])*$/', $name,
                'a preference name Admidio would refuse to register');
        }

        // The two flags mean different things and must not collide: <id>_plugin_enabled is the
        // visibility of the widget, plugin_<id>_enabled is whether Admidio loads the plugin here.
        $this->assertNotContains($plugin->getEnabledSettingName(), $preferences);
    }

    /**
     * @testdox The update steps sit where the installer looks for them
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testUpdateStepsAreWhereTheInstallerLooks(string $id, string $namespace, array $preferences): void
    {
        $plugin = $this->builtIn($id);
        $scripts = $plugin->getDirectory(Plugin::DIR_DB_SCRIPTS);

        if ($scripts === null || glob($scripts . '/update_*.xml') === array()) {
            $this->assertTrue(true, 'the plugin has no update scripts');
            return;
        }

        // PluginInstaller::getUpdateNamespace() is the first autoload prefix plus Service\.
        $file = $plugin->autoload[$namespace] . '/Service/UpdateStepsCode.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString(
            'namespace ' . rtrim($namespace, '\\') . '\\Service;',
            (string)file_get_contents($file)
        );
    }

    /**
     * @testdox Its panel ID is a name the preferences page can put into a URL
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testPanelId(string $id, string $namespace, array $preferences): void
    {
        $panel = PluginPanel::normalizeId($id);

        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $panel);
        $this->assertSame($panel, PluginPanel::normalizeId($panel), 'normalizing twice changes nothing');
    }

    /**
     * @testdox Its entry file refuses to be an entry point of its own
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testEntryFileRefusesDirectAccess(string $id, string $namespace, array $preferences): void
    {
        $this->assertStringContainsString(
            "realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__",
            (string)file_get_contents($this->builtIn($id)->getEntryFile())
        );
    }

    /**
     * @testdox Every language key it uses is defined in its own language file
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testLanguageKeys(string $id, string $namespace, array $preferences): void
    {
        $plugin = $this->builtIn($id);
        $ownKeys = $this->languageKeys($plugin->getDirectory(Plugin::DIR_LANGUAGES) . '/en.xml');
        $coreKeys = $this->languageKeys(ADMIDIO_PATH . '/languages/en.xml');

        $used = array($plugin->name, $plugin->description);
        foreach ($plugin->settings as $setting) {
            $used[] = $setting['label'];
            $used[] = $setting['description'];
        }
        foreach ($this->sourceFiles($plugin) as $file) {
            preg_match_all('/PLG_[A-Z0-9_]+/', (string)file_get_contents($file), $matches);
            $used = array_merge($used, $matches[0]);
        }

        foreach (array_unique(array_filter($used)) as $key) {
            // A plugin may use a core string, but its own strings have to be in its own file.
            $this->assertContains($key, str_starts_with($key, 'PLG_') ? $ownKeys : $coreKeys,
                $id . ' uses the undefined language key ' . $key);
        }
    }

    /**
     * The names of every string an Admidio language file defines.
     * @return array<int,string>
     */
    private function languageKeys(string $file): array
    {
        $xml = simplexml_load_file($file);
        $this->assertNotFalse($xml, 'the language file ' . $file . ' is not valid XML');

        $keys = array();
        foreach ($xml->string as $string) {
            $keys[] = (string)$string['name'];
        }

        return $keys;
    }

    /**
     * @testdox Loading it declares its overview widget and its preferences panel
     * @dataProvider convertedPlugins
     * @param array<int,string> $preferences
     */
    public function testLoadingRegistersTheExtensionPoints(string $id, string $namespace, array $preferences): void
    {
        Hooks::reset();
        PluginWidget::reset();
        PluginPanel::reset();
        PluginRegistry::setPluginsPath(ADMIDIO_PATH . '/plugins');
        PluginRegistry::setInstallations(array($id => array('comId' => 1, 'version' => '1.0.0')));

        try {
            $this->assertTrue(PluginLoader::load(PluginRegistry::get($id)), implode(' ', PluginLoader::getFailures()));

            $declaration = PluginWidget::getDeclarations()[$id] ?? null;
            $this->assertNotNull($declaration, $id . ' declares no overview widget');
            $this->assertContains($declaration['sequencePreference'], $preferences,
                'the widget is placed by a preference the plugin declares');
            $this->assertContains($declaration['enabledPreference'], $preferences,
                'the widget is shown or hidden by a preference the plugin declares');

            $panel = PluginPanel::get(PluginPanel::normalizeId($id));
            $this->assertNotNull($panel, $id . ' declares no preferences panel');
            $this->assertTrue(is_callable($panel['create']));
        } finally {
            Hooks::reset();
            PluginWidget::reset();
            PluginPanel::reset();
        }
    }

    /**
     * The plugin as it is shipped. The registry is pointed at the fixtures by the base class, so the
     * real directory is read directly.
     */
    private function builtIn(string $id): Plugin
    {
        return Plugin::read(ADMIDIO_PATH . '/plugins/' . $id);
    }

    /**
     * The entry file, the classes and the templates of a plugin.
     * @return array<int,string>
     */
    private function sourceFiles(Plugin $plugin): array
    {
        $files = array($plugin->getEntryFile());

        foreach (array(Plugin::DIR_PAGES, Plugin::DIR_TEMPLATES) as $directory) {
            $path = $plugin->getDirectory($directory);
            if ($path !== null) {
                $files = array_merge($files, glob($path . '/*') ?: array());
            }
        }

        foreach ($plugin->autoload as $path) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
