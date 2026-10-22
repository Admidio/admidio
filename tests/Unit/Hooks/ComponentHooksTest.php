<?php
/**
 * component_visible and component_administrable: the two filters that decide, in one place, which
 * modules the menu shows and which of them a user may administrate.
 *
 * `Component` reads the settings, the current user and the database, so it cannot be constructed here.
 * What this executes is the real `Hooks` engine and a stand-in that has the wrapper of both methods
 * verbatim - the wrapper is the whole change, the switch statements below it are untouched.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Components\Entity\Component;
use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use Throwable;

/** The two public methods of Component as they are after the patch. */
class ProbeComponent
{
    /** @var array<string,bool> what Admidio itself answers, standing in for the two switch statements */
    public static array $rights = array();

    public static function isVisible(string $componentName): bool
    {
        return Hooks::applyTypedFilters('component_visible', self::checkVisible($componentName), $componentName);
    }

    public static function isAdministrable(string $componentName): bool
    {
        return Hooks::applyTypedFilters('component_administrable', self::checkAdministrable($componentName), $componentName);
    }

    private static function checkVisible(string $componentName): bool
    {
        return self::$rights['visible:' . $componentName] ?? false;
    }

    private static function checkAdministrable(string $componentName): bool
    {
        // the real one does the same: an administrator has to be able to see the module first
        if (self::isVisible($componentName)) {
            return self::$rights['admin:' . $componentName] ?? false;
        }

        return false;
    }
}

/**
 * isVisible() is static and the CATEGORY-REPORT branch of its switch asks nothing but the settings
 * and the current user, so the real method can be executed here with those two stubbed. Nothing is
 * constructed and no other branch is reached.
 */
class ComponentStubSettings
{
    public array $values = array();

    public function getBool(string $name): bool
    {
        return (bool)($this->values[$name] ?? false);
    }

    public function getInt(string $name): int
    {
        return (int)($this->values[$name] ?? 0);
    }

    public function getString(string $name): string
    {
        return (string)($this->values[$name] ?? '');
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }
}

class ComponentStubUser
{
    public array $rights = array();

    public function checkRolesRight(string $right): bool
    {
        return (bool)($this->rights[$right] ?? false);
    }
}

class ComponentHooksTest extends AdmidioTestCase
{
    private function fixture(array $rights): void
    {
        Hooks::reset();
        ProbeComponent::$rights = $rights;
    }

    public function testWithoutAFilterTheRightsOfAdmidioAreTheAnswer(): void
    {
        $this->fixture(array('visible:PHOTOS' => true, 'admin:PHOTOS' => false));

        $this->assertTrue(ProbeComponent::isVisible('PHOTOS'));
        $this->assertFalse(ProbeComponent::isAdministrable('PHOTOS'));
    }

    public function testAFilterCanRevokeVisibilityAndAdministrationWithIt(): void
    {
        $this->fixture(array('visible:PHOTOS' => true, 'admin:PHOTOS' => true));
        Hooks::addFilter('component_visible', function (bool $visible, string $component) {
            return ($component === 'PHOTOS') ? false : $visible;
        });

        $this->assertFalse(ProbeComponent::isVisible('PHOTOS'), 'a filter can hide a module');
        $this->assertFalse(
            ProbeComponent::isAdministrable('PHOTOS'),
            'checkAdministrable() asks isVisible(), so hiding it also takes the administration with it'
        );
    }

    public function testAFilterCanAlsoGrantVisibility(): void
    {
        $this->fixture(array('visible:FORUM' => false));
        Hooks::addFilter('component_visible', function (bool $visible, string $component) {
            return ($component === 'FORUM') ? true : $visible;
        });

        $this->assertTrue(ProbeComponent::isVisible('FORUM'), 'a filter can also show a module that Admidio would hide');
    }

    public function testTheTwoFiltersAreSeparateAnswers(): void
    {
        $this->fixture(array('visible:EVENTS' => true, 'admin:EVENTS' => false));
        Hooks::addFilter('component_administrable', function (bool $administrable, string $component) {
            return ($component === 'EVENTS') ? true : $administrable;
        });

        $this->assertTrue(ProbeComponent::isAdministrable('EVENTS'), 'component_administrable grants administration on its own');
        $this->assertTrue(ProbeComponent::isVisible('EVENTS'), 'and does not change what is visible');
    }

    public function testOneComponentDoesNotAffectAnother(): void
    {
        $this->fixture(array('visible:PHOTOS' => true, 'visible:LINKS' => true));
        Hooks::addFilter('component_visible', function (bool $visible, string $component) {
            return ($component === 'PHOTOS') ? false : $visible;
        });

        $this->assertTrue(ProbeComponent::isVisible('LINKS'), 'a filter that names one component leaves the others alone');
    }

    public function testAPermissionIsNeverCoerced(): void
    {
        $this->fixture(array('visible:PHOTOS' => true));
        Hooks::addFilter('component_visible', function () {
            return 1;                       // truthy, but not an answer
        });

        $refused = false;
        try {
            ProbeComponent::isVisible('PHOTOS');
        } catch (Throwable $exception) {
            $refused = str_contains($exception->getMessage(), 'returned int instead of bool');
        }
        $this->assertTrue($refused, 'a filter that does not answer with a bool is refused, not coerced');

        $this->fixture(array('visible:PHOTOS' => true));
        Hooks::addFilter('component_visible', function () {
            return 'yes';
        });
        $refused = false;
        try {
            ProbeComponent::isVisible('PHOTOS');
        } catch (Throwable $exception) {
            $refused = str_contains($exception->getMessage(), 'returned string instead of bool');
        }
        $this->assertTrue($refused, 'and neither is a string that looks like one');
    }

    public function testTheChainIsAChain(): void
    {
        $this->fixture(array('visible:PHOTOS' => true));
        $seen = array();
        Hooks::addFilter('component_visible', function (bool $visible) use (&$seen) {
            $seen[] = $visible;
            return false;
        }, 5);
        Hooks::addFilter('component_visible', function (bool $visible) use (&$seen) {
            $seen[] = $visible;
            return $visible;
        }, 10);

        $this->assertFalse(ProbeComponent::isVisible('PHOTOS'));
        $this->assertSame(array(true, false), $seen, 'every callback sees the answer of the one before it');
    }

    private function categoryReport(bool $moduleEnabled, bool $hasRight): bool
    {
        Hooks::reset();
        $settings = new ComponentStubSettings();
        $settings->values['category_report_module_enabled'] = $moduleEnabled;
        $user = new ComponentStubUser();
        $user->rights['rol_all_lists_view'] = $hasRight;

        $GLOBALS['gSettingsManager'] = $settings;
        $GLOBALS['gCurrentUser'] = $user;
        $GLOBALS['gValidLogin'] = true;

        return Component::isVisible('CATEGORY-REPORT');
    }

    public function testTheRealComponentForCategoryReport(): void
    {
        $this->assertTrue(
            $this->categoryReport(true, true),
            'the category report is visible when it is switched on and the right is there'
        );
        $this->assertFalse($this->categoryReport(true, false), 'it is not visible without the right');
        $this->assertFalse(
            $this->categoryReport(false, true),
            'and it is not visible when the module is switched off, which is what finding 92 was'
        );
    }
}
