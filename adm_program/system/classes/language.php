<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Language
 * @brief Reads language specific texts that are identified with text ids out of language xml files
 *
 * The class will read a language specific text that is identified with their
 * text id out of an language xml file. The access will be manages with the
 * \SimpleXMLElement which search through xml files. An object of this class
 * can't be stored in a PHP session because it creates PHP core objects which
 * couldn't be stored in sessions. Therefore an object of @b LanguageData
 * should be assigned to this class that stored all necessary data and can be
 * stored in a session.
 * @par Examples
 * @code
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
 * @endcode
 */
class Language
{
    /**
     * @var LanguageData An object of the class @b LanguageData that stores all necessary language data in a session
     */
    private $languageData;
    /**
     * @var array<string,string> An Array with all available languages and their ISO codes
     */
    private $languages = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the language from all paths that are set in @b $languageData.
     */
    private $xmlLanguageObjects = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the reference language from all paths that are set in @b $languageData.
     */
    private $xmlRefLanguageObjects = array();

    /**
     * Language constructor.
     * @param LanguageData $languageDataObject An object of the class @b LanguageData.
     */
    public function __construct(LanguageData $languageDataObject = null)
    {
        global $gPreferences;

        if ($languageDataObject === null)
        {
            $languageDataObject = new LanguageData($gPreferences['system_language']);
        }
        $this->languageData =& $languageDataObject;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to @b true than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage($referenceLanguage = false)
    {
        global $gLogger;

        if ($referenceLanguage)
        {
            $gLogger->warning('DEPRECATED: "$gL10n->getLanguage(true)" is deprecated, use "LanguageData::REFERENCE_LANGUAGE" instead!');

            return LanguageData::REFERENCE_LANGUAGE;
        }

        return $this->languageData->getLanguage();
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
     * Returns the ISO code of the language of this object.
     * @param bool $referenceLanguage If set to @b true than the ISO code of the reference language will returned.
     * @return string Returns the ISO code of the language of this object or the reference language e.g. @b de or @b en.
     */
    public function getLanguageIsoCode($referenceLanguage = false)
    {
        $language = $this->getLanguage($referenceLanguage);

        if ($language === 'de_sie')
        {
            return 'de';
        }

        return $language;
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
     * @return string|false Return the three digits ISO code (ISO 3166 ALPHA-3) of the country or false if country not found.
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
     * @param string $text
     * @return string
     */
    private static function prepareXmlText($text)
    {
        // set line break with html
        // Within Android string resource all apostrophe are escaped so we must remove the escape char
        // replace highly comma, so there are no problems in the code later
        $replaceArray = array(
            '\\n'  => '<br />',
            '\\\'' => '\'',
            '\''   => '&rsquo;'
        );
        return str_replace(array_keys($replaceArray), array_values($replaceArray), $text);
    }

    /**
     * Search for text id in a language xml file and return the text. If no text was found than nothing is returned.
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects The reference to an array where every SimpleXMLElement of each language path is stored
     * @param string                          $languageFolderPath The path in which the different language xml files are.
     * @param string                          $language           The ISO code of the language in which the text will be searched
     * @param string                          $textId             The id of the text that will be searched in the file.
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     * @return string Return the text in the language or nothing if text id wasn't found.
     */
    public function searchLanguageText(array &$xmlLanguageObjects, $languageFolderPath, $language, $textId)
    {
        global $gLogger;

        // if not exists create a \SimpleXMLElement of the language file in the language path
        // and add it to the array of language objects
        if (!array_key_exists($languageFolderPath, $xmlLanguageObjects))
        {
            $languageFilePath = $languageFolderPath . '/' . $language . '.xml';
            if (!is_file($languageFilePath))
            {
                $gLogger->error('L10N: Language file does not exist!', array('languageFilePath' => $languageFilePath));

                throw new \UnexpectedValueException('Language file does not exist!');
            }

            $xmlLanguageObjects[$languageFolderPath] = new \SimpleXMLElement($languageFilePath, 0, true);
        }

        // text not in cache -> read from xml file in "Android Resource String" format
        $xmlNodes = $xmlLanguageObjects[$languageFolderPath]->xpath('/resources/string[@name="'.$textId.'"]');

        if ($xmlNodes === false || count($xmlNodes) === 0)
        {
            // fallback for old Admidio language format prior to version 3.1
            $xmlNodes = $xmlLanguageObjects[$languageFolderPath]->xpath('/language/version/text[@id="'.$textId.'"]');

            if ($xmlNodes === false || count($xmlNodes) === 0)
            {
                throw new \OutOfBoundsException('Could not found text-id!');
            }
        }

        $text = self::prepareXmlText($xmlNodes[0]);

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
    private function searchTextIdInLangObject(array $xmlLanguageObjects, $language, $textId)
    {
        global $gLogger;

        $languageFolderPaths = $this->languageData->getLanguageFolderPaths();
        foreach ($languageFolderPaths as $languageFolderPath)
        {
            try
            {
                return $this->searchLanguageText($xmlLanguageObjects, $languageFolderPath, $language, $textId);
            }
            catch (\OutOfBoundsException $exception)
            {
                // continue searching
                $gLogger->debug(
                    'L10N: ' . $exception->getMessage(),
                    array('languageFolderPath' => $languageFolderPath, 'language' => $language, 'textId' => $textId)
                );
            }
        }

        throw new \OutOfBoundsException('Could not found text-id!');
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
     * @param string            $text
     * @param array<int,string> $params
     * @return string
     */
    private static function prepareTextPlaceholders($text, array $params)
    {
        // replace placeholder with value of parameters
        foreach ($params as $index => $param)
        {
            $paramNr = $index + 1;

            $replaceArray = array(
                '#VAR' . $paramNr . '#'      => $param,
                '#VAR' . $paramNr . '_BOLD#' => '<strong>' . $param . '</strong>'
            );
            $text = str_replace(array_keys($replaceArray), array_values($replaceArray), $text);
        }

        // replace square brackets with html tags
        return strtr($text, '[]', '<>');
    }

    /**
     * Reads a text string out of a language xml file that is identified
     * with a unique text id e.g. SYS_COMMON. If the text contains placeholders
     * than you must set more parameters to replace them.
     * @param string            $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @param array<int,string> $params Optional parameter to replace placeholders in the text.
     *                                  $params[0] will replace @b #VAR1# or @b #VAR1_BOLD#,
     *                                  $params[1] will replace @b #VAR2# or @b #VAR2_BOLD# etc.
     * @return string Returns the text string with replaced placeholders of the text id.
     * @par Examples
     * @code
     * // display a text without placeholders
     * echo $gL10n->get('SYS_NUMBER');
     * // display a text with placeholders for individual content
     * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', array('John Doe', 'Demo-Organization', 'Administrator'));
     * @endcode
     */
    public function get($textId, $params = array())
    {
        global $gLogger;

        try
        {
            $text = $this->getTextFromTextId($textId);
        }
        catch (\RuntimeException $exception)
        {
            $gLogger->error('L10N: ' . $exception->getMessage(), array('textId' => $textId));

            // no text found then write #undefined text#
            return '#' . $textId . '#';
        }

        // unify different formats into one
        if (is_array($params))
        {
            $paramsArray = $params;
        }
        else
        {
            // TODO deprecated: Remove in Admidio 4.0
            $paramsArray = func_get_args();
            $txtId = '\'' . array_shift($paramsArray) . '\'';
            $paramsString = '\'' . implode('\', \'', $paramsArray) . '\'';

            $gLogger->warning(
                'DEPRECATED: "$gL10n->get(' . $txtId . ', ' . $paramsString . ')" is deprecated, use "$gL10n->get(' . $txtId . ', array(' . $paramsString . '))" instead!',
                array('textId' => $textId, 'params' => $params, 'allParams' => func_get_args())
            );
        }

        return self::prepareTextPlaceholders($text, $paramsArray);
    }

    /**
     * Adds a language data object to this class. The object contains all necessary
     * language data that is stored in the PHP session.
     * @deprecated 3.3.0:4.0.0 "$gL10n->addLanguageData($languageData)" is deprecated. Use "$gL10n = new Language($languageData)" instead.
     * @param LanguageData $languageDataObject An object of the class @b LanguageData.
     */
    public function addLanguageData(LanguageData $languageDataObject)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "$gL10n->addLanguageData($languageData)" is deprecated, use "$gL10n = new Language($languageData)" instead!');

        $this->languageData =& $languageDataObject;
    }

    /**
     * Adds a new path of language files to the array with all language paths where Admidio
     * should search for language files.
     * @deprecated 3.3.0:4.0.0 "$gL10n->addLanguagePath()" is deprecated, use "$gL10n->addLanguageFolderPath()" instead.
     * @param string $languageFolderPath Server path where Admidio should search for language files.
     * @return bool Returns true if language path is added.
     */
    public function addLanguagePath($languageFolderPath)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "$gL10n->addLanguagePath()" is deprecated, use "$gL10n->addLanguageFolderPath()" instead!');

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
     * Returns the name of the country in the language of this object. The country will be
     * identified by the ISO code (ISO 3166 ALPHA-3) e.g. 'DEU' or 'GBR' ...
     * @param string $countryIsoCode The three digits ISO code (ISO 3166 ALPHA-3) of the country where the name should be returned.
     * @deprecated 3.3.0:4.0.0 "$gL10n->getCountryByCode()" is deprecated, use "$gL10n->getCountryName()" instead.
     * @return string|false Return the name of the country in the language of this object.
     */
    public function getCountryByCode($countryIsoCode)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "$gL10n->getCountryByCode()" is deprecated, use "$gL10n->getCountryName()" instead!');

        try
        {
            return $this->getCountryName($countryIsoCode);
        }
        catch (\RuntimeException $exception)
        {
            return false;
        }
    }

    /**
     * Returns the three digits ISO code (ISO 3166 ALPHA-3) of the country. The country will be identified
     * by the name in the language of this object
     * @param string $countryName The name of the country in the language of this object.
     * @deprecated 3.3.0:4.0.0 "$gL10n->getCountryByName()" is deprecated, use "$gL10n->getCountryIsoCode()" instead.
     * @return string|false Return the three digits ISO code (ISO 3166 ALPHA-3) of the country or false if country not found.
     */
    public function getCountryByName($countryName)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "$gL10n->getCountryByName()" is deprecated, use "$gL10n->getCountryIsoCode()" instead!');

        try
        {
            return $this->getCountryIsoCode($countryName);
        }
        catch (\RuntimeException $exception)
        {
            return false;
        }
    }
}
