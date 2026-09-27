<?php

namespace Admidio\Tests\Unit;

use Admidio\Preferences\Service\PreferencesService;
use PHPUnit\Framework\TestCase;

class PluginPreferenceRoutingTest extends TestCase
{
    public function testEveryBundledPluginPanelResolvesToItsRegisteredForm(): void
    {
        $root = dirname(__DIR__, 2);
        $getPanelId = new \ReflectionMethod(PreferencesService::class, 'getPluginPanelId');
        $getPanelId->setAccessible(true);
        $files = glob($root . '/plugins/*/classes/Presenter/*PreferencesPresenter.php');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $source = file_get_contents($file);
            preg_match('/namespace\s+([^;]+);/', $source, $namespace);
            preg_match('/^class\s+(\w+)/m', $source, $class);
            preg_match('/public static function (create\w+Form)\(/', $source, $method);
            require_once $file;
            $callback = array($namespace[1] . '\\' . $class[1], $method[1]);
            $panel = $getPanelId->invoke(null, array($callback));
            // This is the route-to-method conversion used by modules/preferences.php.
            $resolved = 'create' . str_replace('_', '', ucwords($panel, '_')) . 'Form';
            $this->assertSame($method[1], $resolved, $file);
            $this->assertTrue(is_callable(array($callback[0], $resolved)), $file);
            if ($method[1] === 'createAnnouncementListForm') {
                $this->assertSame('announcement_list', $panel);
                $de = simplexml_load_file($root . '/plugins/announcement-list/languages/de.xml');
                $en = simplexml_load_file($root . '/plugins/announcement-list/languages/en.xml');
                $id = '/resources/string[@name="PLG_ANNOUNCEMENT_LIST_PLUGIN_NAME"]';
                $this->assertNotSame((string)$de->xpath($id)[0], (string)$en->xpath($id)[0]);
            }
        }
    }
}
