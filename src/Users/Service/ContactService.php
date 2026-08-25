<?php
namespace Admidio\Users\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;
use Throwable;

/**
 ***********************************************************************************************
 * The operations on a contact that the contacts module and headless callers share.
 *
 * Ending a membership and deleting a contact are guarded by rules that are not derived from the
 * module right alone: a user may not remove or delete himself, an administrator may only be
 * touched by an administrator, and a contact that still belongs to another organization may not be
 * deleted at all. Those rules lived in modules/contacts/contacts_function.php only, so every other
 * caller had to repeat them - and repeated them differently.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class ContactService
{
    protected Database $db;
    protected ProfileFields $profileFields;

    public function __construct(Database $database, ProfileFields $profileFields)
    {
        $this->db = $database;
        $this->profileFields = $profileFields;
    }

    /**
     * End every membership the contact holds in the current organization.
     *
     * The contact keeps his account and his profile data and becomes a former member.
     *
     * @throws Exception
     */
    public function endMembership(User $user): void
    {
        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId;

        $userId = (int)$user->getValue('usr_id');

        /*
         * Somebody who is not a member has nothing to end, nobody may end his own membership
         * through this path, and an administrator may only be ended by an administrator.
         */
        if (!isMember($userId)
            || $gCurrentUserId === $userId
            || (!$gCurrentUser->isAdministrator() && $user->isAdministrator())) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $statement = $this->db->queryPrepared(
            'SELECT mem_rol_id, mem_usr_id
               FROM ' . TBL_MEMBERS . '
         INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
         INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
              WHERE rol_valid = true
                AND (cat_org_id = ? OR cat_org_id IS NULL)
                AND mem_begin <= ?
                AND mem_end > ?
                AND mem_usr_id = ?',
            array($gCurrentOrgId, DATE_NOW, DATE_NOW, $userId)
        );

        // Either every membership of the organization ends or none does.
        $this->db->startTransaction();
        try {
            while ($row = $statement->fetch()) {
                $role = new Role($this->db, (int)$row['mem_rol_id']);
                $role->stopMembership((int)$row['mem_usr_id']);
            }
            $this->db->endTransaction();
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    /**
     * Delete the contact and everything that belongs to him from the database.
     *
     * @throws Exception
     */
    public function delete(User $user): void
    {
        global $gCurrentUser, $gCurrentUserId;

        $userId = (int)$user->getValue('usr_id');

        /*
         * Deleting a contact removes a person from the installation and not only from the
         * organization, so it needs a full administrator, it may not be done to oneself, and it
         * may not be done while another organization still has the person as a member.
         */
        if ($userId === 0
            || $gCurrentUserId === $userId
            || !$gCurrentUser->isAdministrator()
            || $this->isMemberOfOtherOrganization($userId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $user->delete();
    }

    /**
     * Whether the contact currently holds a membership in an organization other than the current one.
     *
     * @throws Exception
     */
    public function isMemberOfOtherOrganization(int $userId): bool
    {
        global $gCurrentOrgId;

        return (int)$this->db->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_MEMBERS . '
         INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
         INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
              WHERE rol_valid = true
                AND cat_org_id <> ?
                AND mem_begin <= ?
                AND mem_end > ?
                AND mem_usr_id = ?',
            array($gCurrentOrgId, DATE_NOW, DATE_NOW, $userId)
        )->fetchColumn() > 0;
    }

    /**
     * Check that every profile field the organization requires carries a value.
     *
     * The contact form expresses this by marking the field required and letting FormPresenter
     * reject the submission. A caller that builds the user object itself has to ask for the same
     * decision, otherwise it can store a contact the web interface would have refused.
     *
     * @param bool $registration True while a visitor registers himself, which changes which fields
     *                           ProfileField::hasRequiredInput() considers mandatory.
     * @throws Exception
     */
    public function assertRequiredFields(User $user, bool $registration = false): void
    {
        $userId = (int)$user->getValue('usr_id');

        foreach ($this->profileFields->getProfileFields() as $nameIntern => $field) {
            if (!$this->profileFields->hasRequiredInput((string)$nameIntern, $userId, $registration)) {
                continue;
            }

            if (trim((string)$user->getValue((string)$nameIntern, 'database')) === '') {
                throw new Exception('SYS_FIELD_EMPTY', array($field->getValue('usf_name')));
            }
        }
    }
}
