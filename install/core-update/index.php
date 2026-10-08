<?php
/**
 * Entry point of the self-contained Admidio core updater.
 *
 * Only an administrator gets past this file: with the updater cookie, which the Admidio settings
 * hand over when they start an update, or with the login of the updater, which works without
 * Admidio - for an installation that got the updater copied in by hand, in another browser, or
 * after the cookie was lost. Every request that changes something is a POST with the CSRF token of
 * the update.
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
use AdmidioCoreUpdater\Login;
use AdmidioCoreUpdater\Page;
use AdmidioCoreUpdater\StateStore;
use AdmidioCoreUpdater\Texts;
use AdmidioCoreUpdater\Throttle;
use AdmidioCoreUpdater\UpdaterException;

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
    $updateId = authenticate($store, $_COOKIE[$cookieName] ?? null, $config);

    if ($updateId === null) {
        handleLogin($config, $store, $db, $texts, $isPost && $postedAction === 'login');
        exit;
    }

    $state = $store->read($updateId);
    if ($isPost) {
        if (!Csrf::isValid($state, $_POST['csrf_token'] ?? null)) {
            throw new UpdaterException('SYS_CORE_UPDATE_REQUEST_INVALID');
        }
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

    showStatus($updater, $texts, (string)$state['csrfToken']);
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
function authenticate(StateStore $store, mixed $cookieValue, Config $config): ?string
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
 * The login of the updater: the form, and what happens when it is sent.
 *
 * A successful login continues the unfinished update with a credential for this browser. Without
 * one there is nothing to continue; an update is started from the Admidio settings.
 * @param Config $config
 * @param StateStore $store
 * @param Db|null $db
 * @param Texts $texts
 * @param bool $submitted
 * @return void
 */
function handleLogin(Config $config, StateStore $store, ?Db $db, Texts $texts, bool $submitted): void
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
                throw new UpdaterException('SYS_CORE_UPDATE_NOTHING_TO_RESUME');
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
function showStatus(CoreUpdater $updater, Texts $texts, string $csrfToken): void
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
