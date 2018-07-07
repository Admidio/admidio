<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
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
 * **Code example:**
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
    const REFERENCE_LANGUAGE = 'en'; // The ISO code of the default language that should be read if in the current language the text id is not translated

    /**
     * @var string The ISO code of the language that should be read in this object
     */
    private $language;
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
     * Creates an object that stores all necessary language data and can be handled in session.
     * Therefore the language must be set and optional a path where the language files are stored.
     * @param string $language           The ISO code of the language for which the texts should be read e.g. **'de'**
     *                                   If no language is set than the browser language will be determined.
     * @param string $languageFolderPath Optional a server path to the language files. If no path is set
     *                                   than the default Admidio language path **adm_program/languages** will be set.
     * @throws \UnexpectedValueException
     */
    public function __construct($language = '', $languageFolderPath = '')
    {
        if ($language === '')
        {
            // get browser language and set this language as default
            $language = static::determineBrowserLanguage(self::REFERENCE_LANGUAGE);
        }
        $this->language = $language;

        if ($languageFolderPath === '')
        {
            $languageFolderPath = ADMIDIO_PATH . FOLDER_LANGUAGES;
        }

        $this->addLanguageFolderPath($languageFolderPath);
        foreach (self::getPluginLanguageFolderPaths() as $pluginLanguageFolderPath)
        {
            $this->addLanguageFolderPath($pluginLanguageFolderPath);
        }
    }

    /**
     * Search and returns all plugin language folder paths
     * @return array<int,string> Returns all plugin language folder paths
     */
    private static function getPluginLanguageFolderPaths()
    {
        global $gLogger;

        try
        {
            $pluginFolders = FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_PLUGINS, false, true, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY));
        }
        catch (\RuntimeException $exception)
        {
            $gLogger->error('L10N: Plugins folder content could not be loaded!', array('errorMessage' => $exception->getMessage()));

            return array();
        }

        $languageFolders = array();
        foreach ($pluginFolders as $pluginFolder => $type)
        {
            $languageFolder = $pluginFolder . '/languages';
            if (is_dir($languageFolder))
            {
                $languageFolders[] = $languageFolder;
            }
        }

        return $languageFolders;
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
        if ($languageFolderPath === '' || !is_dir($languageFolderPath))
        {
            throw new \UnexpectedValueException('Invalid folder path!');
        }

        if (in_array($languageFolderPath, $this->languageFolderPaths, true))
        {
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
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        {
            return $defaultLanguage;
        }

        $languages = preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $languageChoosed = $defaultLanguage;
        $priorityChoosed = 0;

        foreach ($languages as $value)
        {
            if (!preg_match('/^([a-z]{2,3}(?:-[a-zA-Z]{2,3})?|\*)(?:\s*;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?$/', $value, $matches))
            {
                continue;
            }

            $langCodes = explode('-', $matches[1]);

            $priority = 1.0;
            if (isset($matches[2]))
            {
                $priority = (float) $matches[2];
            }

            if ($priorityChoosed < $priority && $langCodes[0] !== '*')
            {
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
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to **true** than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage($referenceLanguage = false)
    {
        global $gLogger;

        if ($referenceLanguage)
        {
            $gLogger->warning('DEPRECATED: "$languageData->getLanguage(true)" is deprecated, use "LanguageData::REFERENCE_LANGUAGE" instead!');

            return self::REFERENCE_LANGUAGE;
        }

        return $this->language;
    }

    /**
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @throws \OutOfBoundsException
     * @return string Returns the cached text or empty string if text id isn't found
     */
    public function getTextCache($textId)
    {
        if (!array_key_exists($textId, $this->textCache))
        {
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
        if ($language === $this->language)
        {
            return false;
        }

        // initialize all parameters
        $this->countries = array();
        $this->textCache = array();

        $this->language = $language;

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

    /**
     * Adds a new path of language files to the array with all language paths
     * where Admidio should search for language files.
     * @deprecated 3.3.0:4.0.0 "addLanguagePath()" is deprecated. Use "addLanguageFolderPath()" instead.
     * @param string $languageFolderPath Server path where Admidio should search for language files.
     * @return bool Returns true if language path is added.
     */
    public function addLanguagePath($languageFolderPath)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "addLanguagePath()" is deprecated. Use "addLanguageFolderPath()" instead!');

        try
        {
            return $this->addLanguageFolderPath($languageFolderPath);
        }
        catch (\UnexpectedValueException $exception)
        {
            return false;
        }
    }

    /**
     * Returns an array with all language paths that were set.
     * @deprecated 3.3.0:4.0.0 "getLanguagePaths()" is deprecated. Use "getLanguageFolderPaths()" instead.
     * @return array<int,string> with all language paths that were set.
     */
    public function getLanguagePaths()
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "getLanguagePaths()" is deprecated. Use "getLanguageFolderPaths()" instead!');

        return $this->getLanguageFolderPaths();
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @deprecated 3.3.0:4.0.0 "getCountriesArray()" is deprecated. Use "getCountries()" instead.
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountriesArray()
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "getCountriesArray()" is deprecated. Use "getCountries()" instead!');

        return $this->getCountries();
    }

    /**
     * Save the array with all countries and their ISO codes in an internal parameter for later use
     * @deprecated 3.3.0:4.0.0 "setCountriesArray()" is deprecated. Use "setCountries()" instead.
     * @param array<string,string> $countries Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function setCountriesArray(array $countries)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "setCountriesArray()" is deprecated. Use "setCountries()" instead!');

        $this->setCountries($countries);
    }
}
