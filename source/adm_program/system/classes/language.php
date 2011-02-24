<?php
/******************************************************************************
 * Klasse zum Einlesen der sprachabhaengigen Texte
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse liest die XML-Datei der jeweils eingestellten Sprache als 
 * SimpleXMLElement ein und bietet Zugriffsmethoden um auf einfache Weise
 * zu bestimmten IDs die sprachabhaengigen Texte auszugeben.
 *
 * Folgende Funktionen stehen zur Verfuegung:
 *
 * get($text_id, $var1='', $var2='', $var3='', $var4='')
 *         - liest den Text mit der uebergebenen ID aus und gibt diese zurueck
 * getReferenceText($text_id, $var1='', $var2='', $var3='', $var4='')
 *         - liefert den Text der ID aus der eingestellten Referenzsprache zurueck
 * setLanguage($language)
 *         - es wird die Sprache gesetzt und damit auch die entsprechende Sprachdatei eingelesen
 *
 *****************************************************************************/

class Language
{
    private $l10nObject;
    private $referenceL10nObject;
    
    private $language;
    private $languageFilePath;
    private $referenceLanguage = 'de';
    
    private $textCache; // eingelesene Texte werden in diesem Array gespeichert und spaeter nur noch aus dem Array gelesen
    
    // es muss das Sprachkuerzel uebergeben werden (Beispiel: 'de')
    public function __construct($language)
    {
        $this->setLanguage($language);
        $this->textCache = array();
    }

    // liest den Text mit der uebergebenen ID aus und gibt diese zurueck
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
        }

        if(strlen($text) > 0)
        {
            // Variablenplatzhalter ersetzen
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
        }
        elseif($this->referenceLanguage != $this->language)
        {
            $text = $this->getReferenceText($text_id, $var1, $var2, $var3, $var4);
        }
        // Hochkomma muessen ersetzt werden, damit es im Code spaeter keine Probleme gibt
        return $text;
    }

    // liefert den Text der ID aus der eingestellten Referenzsprache zurueck
    public function getReferenceText($text_id, $var1='', $var2='', $var3='', $var4='')
    {
        if(is_object($this->referenceL10nObject) == false)
        {
            $this->referenceL10nObject = new Language($this->referenceLanguage);
        }
        return $this->referenceL10nObject->get($text_id, $var1, $var2, $var3, $var4);
    }

    // es wird die Sprache gesetzt und damit auch die entsprechende Sprachdatei eingelesen
    public function setLanguage($language)
    {
        if($language != $this->language)
        {
            $this->language = $language;
            $this->languageFilePath = SERVER_PATH. '/adm_program/languages/'.$language.'.xml';
            $this->l10nObject = new SimpleXMLElement($this->languageFilePath, 0, true);
        }
    }
}
?>