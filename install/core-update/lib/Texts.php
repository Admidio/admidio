<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use Throwable;

/**
 * The texts of the updater.
 *
 * They are part of Admidio's translation system (keys SYS_CORE_UPDATE_* in languages/*.xml), but
 * the updater cannot use Admidio's Language class. The release builder therefore copies those keys
 * into **texts/&lt;language&gt;.json** next to the updater, and that file is what the updater reads.
 * A development tree has no such files; there the keys are read from the language files directly,
 * with the same conversion the builder applies.
 *
 * A text is plain text, never HTML: the page escapes it like any other value.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Texts
{
    /**
     * The prefix of the keys that belong to the updater.
     */
    public const KEY_PREFIX = 'SYS_CORE_UPDATE_';

    /**
     * @param string $language The language the texts were asked for.
     * @param array<string,string> $texts key => text
     */
    public function __construct(private readonly string $language, private readonly array $texts)
    {
    }

    /**
     * The texts in a language. A text that the language lacks is taken from the language it is a
     * variant of (de for de-DE), and then from English.
     * @param string $updaterPath Directory of the updater, which holds texts/.
     * @param string $rootPath Directory of the installation, which holds languages/.
     * @param string $language For example **de-DE**.
     * @return self
     */
    public static function load(string $updaterPath, string $rootPath, string $language): self
    {
        if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/', $language) !== 1) {
            $language = 'en';
        }

        $chain = array_unique(array('en', explode('-', $language)[0], $language));
        $texts = array();
        foreach ($chain as $code) {
            $texts = array_merge($texts, self::readLanguage($updaterPath, $rootPath, $code));
        }

        return new self($language, $texts);
    }

    /**
     * The language of the installation: the language setting of its first organization, which is
     * the one Admidio itself falls back to. English if it cannot be read.
     * @param Db $db
     * @return string
     */
    public static function getInstallationLanguage(Db $db): string
    {
        try {
            $language = $db->fetchValue(
                'SELECT prf_value FROM ' . $db->table('preferences') . '
                  WHERE prf_name = ?
                    AND prf_org_id = (SELECT MIN(org_id) FROM ' . $db->table('organizations') . ')',
                array('system_language')
            );
        } catch (Throwable) {
            return 'en';
        }

        return is_string($language) && $language !== '' ? $language : 'en';
    }

    /**
     * The texts of one language: the file the builder wrote, or else the language file of Admidio.
     * @param string $updaterPath
     * @param string $rootPath
     * @param string $code
     * @return array<string,string>
     */
    private static function readLanguage(string $updaterPath, string $rootPath, string $code): array
    {
        $file = $updaterPath . '/texts/' . $code . '.json';
        if (is_file($file)) {
            $texts = json_decode((string)file_get_contents($file), true);

            return is_array($texts) ? array_map('strval', array_filter($texts, 'is_string')) : array();
        }

        $languageFile = $rootPath . '/languages/' . $code . '.xml';

        return is_file($languageFile) ? self::readLanguageFile($languageFile) : array();
    }

    /**
     * The updater texts of an Admidio language file, converted to plain text.
     *
     * The language files use the Android string resource format, in which an apostrophe and a
     * quotation mark are escaped with a backslash and a line break is written as \n. The release
     * builder uses this method as well, so a development tree and a release read the same texts.
     * @param string $file
     * @return array<string,string>
     */
    public static function readLanguageFile(string $file): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string((string)file_get_contents($file), 'SimpleXMLElement', LIBXML_NONET);
        } finally {
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            return array();
        }

        $texts = array();
        foreach ($xml->string as $node) {
            $key = (string)$node['name'];
            if (str_starts_with($key, self::KEY_PREFIX)) {
                $texts[$key] = strtr((string)$node, array('\\n' => "\n", '\\\'' => '\'', '\\"' => '"'));
            }
        }

        return $texts;
    }

    /**
     * Whether a string is a key of the updater texts.
     * @param string $value
     * @return bool
     */
    public static function isKey(string $value): bool
    {
        return preg_match('/^' . self::KEY_PREFIX . '[A-Z0-9_]+$/', $value) === 1;
    }

    /**
     * One text with its placeholders #VAR1#, #VAR2#, ... replaced. A key without a text is returned
     * as it is, so that a missing text is visible instead of an empty message.
     * @param string $key
     * @param array<int,string> $params
     * @return string
     */
    public function get(string $key, array $params = array()): string
    {
        $text = $this->texts[$key] ?? $key;
        foreach (array_values($params) as $index => $param) {
            $text = str_replace('#VAR' . ($index + 1) . '#', $param, $text);
        }

        return $text;
    }

    /**
     * The language the texts were asked for, for the lang attribute of the page.
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }
}
