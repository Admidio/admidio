<?php

namespace Admidio\Events\Service;

use Admidio\Categories\Entity\Category;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Users\Entity\User;
use DateTime;

/**
 * Service for creating and editing events independent of the web form.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Save already validated event data.
     *
     * The array uses the same semantic field names as the current event edit form.
     * Web form validation therefore remains in the module while CLI callers can
     * provide equivalently validated data.
     *
     * @param string $eventUuid Existing event UUID. For copies this is the source event UUID.
     * @param array<string,mixed> $formValues
     * @param string $userUuid User whose leader participation should be adjusted.
     * @param bool $copy Create a new event while copying participant-role settings from $eventUuid.
     * @return Event Saved event entity.
     * @throws Exception
     */
    public function saveData(
        string $eventUuid,
        array $formValues,
        string $userUuid = '',
        bool $copy = false
    ): Event {
        global $gCurrentOrgId, $gCurrentUser, $gL10n, $gProfileFields, $gSettingsManager;

        $originalEventUuid = '';
        $targetEventUuid = $eventUuid;

        if ($copy) {
            $originalEventUuid = $eventUuid;
            $targetEventUuid = '';
        }

        $event = new Event($this->db);
        if ($targetEventUuid !== '') {
            $event->readDataByUuid($targetEventUuid);
            if ($event->isNewRecord() || !$event->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } elseif (count($gCurrentUser->getAllEditableCategories('EVT')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ($userUuid === '') {
            $userUuid = (string)$gCurrentUser->getValue('usr_uuid');
        }

        $user = new User($this->db, $gProfileFields);
        $user->readDataByUuid($userUuid);
        if ($user->isNewRecord()) {
            throw new Exception('SYS_USER_ID_NOT_FOUND');
        }

        if ((string)($formValues['dat_location'] ?? '') === '') {
            $formValues['dat_country'] = null;
        }

        $participationPossible = !empty($formValues['event_participation_possible']);
        $participationRights = array_map(
            'intval',
            $formValues['adm_event_participation_right'] ?? array()
        );

        if ($participationPossible && count($participationRights) === 0) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_REGISTRATION_POSSIBLE_FOR'));
        }

        if (empty($formValues['cat_uuid'])) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_CALENDAR'));
        }

        $calendar = new Category($this->db);
        $calendar->readDataByUuid((string)$formValues['cat_uuid']);
        if ($calendar->isNewRecord()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $formValues['dat_cat_id'] = $calendar->getValue('cat_id');

        if (!empty($formValues['dat_all_day'])) {
            $formValues['event_from_time'] = '00:00';
            $formValues['event_to_time'] = '00:00';
        }

        $eventFrom = (string)($formValues['event_from'] ?? '');
        $eventFromTime = (string)($formValues['event_from_time'] ?? '');
        $startDateTime = DateTime::createFromFormat('Y-m-d H:i', $eventFrom . ' ' . $eventFromTime);

        if (!$startDateTime) {
            $startDateTime = DateTime::createFromFormat('Y-m-d', $eventFrom);
            if (!$startDateTime) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_START', 'YYYY-MM-DD'));
            }

            throw new Exception(
                'SYS_TIME_INVALID',
                array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_START'), 'HH:ii')
            );
        }
        $formValues['dat_begin'] = $eventFrom . ' ' . $eventFromTime;

        if ((string)($formValues['event_to'] ?? '') === '') {
            $formValues['event_to'] = $eventFrom;
        }
        if ((string)($formValues['event_to_time'] ?? '') === '') {
            $formValues['event_to_time'] = $eventFromTime;
        }

        $endDateTime = DateTime::createFromFormat(
            'Y-m-d H:i',
            $formValues['event_to'] . ' ' . $formValues['event_to_time']
        );

        if (!$endDateTime) {
            $endDateTime = DateTime::createFromFormat('Y-m-d', (string)$formValues['event_to']);
            if (!$endDateTime) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_END', 'YYYY-MM-DD'));
            }

            throw new Exception(
                'SYS_TIME_INVALID',
                array($gL10n->get('SYS_TIME') . ' ' . $gL10n->get('SYS_END'), 'HH:ii')
            );
        }
        $formValues['dat_end'] = $formValues['event_to'] . ' ' . $formValues['event_to_time'];

        if ($startDateTime > $endDateTime) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        if (!isset($formValues['dat_room_id'])) {
            $formValues['dat_room_id'] = 0;
        }

        if (!is_numeric($formValues['dat_max_members'] ?? null)) {
            $formValues['dat_max_members'] = 0;
        } else {
            $participants = new Participants($this->db, (int)$event->getValue('dat_rol_id'));
            $totalMembers = $participants->getCount();
            if ((int)$formValues['dat_max_members'] < $totalMembers
                && (int)$formValues['dat_max_members'] > 0) {
                $formValues['dat_max_members'] = $totalMembers;
            }
        }

        if ($participationPossible && (string)($formValues['event_deadline'] ?? '') !== '') {
            $formValues['dat_deadline'] = $formValues['event_deadline'] . ' '
                . ((string)($formValues['event_deadline_time'] ?? '') === ''
                    ? '00:00'
                    : $formValues['event_deadline_time']);
        } else {
            $formValues['dat_deadline'] = null;
        }

        if (array_key_exists('adm_event_participation_right', $formValues)) {
            $rightCategoryView = new RolesRights(
                $this->db,
                'category_view',
                (int)$calendar->getValue('cat_id')
            );

            if (count($rightCategoryView->getRolesIds()) > 0
                && count(array_intersect($participationRights, $rightCategoryView->getRolesIds()))
                    !== count($participationRights)) {
                throw new Exception(
                    'SYS_EVENT_CATEGORIES_ROLES_DIFFERENT',
                    array(implode(', ', $rightCategoryView->getRolesNames()))
                );
            }
        }

        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $eventRoomId = (int)$formValues['dat_room_id'];

            if ($eventRoomId > 0) {
                $sql = 'SELECT COUNT(*) AS count
                          FROM ' . TBL_EVENTS . '
                         WHERE dat_begin <= ?
                           AND dat_end >= ?
                           AND dat_room_id = ?
                           AND dat_uuid <> ?';
                $eventsStatement = $this->db->queryPrepared(
                    $sql,
                    array(
                        $endDateTime->format('Y-m-d H:i:s'),
                        $startDateTime->format('Y-m-d H:i:s'),
                        $eventRoomId,
                        $targetEventUuid
                    )
                );

                if ($eventsStatement->fetchColumn()) {
                    throw new Exception('SYS_ROOM_RESERVED');
                }

                $event->setValue('dat_room_id', $eventRoomId);
                $room = new Room($this->db);
                $room->readDataById($eventRoomId);
                $number = (int)$room->getValue('room_capacity') + (int)$room->getValue('room_overhang');
                $event->setValue('dat_max_members', $number);
                $eventMaxMembers = (int)$formValues['dat_max_members'];

                if ($eventMaxMembers > 0 && $eventMaxMembers < $number) {
                    $event->setValue('dat_max_members', $eventMaxMembers);
                }
            }
        }

        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'dat_')) {
                $event->setValue($key, $value);
            }
        }

        $this->db->startTransaction();

        if ($event->save()) {
            $event->sendNotification();
        }

        $rightEventParticipation = new RolesRights(
            $this->db,
            'event_participation',
            (int)$event->getValue('dat_id')
        );
        $rightEventParticipation->saveRoles($participationRights);

        if ($participationPossible) {
            if ((int)$event->getValue('dat_rol_id') > 0) {
                $role = new Role($this->db, (int)$event->getValue('dat_rol_id'));
                $role->setValue(
                    'rol_name',
                    $event->getDateTimePeriod(false) . ' ' . $event->getValue('dat_headline')
                );
                $role->setValue(
                    'rol_description',
                    substr((string)$event->getValue('dat_description'), 0, 3999)
                );
                $role->setValue(
                    'rol_view_memberships',
                    !empty($formValues['event_right_list_view'])
                        ? Role::VIEW_ROLE_MEMBERS
                        : Role::VIEW_LEADERS
                );
                $role->setValue(
                    'rol_mail_this_role',
                    !empty($formValues['event_right_send_mail'])
                        ? Role::VIEW_ROLE_MEMBERS
                        : Role::VIEW_NOBODY
                );
                $role->setValue('rol_max_members', (int)$event->getValue('dat_max_members'));
                $role->save();
            } else {
                if ($copy && $originalEventUuid !== '') {
                    $sql = 'SELECT dat_rol_id
                              FROM ' . TBL_EVENTS . '
                             WHERE dat_uuid = ?';
                    $pdoStatement = $this->db->queryPrepared($sql, array($originalEventUuid));

                    $role = new Role($this->db, (int)$pdoStatement->fetchColumn());
                    $role->setNewRecord();
                } else {
                    $sql = 'SELECT cat_id
                              FROM ' . TBL_CATEGORIES . '
                             WHERE cat_name_intern = \'EVENTS\'
                               AND cat_org_id = ?';
                    $pdoStatement = $this->db->queryPrepared($sql, array($gCurrentOrgId));
                    if (!$row = $pdoStatement->fetch()) {
                        throw new Exception('No category found for event participation!');
                    }

                    $role = new Role($this->db);
                    $role->setType(Role::ROLE_EVENT);
                    $role->setValue('rol_cat_id', (int)$row['cat_id']);
                    $role->setValue(
                        'rol_view_memberships',
                        !empty($formValues['event_right_list_view'])
                            ? Role::VIEW_ROLE_MEMBERS
                            : Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT
                    );
                    $role->setValue(
                        'rol_mail_this_role',
                        !empty($formValues['event_right_send_mail'])
                            ? Role::VIEW_ROLE_MEMBERS
                            : Role::VIEW_NOBODY
                    );
                    $role->setValue('rol_leader_rights', Role::ROLE_LEADER_MEMBERS_ASSIGN);
                    $role->setValue('rol_max_members', (int)$formValues['dat_max_members']);
                }

                $role->setValue(
                    'rol_name',
                    $event->getDateTimePeriod(false) . ' '
                        . $event->getValue('dat_headline', 'database')
                );
                $role->setValue(
                    'rol_description',
                    substr((string)$event->getValue('dat_description', 'database'), 0, 3999)
                );
                $role->save();

                $event->setValue('dat_rol_id', (int)$role->getValue('rol_id'));
                $event->save();
            }

            if (!empty($formValues['event_current_user_assigned'])
                && !$gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'))) {
                $role->startMembership((int)$user->getValue('usr_id'), true);
            } elseif (empty($formValues['event_current_user_assigned'])
                && $gCurrentUser->isMemberOfRole((int)$event->getValue('dat_rol_id'))) {
                $member = new Membership($this->db);
                $member->readDataByColumns(
                    array(
                        'mem_rol_id' => (int)$role->getValue('rol_id'),
                        'mem_usr_id' => (int)$user->getValue('usr_id')
                    )
                );
                $member->setValue('mem_leader', 0);
                $member->save();
            }
        } elseif ((int)$event->getValue('dat_rol_id') > 0) {
            $role = new Role($this->db, (int)$event->getValue('dat_rol_id'));
            $event->setValue('dat_rol_id', '');
            $event->save();
            $role->delete();
        }

        $this->db->endTransaction();

        return $event;
    }
}
