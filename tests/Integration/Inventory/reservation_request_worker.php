<?php

use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Service\ReservationService;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;

require dirname(__DIR__, 2) . '/bootstrap-admidio.php';

$itemId = (int)$argv[1];
$userId = (int)$argv[2];
$startFile = $argv[3];
$resultFile = $argv[4];
$database = $GLOBALS['gDb'];
$GLOBALS['gCurrentOrgId'] = 1;
$GLOBALS['gProfileFields'] = new ProfileFields($database, 1);
$user = new User($database, $GLOBALS['gProfileFields']);
$user->readDataById($userId);
$organization = new Organization($database, 1);
$GLOBALS['gCurrentUser'] = $user;
$GLOBALS['gCurrentUserId'] = $userId;
$GLOBALS['gCurrentOrgId'] = 1;
$GLOBALS['gCurrentOrganization'] = $organization;
$GLOBALS['gSettingsManager'] = $organization->getSettingsManager();
$GLOBALS['gValidLogin'] = true;

for ($wait = 0; !file_exists($startFile) && $wait < 100; ++$wait) {
    usleep(10000);
}

try {
    (new ReservationService($database))->request(
        $itemId,
        new DateTimeImmutable('2031-01-10 10:00:00'),
        new DateTimeImmutable('2031-01-10 12:00:00')
    );
    file_put_contents($resultFile, 'approved');
} catch (Exception $exception) {
    file_put_contents($resultFile, $exception->getTranslationId());
}
