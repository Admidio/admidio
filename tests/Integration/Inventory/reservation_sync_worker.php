<?php

use Admidio\Infrastructure\Database;
use Admidio\Inventory\Service\ReservationService;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;

require dirname(__DIR__, 2) . '/bootstrap-admidio.php';

// Pause after the ordinary discovery read. The parent then commits a handover before
// synchronization acquires its item locks and must reload the current reservation state.
$config = admidioTestDatabaseConfig();
$database = new class (DB_TYPE, $config['host'], $config['port'], $config['database'], $config['user'], $config['password']) extends Database {
    public string $readyFile;
    public string $continueFile;
    private bool $paused = false;

    public function queryPrepared(string $sql, array $params = [], bool $showError = true): false|PDOStatement
    {
        $statement = parent::queryPrepared($sql, $params, $showError);
        if (!$this->paused && str_contains($sql, 'SELECT ivr_') && str_contains($sql, 'WHERE ivr_dat_id = ?') && !str_contains($sql, 'FOR UPDATE')) {
            $this->paused = true;
            touch($this->readyFile);
            $deadline = microtime(true) + 10;
            while (!file_exists($this->continueFile)) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Timed out waiting for the concurrent handover.');
                }
                usleep(10000);
            }
        }
        return $statement;
    }
};
$database->readyFile = $argv[3];
$database->continueFile = $argv[4];
$GLOBALS['gDb'] = $database;
$GLOBALS['gCurrentOrgId'] = 1;
$GLOBALS['gProfileFields'] = new ProfileFields($database, 1);
$user = new User($database, $GLOBALS['gProfileFields']);
$user->readDataById((int) $argv[2]);
$organization = new Organization($database, 1);
$GLOBALS['gCurrentUser'] = $user;
$GLOBALS['gCurrentUserId'] = (int) $argv[2];
$GLOBALS['gCurrentOrganization'] = $organization;
$GLOBALS['gSettingsManager'] = $organization->getSettingsManager();
$GLOBALS['gValidLogin'] = true;

// Use each engine's default isolation. PostgreSQL REPEATABLE READ intentionally rejects
// this interleaving with a serialization failure instead of reading the newly committed row.
$database->queryPrepared(DB_TYPE === Database::PDO_ENGINE_PGSQL
    ? 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED'
    : 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$database->startTransaction();
(new ReservationService($database))->syncEventReservations(
    (int) $argv[1],
    [],
    new DateTimeImmutable('2031-01-10 10:00'),
    new DateTimeImmutable('2031-01-10 12:00'),
);
$database->endTransaction();
