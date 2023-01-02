<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Stores language data in a class object
 *
 * This class stores data of the Language object. These are the paths to all
 * relevant language files, the configured language and the default language.
 * This object is designed to be stored in a PHP session. The Language
 * object itself couldn't be stored in a Session because it uses PHP objects
 * which couldn't stored in a PHP session.
 *
 * **Code example**
 * ```
 * // show how to use this class with the language class and sessions
 * script_a.php
 * // create a language data object and assign it to the language object
 * $languageData = new LanguageData('de');
 * $language = new Language($languageData);
 * $session->addObject('languageData', $languageData);
 *
 * script_b.php
 * // read language data from session and add it to language object
 * $languageData = $session->getObject('languageData')
 * $language = new Language($languageData);
 * ```
 */
class LanguageData
{
    public const REFERENCE_LANGUAGE = 'en'; // The ISO code of the default language that should be read if in the current language the text id is not translated

    /**
     * @var string The code of the language that should be read in this object
     */
    private $language;
    /**
     * @var string The ISO 639-1 code of the language
     */
    private $languageIsoCode;
    /**
     * @var array<int,string> Array with all relevant language files
     */
    private $languageLibs;
    /**
     * @var array<int,string> Array with all relevant language files
     */
    private $languageFolderPaths = array();
    /**
     * @var array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    private $countries = array();
    /**
     * @var array<string,string> Stores all read text data in an array to get quick access if a text is required several times
     */
    private $textCache = array();
    /**
     * @var bool Set to true if the language folders of the plugins are already loaded.
     */
    private $pluginLanguageFoldersLoaded = false;

    /**
     * Creates an object that stores all necessary language data and can be handled in session.
     * Therefore the language must be set and optional a path where the language files are stored.
     * @param string $language The ISO code of the language for which the texts should be read e.g. **'de'**
     *                         If no language is set than the browser language will be determined.
     * @param array $languageInfos An array with additional necessary informations such as iso code, name etc.
     *                             The array must have the following keys 'isocode' and 'libs'.
     * @throws \UnexpectedValueException
     */
    public function __construct($language = '', $languageInfos = array())
    {
        if ($language === '') {
            // get browser language and set this language as default
            $language = static::determineBrowserLanguage(self::REFERENCE_LANGUAGE);
        }

        $this->setLanguage($language);
        $this->addLanguageFolderPath(ADMIDIO_PATH . FOLDER_LANGUAGES);

        $this->addPluginLanguageFolderPaths();
    }

    /**
     * A wakeup add the current database object to this class.
     */
    public function __wakeup()
    {
        $this->pluginLanguageFoldersLoaded = false;
    }

    /**
     * Read language folder of each plugin in adm_plugins and add this folder to the language folder
     * array of this class.
     */
    public function addPluginLanguageFolderPaths()
    {
        if (!$this->pluginLanguageFoldersLoaded) {
            try {
                $pluginFolders = FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_PLUGINS, false, true, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY));
            } catch (\RuntimeException $exception) {
                $GLOBALS['gLogger']->error('L10N: Plugins folder content could not be loaded!', array('errorMessage' => $exception->getMessage()));

                return array();
            }

            foreach ($pluginFolders as $pluginFolder => $type) {
                $languageFolder = $pluginFolder . '/languages';

                if (is_dir($languageFolder)) {
                    $this->addLanguageFolderPath($languageFolder);
                }
            }

            $this->pluginLanguageFoldersLoaded = true;
        }
    }

    /**
     * Adds a new path of language files to the array with all language paths
     * where Admidio should search for language files.
     * @param string $languageFolderPath Server path where Admidio should search for language files.
     * @throws \UnexpectedValueException
     * @return bool Returns true if language path is added.
     */
    public function addLanguageFolderPath($languageFolderPath)
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
     * Determine the language from the browser preferences of the user.
     * @param string $defaultLanguage This language will be set if no browser language could be determined
     * @return string Return the preferred language code of the client browser
     */
    public static function determineBrowserLanguage($defaultLanguage)
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $defaultLanguage;
        }

        $languages = preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $languageChoosed = $defaultLanguage;
        $priorityChoosed = 0;

        foreach ($languages as $value) {
            if (!preg_match('/^([a-z]{2,3}(?:-[a-zA-Z]{2,3})?|\*)(?:\s*;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?$/', $value, $matches)) {
                continue;
            }

            $langCodes = explode('-', $matches[1]);

            $priority = 1.0;
            if (isset($matches[2])) {
                $priority = (float) $matches[2];
            }

            if ($priorityChoosed < $priority && $langCodes[0] !== '*') {
                $languageChoosed = $langCodes[0];
                $priorityChoosed = $priority;
            }
        }

        return $languageChoosed;
    }

    /**
     * Returns an array with all language paths that were set.
     * @return array<int,string> with all language paths that were set.
     */
    public function getLanguageFolderPaths()
    {
        return $this->languageFolderPaths;
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountries()
    {
        return $this->countries;
    }

    /**
     * Returns the language code of the language of this object. That will also return the country specific
     * codes such as de-CH. If you only want the ISO code then call getLanguageIsoCode().
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Returns the language ISO 639-1 code
     * @return string Returns the language ISO 639-1 code
     */
    public function getLanguageIsoCode()
    {
        return $this->languageIsoCode;
    }

    /**
     * Returns the language code of the language that we need for some libs e.g. datepicker or ckeditor.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguageLibs()
    {
        return $this->languageLibs;
    }

    /**
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @throws \OutOfBoundsException
     * @return string Returns the cached text or empty string if text id isn't found
     */
    public function getTextCache($textId)
    {
        if (!array_key_exists($textId, $this->textCache)) {
            throw new \OutOfBoundsException('Text-id is not cached!');
        }

        return $this->textCache[$textId];
    }

    /**
     * Save the array with all countries and their ISO codes in an internal parameter for later use
     * @param array<string,string> $countries Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     * @return bool Returns true if language changed.
     */
    public function setLanguage($language)
    {
        global $gSupportedLanguages;

        if ($language === $this->language) {
            return false;
        }

        // initialize all parameters
        $this->countries = array();
        $this->textCache = array();

        $this->language = $language;
        $this->languageLibs = $gSupportedLanguages[$language]['libs'];
        $this->languageIsoCode = $gSupportedLanguages[$language]['isocode'];

        return true;
    }

    /**
     * Sets a new text into the text-cache
     * @param string $textId Unique text id where to set the text e.g. SYS_COMMON
     * @param string $text   The text to cache
     */
    public function setTextCache($textId, $text)
    {
        $this->textCache[$textId] = $text;
    }
}
