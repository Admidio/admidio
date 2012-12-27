<?php
/*****************************************************************************/
/** @class LanguageData
 *  @brief Stores language data in a class object
 *
 *  This class stores data of the Language object. These are the paths to all
 *  relevant language files, the configured language and the default language.
 *  This object is designed to be stored in a PHP session. The Language 
 *  object itself couldn't be stored in a Session because it uses PHP objects
 *  which couldn't stored in a PHP session.
 *  @par Examples
 *  @code // show how to use this class with the language class and sessions
 *  script_a.php
 *  // create a language data object and assign it to the language object
 *  $language = new Language();
 *  $languageData = new LanguageData('de');
 *  $language->addLanguageData($languageData);
 *  $session->addObject('languageData', $languageData);
 *  
 *  script_b.php
 *  // read language data from session and add it to language object
 *  $language = new Language();
 *  $language->addLanguageData($session->getObject('languageData'));@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class LanguageData
{
    public $textCache = array();		///< Stores all read text data in an array to get quick access if a text is required several times
	
	private $languageFilePath = array();///< Array with all relevant language files
    private $language;					///< The ISO code of the language that should be read in this object
    private $referenceLanguage = 'en';	///< The ISO code of the default language that should be read if in the current language the text id is not translated
	private $countries = array();		///< Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
	
	/** Creates an object that stores all neccessary language data
	 *  and can be handled in session. Therefore the language must be set
	 *  and optional a path where the language files are stored.
	 *  @param $language     The ISO code of the language for which the texts should be read e.g. @b 'de'
	 *  @param $languagePath Optional a server path to the language files. If no path is set 
	 *                       than the default Admidio language path @b adm_program/languages will be set.
	 */
    public function __construct($language, $languagePath = '')
    {
		if(strlen($languagePath) == 0)
		{
			$this->addLanguagePath(SERVER_PATH. '/adm_program/languages');
		}
		else
		{
			$this->addLanguagePath($languagePath);
		}
        $this->setLanguage($language);
        $this->textCache = array();
    }

	/** Adds a new path of language files to the array with all language paths where Admidio 
	 *  should search for language files.
	 *  @param $path Server path where Admidio should search for language files.
	 */
	public function addLanguagePath($path)
	{
		if(array_key_exists($path, $this->languageFilePath) == false && strlen($path) > 0)
		{
			$this->languageFilePath[] = $path;
		}
	}
	
	/** Returns an array with all countries and their ISO codes
	 *  @return Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
	 */
	public function getCountriesArray()
	{
		return $this->countries;
	}
	
	/** Returns the ISO code of the language of this object. 
	 *  @param $referenceLanguage If set to @b true than the ISO code of the reference language will returned.
	 *  @return Returns the ISO code of the language of this object or the reference language.
	 */
    public function getLanguage($referenceLanguage = false)
    {
		if($referenceLanguage)
		{
			return $this->referenceLanguage;
		}
		else
		{
			return $this->language;
		}
    }
	
	/** Returns an array with all language paths that were set.
	 *  @return array with all language paths that were set.
	 */
	public function getLanguagePaths()
	{
		return $this->languageFilePath;
	}
	
	/** Save the array with all countries and their ISO codes in an internal parameter for later use
	 *  @param $countries Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
	 */
	public function setCountriesArray($countries)
	{
		if(is_array($countries))
		{
			$this->countries = $countries;
		}
	}

	/** Set a language to this object. If there was a language before than initialize the cache
	 *  @param $language ISO code of the language that should be set to this object.
	 */
    public function setLanguage($language)
    {
        if($language != $this->language)
        {
			// initialize all parameters
			$this->textCache = array();
			
            $this->language = $language;
        }
    }
}
?>