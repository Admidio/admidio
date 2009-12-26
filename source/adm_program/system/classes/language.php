<?php
/******************************************************************************
 * Klasse zum Einlesen der sprachabhaengigen Texte
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse liest die XML-Datei der jeweils eingestellten Sprache als 
 * SimpleXMLElement ein und bietet Zugriffsmethoden um auf einfache Weise
 * zu bestimmten IDs die sprachabhaengigen Texte auszugeben.
 *
 *****************************************************************************/

class Language
{
    private $l10nObject;
    private $referenceL10nObject;
    private $modules;
    
    // die Uebergaben sind identisch zur PHP-SimpleXML-Klasse -> siehe Doku dort
    public function __construct($data, $options, $data_is_url)
    {
        $this->l10nObject = new SimpleXMLElement($data, $options, $data_is_url);
        $this->modules = array('ANN' => 'announcements', 
                               'ASS' => 'assign', 
                               'CAT' => 'category', 
                               'DAT' => 'dates',
                               'DOW' => 'downloads',
                               'ECA' => 'ecards', 
                               'GBO' => 'guestbook',
                               'LST' => 'lists',
                               'MAI' => 'mail', 
                               'MEM' => 'members', 
                               'NWU' => 'new_user',
                               'ORG' => 'organizsation', 
                               'PHO' => 'photos',
                               'PRO' => 'profile', 
                               'ROL' => 'roles',
                               'SYS' => 'system');
    }

    // liest den Text mit der uebergebenen ID aus und gibt diese zurueck
    public function get($text_id, $var1='', $var2='', $var3='', $var4='')
    {
        $text = '';
        $module = $this->modules[substr($text_id, 0, 3)];
        $node = $this->l10nObject->xpath("/language/".$module."/text[@id='".$text_id."']");

        if($node != false)
        {
            $text = $node[0];

            // Zeilenumbrueche in HTML setzen
            $text = str_replace('\n', '<br />', $text);

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
        else
        {
            $text = $this->getReferenceText($text_id, $var1, $var2, $var3, $var4);
        }
        return $text;
    }

    // liefert den Text der ID aus der eingestellten Referenzsprache zurueck
    public function getReferenceText($text_id, $var1='', $var2='', $var3='', $var4='')
    {
        if(is_object($this->referenceL10nObject) == false)
        {
            $language_path = SERVER_PATH. '/adm_program/languages/de.xml';
            $this->referenceL10nObject = new Language($language_path, 0, true);
        }
        return $this->referenceL10nObject->get($text_id, $var1, $var2, $var3);
    }
}
?>