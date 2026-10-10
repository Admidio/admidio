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
            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_ADMIDIO_LOGIN')) . '</p>',
                '<a class="button" href="' . U\h($config['baseUrl'] . '/system/login.php') . '">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_LOGIN')) . '</a>',
                null,
                '',
                $texts['language']
            );
            exit;
        }

        if (($_GET['action'] ?? '') === 'finish' && U\activeUpdateId($rootPath) === null) {
            // The result page of an update that another browser finished: nothing to start.
            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_ALREADY_COMPLETED')) . '</p>',
                '<a class="button" href="' . U\h($config['baseUrl'] . '/modules/overview.php') . '">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>',
                null,
                'success',
                $texts['language']
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
        '<p>' . U\h($message) . '</p>',
        '',
        null,
        'error',
        $texts !== null ? $texts['language'] : 'en'
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

    U\showPage(
        U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
        U\loginForm($texts, U\loginCsrfToken($config, $loginCookie), $error),
        '',
        null,
        '',
        $texts['language']
    );
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
            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCELLED')) . '</p>',
                '<a class="button" href="' . U\h($config['baseUrl'] . '/modules/overview.php') . '">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>',
                null,
                '',
                $texts['language']
            );

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
    $error = (string)($state['lastError'] ?? '') !== ''
        ? '<div class="message error">' . U\h((string)$state['lastError']) . '</div>' : '';
    $cancel = '<form method="post" action="index.php" class="inline">' . $csrf
        . '<input type="hidden" name="action" value="cancel">'
        . '<button class="button secondary" type="submit">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCEL')) . '</button></form>';
    $versions = '<dl><dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_INSTALLED_VERSION')) . '</dt><dd>'
        . U\h((string)($state['installedVersion'] ?? '')) . '</dd>';

    switch ($state['state']) {
        case 'new':
        case 'checking':
            $channels = U\availableChannels($config);
            $choice = '';
            foreach ($channels as $channel) {
                $choice .= '<label class="choice"><input type="radio" name="channel" value="' . U\h($channel) . '"'
                    . ($channel === ($state['channel'] ?? 'stable') ? ' checked' : '') . '> '
                    . U\h(coreUpdateChannelName($channel, $texts)) . '</label>';
            }
            if (count($channels) > 1) {
                $choice = '<fieldset><legend>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHANNEL')) . '</legend>' . $choice . '</fieldset>';
            } else {
                $choice = '<input type="hidden" name="channel" value="stable">';
            }

            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                $error . '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_START_DESC')) . '</p>' . $versions . '</dl>',
                '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="check">'
                    . $choice . '<button class="button" type="submit">'
                    . U\h(U\text($texts, count($channels) > 1 ? 'SYS_CORE_UPDATE_CONTINUE' : 'SYS_CORE_UPDATE_CHECK_AGAIN')) . '</button></form>'
                    . $cancel,
                null,
                '',
                $texts['language']
            );
            break;

        case 'checked':
            $checks = (array)($state['checks'] ?? array());
            $list = '';
            foreach ($checks as $check) {
                $list .= '<li class="check-' . U\h($check['status']) . '">' . U\h(U\text($texts, $check['text'], $check['params'])) . '</li>';
            }

            $actions = '';
            if (!U\checksHaveErrors($checks)) {
                $actions .= '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="confirm">'
                    . '<button class="button" type="submit">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DOWNLOAD')) . '</button></form>';
            }
            $actions .= '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="check">'
                . '<input type="hidden" name="channel" value="' . U\h((string)$state['channel']) . '">'
                . '<button class="button secondary" type="submit">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECK_AGAIN')) . '</button></form>'
                . $cancel;

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

            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                $error . $versions
                    . '<dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_TARGET')) . '</dt><dd>'
                    . U\h((string)($state['targetVersionText'] ?? $state['targetVersion'])) . '</dd>'
                    . '<dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHANNEL')) . '</dt><dd>'
                    . U\h(coreUpdateChannelName((string)$state['channel'], $texts)) . '</dd></dl>'
                    . ($state['channel'] === 'master' ? '<div class="warning">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_MASTER_WARNING')) . '</div>' : '')
                    . '<h2>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECKS')) . '</h2><ul class="checks">' . $list . '</ul>'
                    . '<div id="reachable" class="warning" hidden>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECK_REACHABLE')) . '</div>',
                $actions . $script,
                null,
                U\checksHaveErrors($checks) ? 'error' : '',
                $texts['language']
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
                    const response = await fetch("index.php", {method: "POST", credentials: "same-origin", body: body});
                    try {
                        return await response.json();
                    } catch (error) {
                        return {status: "error", message: requestFailedText};
                    }
                }

                async function download() {
                    retry.hidden = true;
                    message.classList.remove("error");
                    while (true) {
                        const result = await step();
                        message.textContent = result.message || "";
                        progress.style.width = (result.progress || 0) + "%";
                        if (result.status === "prepared") {
                            window.location.reload();
                            return;
                        }
                        if (result.status === "failed") {
                            message.classList.add("error");
                            return;
                        }
                        if (result.status !== "success") {
                            message.classList.add("error");
                            retry.hidden = false;
                            return;
                        }
                    }
                }

                retry.addEventListener("click", download);
                download();
            </script>';

            U\showPage(
                U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                '<div id="message" class="message">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DOWNLOADING', array((string)$state['targetVersion']))) . '</div>',
                '<button class="button" id="retry-button" type="button" hidden>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_START_RESUME')) . '</button>'
                    . $cancel . $script,
                U\phaseAProgress($state),
                '',
                $texts['language'],
                U\text($texts, 'SYS_CORE_UPDATE_PROGRESS')
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
                U\phaseBConfirm($context, $updateId);
                header('Location: index.php', true, 303);
            })(),
            'cancel' => (static function () use ($context, $updateId, $config, $texts): void {
                U\phaseBCancel($context, $updateId);
                U\showPage(
                    U\text($texts, 'SYS_CORE_UPDATE_TITLE'),
                    '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCELLED')) . '</p>',
                    '<a class="button" href="' . U\h($config['baseUrl'] . '/modules/overview.php') . '">'
                        . U\h(U\text($texts, 'SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>',
                    null,
                    '',
                    $texts['language']
                );
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
    $openAdmidio = '<a class="button" href="' . U\h($config['baseUrl'] . '/modules/overview.php') . '">'
        . U\h(U\text($texts, 'SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>';
    $show = static fn(string $content, string $actions, ?int $progress, string $class = '') => U\showPage(
        U\text($texts, 'SYS_CORE_UPDATE_TITLE'), $content, $actions, $progress, $class, $texts['language'],
        U\text($texts, 'SYS_CORE_UPDATE_PROGRESS'));

    switch ($state['state']) {
        case 'review':
            $plan = (array)($state['plan'] ?? array());
            $checks = (array)($state['planChecks'] ?? array());
            $list = '';
            foreach (array('create' => 'SYS_CORE_UPDATE_PLAN_CREATE', 'replace' => 'SYS_CORE_UPDATE_PLAN_REPLACE',
                'delete' => 'SYS_CORE_UPDATE_PLAN_DELETE', 'unchanged' => 'SYS_CORE_UPDATE_PLAN_UNCHANGED') as $key => $text) {
                $list .= '<li>' . U\h(U\text($texts, $text, array((string)($plan[$key] ?? 0)))) . '</li>';
            }
            $removed = array_column(array_filter(U\readPlan(U\workspace($config['rootPath'], (string)$state['updateId'])),
                static fn(array $operation): bool => $operation['action'] === 'delete'), 'path');
            $checkList = '';
            foreach ($checks as $check) {
                $checkList .= '<li class="check-' . U\h($check['status']) . '">' . U\h(U\text($texts, $check['text'], $check['params'])) . '</li>';
            }

            $actions = '';
            if (!U\checksHaveErrors($checks)) {
                $actions .= '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="confirm">'
                    . '<button class="button" type="submit">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_INSTALL')) . '</button></form>';
            }
            $actions .= coreUpdateCancelForm($csrf, $texts);

            $show(
                '<dl><dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_INSTALLED_VERSION')) . '</dt><dd>' . U\h((string)$state['installedVersion']) . '</dd>'
                    . '<dt>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_TARGET')) . '</dt><dd>' . U\h((string)($state['targetVersionText'] ?? $state['targetVersion'])) . '</dd></dl>'
                    . '<ul>' . $list . '</ul>'
                    . ($removed !== array() ? '<details><summary>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_PLAN_DELETED_FILES')) . '</summary><ul>'
                        . implode('', array_map(static fn(string $path): string => '<li>' . U\h($path) . '</li>', $removed)) . '</ul></details>' : '')
                    . '<h2>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CHECKS')) . '</h2><ul class="checks">' . $checkList . '</ul>'
                    . '<div class="warning">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_REVIEW_DESC')) . '</div>',
                $actions,
                30,
                U\checksHaveErrors($checks) ? 'error' : ''
            );
            break;

        case 'rollback_required':
            $show(
                '<div class="message error">' . U\h((string)$state['lastError']) . '</div>'
                    . '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_ROLLBACK_REQUIRED')) . '</p>'
                    . '<div id="message" class="message" hidden></div>',
                '<button class="button" id="continue-button" type="button">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CONTINUE')) . '</button>'
                    . (empty($state['databaseStarted']) ? '<button class="button secondary" id="rollback-button" type="button">'
                        . U\h(U\text($texts, 'SYS_CORE_UPDATE_RESTORE_PREVIOUS')) . '</button>' : '')
                    . coreUpdateRunScript($state, $texts, false),
                U\phaseBProgress($state),
                ''
            );
            break;

        case 'files_installed':
        case 'database_running':
        case 'database_failed':
            $show(
                '<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_DATABASE_CONTINUE')) . '</p>'
                    . ((string)($state['lastError'] ?? '') !== '' ? '<div class="message error">' . U\h((string)$state['lastError']) . '</div>' : ''),
                '<a class="button" href="' . U\h(U\databaseUpdateUrl($config, (string)$state['updateId'])) . '">'
                    . U\h(U\text($texts, 'SYS_CORE_UPDATE_CONTINUE')) . '</a>',
                95
            );
            break;

        case 'completed':
            U\phaseBFinish($context, (string)$state['updateId']);
            $show('<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_SUCCESSFUL', array((string)$state['targetVersion']))) . '</p>', $openAdmidio, 100, 'success');
            break;

        case 'rolled_back':
            $show('<p>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_FILESYSTEM_ROLLED_BACK')) . '</p>', $openAdmidio, null, 'success');
            break;

        default:
            // prepared, extracting, planning, ready, installing, verifying, rolling_back: the page
            // drives the update to the next point where the administrator decides.
            $before = in_array($state['state'], array('prepared', 'extracting', 'planning'), true);
            $show(
                '<div id="message" class="message">' . U\h(U\phaseBResult($context, $state)['message']) . '</div>',
                '<button class="button" id="continue-button" type="button" hidden>' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CONTINUE')) . '</button>'
                    . ($before ? coreUpdateCancelForm($csrf, $texts) : '')
                    . coreUpdateRunScript($state, $texts, true),
                U\phaseBProgress($state)
            );
    }
}

/**
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateCancelForm(string $csrf, array $texts): string
{
    return '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="cancel">'
        . '<button class="button secondary" type="submit">' . U\h(U\text($texts, 'SYS_CORE_UPDATE_CANCEL')) . '</button></form>';
}

/**
 * The script that sends one request after the other until the update reaches a point where the
 * administrator decides, and then reloads the page or follows the redirect.
 * @param array<string,mixed> $state
 * @param array{language: string, texts: array<string,string>} $texts
 */
function coreUpdateRunScript(array $state, array $texts, bool $start): string
{
    return '<script>
        const csrfToken = ' . U\js((string)$state['csrfToken']) . ';
        const requestFailedText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
        const confirmRestoreText = ' . U\js(U\text($texts, 'SYS_CORE_UPDATE_CONFIRM_RESTORE')) . ';
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
            message.classList.remove("error");
            let result = await post(action);
            while (true) {
                message.textContent = result.message || message.textContent;
                if (progress) {
                    progress.style.width = (result.progress || 0) + "%";
                }
                if (result.status === "redirect") {
                    window.location.href = result.url;
                    return;
                }
                if (result.status === "review" || result.status === "rolled_back" || result.state === "rollback_required") {
                    window.location.reload();
                    return;
                }
                if (result.status !== "success") {
                    message.classList.add("error");
                    continueButton.hidden = result.status === "failed";
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
