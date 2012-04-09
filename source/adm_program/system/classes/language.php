<?php
/******************************************************************************
 * Read text out of a xml language file
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse liest die XML-Datei der jeweils eingestellten Sprache als 
 * SimpleXMLElement ein und bietet Zugriffsmethoden um auf einfache Weise
 * zu bestimmten IDs die sprachabhaengigen Texte auszugeben.
 *
 * The following functions are available:
 *
 * addLanguagePath($path)
 *         - gibt einen weiteren Ordner an, der Sprachdateien enthaelt
 *         - aus diesem Ordner wird versucht die eingestellte Default-Sprachdatei zu oeffnen
 * get($text_id, $var1='', $var2='', $var3='', $var4='')
 *         - liest den Text mit der uebergebenen ID aus und gibt diese zurueck
 *         - zuerst wird die Default-Sprachdatei ueberprueft, danach alle weiteren
 *         - angehaengten Sprachordner, dann die Referenzsprache
 * getCountries() 
 *         - liefert ein Array mit allen Laendern und Laendercodes zurueck
 *         - Beispiel: array('DEU' => 'Deutschland' ...)
 * getCountryByCode($isoCode)
 *         - Land sprachabhaengig nach Uebergabe des ISO-Codes ausgeben
 * getCountryByName($country)
 *         - Land sprachabhaengig nach Uebergabe des ISO-Codes ausgeben
 * getLanguages()
 *         - liefert ein Array mit allen Sprachen und Sprachcodes zurueck, 
 *         - fuer die Admidio-Uebersetzungen zur Verfuegung stehen
 *         - Beispiel: array('DE' => 'deutsch' ...)
 * getReferenceText($text_id, $var1='', $var2='', $var3='', $var4='')
 *         - liefert den Text der ID aus der eingestellten Referenzsprache zurueck
 * setLanguage($language)
 *         - es wird die Sprache gesetzt und damit auch die entsprechende Sprachdatei eingelesen
 *
 *****************************************************************************/

class Language
{
    private $l10nObject;					// das XML-Objekt dieser Klasse
    private $referenceL10nObject;			// XML-Objekt der Referenzsprache
	private $otherL10nObjects = array();	// Array ueber alle zusaetzlichen Sprachpfade der Plugins
	private $defaultL10nObject = false;		// dies ist das Sprachobjekt zur Standard-Sprachdatei von Admidio
    
    private $language;						// Sprache dieser Klasse
    private $languageFilePath;				// Pfad der Sprachdatei aus dieser Klasse
    private $referenceLanguage = 'en';		// die Referenzsprache, aus der Texte zurueckgegeben werden, wenn sie in der Defaultsprache nicht vorhanden sind
    
    private $textCache; 					// eingelesene Texte werden in diesem Array gespeichert und spaeter nur noch aus dem Array gelesen
	
	private $countries = array();			// Array mit allen Laendern und deren Laendercodes
	private $languages = array();			// Array mit allen Sprachen und deren Sprachcodes
    
    // es muss das Sprachkuerzel uebergeben werden (Beispiel: 'de')
	// optional kann noch ein Pfad angegeben werden, in dem sich weitere Sprachdateien befinden, 
	// auf die zugegriffen werden soll (per Default wird Admidio-Standard-Sprachdatei eingelesen)
    public function __construct($language, $path = '')
    {
		if(strlen($path) == 0)
		{
			$this->languageFilePath = SERVER_PATH. '/adm_program/languages';
			$this->defaultL10nObject = true;
		}
		else
		{
			$this->languageFilePath = $path;
			$this->defaultL10nObject = false;
		}
        $this->setLanguage($language);
        $this->textCache = array();
    }

	// gibt einen weiteren Ordner an, der Sprachdateien enthaelt
	// aus diesem Ordner wird versucht die eingestellte Default-Sprachdatei zu oeffnen
	public function addLanguagePath($path)
	{
		$this->otherL10nObjects[] = new Language($this->language, $path);
	}
	
    // liest den Text mit der uebergebenen ID aus und gibt diese zurueck
	// zuerst wird die Default-Sprachdatei ueberprueft, danach alle weiteren
	// angehaengten Sprachordner, dann die Referenzsprache
    public function get($text_id, $var1='', $var2='', $var3='', $var4='')
    {
        $text   = '';
        if(isset($this->textCache[$text_id]))
        {
            // Text aus dem Cache auslesen
            $text = $this->textCache[$text_id];
        }
        else
        {
			if(is_object($this->l10nObject))
			{
				// Text nicht im Cache -> aus XML-Datei einlesen
				$node   = $this->l10nObject->xpath("/language/version/text[@id='".$text_id."']");
				if($node != false)
				{
					// Zeilenumbrueche in HTML setzen
					$text = str_replace('\n', '<br />', $node[0]);
					// Hochkomma muessen ersetzt werden, damit es im Code spaeter keine Probleme gibt
					$text = str_replace('\'', '&rsquo;', $text);
					$this->textCache[$text_id] = $text;
				}
				else
				{
					// in der ersten Sprachdatei wurde nichts gefunden, dann weitere Sprachdateien durchgehen
					foreach($this->otherL10nObjects as $key => $object)
					{
						if(strlen($text) == 0)
						{
							$text = $object->get($text_id, $var1, $var2, $var3, $var4);
						}
					}
				}
			}
        }

        if(strlen($text) > 0)
        {
            // replace placeholder for parameters
            if(strlen($var1) > 0)
            {
                $text = str_replace('%VAR1%', $var1, $text);
                $text = str_replace('%VAR1_BOLD%', '<strong>'.$var1.'</strong>', $text);
                
                if(strlen($var2) > 0)
                {
                    $text = str_replace('%VAR2%', $var2, $text);
                    $text = str_replace('%VAR2_BOLD%', '<strong>'.$var2.'</strong>', $text);

                    if(strlen($var3) > 0)
                    {
                        $text = str_replace('%VAR3%', $var3, $text);
                        $text = str_replace('%VAR3_BOLD%', '<strong>'.$var3.'</strong>', $text);
                        
                        if(strlen($var4) > 0)
                        {
                            $text = str_replace('%VAR4%', $var4, $text);
                            $text = str_replace('%VAR4_BOLD%', '<strong>'.$var4.'</strong>', $text);
                        }
                    }
                }
            }
            
            // replace square brackets with html tags
            $text = strtr($text, '[]', '<>');
        }
        // no text found then search in reference language for a string
        elseif($this->referenceLanguage != $this->language)
        {
            $text = $this->getReferenceText($text_id, $var1, $var2, $var3, $var4);
        }
					
		// no text found then write #undefined text#
		if($this->defaultL10nObject == true && strlen($text) == 0)
		{
			$text = '#'.$text_id.'#';
		}

        return $text;
    }

	// liefert ein Array mit allen Laendern und Laendercodes zurueck
	// Beispiel: array('DEU' => 'Deutschland' ...)
	public function getCountries()
	{
		if(count($this->countries) == 0)
		{
			if(file_exists(SERVER_PATH.'/adm_program/languages/countries_'.$this->language.'.xml'))
			{
				$file = SERVER_PATH.'/adm_program/languages/countries_'.$this->language.'.xml';
			}
			elseif(file_exists(SERVER_PATH.'/adm_program/languages/countries_'.$this->referenceLanguage.'.xml'))
			{
				$file = SERVER_PATH.'/adm_program/languages/countries_'.$this->referenceLanguage.'.xml';
			}
			else
			{
				return array();
			}

			$data = implode('', file($file));
			$p = xml_parser_create();
			xml_parse_into_struct($p, $data, $vals, $index);
			xml_parser_free($p);

			for($i = 0; $i < count($index['ISOCODE']); $i++)
			{
				$this->countries[$vals[$index['ISOCODE'][$i]]['value']] = $vals[$index['NAME'][$i]]['value'];
			}
		}
		return $this->countries;
	}
	
	// Land sprachabhaengig nach Uebergabe des ISO-Codes ausgeben
	public function getCountryByCode($isoCode)
	{
		if(count($this->countries) == 0)
		{
			$this->getCountries();
		}
		return $this->countries[$isoCode];
	}
	
	// Land sprachabhaengig nach Uebergabe des ISO-Codes ausgeben
	public function getCountryByName($country)
	{
		if(count($this->countries) == 0)
		{
			$this->getCountries();
		}
		return array_search($country, $this->countries);
	}

	// liefert ein Array mit allen Sprachen und Sprachcodes zurueck, 
	// fuer die Admidio-Uebersetzungen zur Verfuegung stehen
	// Beispiel: array('DE' => 'deutsch' ...)
	public function getLanguages()
	{
		if(count($this->languages) == 0)
		{
			$data = implode('', file(SERVER_PATH.'/adm_program/languages/languages.xml'));
			$p = xml_parser_create();
			xml_parse_into_struct($p, $data, $vals, $index);
			xml_parser_free($p);

			for($i = 0; $i < count($index['ISOCODE']); $i++)
			{
				$this->languages[$vals[$index['ISOCODE'][$i]]['value']] = $vals[$index['NAME'][$i]]['value'];
			}
		}
		return $this->languages;
	}

    // liefert den Text der ID aus der eingestellten Referenzsprache zurueck
    public function getReferenceText($text_id, $var1='', $var2='', $var3='', $var4='')
    {
        if(is_object($this->referenceL10nObject) == false)
        {
            $this->referenceL10nObject = new Language($this->referenceLanguage, $this->languageFilePath);
        }
        return $this->referenceL10nObject->get($text_id, $var1, $var2, $var3, $var4);
    }

    // es wird die Sprache gesetzt und damit auch die entsprechende Sprachdatei eingelesen
    public function setLanguage($language)
    {
        if($language != $this->language)
        {
            $this->language = $language;
            $languageFile = $this->languageFilePath.'/'.$language.'.xml';

			if(file_exists($languageFile))
			{
				$this->l10nObject = new SimpleXMLElement($languageFile, 0, true);
			}
        }
    }
}
?>