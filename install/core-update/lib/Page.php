<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The pages of the updater: plain HTML with inline CSS, because the updater must not depend on the
 * theme or the libraries of the release it replaces.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Page
{
    /**
     * Headers that every answer of the updater carries.
     * @return void
     */
    public static function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Answer a request of the updater page's JavaScript.
     * @param array<string,mixed> $data
     * @param int $status
     * @return void
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Escape a value for HTML.
     * @param string $value
     * @return string
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Encode a value for a script block.
     * @param mixed $value
     * @return string
     */
    public static function js(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * The login form of the updater.
     * @param Texts $texts
     * @param string $csrfToken
     * @param string $error An error to show above the form, already translated.
     * @return string
     */
    public static function loginForm(Texts $texts, string $csrfToken, string $error = ''): string
    {
        return ($error !== '' ? '<div class="message error">' . self::escape($error) . '</div>' : '')
            . '<p>' . self::escape($texts->get('SYS_CORE_UPDATE_LOGIN_DESC')) . '</p>'
            . '<form method="post" action="index.php" autocomplete="off">'
            . '<input type="hidden" name="action" value="login">'
            . '<input type="hidden" name="csrf_token" value="' . self::escape($csrfToken) . '">'
            . '<label>' . self::escape($texts->get('SYS_CORE_UPDATE_LOGIN_NAME'))
            . '<input type="text" name="login_name" required maxlength="254" autocomplete="username"></label>'
            . '<label>' . self::escape($texts->get('SYS_CORE_UPDATE_PASSWORD'))
            . '<input type="password" name="password" required autocomplete="current-password"></label>'
            . '<label>' . self::escape($texts->get('SYS_CORE_UPDATE_SECURITY_CODE'))
            . '<input type="text" name="totp_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>'
            . '<button class="button" type="submit">' . self::escape($texts->get('SYS_CORE_UPDATE_LOGIN')) . '</button>'
            . '</form>';
    }

    /**
     * Send a whole page.
     * @param string $title Plain text.
     * @param string $content HTML.
     * @param string $actions HTML.
     * @param int|null $progress Percentage of the progress bar, or **null** for none.
     * @param string $messageClass **error**, **success** or an empty string.
     * @param string $languageCode
     * @param string $progressLabel
     * @return void
     */
    public static function show(
        string $title,
        string $content,
        string $actions = '',
        ?int $progress = null,
        string $messageClass = '',
        string $languageCode = 'en',
        string $progressLabel = 'Update progress'
    ): void {
        $safeTitle = self::escape($title);
        $progressBar = $progress === null ? '' : '
    <div class="progress" role="progressbar" aria-label="' . self::escape($progressLabel) . '" aria-valuemin="0"
        aria-valuemax="100" aria-valuenow="' . $progress . '">
        <div id="progress-bar" class="progress-bar" style="width:' . $progress . '%"></div>
    </div>';

        echo '<!doctype html>
<html lang="' . self::escape($languageCode) . '">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
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
        label { display: block; margin: 0 0 1rem; font-weight: 700; }
        input[type=text], input[type=password] { display: block; box-sizing: border-box; width: 100%; max-width: 24rem;
            margin-top: .3rem; padding: .5rem; font-size: 1rem; border: 1px solid #adb5bd; border-radius: .3rem; }
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
        form.inline { display: inline; }
        fieldset { border: 0; margin: 0 0 1rem; padding: 0; }
        legend { font-weight: 700; margin-bottom: .3rem; }
        label.choice { display: block; font-weight: 400; margin: 0 0 .3rem; }
        ul.checks { list-style: none; padding: 0; }
        ul.checks li { padding: .3rem 0 .3rem 1.6rem; position: relative; }
        ul.checks li::before { position: absolute; left: 0; font-weight: 700; }
        .check-ok::before { content: "\2713"; color: #198754; }
        .check-warning::before { content: "!"; color: #b58100; }
        .check-error::before { content: "\2717"; color: #dc3545; }
        @media (prefers-color-scheme: dark) {
            body { background: #181a1b; color: #e8e6e3; }
            main { background: #242729; }
            .progress { background: #3b4044; }
            input[type=text], input[type=password] { background: #181a1b; color: #e8e6e3; }
        }
    </style>
</head>
<body>
<main>
    <h1>' . $safeTitle . '</h1>' . $progressBar . '
    <div' . ($messageClass !== '' ? ' class="' . self::escape($messageClass) . '"' : '') . '>' . $content . '</div>
    <div>' . $actions . '</div>
</main>
</body>
</html>';
    }
}
