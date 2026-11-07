<?php

namespace Admidio\Tests\Unit\Rss;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeedAccess;
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\ValueObject\SettingsManager;
use PHPUnit\Framework\TestCase;

class RssFeedAccessTest extends TestCase
{
    public static function accessCases(): array
    {
        $cases = array();
        foreach (array(false, true) as $loggedIn) {
            foreach (array('forum', 'announcements', 'events', 'weblinks', 'photo') as $module) {
                $cases[] = array($module, $loggedIn, false, 1, 'SYS_RSS_DISABLED');
                $cases[] = array($module, $loggedIn, true, 0, 'SYS_MODULE_DISABLED');
                $cases[] = array($module, $loggedIn, true, 1, null);
                $cases[] = array($module, $loggedIn, true, 2, 'SYS_NO_RIGHTS');
            }
        }
        return $cases;
    }

    /** @dataProvider accessCases */
    public function testOnlyPublicModulesProvideFeeds(string $module, bool $loggedIn, bool $rssEnabled, int $mode, ?string $error): void
    {
        $previous = array();
        foreach (array('gDb', 'gLogger', 'gL10n', 'gValidLogin') as $name) {
            $previous[$name] = $GLOBALS[$name] ?? null;
        }
        $GLOBALS['gDb'] = null;
        $GLOBALS['gValidLogin'] = $loggedIn;
        $GLOBALS['gLogger'] = new class {
            public function notice($message, $context): void {}
        };
        $GLOBALS['gL10n'] = new class {
            public function get($message, $params): string { return $message; }
        };
        $settings = $this->createMock(SettingsManager::class);
        $settings->expects($this->once())->method('getBool')->with('enable_rss')->willReturn($rssEnabled);
        $settings->expects($rssEnabled ? $this->once() : $this->never())
            ->method('getInt')->with($module . '_module_enabled')->willReturn($mode);
        $organization = $this->createMock(Organization::class);
        $organization->method('getSettingsManager')->willReturn($settings);

        try {
            if ($error !== null) {
                $this->expectException(Exception::class);
                $this->expectExceptionMessage($error);
            }
            RssFeedAccess::assertAccessible($organization, $module . '_module_enabled');
        } finally {
            foreach ($previous as $name => $value) {
                $GLOBALS[$name] = $value;
            }
        }
    }
}
