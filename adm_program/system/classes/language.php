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
 * SimpleXMLElement which search through xml files. An object of this class
 * can't be stored in a PHP session because it creates PHP core objects which
 * couldn't be stored in sessions. Therefore an object of @b LanguageData
 * should be assigned to this class that stored all necessary data and can be
 * stored in a session.
 * @par Examples
 * @code // show how to use this class with the language data class and sessions
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
 * $language->addLanguageData($session->getObject('languageData'));
 *
 * // read and display a language specific text with placeholders for individual content
 * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', 'John Doe', 'Demo-Organization', 'Administrator');@endcode
 */
class Language
{
    private $languageData;                  ///< An object of the class @b LanguageData that stores all necessary language data in a session
    private $languages = array();           ///< An Array with all available languages and their ISO codes
    private $xmlLanguageObjects = array();  ///< An array with all SimpleXMLElement object of the language from all paths that are set in @b $languageData.
    private $xmlReferenceLanguageObjects = array(); ///< An array with all SimpleXMLElement object of the reference language from all paths that are set in @b $languageData.

    /**
     * Adds a language data object to this class. The object contains all necessary
     * language data that is stored in the PHP session.
     * @param \LanguageData $languageDataObject An object of the class @b LanguageData.
     */
    public function addLanguageData(&$languageDataObject)
    {
        $this->languageData =& $languageDataObject;
    }

    /**
     * Adds a new path of language files to the array with all language paths where Admidio
     * should search for language files.
     * @param string $path Server path where Admidio should search for language files.
     */
    public function addLanguagePath($path)
    {
        $this->languageData->addLanguagePath($path);
    }

    /**
     * Reads a text string out of a language xml file that is identified with a unique text id e.g. SYS_COMMON.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the text string of the text id or empty string if not found.
     */
    protected function getTextFromTextId($textId)
    {
        // first read text from cache if it exists there
        if(array_key_exists($textId, $this->languageData->textCache))
        {
            return $this->languageData->textCache[$textId];
        }

        // search for text id in every SimpleXMLElement (language file) of the object array
        foreach($this->languageData->getLanguagePaths() as $languagePath)
        {
            $text = $this->searchLanguageText($this->xmlLanguageObjects, $languagePath, $this->languageData->getLanguage(), $textId);

            if($text !== '')
            {
                return $text;
            }
        }

        // if text id wasn't found than search for it in reference language
        // search for text id in every SimpleXMLElement (language file) of the object array
        foreach($this->languageData->getLanguagePaths() as $languagePath)
        {
            $text = $this->searchLanguageText($this->xmlReferenceLanguageObjects, $languagePath, $this->languageData->getLanguage(true), $textId);

            if($text !== '')
            {
                return $text;
            }
        }

        return '';
    }

    /**
     * Reads a text string out of a language xml file that is identified
     * with a unique text id e.g. SYS_COMMON. If the text contains placeholders
     * than you must set more parameters to replace them.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     *
     * param  string $param1,$param2... The function accepts an undefined number of values which will be used
     *                                  to replace the placeholder in the text.
     *                                  $param1 will replace @b #VAR1# or @b #VAR1_BOLD#,
     *                                  $param2 will replace @b #VAR2# or @b #VAR2_BOLD# etc.
     * @return string Returns the text string with replaced placeholders of the text id.
     * @par Examples
     * @code // display a text without placeholders
     *                echo $gL10n->get('SYS_NUMBER');
     *
     * // display a text with placeholders for individual content
     * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', 'John Doe', 'Demo-Organization', 'Administrator');
     * @endcode
     */
    public function get($textId)
    {
        if(!$this->languageData instanceof \LanguageData)
        {
            return 'Error: '.$this->languageData.' is not an object!';
        }

        $text = $this->getTextFromTextId($textId);

        // no text found then write #undefined text#
        if($text === '')
        {
            return '#'.$textId.'#';
        }

        // replace placeholder with value of parameters
        $paramCount = func_num_args();
        $paramArray = func_get_args();

        for($paramNumber = 1; $paramNumber < $paramCount; ++$paramNumber)
        {
            $replaceArray = array(
                '#VAR'.$paramNumber.'#'      => $paramArray[$paramNumber],
                '#VAR'.$paramNumber.'_BOLD#' => '<strong>'.$paramArray[$paramNumber].'</strong>'
            );
            $text = str_replace(array_keys($replaceArray), array_values($replaceArray), $text);
        }

        // replace square brackets with html tags
        $text = strtr($text, '[]', '<>');

        return $text;
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @return string[] Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountries()
    {
        $countries = $this->languageData->getCountriesArray();

        if(count($countries) > 0)
        {
            return $countries;
        }

        // set path to language file of countries
        $countriesFilesPath = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries_';

        if(is_file($countriesFilesPath.$this->languageData->getLanguage().'.xml'))
        {
            $file = $countriesFilesPath.$this->languageData->getLanguage().'.xml';
        }
        elseif(is_file($countriesFilesPath.$this->languageData->getLanguage(true).'.xml'))
        {
            $file = $countriesFilesPath.$this->languageData->getLanguage(true).'.xml';
        }
        else
        {
            return array();
        }

        // read all countries from xml file
        $countriesXml = new SimpleXMLElement($file, null, true);

        foreach($countriesXml->children() as $stringNode)
        {
            $attributes = $stringNode->attributes();
            $countries[(string) $attributes->name] = (string) $stringNode;
        }

        asort($countries, SORT_LOCALE_STRING);
        $this->languageData->setCountriesArray($countries);

        return $this->languageData->getCountriesArray();
    }

    /**
     * Returns the name of the country in the language of this object. The country will be
     * identified by the ISO code e.g. 'DEU' or 'GBR' ...
     * @param string $isoCode The three digits ISO code of the country where the name should be returned.
     * @return string Return the name of the country in the language of this object.
     */
    public function getCountryByCode($isoCode)
    {
        if($isoCode === '')
        {
            return '';
        }

        $countries = $this->languageData->getCountriesArray();

        if(count($countries) === 0)
        {
            $countries = $this->getCountries();
        }
        return $countries[$isoCode];
    }

    /**
     * Returns the three digits ISO code of the country. The country will be identified
     * by the name in the language of this object
     * @param string $country The name of the country in the language of this object.
     * @return string|false Return the three digits ISO code of the country or false if country not found.
     */
    public function getCountryByName($country)
    {
        $countries = $this->languageData->getCountriesArray();

        if(count($countries) === 0)
        {
            $countries = $this->getCountries();
        }
        return array_search($country, $countries, true);
    }

    /**
     * Returns the ISO code of the language of this object.
     * @param bool $referenceLanguage If set to @b true than the ISO code of the reference language will returned.
     * @return string Returns the ISO code of the language of this object or the reference language e.g. @b de or @b en.
     */
    public function getLanguageIsoCode($referenceLanguage = false)
    {
        $language = $this->languageData->getLanguage($referenceLanguage);

        if($language === 'de_sie')
        {
            return 'de';
        }

        return $language;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to @b true than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage($referenceLanguage = false)
    {
        return $this->languageData->getLanguage($referenceLanguage);
    }

    /**
     * Creates an array with all languages that are possible in Admidio.
     * The array will have the following syntax e.g.: array('DE' => 'deutsch' ...)
     * @return string[] Return an array with all available languages.
     */
    public function getAvailableLanguages()
    {
        if(count($this->languages) === 0)
        {
            $languagesXml = new SimpleXMLElement(ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml', null, true);

            foreach($languagesXml->children() as $stringNode)
            {
                $attributes = $stringNode->children();
                $this->languages[(string) $attributes->isocode] = (string) $attributes->name;
            }
        }

        return $this->languages;
    }

    /**
     * Search for text id in a language xml file and return the text. If no text was found than nothing is returned.
     * @param SimpleXMLElement[] $objectArray  The reference to an array where every SimpleXMLElement of each language path is stored
     * @param string             $languagePath The path in which the different language xml files are.
     * @param string             $language     The ISO code of the language in which the text will be searched
     * @param string             $textId       The id of the text that will be searched in the file.
     * @return string Return the text in the language or nothing if text id wasn't found.
     */
    public function searchLanguageText(array &$objectArray, $languagePath, $language, $textId)
    {
        // if not exists create a SimpleXMLElement of the language file in the language path
        // and add it to the array of language objects
        if(!array_key_exists($languagePath, $objectArray))
        {
            $languageFile = $languagePath.'/'.$language.'.xml';

            if(is_file($languageFile))
            {
                $objectArray[$languagePath] = new SimpleXMLElement($languageFile, null, true);
            }
        }

        if($objectArray[$languagePath] instanceof \SimpleXMLElement)
        {
            // text not in cache -> read from xml file in "Android Resource String" format
            $node = $objectArray[$languagePath]->xpath('/resources/string[@name="'.$textId.'"]');

            if($node == false)
            {
                // fallback for old Admidio language format prior to version 3.1
                $node = $objectArray[$languagePath]->xpath('/language/version/text[@id="'.$textId.'"]');
            }

            if($node != false)
            {
                // set line break with html
                // Within Android string resource all apostrophe are escaped so we must remove the escape char
                // replace highly comma, so there are no problems in the code later
                $text = str_replace(array('\\n', '\\\'', '\''), array('<br />', '\'', '&rsquo;'), $node[0]);
                $this->languageData->textCache[$textId] = $text;

                return $text;
            }
        }

        return '';
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     */
    public function setLanguage($language)
    {
        if($language !== $this->languageData->getLanguage())
        {
            // initialize data
            $this->xmlLanguageObjects = array();
            $this->xmlReferenceLanguageObjects = array();

            $this->languageData->setLanguage($language);
        }
    }
}
