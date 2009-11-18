<?php
/******************************************************************************
 * Klasse zum Einlesen der sprachabhaengigen Texte
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung.
 *
 *****************************************************************************/

class Language extends SimpleXMLElement
{
    private $modules = array('SYS' => 'system', 'ANN' => 'announcement');

    // liest den Text mit der uebergebenen ID aus und gibt diese zurueck
    public function get($text_id, $var1='', $var2='')
    {
        $module = $this->modules[substr($text_id, 0, 3)];
        $node = $this->xpath("/language/".$module."/text[@id='".$text_id."']");
        $text = $node[0];
        
        if(strlen($var1) > 0)
        {
            $text = str_replace('%VAR1%', $var1, $text);
        }
        if(strlen($var2) > 0)
        {
            $text = str_replace('%VAR2%', $var2, $text);
        }       
        return $text;
    }
}
?>