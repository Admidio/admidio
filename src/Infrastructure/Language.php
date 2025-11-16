<?php
namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Reads language specific texts that are identified with text ids out of language xml files
 *
 * The class will read a language specific text that is identified with their
 * text id out of a language xml file. The access will be managed with the
 * \SimpleXMLElement which search through xml files.
 *
 * **Code example**
 * ```
 * // create a language data object and assign it to the language object
 * $gL10n = new Language('de');
 *
 * // read and display a language specific text with placeholders for individual content
 * echo $gL10n->get('SYS_CREATED_BY_AND_AT', array('John Doe', '2019-04-13'));
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Language
{
    public const REFERENCE_LANGUAGE = 'en'; // The ISO code of the default language that should be read if in the current language the text id is not translated

    /**
     * @var string The code of the language that should be read in this object
     */
    private string $language = '';
    /**
     * @var string The ISO 639-1 code of the language
     */
    private string $languageIsoCode = '';
    /**
     * @var string The language code for external libraries.
     */
    private string $languageLibs = '';
    /**
     * @var array<int,string> Array with all relevant language files
     */
    private array $languageFolderPaths = array();
    /**
     * @var array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    private array $countries = array();
    /**
     * @var array<string,string> Stores all read text data in an array to get quick access if a text is required several times
     */
    private array $textCache = array();
    /**
     * @var bool Set to true if the language folders of the plugins are already loaded.
     */
    private bool $pluginLanguageFoldersLoaded = false;
    /**
     * @var array<string,string> An Array with all available languages and their ISO codes
     */
    private array $languages = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the language from all paths that are set in **$languageFolderPaths**.
     */
    private array $xmlLanguageObjects = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the reference language from all paths that are set in **$languageFolderPaths**.
     */
    private array $xmlRefLanguageObjects = array();

    /**
     * Language constructor.
     * @param string $language The ISO code of the language for which the texts should be read e.g. **'de'**
     *                         If no language is set than the browser language will be determined.
     */
    public function __construct(string $language, bool $useBrowserLanguageIfAvailable = false)
    {
        if ($useBrowserLanguageIfAvailable) {
            // get browser language and set this language as default
            if(!$this->setLanguage(static::determineBrowserLanguage($language))) {
                // if the browser language is not available then set the default language
                $this->setLanguage($language);
            }
        } else {
            $this->setLanguage($language);
        }

        $this->addLanguageFolderPath(ADMIDIO_PATH . FOLDER_LANGUAGES);

        $this->addPluginLanguageFolderPaths();
    }

    /**
     * We need the sleep function at this place because otherwise the system will serialize a SimpleXMLElement
     * which will lead to an exception.
     * @return array<int,string>
     */
    public function __sleep()
    {
        return array('language', 'languageIsoCode', 'languageLibs', 'languageFolderPaths', 'languages', 'countries', 'textCache', 'pluginLanguageFoldersLoaded');
    }

    /**
     * Adds a new path of language files to the array with all language paths where Admidio
     * should search for language files.
     * @param string $languageFolderPath Server path where Admidio should search for language files.
     * @return bool Returns true if language path is added.
     *@throws \UnexpectedValueException
     */
    public function addLanguageFolderPath(string $languageFolderPath): bool
    {
        if ($languageFolderPath === '' || !is_dir($languageFolderPath)) {
            throw new \UnexpectedValueException('Invalid folder path!');
        }

        if (in_array($languageFolderPath, $this->languageFolderPaths, true)) {
            return false;
        }

        $this->languageFolderPaths[] = $languageFolderPath;

        return true;
    }

    /**
     * Read language folder of each plugin in adm_plugins and add this folder to the language folder
     * array of this class.
     */
    public function addPluginLanguageFolderPaths(): void
    {
        global $gLogger;

        if (!$this->pluginLanguageFoldersLoaded) {
            try {
                $pluginFolders = FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_PLUGINS, false, true, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY));

                foreach ($pluginFolders as $pluginFolder => $type) {
                    $languageFolder = $pluginFolder . '/languages';

                    if (is_dir($languageFolder)) {
                        $this->addLanguageFolderPath($languageFolder);
                    }
                }

                $this->pluginLanguageFoldersLoaded = true;
            } catch (\RuntimeException $exception) {
                $gLogger->error('L10N: Plugins folder content could not be loaded!', array('errorMessage' => $exception->getMessage()));
            }
        }
    }

    /**
     * Determine the language from the browser preferences of the user.
     * @param string $defaultLanguage This language will be set if no browser language could be determined
     * @return string Return the preferred language code of the client browser
     */
    public static function determineBrowserLanguage(string $defaultLanguage): string
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $defaultLanguage;
        }

        $languages = preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $languageSelected = $defaultLanguage;
        $prioritySelected = 0;

        foreach ($languages as $value) {
            if (!preg_match('/^([a-z]{2,3}(?:-[a-zA-Z]{2,3})?|\*)(?:\s*;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?$/', $value, $matches)) {
                continue;
            }

            $langCodes = explode('-', $matches[1]);

            $priority = 1.0;
            if (isset($matches[2])) {
                $priority = (float) $matches[2];
            }

            if ($prioritySelected < $priority && $langCodes[0] !== '*') {
                $languageSelected = $matches[1]; //$langCodes[0];
                $prioritySelected = $priority;
            }
        }

        // special case for the german language code
        if ($languageSelected === 'de' && $defaultLanguage === 'de-DE') {
            $languageSelected = 'de-DE';
        } elseif ($languageSelected === 'de-DE' && $defaultLanguage === 'de') {
            $languageSelected = 'de';
        }

        return $languageSelected;
    }

    /**
     * Reads a text string out of a language xml file that is identified
     * with a unique text id e.g. SYS_COMMON. If the text contains placeholders
     * than you must set more parameters to replace them.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @param array<int,string> $params Optional parameter to replace placeholders in the text.
     *                                  $params[0] will replace **#VAR1#**, **#VAR1_BOLD#** or **#VAR1_ITALIC#**,
     *                                  $params[1] will replace **#VAR2#**, **#VAR2_BOLD#** or **#VAR2_ITALIC#** etc.
     * @return string Returns the text string with replaced placeholders of the text id.
     *
     * **Code example**
     * ```
     * // display a text without placeholders
     * echo $gL10n->get('SYS_NUMBER');
     * // display a text with placeholders for individual content
     * echo $gL10n->get('SYS_CREATED_BY_AND_AT', array('John Doe', '2019-04-13'));
     * ```
     * @throws Exception
     */
    public function get(string $textId, array $params = array()): string
    {
        global $gLogger;

        $startTime = microtime(true);

        try {
            $text = $this->getTextFromTextId($textId);

            //$gLogger->debug('L10N: Lookup time:', array('time' => getExecutionTime($startTime), 'textId' => $textId));
        } catch (\OutOfBoundsException $exception) {
            $gLogger->debug('L10N: Lookup time:', array('time' => getExecutionTime($startTime), 'textId' => $textId));
            $gLogger->error('L10N: ' . $exception->getMessage(), array('textId' => $textId));

            // Read language folders of the plugins. Maybe there was a new plugin installed.
            $this->addPluginLanguageFolderPaths();

            // no text found then write #undefined text#
            return '#' . $textId . '#';
        }

        return self::prepareTextPlaceholders($text, $params);
    }

    /**
     * Gets an array with all languages that are possible in Admidio.
     * The array will have the following syntax e.g.: array('DE' => 'deutsch' ...)
     * @return array<string,string> Return an array with all available languages.
     */
    public function getAvailableLanguages(): array
    {
        if (count($this->languages) === 0) {
            $this->languages = self::loadAvailableLanguages();
        }

        return $this->languages;
    }

    /**
     * Returns the path of a country file.
     * @return string
     * @throws Exception
     */
    private function getCountryFile(): string
    {
        $langFile    = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries-' . $this->language . '.xml';
        $langFileRef = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries-' . $this::REFERENCE_LANGUAGE   . '.xml';

        if (is_file($langFile)) {
            return $langFile;
        }
        if (is_file($langFileRef)) {
            return $langFileRef;
        }

        throw new Exception('Country files not found!');
    }

    /**
     * Returns an array with all countries and their ISO codes (ISO 3166 ALPHA-3)
     * @return array<string,string> Array with all countries and their ISO codes (ISO 3166 ALPHA-3) e.g.: array('DEU' => 'Germany' ...)
     * @throws Exception
     */
    public function getCountries(): array
    {
        if (count($this->countries) === 0) {
            $this->countries = $this->loadCountries();
        }

        return $this->countries;
    }

    /**
     * Returns the name of the country in the language of this object. The country will be
     * identified by the ISO code (ISO 3166 ALPHA-3) e.g. 'DEU' or 'GBR' ...
     * @param string $countryIsoCode The three digits ISO code (ISO 3166 ALPHA-3) of the country where the name should be returned.
     * @return string Return the name of the country in the language of this object.
     * @throws Exception
     */
    public function getCountryName(string $countryIsoCode): string
    {
        if (empty($countryIsoCode)) {
            return '';
        }

        if (!preg_match('/^[A-Z]{3}$/', $countryIsoCode)) {
            throw new Exception('SYS_COUNTRY_ISO');
        }

        $countries = $this->getCountries();

        if (!array_key_exists($countryIsoCode, $countries)) {
            throw new Exception('Country-iso-code does not exist!');
        }

        return $countries[$countryIsoCode];
    }

    /**
     * Returns the three digits ISO code (ISO 3166 ALPHA-3) of the country. The country will be identified
     * by the name in the language of this object
     * @param string $countryName The name of the country in the language of this object.
     * @return string Return the three digits ISO code (ISO 3166 ALPHA-3) of the country.
     * @throws Exception
     */
    public function getCountryIsoCode(string $countryName): string
    {
        if ($countryName === '') {
            throw new Exception('Invalid country name!');
        }

        $countries = $this->getCountries();

        $result = array_search($countryName, $countries, true);
        if ($result === false) {
            throw new Exception('Country name does not exist!');
        }

        return $result;
    }

    /**
     * Returns the language code of the language of this object. That will also return the country specific
     * codes such as de-CH. If you only want the ISO code then call getLanguageIsoCode().
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Returns the ISO 639-1 code of the language of this object.
     * @return string Returns the ISO 639-1 code of the language of this object e.g. **de** or **en**.
     */
    public function getLanguageIsoCode(): string
    {
        return $this->languageIsoCode;
    }

    /**
     * Returns the language code of the language that we need for some libs e.g. datepicker or ckeditor.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguageLibs(): string
    {
        return $this->languageLibs;
    }

    /**
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the cached text or empty string if text id isn't found
     * @throws \OutOfBoundsException
     */
    private function getTextCache(string $textId): string
    {
        if (!array_key_exists($textId, $this->textCache)) {
            throw new \OutOfBoundsException('Text-id is not cached!');
        }

        return $this->textCache[$textId];
    }

    /**
     * Reads a text string out of a language xml file that is identified with a unique text id e.g. SYS_COMMON.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the text string of the text id.
     * @throws Exception
     */
    private function getTextFromTextId(string $textId): string
    {
        // first search text id in text-cache
        try {
            return $this->getTextCache($textId);
        } catch (\OutOfBoundsException) {
            // if text id wasn't found than search for it in language
            try {
                // search for text id in every \SimpleXMLElement (language file) of the object array
                return $this->searchTextIdInLangObject($this->xmlLanguageObjects, $this->language, $textId);
            } catch (\OutOfBoundsException) {
                // if text id wasn't found than search for it in reference language
                try {
                    // search for text id in every \SimpleXMLElement (language file) of the object array
                    return $this->searchTextIdInLangObject($this->xmlRefLanguageObjects, $this::REFERENCE_LANGUAGE, $textId);
                } catch (\OutOfBoundsException $exception) {
                    throw new \OutOfBoundsException($exception->getMessage());
                }
            }
        }
    }

    /**
     * Checks if a given string is a translation-string-id
     * @param string $string The string to check
     * @return bool Returns true if the given string is a translation-string-id
     */
    public static function isTranslationStringId(string $string): bool
    {
        return (bool) preg_match('/^[A-Z]{3}_([A-Z0-9]_?)*[A-Z0-9]$/', $string);
    }

    /**
     * Creates an array with all languages that are possible in Admidio.
     * The array will have the following syntax e.g.: array('DE' => 'deutsch' ...)
     * @return array<string,string>
     */
    private static function loadAvailableLanguages(): array
    {
        require(ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.php');

        return array_map(function ($languageInfos) {
            return $languageInfos['name'];
        }, $gSupportedLanguages);
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     * @throws Exception
     */
    private function loadCountries(): array
    {
        $countryFile = $this->getCountryFile();

        // read all countries from xml file
        try {
            $countriesXml = new \SimpleXMLElement($countryFile, 0, true);
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
        $countries = array();

        /**
         * @var \SimpleXMLElement $xmlNode
         */
        foreach ($countriesXml->children() as $xmlNode) {
            $countries[(string) $xmlNode['name']] = (string) $xmlNode;
        }

        asort($countries, SORT_LOCALE_STRING);

        return $countries;
    }

    /**
     * Replaces all placeholders of the translation string with their values that are set through the array **$params**.
     * If the value of the array is a translation id the method will automatically try to replace this id with the
     * translation string.
     * @param string $text The translation string with the static placeholders
     * @param array<int,string> $params An array with values for each placeholder of the string.
     * @return string Returns the translation string with the replaced placeholders.
     * @throws Exception
     */
    private function prepareTextPlaceholders(string $text, array $params): string
    {
        // replace placeholder with value of parameters
        foreach ($params as $index => $param) {
            $paramNr = $index + 1;

            $param = self::translateIfTranslationStrId($param);

            $replaces = array(
                '#VAR' . $paramNr . '#'      => $param,
                '#VAR' . $paramNr . '_BOLD#' => '<strong>' . $param . '</strong>',
                '#VAR' . $paramNr . '_ITALIC#' => '<em>' . $param . '</em>'
            );
            $text = StringUtils::strMultiReplace($text, $replaces);
        }

        // replace square brackets with html tags
        return strtr($text, '[]', '<>');
    }

    /**
     * @param string $text
     * @return string
     */
    private static function prepareXmlText(string $text): string
    {
        // set line break with html
        // Within Android string resource all apostrophe are escaped, so we must remove the escape char
        // replace highly comma, so there are no problems in the code later
        $replaces = array(
            '\\n'  => '<br />',
            '\\\'' => '\'',
            '\''   => '&rsquo;',
            '\\"'  => '&quot;'
        );
        return StringUtils::strMultiReplace($text, $replaces);
    }

    /**
     * Search for text id in a language xml file and return the text. If no text was found than nothing is returned.
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects The reference to an array where every SimpleXMLElement of each language path is stored
     * @param string $languageFilePath The path of the language file to search in.
     * @param string $textId The id of the text that will be searched in the file.
     * @return string Return the text in the language or nothing if text id wasn't found.
     * @throws \OutOfBoundsException|Exception
     */
    private function searchLanguageText(array &$xmlLanguageObjects, string $languageFilePath, string $textId): string
    {
        // if not exists create a \SimpleXMLElement of the language file in the language path
        // and add it to the array of language objects
        if (!array_key_exists($languageFilePath, $xmlLanguageObjects)) {
            if (!is_file($languageFilePath)) {
                // throw exception and don't log missing file because user could not fix that problem if there is no translation file
                throw new \OutOfBoundsException('Language file does not exist!');
            }

            try {
                $xmlLanguageObjects[$languageFilePath] = new \SimpleXMLElement($languageFilePath, 0, true);
            } catch (\Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        // text not in cache -> read from xml file in "Android Resource String" format
        $xmlNodes = $xmlLanguageObjects[$languageFilePath]->xpath('/resources/string[@name="'.$textId.'"]');

        if ($xmlNodes === false || count($xmlNodes) === 0) {
            throw new \OutOfBoundsException('Could not found text-id!');
        }

        $text = self::prepareXmlText((string) $xmlNodes[0]);

        $this->textCache[$textId] = $text;

        return $text;
    }

    /**
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects SimpleXMLElement array of each language path is stored
     * @param string $language           Language code
     * @param string $textId             Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the text string of the text id.
     * @throws \UnexpectedValueException|Exception
     * @throws \OutOfBoundsException
     */
    private function searchTextIdInLangObject(array &$xmlLanguageObjects, string $language, string $textId): string
    {
        foreach ($this->languageFolderPaths as $languageFolderPath) {
            try {
                $languageFilePath = $languageFolderPath . '/' . $language . '.xml';

                return $this->searchLanguageText($xmlLanguageObjects, $languageFilePath, $textId);
            } catch (\OutOfBoundsException) {
                // continue searching, no debug output because this will be default way if you have several language path through plugins
            }
        }

        throw new \OutOfBoundsException('Could not found text-id!');
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     * @return bool Returns true if language exists and could be set.
     */
    public function setLanguage(string $language): bool
    {
        require(ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.php');

        if (!array_key_exists($language, $gSupportedLanguages)) {
            // if language with country code is not available try to set only the language code
            if (strlen($language) > 2) {
                $language = substr($language, 0, 2);
                if (!array_key_exists($language, $gSupportedLanguages)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if ($language <> $this->language) {
            // initialize data
            $this->xmlLanguageObjects    = array();
            $this->xmlRefLanguageObjects = array();
            $this->countries = array();
            $this->textCache = array();

            $this->language = $language;
            $this->languageLibs = $gSupportedLanguages[$language]['libs'];
            $this->languageIsoCode = $gSupportedLanguages[$language]['isocode'];
        }

        return true;
    }

    /**
     * Checks if a given string is a translation-string-id and translate it
     * @param string $string The string to check for translation
     * @return string Returns the translated or original string
     * @throws Exception
     */
    public static function translateIfTranslationStrId(string $string): string
    {
        global $gL10n;

        if (self::isTranslationStringId($string)) {
            return $gL10n->get($string);
        }

        return $string;
    }
}
