<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Reads language specific texts that are identified with text ids out of language xml files
 *
 * The class will read a language specific text that is identified with their
 * text id out of an language xml file. The access will be manages with the
 * \SimpleXMLElement which search through xml files. An object of this class
 * can't be stored in a PHP session because it creates PHP core objects which
 * couldn't be stored in sessions. Therefore an object of **LanguageData**
 * should be assigned to this class that stored all necessary data and can be
 * stored in a session.
 *
 * **Code example:**
 * ```
 * // show how to use this class with the language data class and sessions
 * script_a.php
 * // create a language data object and assign it to the language object
 * $languageData = new LanguageData('de');
 * $language = new Language($languageData);
 * $session->addObject('languageData', $languageData);
 *
 * script_b.php
 * // read language data from session and add it to language object
 * $languageData = $session->getObject('languageData')
 * $language = new Language();
 *
 * // read and display a language specific text with placeholders for individual content
 * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', array('John Doe', 'Demo-Organization', 'Administrator'));
 * ```
 */
class Language
{
    /**
     * @var LanguageData An object of the class **LanguageData** that stores all necessary language data in a session
     */
    private $languageData;
    /**
     * @var array<string,string> An Array with all available languages and their ISO codes
     */
    private $languages = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the language from all paths that are set in **$languageData**.
     */
    private $xmlLanguageObjects = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the reference language from all paths that are set in **$languageData**.
     */
    private $xmlRefLanguageObjects = array();

    /**
     * Language constructor.
     * @param LanguageData $languageDataObject An object of the class **LanguageData**.
     * @throws \UnexpectedValueException
     */
    public function __construct(LanguageData $languageDataObject = null)
    {
        global $gSettingsManager;

        if ($languageDataObject === null)
        {
            $languageDataObject = new LanguageData($gSettingsManager->getString('system_language'));
        }
        $this->languageData =& $languageDataObject;
    }

    /**
     * Adds a new path of language files to the array with all language paths where Admidio
     * should search for language files.
     * @param string $languageFolderPath Server path where Admidio should search for language files.
     * @throws \UnexpectedValueException
     * @return bool Returns true if language path is added.
     */
    public function addLanguageFolderPath($languageFolderPath)
    {
        return $this->languageData->addLanguageFolderPath($languageFolderPath);
    }

    /**
     * Reads a text string out of a language xml file that is identified
     * with a unique text id e.g. SYS_COMMON. If the text contains placeholders
     * than you must set more parameters to replace them.
     * @param string            $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @param array<int,string> $params Optional parameter to replace placeholders in the text.
     *                                  $params[0] will replace **#VAR1#** or **#VAR1_BOLD#**,
     *                                  $params[1] will replace **#VAR2#** or **#VAR2_BOLD#** etc.
     * @return string Returns the text string with replaced placeholders of the text id.
     *
     * **Code example:**
     * ```
     * // display a text without placeholders
     * echo $gL10n->get('SYS_NUMBER');
     * // display a text with placeholders for individual content
     * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', array('John Doe', 'Demo-Organization', 'Administrator'));
     * ``
     */
    public function get($textId, array $params = array())
    {
        global $gLogger;

        $startTime = microtime(true);

        try
        {
            $text = $this->getTextFromTextId($textId);

            //$gLogger->debug('L10N: Lookup time:', array('time' => getExecutionTime($startTime), 'textId' => $textId));
        }
        catch (\RuntimeException $exception)
        {
            $gLogger->debug('L10N: Lookup time:', array('time' => getExecutionTime($startTime), 'textId' => $textId));
            $gLogger->error('L10N: ' . $exception->getMessage(), array('textId' => $textId));

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
    public function getAvailableLanguages()
    {
        if (count($this->languages) === 0)
        {
            $this->languages = self::loadAvailableLanguages();
        }

        return $this->languages;
    }

    /**
     * Returns the path of a country file.
     * @throws \UnexpectedValueException
     * @return string
     */
    private function getCountryFile()
    {
        $langFile    = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries_' . $this->languageData->getLanguage() . '.xml';
        $langFileRef = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries_' . LanguageData::REFERENCE_LANGUAGE   . '.xml';

        if (is_file($langFile))
        {
            return $langFile;
        }
        if (is_file($langFileRef))
        {
            return $langFileRef;
        }

        throw new \UnexpectedValueException('Country files not found!');
    }

    /**
     * Returns an array with all countries and their ISO codes (ISO 3166 ALPHA-3)
     * @throws \UnexpectedValueException
     * @return array<string,string> Array with all countries and their ISO codes (ISO 3166 ALPHA-3) e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountries()
    {
        $countries = $this->languageData->getCountries();

        if (count($countries) === 0)
        {
            $countries = $this->loadCountries();
            $this->languageData->setCountries($countries);
        }

        return $countries;
    }

    /**
     * Returns the name of the country in the language of this object. The country will be
     * identified by the ISO code (ISO 3166 ALPHA-3) e.g. 'DEU' or 'GBR' ...
     * @param string $countryIsoCode The three digits ISO code (ISO 3166 ALPHA-3) of the country where the name should be returned.
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     * @return string Return the name of the country in the language of this object.
     */
    public function getCountryName($countryIsoCode)
    {
        if (!preg_match('/^[A-Z]{3}$/', $countryIsoCode))
        {
            throw new \UnexpectedValueException('Invalid country-iso-code!');
        }

        $countries = $this->getCountries();

        if (!array_key_exists($countryIsoCode, $countries))
        {
            throw new \OutOfBoundsException('Country-iso-code does not exist!');
        }

        return $countries[$countryIsoCode];
    }

    /**
     * Returns the three digits ISO code (ISO 3166 ALPHA-3) of the country. The country will be identified
     * by the name in the language of this object
     * @param string $countryName The name of the country in the language of this object.
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     * @return string Return the three digits ISO code (ISO 3166 ALPHA-3) of the country.
     */
    public function getCountryIsoCode($countryName)
    {
        if ($countryName === '')
        {
            throw new \UnexpectedValueException('Invalid country-name!');
        }

        $countries = $this->getCountries();

        $result = array_search($countryName, $countries, true);
        if ($result === false)
        {
            throw new \OutOfBoundsException('Country-name does not exist!');
        }

        return $result;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage()
    {
        return $this->languageData->getLanguage();
    }

    /**
     * Returns the ISO code of the language of this object.
     * @return string Returns the ISO code of the language of this object or the reference language e.g. **de** or **en**.
     */
    public function getLanguageIsoCode()
    {
        $language = $this->getLanguage();

        if ($language === 'de_sie')
        {
            return 'de';
        }

        return $language;
    }

    /**
     * Reads a text string out of a language xml file that is identified with a unique text id e.g. SYS_COMMON.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @throws \OutOfBoundsException
     * @throws \UnexpectedValueException
     * @return string Returns the text string of the text id.
     */
    private function getTextFromTextId($textId)
    {
        // first search text id in text-cache
        try
        {
            return $this->languageData->getTextCache($textId);
        }
        catch (\OutOfBoundsException $exception)
        {
            // if text id wasn't found than search for it in language
            try
            {
                // search for text id in every \SimpleXMLElement (language file) of the object array
                return $this->searchTextIdInLangObject($this->xmlLanguageObjects, $this->languageData->getLanguage(), $textId);
            }
            catch (\OutOfBoundsException $exception)
            {
                // if text id wasn't found than search for it in reference language
                try
                {
                    // search for text id in every \SimpleXMLElement (language file) of the object array
                    return $this->searchTextIdInLangObject($this->xmlRefLanguageObjects, LanguageData::REFERENCE_LANGUAGE, $textId);
                }
                catch (\OutOfBoundsException $exception)
                {
                    throw new \OutOfBoundsException($exception->getMessage());
                }
            }
        }
    }

    /**
     * Creates an array with all languages that are possible in Admidio.
     * The array will have the following syntax e.g.: array('DE' => 'deutsch' ...)
     * @return array<string,string>
     */
    private static function loadAvailableLanguages()
    {
        $languagesXml = new \SimpleXMLElement(ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml', 0, true);

        $languages = array();

        /**
         * @var \SimpleXMLElement $xmlNode
         */
        foreach ($languagesXml->children() as $xmlNode)
        {
            $xmlChildNodes = $xmlNode->children();
            $languages[(string) $xmlChildNodes->isocode] = (string) $xmlChildNodes->name;
        }

        return $languages;
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @throws \UnexpectedValueException
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    private function loadCountries()
    {
        $countryFile = $this->getCountryFile();

        // read all countries from xml file
        $countriesXml = new \SimpleXMLElement($countryFile, 0, true);

        $countries = array();

        /**
         * @var \SimpleXMLElement $xmlNode
         */
        foreach ($countriesXml->children() as $xmlNode)
        {
            $countries[(string) $xmlNode['name']] = (string) $xmlNode;
        }

        asort($countries, SORT_LOCALE_STRING);

        return $countries;
    }

    /**
     * Replaces all placeholders of the translation string with their values that are set through the array **$params**.
     * If the value of the array is a translation id the method will automatically try to replace this id with the
     * translation string.
     * @param string            $text   The translation string with the static placeholders
     * @param array<int,string> $params An array with values for each placeholder of the string.
     * @return string Returns the translation string with the replaced placeholders.
     */
    private function prepareTextPlaceholders($text, array $params)
    {
        // replace placeholder with value of parameters
        foreach ($params as $index => $param)
        {
            $paramNr = $index + 1;

            $param = self::translateIfTranslationStrId($param);

            $replaces = array(
                '#VAR' . $paramNr . '#'      => $param,
                '#VAR' . $paramNr . '_BOLD#' => '<strong>' . $param . '</strong>'
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
    private static function prepareXmlText($text)
    {
        // set line break with html
        // Within Android string resource all apostrophe are escaped so we must remove the escape char
        // replace highly comma, so there are no problems in the code later
        $replaces = array(
            '\\n'  => '<br />',
            '\\\'' => '\'',
            '\''   => '&rsquo;'
        );
        return StringUtils::strMultiReplace($text, $replaces);
    }

    /**
     * Search for text id in a language xml file and return the text. If no text was found than nothing is returned.
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects The reference to an array where every SimpleXMLElement of each language path is stored
     * @param string                          $languageFilePath   The path of the language file to search in.
     * @param string                          $textId             The id of the text that will be searched in the file.
     * @throws \OutOfBoundsException
     * @return string Return the text in the language or nothing if text id wasn't found.
     */
    private function searchLanguageText(array &$xmlLanguageObjects, $languageFilePath, $textId)
    {
        global $gLogger;

        // if not exists create a \SimpleXMLElement of the language file in the language path
        // and add it to the array of language objects
        if (!array_key_exists($languageFilePath, $xmlLanguageObjects))
        {
            if (!is_file($languageFilePath))
            {
                $gLogger->error('L10N: Language file does not exist!', array('languageFilePath' => $languageFilePath));

                throw new \OutOfBoundsException('Language file does not exist!');
            }

            $xmlLanguageObjects[$languageFilePath] = new \SimpleXMLElement($languageFilePath, 0, true);
        }

        // text not in cache -> read from xml file in "Android Resource String" format
        $xmlNodes = $xmlLanguageObjects[$languageFilePath]->xpath('/resources/string[@name="'.$textId.'"]');

        if ($xmlNodes === false || count($xmlNodes) === 0)
        {
            throw new \OutOfBoundsException('Could not found text-id!');
        }

        $text = self::prepareXmlText((string) $xmlNodes[0]);

        $this->languageData->setTextCache($textId, $text);

        return $text;
    }

    /**
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects SimpleXMLElement array of each language path is stored
     * @param string                          $language           Language code
     * @param string                          $textId             Unique text id of the text that should be read e.g. SYS_COMMON
     * @throws \OutOfBoundsException
     * @throws \UnexpectedValueException
     * @return string Returns the text string of the text id.
     */
    private function searchTextIdInLangObject(array &$xmlLanguageObjects, $language, $textId)
    {
        $languageFolderPaths = $this->languageData->getLanguageFolderPaths();
        foreach ($languageFolderPaths as $languageFolderPath)
        {
            try
            {
                $languageFilePath = $languageFolderPath . '/' . $language . '.xml';

                return $this->searchLanguageText($xmlLanguageObjects, $languageFilePath, $textId);
            }
            catch (\OutOfBoundsException $exception)
            {
                // continue searching, no debug output because this will be default way if you have several language path through plugins
            }
        }

        throw new \OutOfBoundsException('Could not found text-id!');
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     * @return bool Returns true if language changed.
     */
    public function setLanguage($language)
    {
        if ($language === $this->languageData->getLanguage())
        {
            return false;
        }

        // initialize data
        $this->xmlLanguageObjects    = array();
        $this->xmlRefLanguageObjects = array();

        $this->languageData->setLanguage($language);

        return true;
    }

    /**
     * Checks if a given string is a translation-string-id
     * @param string $string The string to check
     * @return bool Returns true if the given string is a translation-string-id
     */
    public static function isTranslationStringId($string)
    {
        return (bool) preg_match('/^[A-Z]{3}_([A-Z0-9]_?)*[A-Z0-9]$/', $string);
    }

    /**
     * Checks if a given string is a translation-string-id and translate it
     * @param string $string The string to check for translation
     * @return string Returns the translated or original string
     */
    public static function translateIfTranslationStrId($string)
    {
        global $gL10n;

        if (self::isTranslationStringId($string))
        {
            return $gL10n->get($string);
        }

        return $string;
    }
}
