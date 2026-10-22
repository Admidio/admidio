<?php
/**
 * Helpers for tests that depend on the organization and login context.
 *
 * Admidio resolves rights against globals: User::__construct() copies $gCurrentOrgId into the
 * object, and Component::isVisible() reads $gCurrentUser, $gValidLogin and $gSettingsManager.
 * A test therefore has to set those globals around the code it exercises and put them back
 * afterwards, so that no test leaks its context into the next one.
 */

namespace Admidio\Tests\Support;

use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Users\Entity\User;

trait PermissionContext
{
    /**
     * Run a callback with the given organization as the current one.
     *
     * @param int $orgId Organization that should be current inside the callback
     * @param callable $callback Receives no arguments, its return value is passed through
     * @return mixed The return value of the callback
     */
    protected function withOrganization(int $orgId, callable $callback)
    {
        $previousOrgId = $GLOBALS['gCurrentOrgId'];
        $GLOBALS['gCurrentOrgId'] = $orgId;

        try {
            return $callback();
        } finally {
            $GLOBALS['gCurrentOrgId'] = $previousOrgId;
        }
    }

    /**
     * Load a user whose rights are resolved against the given organization.
     * The organization is fixed when the object is constructed, so it cannot be changed afterwards.
     *
     * @param int $usrId User whose record should be read
     * @param int $orgId Organization the rights should be resolved against
     * @return User
     */
    protected function loadUserInOrganization(int $usrId, int $orgId): User
    {
        return $this->withOrganization($orgId, function () use ($usrId) {
            $user = new User($this->getDatabase());
            $user->readDataById($usrId);

            return $user;
        });
    }

    /**
     * Run a callback as the given user, with the settings of the given organization.
     *
     * @param User $user The user that Component and friends should see as the current one
     * @param int $orgId Organization whose settings should be used
     * @param bool $validLogin Whether the user counts as logged in
     * @param callable $callback Receives no arguments, its return value is passed through
     * @return mixed The return value of the callback
     */
    protected function withCurrentUser(User $user, int $orgId, bool $validLogin, callable $callback)
    {
        $organization = new Organization($this->getDatabase(), $orgId);

        $previous = array(
            'gCurrentUser' => $GLOBALS['gCurrentUser'] ?? null,
            'gCurrentUserId' => $GLOBALS['gCurrentUserId'] ?? 0,
            'gCurrentOrganization' => $GLOBALS['gCurrentOrganization'] ?? null,
            'gValidLogin' => $GLOBALS['gValidLogin'] ?? null,
            'gSettingsManager' => $GLOBALS['gSettingsManager'] ?? null,
        );

        $GLOBALS['gCurrentUser'] = $user;
        $GLOBALS['gCurrentUserId'] = (int) $user->getValue('usr_id');
        $GLOBALS['gCurrentOrganization'] = $organization;
        $GLOBALS['gValidLogin'] = $validLogin;
        $GLOBALS['gSettingsManager'] = $organization->getSettingsManager();

        try {
            return $this->withOrganization($orgId, $callback);
        } finally {
            foreach ($previous as $name => $value) {
                $GLOBALS[$name] = $value;
            }
        }
    }

    /**
     * Get the settings manager of an organization.
     * An organization created by the fixture starts without any preferences, so a test that
     * exercises a module setting has to write it first.
     *
     * @param int $orgId Organization ID
     * @return SettingsManager
     */
    protected function settingsOf(int $orgId): SettingsManager
    {
        $organization = new Organization($this->getDatabase(), $orgId);

        return $organization->getSettingsManager();
    }
}
