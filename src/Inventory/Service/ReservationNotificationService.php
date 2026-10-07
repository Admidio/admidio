<?php

namespace Admidio\Inventory\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Users\Entity\User;

/** Sends the configured reservation emails only after the reservation has committed. */
class ReservationNotificationService
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /** Notify reservation managers about a newly created reservation and requesters about automatic approval. */
    public function notifyCreated(Reservation $reservation): void
    {
        $this->notifyAfterCommit(
            $reservation,
            true,
            (string)$reservation->getValue('ivr_status') === Reservation::STATUS_APPROVED
        );
    }

    /** Notify the requester of a decision and managers when an open reservation is cancelled. */
    public function notifyStatusChanged(Reservation $reservation): void
    {
        $status = (string)$reservation->getValue('ivr_status');
        $this->notifyAfterCommit(
            $reservation,
            $status === Reservation::STATUS_CANCELLED,
            in_array($status, array(
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_REJECTED,
                Reservation::STATUS_CANCELLED
            ), true)
        );
    }

    private function notifyAfterCommit(Reservation $reservation, bool $notifyManagers, bool $notifyRequester): void
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('system_notifications_enabled')
            || !$gSettingsManager->getBool('inventory_reservation_notifications_enabled')) {
            return;
        }

        $this->database->registerAfterCommit(function () use ($reservation, $notifyManagers, $notifyRequester): void {
            if ($notifyManagers) {
                $this->sendToManagers($reservation);
            }
            if ($notifyRequester) {
                $this->sendToRequester($reservation);
            }
        });
    }

    private function sendToManagers(Reservation $reservation): void
    {
        global $gSettingsManager;

        $roleUuid = $gSettingsManager->getString('inventory_reservation_notification_role');
        if ($roleUuid === '') {
            return;
        }

        $email = $this->createEmail($reservation);
        if ($email->addRecipientsByRole($roleUuid) > 0) {
            $email->sendEmail();
        }
    }

    private function sendToRequester(Reservation $reservation): void
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('inventory_reservation_notify_requester')) {
            return;
        }

        $requester = $this->getRequester($reservation);
        if ($requester['email'] === '') {
            return;
        }

        $email = $this->createEmail($reservation);
        if ($email->addRecipient($requester['email'], $requester['firstName'], $requester['lastName'])) {
            $email->sendEmail();
        }
    }

    private function createEmail(Reservation $reservation): Email
    {
        global $gCurrentOrganization, $gL10n, $gSettingsManager;

        $html = $gSettingsManager->getBool('mail_html_registered_users');
        $item = new Item($this->database, null, (int)$reservation->getValue('ivr_ini_id'));
        $requester = $this->getRequester($reservation);
        $format = static function (string $value) use ($html): string {
            return $html ? SecurityUtils::encodeHTML($value) : $value;
        };
        $dateFormat = $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time');
        $status = $gL10n->get('SYS_INVENTORY_RESERVATION_STATUS_' . strtoupper((string)$reservation->getValue('ivr_status')));
        $message = $gL10n->get('SYS_INVENTORY_RESERVATION_NOTIFICATION_MESSAGE', array(
            $format($status),
            $format($item->readableName()),
            $format((new \DateTimeImmutable((string)$reservation->getValue('ivr_begin')))->format($dateFormat)),
            $format((new \DateTimeImmutable((string)$reservation->getValue('ivr_end')))->format($dateFormat)),
            $format(trim($requester['firstName'] . ' ' . $requester['lastName']))
        ));
        if (!$html) {
            $message = str_replace('<br />', "\n", $message);
        }

        $email = new Email();
        if ($html) {
            $email->setHtmlMail();
        }
        $email->setSubject($gCurrentOrganization->getValue('org_shortname') . ': '
            . $gL10n->get('SYS_INVENTORY_RESERVATION_NOTIFICATION_SUBJECT', array($item->readableName(), $status)));
        $email->setText($message);

        return $email;
    }

    /** @return array{email:string,firstName:string,lastName:string} */
    private function getRequester(Reservation $reservation): array
    {
        $userId = (int)$reservation->getValue('ivr_usr_id');
        if ($userId > 0) {
            $user = new User($this->database, null, $userId);
            return array(
                'email' => (string)$user->getValue('EMAIL'),
                'firstName' => (string)$user->getValue('FIRST_NAME'),
                'lastName' => (string)$user->getValue('LAST_NAME')
            );
        }

        return array(
            'email' => (string)$reservation->getValue('ivr_guest_email'),
            'firstName' => (string)$reservation->getValue('ivr_guest_name'),
            'lastName' => ''
        );
    }
}
