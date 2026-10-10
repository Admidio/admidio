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

use AdmidioCoreUpdater\Config;
use AdmidioCoreUpdater\Credentials;
use AdmidioCoreUpdater\Csrf;
use AdmidioCoreUpdater\Db;
use AdmidioCoreUpdater\HttpClient;
use AdmidioCoreUpdater\Login;
use AdmidioCoreUpdater\Page;
use AdmidioCoreUpdater\PhaseA;
use AdmidioCoreUpdater\Preflight;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\Texts;
use AdmidioCoreUpdater\Throttle;
use AdmidioCoreUpdater\UpdaterException;
use AdmidioCoreUpdater\Version;

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/CoreUpdater.php';

Page::sendSecurityHeaders();

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postedAction = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$texts = null;

try {
    $config = Config::load(dirname(__DIR__, 2));
    if (in_array($config->timezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($config->timezone);
    }

    $db = null;
    try {
        $db = Db::connect($config);
        $texts = Texts::load(__DIR__, $config->rootPath, Texts::getInstallationLanguage($db));
    } catch (UpdaterException) {
        // The login needs the database, the update itself does not. Without it the texts are English.
        $texts = Texts::load(__DIR__, $config->rootPath, 'en');
    }

    $store = new StateStore($config->rootPath);
    $cookieName = Credentials::getCookieName($config->getCookiePrefix());
    $updateId = coreUpdateAuthenticate($store, $_COOKIE[$cookieName] ?? null, $config);

    if ($updateId === null && $store->isAdmidioIntact()) {
        // The Admidio login decides. common.php has to be included here, at the top level, so that
        // its globals are globals. Config::load() has read config.php in a scope of its own, so the
        // require_once of common.php would skip it: it is included here first.
        include $config->rootPath . '/adm_my_files/config.php';
        require_once $config->rootPath . '/system/common.php';

        if (!$gValidLogin || !$gCurrentUser->isAdministrator()) {
            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                '<p>' . Page::escape($texts->get('SYS_CORE_UPDATE_ADMIDIO_LOGIN')) . '</p>',
                '<a class="button" href="' . Page::escape($config->baseUrl . '/system/login.php') . '">'
                    . Page::escape($texts->get('SYS_CORE_UPDATE_LOGIN')) . '</a>',
                null,
                '',
                $texts->getLanguage()
            );
            exit;
        }

        $updateId = $store->getActiveId()
            ?? $store->create(array('installedVersion' => Version::ofFiles($config->rootPath)))['updateId'];
    }

    if ($updateId === null) {
        // Maintenance mode is on and this browser has no updater cookie.
        coreUpdateHandleLogin($config, $store, $db, $texts, $isPost && $postedAction === 'login');
        exit;
    }

    $state = $store->read($updateId);
    if ($isPost) {
        if (!Csrf::isValid($state, $_POST['csrf_token'] ?? null)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
    }

    // Phase A: from the choice of the channel to the prepared package.
    if (in_array((string)$state['state'], PhaseA::STATES, true)) {
        // Plain HTTP is only allowed below the test server of debug mode, which Config only sets then.
        $phaseA = new PhaseA($config, $store, new HttpClient($config->serverUrl), $db, $texts, __DIR__);
        coreUpdateHandlePhaseA($phaseA, $state, $config, $texts, $isPost, $postedAction);
        exit;
    }

    $updater = new CoreUpdater($config, $store, $texts, $updateId);

    if ($isPost) {
        if ($postedAction === 'run') {
            Page::json($updater->runNextStep());
        } elseif ($postedAction === 'rollback') {
            Page::json($updater->rollback());
        } else {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
        exit;
    }

    if (($_GET['action'] ?? '') === 'finish') {
        $result = $updater->finishUpdate();
        Page::show(
            $texts->get('SYS_CORE_UPDATE_TITLE'),
            '<p>' . Page::escape((string)$result['message']) . '</p>',
            '<a class="button" href="' . Page::escape((string)$result['url']) . '">'
                . Page::escape($texts->get('SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>',
            100,
            'success',
            $texts->getLanguage(),
            $texts->get('SYS_CORE_UPDATE_PROGRESS')
        );
        exit;
    }

    coreUpdateShowStatus($updater, $texts, (string)$state['csrfToken']);
} catch (Throwable $exception) {
    // Before the administrator is known, an error says nothing about the installation.
    $message = $exception instanceof UpdaterException ? $exception->getText($texts) : 'The update could not be continued.';
    if (!$exception instanceof UpdaterException) {
        error_log('Admidio core updater: ' . $exception);
    }

    if ($isPost && $postedAction !== 'login') {
        Page::json(array('status' => 'error', 'message' => $message), 500);
        exit;
    }

    http_response_code(500);
    Page::show(
        $texts !== null ? $texts->get('SYS_CORE_UPDATE_TITLE') : 'Admidio update',
        '<p>' . Page::escape($message) . '</p>',
        '',
        null,
        'error',
        $texts !== null ? $texts->getLanguage() : 'en'
    );
}

/**
 * The update the updater cookie belongs to, or **null** if the request carries no valid credential.
 * A valid credential is kept alive for another day, in the state and in the browser.
 * @param StateStore $store
 * @param mixed $cookieValue
 * @param Config $config
 * @return string|null
 */
function coreUpdateAuthenticate(StateStore $store, mixed $cookieValue, Config $config): ?string
{
    $parsed = Credentials::parse($cookieValue);
    if ($parsed === null || !$store->exists($parsed[0])) {
        return null;
    }

    $valid = false;
    $store->update($parsed[0], static function (array $state) use ($cookieValue, &$valid): array {
        $valid = Credentials::refresh($state, $cookieValue, time());

        return $state;
    });

    if (!$valid) {
        return null;
    }

    Credentials::sendCookie($config, (string)$cookieValue);

    return $parsed[0];
}

/**
 * The updater's own login: the form, and what happens when it is sent.
 *
 * It is only needed while maintenance mode is on and Admidio cannot be used: to continue the
 * update from another device, or after the updater cookie was lost. A successful login gives this
 * browser a credential for the unfinished update.
 * @param Config $config
 * @param StateStore $store
 * @param Db|null $db
 * @param Texts $texts
 * @param bool $submitted
 * @return void
 */
function coreUpdateHandleLogin(Config $config, StateStore $store, ?Db $db, Texts $texts, bool $submitted): void
{
    $loginCookie = $_COOKIE[Csrf::getLoginCookieName($config)] ?? null;
    $error = '';

    if ($submitted) {
        try {
            if (!Csrf::isValidLogin($loginCookie, $_POST['csrf_token'] ?? null)) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }
            if ($db === null) {
                throw new UpdaterException('The database of the installation could not be reached.');
            }

            $login = new Login(
                $db,
                new Throttle($store->getDirectory() . '/login-throttle.json'),
                $store->getDirectory() . '/login.log'
            );
            $login->authenticate(
                (string)($_POST['login_name'] ?? ''),
                (string)($_POST['password'] ?? ''),
                (string)($_POST['totp_code'] ?? ''),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                time()
            );

            $updateId = $store->getActiveId();
            if ($updateId === null) {
                throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
            }

            $cookieValue = '';
            $store->update($updateId, static function (array $state) use (&$cookieValue): array {
                $cookieValue = Credentials::issue($state, time());

                return $state;
            });
            Credentials::sendCookie($config, $cookieValue);

            // Post/Redirect/Get, so that a reload does not send the password again.
            header('Location: index.php', true, 303);
            return;
        } catch (UpdaterException $exception) {
            $error = $exception->getText($texts);
        }
    }

    Page::show(
        $texts->get('SYS_CORE_UPDATE_TITLE'),
        Page::loginForm($texts, Csrf::getLoginToken($config, $loginCookie), $error),
        '',
        null,
        '',
        $texts->getLanguage()
    );
}

/**
 * The status page of the update, with the buttons that drive it.
 * @param CoreUpdater $updater
 * @param Texts $texts
 * @param string $csrfToken
 * @return void
 */
function coreUpdateShowStatus(CoreUpdater $updater, Texts $texts, string $csrfToken): void
{
    $status = $updater->getStatus();
    $warning = '';

    if ($status['channel'] === 'master') {
        $warning = '<div class="warning">' . Page::escape($texts->get('SYS_CORE_UPDATE_MASTER_WARNING')) . '</div>';
    }

    $details = $warning
        . '<dl>'
        . '<dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_INSTALLED_VERSION')) . '</dt><dd>'
        . Page::escape((string)$status['installedVersion']) . '</dd>'
        . '<dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_TARGET')) . '</dt><dd>'
        . Page::escape((string)$status['targetVersion']) . '</dd>'
        . '<dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_CURRENT_STATE')) . '</dt><dd id="state">'
        . Page::escape((string)$status['state']) . '</dd>'
        . '</dl>'
        . '<div id="message" class="message">'
        . Page::escape($status['lastError'] !== '' ? (string)$status['lastError'] : $texts->get('SYS_CORE_UPDATE_READY'))
        . '</div>';

    $buttons = '<button class="button" id="continue-button" type="button">'
        . Page::escape($texts->get('SYS_CORE_UPDATE_START_RESUME')) . '</button>'
        . '<button class="button secondary" id="rollback-button" type="button"'
        . ($status['state'] === 'rollback_required' ? '' : ' hidden')
        . '>' . Page::escape($texts->get('SYS_CORE_UPDATE_RESTORE_PREVIOUS')) . '</button>';

    $script = '<script>
        const endpoint = "index.php";
        const csrfToken = ' . Page::js($csrfToken) . ';
        const requestFailedText = ' . Page::js($texts->get('SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
        const confirmRestoreText = ' . Page::js($texts->get('SYS_CORE_UPDATE_CONFIRM_RESTORE')) . ';
        const continueButton = document.getElementById("continue-button");
        const rollbackButton = document.getElementById("rollback-button");
        const message = document.getElementById("message");
        const state = document.getElementById("state");
        const progress = document.getElementById("progress-bar");

        async function postAction(action) {
            const body = new URLSearchParams();
            body.set("action", action);
            body.set("csrf_token", csrfToken);

            const response = await fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {"Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"},
                body: body.toString()
            });
            let result;
            try {
                result = await response.json();
            } catch (error) {
                throw new Error(requestFailedText);
            }

            if (!response.ok || result.status === "error") {
                throw new Error(result.message || requestFailedText);
            }
            return result;
        }

        function updateDisplay(result) {
            message.textContent = result.message || "";
            progress.style.width = (result.progress || 0) + "%";
            progress.parentElement.setAttribute("aria-valuenow", result.progress || 0);
            if (result.state) {
                state.textContent = result.state;
            }
        }

        async function continueUpdate() {
            continueButton.disabled = true;
            rollbackButton.disabled = true;

            try {
                while (true) {
                    const result = await postAction("run");
                    updateDisplay(result);

                    if (result.status === "redirect") {
                        window.location.href = result.url;
                        return;
                    }
                    if (result.status === "rollback_required") {
                        rollbackButton.hidden = false;
                        return;
                    }
                    if (result.status === "completed") {
                        window.location.reload();
                        return;
                    }
                    await new Promise(resolve => setTimeout(resolve, 150));
                }
            } catch (error) {
                message.textContent = error.message;
                message.classList.add("error");
                rollbackButton.hidden = false;
            } finally {
                continueButton.disabled = false;
                rollbackButton.disabled = false;
            }
        }

        async function rollbackUpdate() {
            if (!window.confirm(confirmRestoreText)) {
                return;
            }

            continueButton.disabled = true;
            rollbackButton.disabled = true;
            try {
                const result = await postAction("rollback");
                updateDisplay(result);
                rollbackButton.hidden = true;
            } catch (error) {
                message.textContent = error.message;
                message.classList.add("error");
            } finally {
                continueButton.disabled = false;
                rollbackButton.disabled = false;
            }
        }

        continueButton.addEventListener("click", continueUpdate);
        rollbackButton.addEventListener("click", rollbackUpdate);
    </script>';

    Page::show(
        $texts->get('SYS_CORE_UPDATE_TITLE'),
        $details,
        $buttons . $script,
        (int)$status['progress'],
        $status['state'] === 'rollback_required' ? 'error' : '',
        $texts->getLanguage(),
        $texts->get('SYS_CORE_UPDATE_PROGRESS')
    );
}

/**
 * Phase A: the requests of its forms and of the download loop, and its pages.
 *
 * The forms are plain form posts answered with a redirect, so that a reload does not send them
 * again; the download is driven by the page's script, one bounded request after the other.
 * @param PhaseA $phaseA
 * @param array<string,mixed> $state
 * @param Config $config
 * @param Texts $texts
 * @param bool $isPost
 * @param string $action
 * @return void
 */
function coreUpdateHandlePhaseA(PhaseA $phaseA, array $state, Config $config, Texts $texts, bool $isPost, string $action): void
{
    $updateId = (string)$state['updateId'];

    if ($isPost) {
        if ($action === 'run') {
            Page::json($phaseA->run($updateId));

            return;
        }
        if ($action === 'cancel') {
            $phaseA->cancel($updateId);
            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                '<p>' . Page::escape($texts->get('SYS_CORE_UPDATE_CANCELLED')) . '</p>',
                '<a class="button" href="' . Page::escape($config->baseUrl . '/modules/overview.php') . '">'
                    . Page::escape($texts->get('SYS_CORE_UPDATE_OPEN_ADMIDIO')) . '</a>',
                null,
                '',
                $texts->getLanguage()
            );

            return;
        }

        match ($action) {
            'check' => $phaseA->check($updateId, (string)($_POST['channel'] ?? 'stable')),
            'confirm' => $phaseA->confirm($updateId),
            default => throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID')
        };
        header('Location: index.php', true, 303);

        return;
    }

    // With one channel there is nothing to choose, so the updater checks as soon as it is opened.
    // After a failed check the administrator sees the error and starts the next one.
    if ($state['state'] === 'new' && count(PhaseA::getChannels($config)) === 1 && (string)($state['lastError'] ?? '') === '') {
        $state = $phaseA->check($updateId, 'stable');
    }

    coreUpdateShowPhaseA($state, $config, $texts);
}

/**
 * The page of phase A for the state the update is in.
 * @param array<string,mixed> $state
 * @param Config $config
 * @param Texts $texts
 * @return void
 */
function coreUpdateShowPhaseA(array $state, Config $config, Texts $texts): void
{
    $csrf = '<input type="hidden" name="csrf_token" value="' . Page::escape((string)$state['csrfToken']) . '">';
    $error = (string)($state['lastError'] ?? '') !== ''
        ? '<div class="message error">' . Page::escape((string)$state['lastError']) . '</div>' : '';
    $cancel = '<form method="post" action="index.php" class="inline">' . $csrf
        . '<input type="hidden" name="action" value="cancel">'
        . '<button class="button secondary" type="submit">' . Page::escape($texts->get('SYS_CORE_UPDATE_CANCEL')) . '</button></form>';
    $versions = '<dl><dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_INSTALLED_VERSION')) . '</dt><dd>'
        . Page::escape((string)($state['installedVersion'] ?? '')) . '</dd>';

    switch ($state['state']) {
        case 'new':
        case 'checking':
            $channels = PhaseA::getChannels($config);
            $choice = '';
            foreach ($channels as $channel) {
                $choice .= '<label class="choice"><input type="radio" name="channel" value="' . Page::escape($channel) . '"'
                    . ($channel === ($state['channel'] ?? 'stable') ? ' checked' : '') . '> '
                    . Page::escape(coreUpdateChannelName($channel, $texts)) . '</label>';
            }
            if (count($channels) > 1) {
                $choice = '<fieldset><legend>' . Page::escape($texts->get('SYS_CORE_UPDATE_CHANNEL')) . '</legend>' . $choice . '</fieldset>';
            } else {
                $choice = '<input type="hidden" name="channel" value="stable">';
            }

            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                $error . '<p>' . Page::escape($texts->get('SYS_CORE_UPDATE_START_DESC')) . '</p>' . $versions . '</dl>',
                '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="check">'
                    . $choice . '<button class="button" type="submit">'
                    . Page::escape($texts->get(count($channels) > 1 ? 'SYS_CORE_UPDATE_CONTINUE' : 'SYS_CORE_UPDATE_CHECK_AGAIN')) . '</button></form>'
                    . $cancel,
                null,
                '',
                $texts->getLanguage()
            );
            break;

        case 'checked':
            $checks = (array)($state['checks'] ?? array());
            $list = '';
            foreach ($checks as $check) {
                $list .= '<li class="check-' . Page::escape($check['status']) . '">' . Page::escape($texts->get($check['text'], $check['params'])) . '</li>';
            }

            $actions = '';
            if (!Preflight::hasErrors($checks)) {
                $actions .= '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="confirm">'
                    . '<button class="button" type="submit">' . Page::escape($texts->get('SYS_CORE_UPDATE_DOWNLOAD')) . '</button></form>';
            }
            $actions .= '<form method="post" action="index.php" class="inline">' . $csrf . '<input type="hidden" name="action" value="check">'
                . '<input type="hidden" name="channel" value="' . Page::escape((string)$state['channel']) . '">'
                . '<button class="button secondary" type="submit">' . Page::escape($texts->get('SYS_CORE_UPDATE_CHECK_AGAIN')) . '</button></form>'
                . $cancel;

            // P10: if the browser can read the probe file, adm_my_files is reachable from outside.
            $probeUrl = $config->baseUrl . '/adm_my_files/core-update/' . $state['updateId'] . '/probe.txt';
            $script = '<script>
                fetch(' . Page::js($probeUrl) . ', {cache: "no-store", credentials: "omit"})
                    .then(response => response.ok ? response.text() : "")
                    .then(text => {
                        if (text.trim() === ' . Page::js((string)($state['probeToken'] ?? '')) . ') {
                            document.getElementById("reachable").hidden = false;
                        }
                    })
                    .catch(() => {});
            </script>';

            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                $error . $versions
                    . '<dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_TARGET')) . '</dt><dd>'
                    . Page::escape((string)($state['targetVersionText'] ?? $state['targetVersion'])) . '</dd>'
                    . '<dt>' . Page::escape($texts->get('SYS_CORE_UPDATE_CHANNEL')) . '</dt><dd>'
                    . Page::escape(coreUpdateChannelName((string)$state['channel'], $texts)) . '</dd></dl>'
                    . ($state['channel'] === 'master' ? '<div class="warning">' . Page::escape($texts->get('SYS_CORE_UPDATE_MASTER_WARNING')) . '</div>' : '')
                    . '<h2>' . Page::escape($texts->get('SYS_CORE_UPDATE_CHECKS')) . '</h2><ul class="checks">' . $list . '</ul>'
                    . '<div id="reachable" class="warning" hidden>' . Page::escape($texts->get('SYS_CORE_UPDATE_CHECK_REACHABLE')) . '</div>',
                $actions . $script,
                null,
                Preflight::hasErrors($checks) ? 'error' : '',
                $texts->getLanguage()
            );
            break;

        case 'prepared':
            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                '<p>' . Page::escape($texts->get('SYS_CORE_UPDATE_PREPARED', array((string)$state['targetVersion']))) . '</p>',
                $cancel,
                100,
                'success',
                $texts->getLanguage(),
                $texts->get('SYS_CORE_UPDATE_PROGRESS')
            );
            break;

        default:
            // downloading, downloaded: the page drives the download until the package is prepared.
            $script = '<script>
                const csrfToken = ' . Page::js((string)$state['csrfToken']) . ';
                const requestFailedText = ' . Page::js($texts->get('SYS_CORE_UPDATE_REQUEST_FAILED')) . ';
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

            Page::show(
                $texts->get('SYS_CORE_UPDATE_TITLE'),
                '<div id="message" class="message">' . Page::escape($texts->get('SYS_CORE_UPDATE_DOWNLOADING', array((string)$state['targetVersion']))) . '</div>',
                '<button class="button" id="retry-button" type="button" hidden>' . Page::escape($texts->get('SYS_CORE_UPDATE_START_RESUME')) . '</button>'
                    . $cancel . $script,
                PhaseA::progress($state),
                '',
                $texts->getLanguage(),
                $texts->get('SYS_CORE_UPDATE_PROGRESS')
            );
    }
}

/**
 * The name of a channel for the administrator.
 * @param string $channel
 * @param Texts $texts
 * @return string
 */
function coreUpdateChannelName(string $channel, Texts $texts): string
{
    return match ($channel) {
        'beta' => $texts->get('SYS_CORE_UPDATE_CHANNEL_BETA'),
        'master' => $texts->get('SYS_CORE_UPDATE_CHANNEL_MASTER'),
        default => $texts->get('SYS_CORE_UPDATE_CHANNEL_STABLE')
    };
}
