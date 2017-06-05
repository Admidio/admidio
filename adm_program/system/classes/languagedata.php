<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class LanguageData
 * @brief Stores language data in a class object
 *
 * This class stores data of the Language object. These are the paths to all
 * relevant language files, the configured language and the default language.
 * This object is designed to be stored in a PHP session. The Language
 * object itself couldn't be stored in a Session because it uses PHP objects
 * which couldn't stored in a PHP session.
 * @par Examples
 * @code // show how to use this class with the language class and sessions
 * script_a.php
 * // create a language data object and assign it to the language object
 * $language = new Language();
 * $languageData = new LanguageData('de');
 * $language->addLanguageData($languageData);
 * $session->addObject('languageData', $languageData);
 *
 * script_b.php
 * // read language data from session and add it to language object
 * $language = new Language();
 * $language->addLanguageData($session->getObject('languageData')); @endcode
 */
class LanguageData
{
    public $textCache = array();         ///< Stores all read text data in an array to get quick access if a text is required several times

    private $languageFilePath = array(); ///< Array with all relevant language files
    private $language;                   ///< The ISO code of the language that should be read in this object
    private $referenceLanguage = 'en';   ///< The ISO code of the default language that should be read if in the current language the text id is not translated
    private $countries = array();        ///< Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)

    /**
     * Creates an object that stores all necessary language data and can be handled in session.
     * Therefore the language must be set and optional a path where the language files are stored.
     * @param string $language     The ISO code of the language for which the texts should be read e.g. @b 'de'
     *                             If no language is set than the browser language will be determined.
     * @param string $languagePath Optional a server path to the language files. If no path is set
     *                             than the default Admidio language path @b adm_program/languages will be set.
     */
    public function __construct($language = '', $languagePath = '')
    {
        if($languagePath === '')
        {
            $this->addLanguagePath(ADMIDIO_PATH . FOLDER_LANGUAGES);
        }
        else
        {
            $this->addLanguagePath($languagePath);
        }

        if($language === '')
        {
            // get browser language and set this language as default
            $language = static::determineBrowserLanguage($this->referenceLanguage);
        }

        $this->setLanguage($language);
    }

    /**
     * Adds a new path of language files to the array with all language paths
     * where Admidio should search for language files.
     * @param string $path Server path where Admidio should search for language files.
     */
    public function addLanguagePath($path)
    {
        if($path !== '' && !in_array($path, $this->languageFilePath, true))
        {
            $this->languageFilePath[] = $path;
        }
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
     * Returns an array with all countries and their ISO codes
     * @return string[] Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountriesArray()
    {
        return $this->countries;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to @b true than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage($referenceLanguage = false)
    {
        if($referenceLanguage)
        {
            return $this->referenceLanguage;
        }

        return $this->language;
    }

    /**
     * Returns an array with all language paths that were set.
     * @return string[] with all language paths that were set.
     */
    public function getLanguagePaths()
    {
        return $this->languageFilePath;
    }

    /**
     * Save the array with all countries and their ISO codes in an internal parameter for later use
     * @param string[] $countries Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function setCountriesArray(array $countries)
    {
        $this->countries = $countries;
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     */
    public function setLanguage($language)
    {
        if($language !== $this->language)
        {
            // initialize all parameters
            $this->textCache = array();
            $this->countries = array();

            $this->language = $language;
        }
    }
}
