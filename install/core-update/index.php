<?php
/**
 * Entry point of the self-contained Admidio core updater.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once(__DIR__ . '/CoreUpdater.php');

$updateId = isset($_GET['update_id']) && is_string($_GET['update_id']) ? $_GET['update_id'] : '';
$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

try {
    $updater = new CoreUpdater(dirname(__DIR__, 2), $updateId);
    $updater->authorize();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');

        $postedAction = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        $updater->validateCsrfToken($csrfToken);

        if ($postedAction === 'run') {
            echo json_encode($updater->runNextStep(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } elseif ($postedAction === 'rollback') {
            echo json_encode($updater->rollback(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } else {
            throw new RuntimeException('Invalid core update action.');
        }
        exit;
    }

    if ($action === 'finish') {
        $result = $updater->finishUpdate();
        showPage(
            $updater->getText('title'),
            '<p>' . escapeHtml((string) $result['message']) . '</p>',
            '<a class="button" href="' . escapeHtml((string) $result['url']) . '">'
                . escapeHtml($updater->getText('openAdmidio')) . '</a>',
            100,
            'success',
            $updater->getLanguageCode(),
            $updater->getText('progressLabel')
        );
        exit;
    }

    $status = $updater->getStatus();
    $warning = '';

    if ($status['source'] === 'master') {
        $warning = '<div class="warning">'
            . escapeHtml($updater->getText('masterWarning'))
            . '</div>';
    }

    $details = $warning
        . '<dl>'
        . '<dt>' . escapeHtml($updater->getText('installedVersion')) . '</dt><dd>'
        . escapeHtml((string) $status['sourceVersion']) . '</dd>'
        . '<dt>' . escapeHtml($updater->getText('target')) . '</dt><dd>'
        . escapeHtml((string) $status['targetVersion']) . '</dd>'
        . '<dt>' . escapeHtml($updater->getText('currentState')) . '</dt><dd id="state">'
        . escapeHtml((string) $status['state']) . '</dd>'
        . '</dl>'
        . '<div id="message" class="message">'
        . escapeHtml($status['lastError'] !== '' ? (string) $status['lastError'] : $updater->getText('ready'))
        . '</div>';

    $buttons = '<button class="button" id="continue-button" type="button">'
        . escapeHtml($updater->getText('startResume')) . '</button>'
        . '<button class="button secondary" id="rollback-button" type="button"'
        . ($status['state'] === 'rollback_required' ? '' : ' hidden')
        . '>' . escapeHtml($updater->getText('restorePrevious')) . '</button>';

    $script = '<script>
        const endpoint = ' . json_encode(
            'index.php?update_id=' . rawurlencode($updateId),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';
        const csrfToken = ' . json_encode(
            $updater->getCsrfToken(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';
        const requestFailedText = ' . json_encode(
            $updater->getText('requestFailed'),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';
        const confirmRestoreText = ' . json_encode(
            $updater->getText('confirmRestore'),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';
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
            const result = await response.json();

            if (!response.ok || result.status === "error") {
                throw new Error(result.message || requestFailedText);
            }
            return result;
        }

        function updateDisplay(result) {
            message.textContent = result.message || "";
            progress.style.width = (result.progress || 0) + "%";
            progress.setAttribute("aria-valuenow", result.progress || 0);
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

    showPage(
        $updater->getText('title'),
        $details,
        $buttons . $script,
        (int) $status['progress'],
        $status['state'] === 'rollback_required' ? 'error' : '',
        $updater->getLanguageCode(),
        $updater->getText('progressLabel')
    );
} catch (Throwable $exception) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'status' => 'error',
            'message' => $exception->getMessage()
        ), JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(500);
    showPage(
        'Admidio core update',
        '<p>' . escapeHtml($exception->getMessage()) . '</p>',
        '',
        0,
        'error'
    );
}

/**
 * @param string $title
 * @param string $content
 * @param string $actions
 * @param int $progress
 * @param string $messageClass
 */
function showPage(
    string $title,
    string $content,
    string $actions,
    int $progress,
    string $messageClass = '',
    string $languageCode = 'en',
    string $progressLabel = 'Update progress'
): void {
    $safeTitle = escapeHtml($title);
    $class = $messageClass !== '' ? ' ' . escapeHtml($messageClass) : '';

    echo '<!doctype html>
<html lang="' . escapeHtml($languageCode) . '">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . $safeTitle . '</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f5f7; color: #20252a; }
        main { max-width: 760px; margin: 5vh auto; padding: 2rem; background: #fff; border-radius: .5rem;
            box-shadow: 0 .25rem 1rem rgba(0,0,0,.12); }
        h1 { margin-top: 0; }
        dl { display: grid; grid-template-columns: 12rem 1fr; gap: .4rem 1rem; }
        dt { font-weight: 700; }
        dd { margin: 0; overflow-wrap: anywhere; }
        .progress { height: 1rem; background: #dfe4e8; border-radius: .5rem; overflow: hidden; margin: 1.5rem 0; }
        .progress-bar { height: 100%; background: #2176bd; transition: width .2s ease; }
        .message, .warning { margin: 1rem 0; padding: 1rem; border-radius: .3rem; background: #e8f2fb; }
        .warning { background: #fff3cd; color: #5f4b00; }
        .error { background: #f8d7da; color: #721c24; }
        .success { background: #d1e7dd; color: #0f5132; }
        .button { display: inline-block; margin: .5rem .5rem 0 0; border: 0; border-radius: .3rem;
            padding: .7rem 1rem; background: #2176bd; color: #fff; text-decoration: none; cursor: pointer; font-size: 1rem; }
        .button.secondary { background: #6c757d; }
        .button:disabled { opacity: .65; cursor: wait; }
        [hidden] { display: none !important; }
        @media (prefers-color-scheme: dark) {
            body { background: #181a1b; color: #e8e6e3; }
            main { background: #242729; }
            .progress { background: #3b4044; }
        }
    </style>
</head>
<body>
<main>
    <h1>' . $safeTitle . '</h1>
    <div class="progress" role="progressbar" aria-label="' . escapeHtml($progressLabel) . '" aria-valuemin="0"
        aria-valuemax="100" aria-valuenow="' . $progress . '">
        <div id="progress-bar" class="progress-bar" style="width:' . $progress . '%"></div>
    </div>
    <div class="' . trim($class) . '">' . $content . '</div>
    <div>' . $actions . '</div>
</main>
</body>
</html>';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
