<?php
/**
 * Entry point of the self-contained Admidio core updater.
 *
 * Only an administrator gets past this file. As long as no file of Admidio has been changed, that
 * is the Admidio login: the updater runs inside the installed Admidio, whatever 5.x release it is.
 * From the maintenance mode on Admidio cannot be used, so the browser that started the
 * installation carries the updater cookie, and the updater's own login is the fallback for another
 * device or a lost cookie. Every request that changes something is a POST with the CSRF token of
 * the update.
 *
 * The functions of this file are prefixed with coreUpdate, because it shares the global namespace
 * with Admidio's functions.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;

require_once __DIR__ . '/lib/bootstrap.php';

U\sendSecurityHeaders();

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postedAction = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$texts = null;

try {
    $config = U\loadConfig(dirname(__DIR__, 2));
    if (in_array($config['timezone'], timezone_identifiers_list(), true)) {
        date_default_timezone_set($config['timezone']);
    }

    $pdo = null;
    try {
        $pdo = U\connectDatabase($config);
        $texts = U\loadTexts(__DIR__, $config['rootPath'], U\installationLanguage($pdo, $config['tablePrefix']));
    } catch (UpdaterException) {
        // The login needs the database, the update itself does not. Without it the texts are English.
        $texts = U\loadTexts(__DIR__, $config['rootPath'], 'en');
    }

    $rootPath = $config['rootPath'];
    $updateId = coreUpdateAuthenticate($config, $_COOKIE[U\cookieName(U\cookiePrefix($config))] ?? null);

    if ($updateId === null && U\isAdmidioIntact($rootPath)) {
        // The Admidio login decides. common.php has to be included here, at the top level, so that
        // its globals are globals. loadConfig() has read config.php in a scope of its own, so the
        // require_once of common.php would skip it: it is included here first.
        include $rootPath . '/adm_my_files/config.php';
        require_once $rootPath . '/system/common.php';

        if (!$gValidLogin || !$gCurrentUser->isAdministrator()) {
            coreUpdatePage(
                $texts,
                null,
                coreUpdateAlert('info', U\h(U\text($texts, 'SYS_CORE_UPDATE_ADMIDIO_LOGIN'))),
                coreUpdateLink($config['baseUrl'] . '/system/login.php', 'bi-box-arrow-in-right', U\text($texts, 'SYS_CORE_UPDATE_LOGIN'))
            );
            exit;
        }

        $updateId = U\activeUpdateId($rootPath)
            ?? U\createUpdate($rootPath, array('installedVersion' => U\filesVersion($rootPath)))['updateId'];
    }

    if ($updateId === null) {
        // Maintenance mode is on and this browser has no updater cookie.
        coreUpdateHandleLogin($config, $pdo, $texts, $isPost && $postedAction === 'login');
        exit;
    }

    $state = U\readState($rootPath, $updateId);
    if ($isPost && !U\isValidCsrfToken($state, $_POST['csrf_token'] ?? null)) {
        throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
    }

    $context = array(
        'config' => $config,
        'pdo' => $pdo,
        'texts' => $texts,
        'updaterPath' => __DIR__,
        // Plain HTTP is only allowed below the test server of debug mode, which only then is set.
        'http' => array('plainHttpBase' => $config['serverUrl']),
        'chunkSize' => U\CHUNK_SIZE
    );

    // Phase A: from the choice of the channel to the prepared package; phase B from there on.
    if ($state['state'] !== 'prepared' && in_array((string)$state['state'], U\PHASE_A_STATES, true)) {
        coreUpdateHandlePhaseA($context, $state, $isPost, $postedAction);
    } else {
        coreUpdateHandlePhaseB($context, $state, $isPost, $postedAction);
    }
} catch (Throwable $exception) {
    // Before the administrator is known, an error says nothing about the installation.
    $message = $exception instanceof UpdaterException ? $exception->getText($texts) : 'The update could not be continued.';
    if (!$exception instanceof UpdaterException) {
        error_log('Admidio core updater: ' . $exception);
    }

    if ($isPost && $postedAction !== 'login') {
        U\jsonResponse(array('status' => 'error', 'message' => $message), 500);
        exit;
    }

    http_response_code(500);
    U\showPage(
        $texts !== null ? U\text($texts, 'SYS_CORE_UPDATE_TITLE') : 'Admidio update',
        coreUpdateAlert('danger', U\h($message)),
        array('language' => $texts !== null ? $texts['language'] : 'en')
    );
}

/**
 * The update the updater cookie belongs to, or **null** if the request carries no valid credential.
 * A valid credential is kept alive for another day, in the state and in the browser.
 * @param array<string,mixed> $config
 */
function coreUpdateAuthenticate(array $config, mixed $cookieValue): ?string
{
    $parsed = U\parseCredential($cookieValue);
    if ($parsed === null || !U\updateExists($config['rootPath'], $parsed[0])) {
        return null;
    }

    $valid = false;
    U\updateState($config['rootPath'], $parsed[0], static function (array $state) use ($cookieValue, &$valid): array {
        $valid = U\refreshCredential($state, $cookieValue, time());

        return $state;
    });

    if (!$valid) {
        return null;
    }

    U\sendCredentialCookie($config, (string)$cookieValue);

    return $parsed[0];
}

/**
 * The updater's own login: the form, and what happens when it is sent.
 *
 * It is only needed while maintenance mode is on and Admidio cannot be used: to continue the
 * update from another device, or after the updater cookie was lost. A successful login gives this
 * browser a credential for the unfinished update.
 * @param array<string,mixed> $config
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateHandleLogin(array $config, ?PDO $pdo, array $texts, bool $submitted): void
{
    $loginCookie = $_COOKIE[U\cookiePrefix($config) . '_CORE_UPDATE_LOGIN'] ?? null;
    $error = '';

    if ($submitted) {
        try {
            if (!U\isValidLoginCsrfToken($loginCookie, $_POST['csrf_token'] ?? null)) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }
            if ($pdo === null) {
                throw new UpdaterException('The database of the installation could not be reached.');
            }

            U\authenticateAdministrator(
                $pdo,
                $config['tablePrefix'],
                U\updatesDirectory($config['rootPath']),
                (string)($_POST['login_name'] ?? ''),
                (string)($_POST['password'] ?? ''),
                (string)($_POST['totp_code'] ?? ''),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                time()
            );

            $updateId = U\activeUpdateId($config['rootPath']);
            if ($updateId === null) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }

            $cookieValue = '';
            U\updateState($config['rootPath'], $updateId, static function (array $state) use (&$cookieValue): array {
                $cookieValue = U\issueCredential($state, time());

                return $state;
            });
            U\sendCredentialCookie($config, $cookieValue);

            // Post/Redirect/Get, so that a reload does not send the password again.
            header('Location: index.php', true, 303);
            return;
        } catch (UpdaterException $exception) {
            $error = $exception->getText($texts);
        }
    }

    coreUpdatePage($texts, null, U\loginForm($texts, U\loginCsrfToken($config, $loginCookie), $error));
}

/**
 * Phase A: the requests of its forms and of the download loop, and its pages.
 *
 * The forms are plain form posts answered with a redirect, so that a reload does not send them
 * again; the download is driven by the page's script, one bounded request after the other.
 * @param array<string,mixed> $context See install/core-update/lib/phase-a.php.
 * @param array<string,mixed> $state
 */
function coreUpdateHandlePhaseA(array $context, array $state, bool $isPost, string $action): void
{
    $updateId = (string)$state['updateId'];
    $config = $context['config'];
    $texts = $context['texts'];

    if ($isPost) {
        if ($action === 'run') {
            U\jsonResponse(U\phaseARun($context, $updateId));

            return;
        }
        if ($action === 'cancel') {
            U\phaseACancel($context, $updateId);
            coreUpdatePage($texts, null, coreUpdateAlert('info', U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCELLED'))),
                coreUpdateOpenAdmidio($config, $texts));

            return;
        }

        match ($action) {
            'check' => U\phaseACheck($context, $updateId, (string)($_POST['channel'] ?? 'stable')),
            'confirm' => U\phaseAConfirm($context, $updateId),
            default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
        };
        header('Location: index.php', true, 303);

        return;
    }

    // With one channel there is nothing to choose, so the updater checks as soon as it is opened.
    // After a failed check the administrator sees the error and starts the next one.
    if ($state['state'] === 'new' && count(U\availableChannels($config)) === 1 && (string)($state['lastError'] ?? '') === '') {
        $state = U\phaseACheck($context, $updateId, 'stable');
    }

    coreUpdateShowPhaseA($state, $config, $texts);
}

/**
 * The page of phase A for the state the update is in.
 * @param array<string,mixed> $state
 * @param array<string,mixed> $config
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateShowPhaseA(array $state, array $config, array $texts): void
{
    $csrf = '<input type="hidden" name="csrf_token" value="' . U\h((string)$state['csrfToken']) . '">';
    $error = (string)($state['lastError'] ?? '') !== '' ? coreUpdateAlert('danger', U\h((string)$state['lastError'])) : '';
    $versions = '<dl class="core-update-versions"><dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_INSTALLED_VERSION')) . '</dt><dd>'
        . U\h((string)($state['installedVersion'] ?? '')) . '</dd>';

    switch ($state['state']) {
        case 'new':
        case 'checking':
            $channels = U\availableChannels($config);
            $choice = '';
            foreach ($channels as $channel) {
                $choice .= '<div class="form-check"><input class="form-check-input" type="radio" name="channel" id="channel_' . U\h($channel)
                    . '" value="' . U\h($channel) . '" form="check-form"' . ($channel === ($state['channel'] ?? 'stable') ? ' checked' : '') . '>'
                    . '<label class="form-check-label" for="channel_' . U\h($channel) . '">' . U\h(coreUpdateChannelName($channel, $texts)) . '</label></div>';
            }
            $choice = count($channels) > 1
                ? '<fieldset class="mb-3"><legend class="fs-6 fw-bold">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHANNEL')) . '</legend>' . $choice . '</fieldset>'
                : '';

            coreUpdatePage(
                $texts,
                $state,
                $error . '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_START_DESC')) . '</p>' . $versions . '</dl>' . $choice,
                '<form method="post" action="index.php" id="check-form">' . $csrf . '<input type="hidden" name="action" value="check">'
                    . (count($channels) > 1 ? '' : '<input type="hidden" name="channel" value="stable">')
                    . coreUpdateButton('bi-search', U\text($texts, count($channels) > 1 ? 'SYS_CORE_UPDATE_CONTINUE' : 'SYS_CORE_UPDATE_CHECK_AGAIN')) . '</form>'
                    . coreUpdateCancelForm($csrf, $texts)
            );
            break;

        case 'checked':
            $checks = (array)($state['checks'] ?? array());
            $actions = '';
            if (!U\checksHaveErrors($checks)) {
                $actions .= '<form method="post" action="index.php">' . $csrf . '<input type="hidden" name="action" value="confirm">'
                    . coreUpdateButton('bi-cloud-download', U\text($texts, 'SYS_CORE_UPDATE_DOWNLOAD')) . '</form>';
            }
            $actions .= '<form method="post" action="index.php">' . $csrf . '<input type="hidden" name="action" value="check">'
                . '<input type="hidden" name="channel" value="' . U\h((string)$state['channel']) . '">'
                . coreUpdateButton('bi-arrow-repeat', U\text($texts, 'SYS_CORE_UPDATE_CHECK_AGAIN'), U\checksHaveErrors($checks)) . '</form>'
                . coreUpdateCancelForm($csrf, $texts);

            // P10: if the browser can read the probe file, adm_my_files is reachable from outside.
            $probeUrl = $config['baseUrl'] . '/adm_my_files/core-update/' . $state['updateId'] . '/probe.txt';
            $script = '<script>
                fetch(' . U\js($probeUrl) . ', {cache: "no-store", credentials: "omit"})
                    .then(response => response.ok ? response.text() : "")
                    .then(text => {
                        if (text.trim() === ' . U\js((string)($state['probeToken'] ?? '')) . ') {
                            document.getElementById("reachable").hidden = false;
                        }
                    })
                    .catch(() => {});
            </script>';

            coreUpdatePage(
                $texts,
                $state,
                $error . $versions
                    . '<dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_TARGET')) . '</dt><dd>'
                    . U\h((string)($state['targetVersionText'] ?? $state['targetVersion'])) . '</dd>'
                    . '<dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHANNEL')) . '</dt><dd>'
                    . U\h(coreUpdateChannelName((string)$state['channel'], $texts)) . '</dd></dl>'
                    . ($state['channel'] === 'master' ? coreUpdateAlert('warning', U\h(U\text($texts, 'SYS_CORE_UPDATE_MASTER_WARNING'))) : '')
                    . '<h4 class="mt-4">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECKS')) . '</h4>' . coreUpdateCheckList($checks, $texts)
                    . '<div id="reachable" hidden>' . coreUpdateAlert('warning', U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECK_REACHABLE'))) . '</div>',
                $actions . $script
            );
            break;

        default:
            // downloading, downloaded: the page drives the download until the package is prepared.
            $script = '<script>
                const csrfToken = ' . U\js((string)$state['csrfToken']) . ';
                const requestFailedText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
                const message = document.getElementById("message");
                const progress = document.getElementById("progress-bar");
                const retry = document.getElementById("retry-button");

                async function step() {
                    const body = new URLSearchParams({action: "run", csrf_token: csrfToken});
                    try {
                        const response = await fetch("index.php", {method: "POST", credentials: "same-origin", body: body});
                        return await response.json();
                    } catch (error) {
                        return {status: "error", message: requestFailedText};
                    }
                }

                async function download() {
                    retry.hidden = true;
                    message.className = "alert alert-info";
                    while (true) {
                        const result = await step();
                        message.textContent = result.message || "";
                        progress.style.width = (result.progress || 0) + "%";
                        if (result.state) {
                            showStep(result.state);
                        }
                        if (result.status === "prepared") {
                            window.location.reload();
                            return;
                        }
                        if (result.status !== "success") {
                            message.className = "alert alert-danger";
                            retry.hidden = result.status === "failed";
                            return;
                        }
                    }
                }

                retry.addEventListener("click", download);
                download();
            </script>';

            coreUpdatePage(
                $texts,
                $state,
                '<div id="message" class="alert alert-info" role="status">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_DOWNLOADING', array((string)$state['targetVersion']))) . '</div>',
                '<button class="btn btn-primary" id="retry-button" type="button" hidden><i class="bi bi-arrow-repeat"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_START_RESUME')) . '</button>'
                    . coreUpdateCancelForm($csrf, $texts) . $script,
                U\phaseAProgress($state)
            );
    }
}

/**
 * The name of a channel for the administrator.
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateChannelName(string $channel, array $texts): string
{
    return match ($channel) {
        'beta' => U\text($texts, 'SYS_CORE_UPDATE_CHANNEL_BETA'),
        'master' => U\text($texts, 'SYS_CORE_UPDATE_CHANNEL_MASTER'),
        default => U\text($texts, 'SYS_CORE_UPDATE_CHANNEL_STABLE')
    };
}

/**
 * Phase B: the requests of its forms and of the loop that drives it, and its pages.
 * @param array<string,mixed> $context See install/core-update/lib/phase-a.php.
 * @param array<string,mixed> $state
 */
function coreUpdateHandlePhaseB(array $context, array $state, bool $isPost, string $action): void
{
    $updateId = (string)$state['updateId'];
    $config = $context['config'];
    $texts = $context['texts'];

    if ($isPost) {
        match ($action) {
            'run' => U\jsonResponse(U\phaseBRun($context, $updateId)),
            'rollback' => U\jsonResponse(U\phaseBResult($context, U\phaseBRollback($context, $updateId))),
            'confirm' => (static function () use ($context, $updateId): void {
                U\phaseBConfirm($context, $updateId, (string)($_POST['backup'] ?? ''));
                header('Location: index.php', true, 303);
            })(),
            'restore' => (static function () use ($context, $updateId): void {
                U\phaseBRestore($context, $updateId, !empty($_POST['own_database_restored']));
                header('Location: index.php', true, 303);
            })(),
            'database_status' => U\jsonResponse(array('state' => (string)$state['state'],
                'running' => U\isDatabaseUpdateRunning($config['rootPath'], $updateId))),
            'cancel' => (static function () use ($context, $updateId, $config, $texts): void {
                U\phaseBCancel($context, $updateId);
                coreUpdatePage($texts, null, coreUpdateAlert('info', U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCELLED'))),
                    coreUpdateOpenAdmidio($config, $texts));
            })(),
            default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
        };

        return;
    }

    coreUpdateShowPhaseB($context, $state);
}

/**
 * The page of phase B for the state the update is in.
 * @param array<string,mixed> $context
 * @param array<string,mixed> $state
 */
function coreUpdateShowPhaseB(array $context, array $state): void
{
    $config = $context['config'];
    $texts = $context['texts'];
    $csrf = '<input type="hidden" name="csrf_token" value="' . U\h((string)$state['csrfToken']) . '">';
    $previous = (string)$state['installedVersion'];
    $error = (string)($state['lastError'] ?? '') !== '' ? coreUpdateAlert('danger', U\h((string)$state['lastError'])) : '';

    switch ($state['state']) {
        case 'review':
            // The decision comes first; what changes in detail is folded away.
            $plan = (array)($state['plan'] ?? array());
            $checks = (array)($state['planChecks'] ?? array());
            $problems = array_filter($checks, static fn(array $check): bool => $check['status'] !== 'ok');
            $target = (string)($state['targetVersionText'] ?? $state['targetVersion']);

            $counts = '';
            foreach (array('create' => 'SYS_CORE_UPDATE_PLAN_CREATE', 'replace' => 'SYS_CORE_UPDATE_PLAN_REPLACE',
                'delete' => 'SYS_CORE_UPDATE_PLAN_DELETE', 'unchanged' => 'SYS_CORE_UPDATE_PLAN_UNCHANGED') as $key => $text) {
                $counts .= '<li>' . U\h(U\text($texts, $text, array((string)($plan[$key] ?? 0)))) . '</li>';
            }
            $operations = U\readPlan(U\workspace($config['rootPath'], (string)$state['updateId']));
            $paths = static fn(callable $filter): array => array_column(array_filter($operations, $filter), 'path');
            $fileList = static fn(string $title, array $files): string => $files === array() ? ''
                : '<details class="mb-2"><summary>' . U\h(U\text($texts, $title)) . ' (' . count($files) . ')</summary><ul>'
                    . implode('', array_map(static fn(string $path): string => '<li>' . U\h($path) . '</li>', $files)) . '</ul></details>';
            $details = '<details class="mb-4"><summary>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_REVIEW_DETAILS')) . '</summary>'
                . '<ul class="mt-2">' . $counts . '</ul>'
                . ((int)($plan['kept'] ?? 0) === 0 ? '' : '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_KEPT_DESC', array((string)$plan['kept'])))
                    . (empty($plan['inventory']) ? ' ' . U\h(U\text($texts, 'SYS_CORE_UPDATE_NO_INVENTORY')) : '') . '</p>')
                . $fileList('SYS_CORE_UPDATE_PLAN_DELETED_FILES', $paths(static fn(array $operation): bool => $operation['action'] === 'delete'))
                . $fileList('SYS_CORE_UPDATE_PLAN_MODIFIED_FILES', $paths(static fn(array $operation): bool => ($operation['keep'] ?? '') === 'modified'))
                . $fileList('SYS_CORE_UPDATE_PLAN_UNKNOWN_FILES', $paths(static fn(array $operation): bool => ($operation['keep'] ?? '') === 'unknown'))
                . '</details>';

            // The backup of the database: by the updater where it can, or confirmed by the administrator.
            $canDump = (string)($state['dumpMethod'] ?? '') !== '';
            $radio = static fn(string $value, string $label, bool $checked): string => '<div class="form-check">'
                . '<input class="form-check-input" type="radio" name="backup" id="backup_' . $value . '" value="' . $value . '" form="install-form" required'
                . ($checked ? ' checked' : '') . '><label class="form-check-label fw-normal" for="backup_' . $value . '">' . U\h(U\text($texts, $label)) . '</label></div>';
            $backup = '<fieldset class="mb-4"><legend class="fs-5">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_BACKUP')) . '</legend>'
                . ($canDump ? $radio('dump', 'SYS_CORE_UPDATE_BACKUP_DATABASE', true)
                    : coreUpdateAlert('warning', U\h(U\text($texts, 'SYS_CORE_UPDATE_BACKUP_NOT_POSSIBLE'))))
                . $radio('own', 'SYS_CORE_UPDATE_BACKUP_OWN', false) . '</fieldset>';

            $actions = '';
            if (!U\checksHaveErrors($checks)) {
                $actions .= '<form method="post" action="index.php" id="install-form">' . $csrf . '<input type="hidden" name="action" value="confirm">'
                    . coreUpdateButton('bi-box-arrow-in-down', U\text($texts, 'SYS_CORE_UPDATE_INSTALL')) . '</form>';
            }
            $actions .= coreUpdateCancelForm($csrf, $texts);

            coreUpdatePage(
                $texts,
                $state,
                '<h3>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_REVIEW_TITLE', array($target))) . '</h3>'
                    . $error
                    . ($problems !== array() ? '<div class="alert alert-' . (U\checksHaveErrors($checks) ? 'danger' : 'warning') . '" role="alert">' . coreUpdateCheckList($problems, $texts) . '</div>' : '')
                    . '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_REVIEW_DESC', array($previous, $target))) . '</p>'
                    . (U\checksHaveErrors($checks) ? '' : $backup)
                    . $details,
                $actions
            );
            break;

        case 'rollback_required':
            // Nothing is lost: the previous files are kept until the update is continued or undone.
            coreUpdatePage(
                $texts,
                $state,
                '<h3>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_INTERRUPTED')) . '</h3>'
                    . coreUpdateAlert('warning', U\h(U\text($texts, 'SYS_CORE_UPDATE_ROLLBACK_REQUIRED', array($previous))))
                    . $error
                    . '<div id="message" class="alert alert-info" role="status" hidden></div>'
                    . '<div class="core-update-actions">'
                    . '<button class="btn btn-primary" id="continue-button" type="button"><i class="bi bi-play-fill"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_CONTINUE_UPDATE')) . '</button>'
                    . '<button class="btn btn-secondary" id="rollback-button" type="button"><i class="bi bi-arrow-counterclockwise"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_RESTORE_VERSION', array($previous))) . '</button></div>'
                    . coreUpdateChoices($texts, array(
                        U\text($texts, 'SYS_CORE_UPDATE_CONTINUE_UPDATE') => U\text($texts, 'SYS_CORE_UPDATE_CONTINUE_DESC'),
                        U\text($texts, 'SYS_CORE_UPDATE_RESTORE_VERSION', array($previous)) => U\text($texts, 'SYS_CORE_UPDATE_RESTORE_FILES_DESC', array($previous))
                    ))
                    . coreUpdateErrorDetails($state, $texts, false),
                coreUpdateRunScript($state, $texts, false)
            );
            break;

        case 'database_failed':
            // Maintenance mode stays on. The update can be retried, or the previous version comes
            // back as a whole: the database from the backup, then the files.
            $canRestore = U\restoreMethod($context, $state) !== '';
            $restore = '<form method="post" action="index.php" onsubmit="return window.confirm('
                . U\h(U\js(U\text($texts, 'SYS_CORE_UPDATE_CONFIRM_RESTORE_VERSION', array($previous)))) . ')">'
                . $csrf . '<input type="hidden" name="action" value="restore">'
                . ($canRestore ? '' : '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="own_database_restored" '
                    . 'id="own_database_restored" value="1" required><label class="form-check-label" for="own_database_restored">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_RESTORE_OWN_CONFIRM')) . '</label></div>')
                . coreUpdateButton('bi-arrow-counterclockwise', U\text($texts, 'SYS_CORE_UPDATE_RESTORE_VERSION', array($previous))) . '</form>';

            coreUpdatePage(
                $texts,
                $state,
                '<h3>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_FAILED_TITLE')) . '</h3>'
                    . coreUpdateAlert('warning', U\h(U\text($texts, $canRestore ? 'SYS_CORE_UPDATE_DATABASE_FAILED' : 'SYS_CORE_UPDATE_DATABASE_FAILED_OWN',
                        array((string)($state['targetVersionText'] ?? $state['targetVersion']), $previous))))
                    . coreUpdateDatabaseMessage($state, $texts, true)
                    . '<div class="core-update-actions">' . $restore
                    . '<button class="btn btn-secondary" id="database-button" type="button"><i class="bi bi-arrow-repeat"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_RETRY')) . '</button></div>'
                    . coreUpdateChoices($texts, array(
                        U\text($texts, 'SYS_CORE_UPDATE_RESTORE_VERSION', array($previous))
                            => U\text($texts, $canRestore ? 'SYS_CORE_UPDATE_RESTORE_VERSION_DESC' : 'SYS_CORE_UPDATE_RESTORE_OWN_DESC', array($previous)),
                        U\text($texts, 'SYS_CORE_UPDATE_DATABASE_RETRY') => U\text($texts, 'SYS_CORE_UPDATE_DATABASE_RETRY_DESC')
                    ))
                    . coreUpdateErrorDetails($state, $texts, true),
                coreUpdateDatabaseScript($state, $texts, '')
            );
            break;

        case 'files_installed':
        case 'database_running':
            // The page starts the database update as soon as the files are installed, and waits for
            // one that is running. One that was started and no longer runs was cut off.
            $interrupted = $state['state'] === 'database_running'
                && !U\isDatabaseUpdateRunning($config['rootPath'], (string)$state['updateId']);
            coreUpdatePage(
                $texts,
                $state,
                '<h3>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_TITLE')) . '</h3>'
                    . ($interrupted ? coreUpdateAlert('warning', U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_INTERRUPTED'))) : '')
                    . coreUpdateDatabaseMessage($state, $texts, $interrupted),
                '<button class="btn btn-primary" id="database-button" type="button"' . ($interrupted ? '' : ' hidden') . '><i class="bi bi-arrow-repeat"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_RETRY')) . '</button>'
                    . coreUpdateDatabaseScript($state, $texts, $interrupted ? '' : ($state['state'] === 'files_installed' ? 'start' : 'wait'))
            );
            break;

        case 'completed':
            U\phaseBFinish($context, (string)$state['updateId']);
            U\resetAdmidioSession($config, $context['pdo']);
            coreUpdatePage(
                $texts,
                $state,
                coreUpdateAlert('success', U\h(U\text($texts, 'SYS_CORE_UPDATE_SUCCESSFUL', array((string)$state['targetVersion']))))
                    . ((string)($state['dumpFile'] ?? '') !== ''
                        ? '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DUMP_FILE', array('adm_my_files/core-update/' . $state['dumpFile']))) . '</p>' : '')
                    . implode('', array_map(static fn(array $note): string => coreUpdateAlert($note['type'] === 'info' ? 'info' : 'warning',
                        nl2br(U\h((string)$note['text']))), (array)($state['databaseNotes'] ?? array())))
                    . ((string)($state['keptArchive'] ?? '') !== ''
                        ? '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_KEPT_ARCHIVE', array('adm_my_files/core-update/' . $state['keptArchive']))) . '</p>' : ''),
                coreUpdateOpenAdmidio($config, $texts)
            );
            break;

        case 'rolled_back':
            // The session of the browser may hold objects of the new release, which the previous one
            // cannot read; the login stays.
            U\resetAdmidioSession($config, $context['pdo']);
            U\phaseBRolledBack($context, (string)$state['updateId']);
            coreUpdatePage($texts, $state, coreUpdateAlert('success', U\h(U\rolledBackMessage($context, $state))),
                coreUpdateOpenAdmidio($config, $texts));
            break;

        default:
            // prepared, extracting, planning, ready, installing, verifying, rolling_back: the page
            // drives the update to the next point where the administrator decides.
            $before = in_array($state['state'], array('prepared', 'extracting', 'planning'), true);
            coreUpdatePage(
                $texts,
                $state,
                '<div id="message" class="alert alert-info" role="status">' . U\h(U\phaseBResult($context, $state)['message']) . '</div>',
                '<button class="btn btn-primary" id="continue-button" type="button" hidden><i class="bi bi-play-fill"></i>'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_CONTINUE')) . '</button>'
                    . ($before ? coreUpdateCancelForm($csrf, $texts) : '')
                    . coreUpdateRunScript($state, $texts, true),
                U\phaseBProgress($state)
            );
    }
}

/**
 * Send a page of the updater: the steps of the update beside the content, if there is an update.
 * @param array{language: string, texts: array<string,string>} $texts
 * @param array<string,mixed>|null $state
 */
function coreUpdatePage(array $texts, ?array $state, string $content, string $actions = '', ?int $progress = null): void
{
    U\showPage(U\text($texts, 'SYS_CORE_UPDATE_TITLE'), $content, array(
        'actions' => $actions,
        'progress' => $progress,
        'progressLabel' => U\text($texts, 'SYS_CORE_UPDATE_PROGRESS'),
        'language' => $texts['language'],
        'steps' => $state === null ? '' : U\stepsSidebar(U\updateSteps($state), $texts)
    ));
}

/**
 * A Bootstrap alert of the type "info", "success", "warning" or "danger".
 * @param string $html The content, already escaped.
 */
function coreUpdateAlert(string $type, string $html): string
{
    $icon = match ($type) {
        'success' => 'bi-check-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'danger' => 'bi-exclamation-circle-fill',
        default => 'bi-info-circle-fill'
    };

    return '<div class="alert alert-' . $type . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
        . '<i class="bi ' . $icon . '"></i>' . $html . '</div>';
}

function coreUpdateButton(string $icon, string $label, bool $secondary = false): string
{
    return '<button class="btn ' . ($secondary ? 'btn-secondary' : 'btn-primary') . '" type="submit"><i class="bi ' . $icon . '"></i>'
        . U\h($label) . '</button>';
}

function coreUpdateLink(string $url, string $icon, string $label, bool $secondary = false): string
{
    return '<a class="btn ' . ($secondary ? 'btn-secondary' : 'btn-primary') . '" href="' . U\h($url) . '"><i class="bi ' . $icon . '"></i>'
        . U\h($label) . '</a>';
}

/**
 * @param array<string,mixed> $config
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateOpenAdmidio(array $config, array $texts): string
{
    return coreUpdateLink($config['baseUrl'] . '/modules/overview.php', 'bi-house-door-fill', U\text($texts, 'SYS_CORE_UPDATE_OPEN_ADMIDIO'));
}

/**
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateCancelForm(string $csrf, array $texts): string
{
    return '<form method="post" action="index.php">' . $csrf . '<input type="hidden" name="action" value="cancel">'
        . coreUpdateButton('bi-x-lg', U\text($texts, 'SYS_CORE_UPDATE_CANCEL'), true) . '</form>';
}

/**
 * The result of the checks, each with its icon.
 * @param array<int,array{status: string, text: string, params: array<int,string>}> $checks
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateCheckList(array $checks, array $texts): string
{
    $list = '';
    foreach ($checks as $check) {
        $icon = match ($check['status']) {
            'ok' => 'bi-check-circle-fill text-success',
            'warning' => 'bi-exclamation-triangle-fill text-warning',
            default => 'bi-x-circle-fill text-danger'
        };
        $list .= '<li class="d-flex gap-2 mb-1"><i class="bi ' . $icon . '" aria-hidden="true"></i><span>'
            . U\h(U\text($texts, $check['text'], $check['params'])) . '</span></li>';
    }

    return '<ul class="list-unstyled mb-0">' . $list . '</ul>';
}

/**
 * What each choice of a decision page does, under the heading "What should I do?".
 * @param array{language: string, texts: array<string,string>} $texts
 * @param array<string,string> $choices The name of each choice and what it does.
 */
function coreUpdateChoices(array $texts, array $choices): string
{
    $list = '';
    foreach ($choices as $name => $description) {
        $list .= '<dt>' . U\h((string)$name) . '</dt><dd>' . U\h($description) . '</dd>';
    }

    return '<h4 class="mt-4">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_WHAT_NOW')) . '</h4><dl>' . $list . '</dl>';
}

/**
 * The error and where the log and the backup are, folded away for a bug report.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateErrorDetails(array $state, array $texts, bool $withError): string
{
    return '<details class="mb-4"><summary>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_ERROR_DETAILS')) . '</summary>'
        . ($withError && (string)($state['lastError'] ?? '') !== '' ? '<pre class="core-update-error mt-2">' . U\h((string)$state['lastError']) . '</pre>' : '')
        . '<p class="mt-2">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_LOG_FILE', array('adm_my_files/core-update/' . $state['updateId'] . '/update.log'))) . '</p>'
        . ((string)($state['dumpFile'] ?? '') !== ''
            ? '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DUMP_FILE', array('adm_my_files/core-update/' . $state['dumpFile']))) . '</p>' : '')
        . '</details>';
}

/**
 * The notice that the database is being updated, which the script of the database update shows.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateDatabaseMessage(array $state, array $texts, bool $hidden): string
{
    return '<div id="database-message" class="alert alert-info" role="status"' . ($hidden ? ' hidden' : '') . '>'
        . '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>'
        . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_UPDATING', array((string)($state['targetVersionText'] ?? $state['targetVersion'])))) . '</div>';
}

/**
 * The script that has install/update.php of the new release update the database, in one request
 * that may take minutes. Afterwards the page shows the result or the failure from the state. When
 * the answer is lost, for example because a proxy cut off the request, it asks the updater until
 * the database update no longer runs.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 * @param string $begin "start" the database update, "wait" for the one that runs, or "" for neither.
 */
function coreUpdateDatabaseScript(array $state, array $texts, string $begin): string
{
    return '<script>
        const csrfToken = ' . U\js((string)$state['csrfToken']) . ';
        const requestFailedText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
        const message = document.getElementById("database-message");
        const runningMessage = message.innerHTML;
        const databaseButton = document.getElementById("database-button");
        const buttons = document.querySelectorAll("button");

        async function post(url, data) {
            try {
                const response = await fetch(url, {method: "POST", credentials: "same-origin",
                    body: new URLSearchParams(Object.assign({csrf_token: csrfToken}, data))});
                return await response.json();
            } catch (error) {
                return null;
            }
        }

        function showError(text) {
            message.className = "alert alert-danger";
            message.textContent = text || requestFailedText;
            buttons.forEach(button => button.disabled = false);
            databaseButton.hidden = false;
        }

        function showRunning() {
            buttons.forEach(button => button.disabled = true);
            message.className = "alert alert-info";
            message.innerHTML = runningMessage;
            message.hidden = false;
        }

        async function waitForDatabase() {
            showRunning();
            while (true) {
                await new Promise(resolve => setTimeout(resolve, 5000));
                const status = await post("index.php", {action: "database_status"});
                if (status === null || status.running) {
                    continue;
                }
                if (status.state === "files_installed") {
                    // The request never got as far as the database.
                    showError(requestFailedText);
                    return;
                }
                window.location.reload();
                return;
            }
        }

        async function updateDatabase() {
            showRunning();
            const result = await post("../update.php?mode=core_update", {});
            if (result === null || result.status === "running") {
                waitForDatabase();
            } else if (result.status === "success" || result.state === "database_failed") {
                window.location.reload();
            } else {
                showError(result.message);
            }
        }

        databaseButton.addEventListener("click", updateDatabase);
        ' . ($begin === 'start' ? 'updateDatabase();' : ($begin === 'wait' ? 'waitForDatabase();' : '')) . '
    </script>';
}

/**
 * The script that sends one request after the other until the update reaches a point where the
 * administrator decides, or the database update, and then reloads the page.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateRunScript(array $state, array $texts, bool $start): string
{
    return '<script>
        const csrfToken = ' . U\js((string)$state['csrfToken']) . ';
        const requestFailedText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
        const confirmRestoreText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_CONFIRM_RESTORE_VERSION', array((string)$state['installedVersion']))) . ';
        const message = document.getElementById("message");
        const progress = document.getElementById("progress-bar");
        const continueButton = document.getElementById("continue-button");
        const rollbackButton = document.getElementById("rollback-button");

        async function post(action) {
            const body = new URLSearchParams({action: action, csrf_token: csrfToken});
            try {
                const response = await fetch("index.php", {method: "POST", credentials: "same-origin", body: body});
                return await response.json();
            } catch (error) {
                return {status: "error", message: requestFailedText};
            }
        }

        async function drive(action) {
            continueButton.hidden = true;
            if (rollbackButton) {
                rollbackButton.disabled = true;
            }
            message.hidden = false;
            message.className = "alert alert-info";
            let result = await post(action);
            while (true) {
                message.textContent = result.message || message.textContent;
                if (progress) {
                    progress.style.width = (result.progress || 0) + "%";
                }
                if (result.status === "review" || result.status === "reload" || result.status === "rolled_back" || result.state === "rollback_required"
                    || (result.state && !showStep(result.state))) {
                    window.location.reload();
                    return;
                }
                if (result.status !== "success") {
                    message.className = "alert alert-danger";
                    continueButton.hidden = result.status === "failed";
                    if (rollbackButton) {
                        rollbackButton.disabled = false;
                    }
                    return;
                }
                result = await post("run");
            }
        }

        continueButton.addEventListener("click", () => drive("run"));
        if (rollbackButton) {
            rollbackButton.addEventListener("click", () => {
                if (window.confirm(confirmRestoreText)) {
                    drive("rollback");
                }
            });
        }
        ' . ($start ? 'drive("run");' : '') . '
    </script>';
}
