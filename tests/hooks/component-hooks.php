<?php
/**
 * component_visible and component_administrable: the two filters that decide, in one place, which
 * modules the menu shows and which of them a user may administrate.
 *
 * `Component` reads the settings, the current user and the database, so it cannot be constructed here.
 * What this file executes is the real `Hooks` engine and a stand-in that has the wrapper of both
 * methods verbatim - the wrapper is the whole change, the switch statements below it are untouched.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;

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

function fixture(array $rights): void
{
    Hooks::reset();
    ProbeComponent::$rights = $rights;
}

// -------------------------------------------------------------------- without a filter nothing moves

fixture(array('visible:PHOTOS' => true, 'admin:PHOTOS' => false));
check('the rights of Admidio are the answer', ProbeComponent::isVisible('PHOTOS') && !ProbeComponent::isAdministrable('PHOTOS'));

// ------------------------------------------------------------------------------- a filter can revoke

fixture(array('visible:PHOTOS' => true, 'admin:PHOTOS' => true));
Hooks::addFilter('component_visible', function (bool $visible, string $component) {
    return ($component === 'PHOTOS') ? false : $visible;
});
check('a filter can hide a module', !ProbeComponent::isVisible('PHOTOS'));
check(
    'and hiding it also takes the administration with it',
    !ProbeComponent::isAdministrable('PHOTOS'),
    'checkAdministrable() asks isVisible(), so it sees the filtered answer'
);

// -------------------------------------------------------------------------------- and it can grant

fixture(array('visible:FORUM' => false));
Hooks::addFilter('component_visible', function (bool $visible, string $component) {
    return ($component === 'FORUM') ? true : $visible;
});
check('a filter can also show a module that Admidio would hide', ProbeComponent::isVisible('FORUM'));

// -------------------------------------------------------------- the two filters are separate answers

fixture(array('visible:EVENTS' => true, 'admin:EVENTS' => false));
Hooks::addFilter('component_administrable', function (bool $administrable, string $component) {
    return ($component === 'EVENTS') ? true : $administrable;
});
check('component_administrable grants administration on its own', ProbeComponent::isAdministrable('EVENTS'));
check('and does not change what is visible', ProbeComponent::isVisible('EVENTS'));

// -------------------------------------------------------------- one component does not affect another

fixture(array('visible:PHOTOS' => true, 'visible:LINKS' => true));
Hooks::addFilter('component_visible', function (bool $visible, string $component) {
    return ($component === 'PHOTOS') ? false : $visible;
});
check('a filter that names one component leaves the others alone', ProbeComponent::isVisible('LINKS'));

// ------------------------------------------------------------------- a permission is never coerced

fixture(array('visible:PHOTOS' => true));
Hooks::addFilter('component_visible', function () {
    return 1;                       // truthy, but not an answer
});
$refused = false;
try {
    ProbeComponent::isVisible('PHOTOS');
} catch (\Throwable $exception) {
    $refused = str_contains($exception->getMessage(), 'returned int instead of bool');
}
check('a filter that does not answer with a bool is refused, not coerced', $refused);

fixture(array('visible:PHOTOS' => true));
Hooks::addFilter('component_visible', function () {
    return 'yes';
});
$refused = false;
try {
    ProbeComponent::isVisible('PHOTOS');
} catch (\Throwable $exception) {
    $refused = str_contains($exception->getMessage(), 'returned string instead of bool');
}
check('and neither is a string that looks like one', $refused);

// -------------------------------------------------------------------------- the chain is a chain

fixture(array('visible:PHOTOS' => true));
$seen = array();
Hooks::addFilter('component_visible', function (bool $visible) use (&$seen) {
    $seen[] = $visible;
    return false;
}, 5);
Hooks::addFilter('component_visible', function (bool $visible) use (&$seen) {
    $seen[] = $visible;
    return $visible;
}, 10);
check('every callback sees the answer of the one before it', ProbeComponent::isVisible('PHOTOS') === false && $seen === array(true, false), json_encode($seen));

// ------------------------------------------------------- the real Component, for one component

/**
 * isVisible() is static and the CATEGORY-REPORT branch of its switch asks nothing but the settings
 * and the current user, so the real method can be executed here with those two stubbed. Nothing is
 * constructed and no other branch is reached.
 */
class StubSettings
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

class StubUser
{
    public array $rights = array();

    public function checkRolesRight(string $right): bool
    {
        return (bool)($this->rights[$right] ?? false);
    }
}

function categoryReport(bool $moduleEnabled, bool $hasRight): bool
{
    Hooks::reset();
    $settings = new StubSettings();
    $settings->values['category_report_module_enabled'] = $moduleEnabled;
    $user = new StubUser();
    $user->rights['rol_all_lists_view'] = $hasRight;

    $GLOBALS['gSettingsManager'] = $settings;
    $GLOBALS['gCurrentUser'] = $user;
    $GLOBALS['gValidLogin'] = true;

    return \Admidio\Components\Entity\Component::isVisible('CATEGORY-REPORT');
}

check('the category report is visible when it is switched on and the right is there', categoryReport(true, true));
check('it is not visible without the right', !categoryReport(true, false));
check(
    'and it is not visible when the module is switched off, which is what finding 92 was',
    !categoryReport(false, true)
);

exit(testSummary());
