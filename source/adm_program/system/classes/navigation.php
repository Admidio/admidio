<?php 
/******************************************************************************
 * Klasse fuer Zuruecknavigation in den einzelnen Modulen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Ueber diese Klasse kann die Navigation innerhalb eines Modules besser
 * verwaltet werden. Ein Objekt dieser Klasse wird in common.php angelegt
 * und als Session-Variable $_SESSION['navigation'] weiter verwendet.
 *
 * Beim Aufruf der Basisseite eines Moduls muss die Funktion
 * $_SESSION['navigation']->clear() aufgerufen werden, um alle vorherigen Eintraege
 * zu loeschen.
 *
 * Nun muss auf allen Seiten innerhalb des Moduls die Funktion
 * $_SESSION['navigation']->addUrl(CURRENT_URL) aufgerufen werde
 *
 * Will man nun an einer Stelle zuruecksurfen, so muss die Funktion
 * $_SESSION['navigation']->getUrl() aufgerufen werden
 *
 * Mit $_SESSION['navigation']->deleteLastUrl() kann man die letzte eingetragene
 * Url aus dem Stack loeschen
 *
 *****************************************************************************/

class Navigation
{
    private $url_arr = array();
    private $count;

    public function __construct()
    {
        $this->count = 0;
    }

    // fuegt eine Seite zum Navigationsstack hinzu
    public function addUrl($url)
    {
        // Url nur hinzufuegen, wenn sie nicht schon als letzte im Array steht
        if($this->count == 0 || $url != $this->url_arr[$this->count-1])
        {
            $this->url_arr[$this->count] = $url;
            $this->count++;
        }
    }

    // entfernt alle Urls aus dem Array
    public function clear()
    {
        for($i = 0; $i < $this->count; $i++)
        {
            unset($this->url_arr[$i]);
        }
        $this->count = 0;
    }
    
    // Anzahl der URLs aus dem Stack zurueckgeben
    public function count()
    {
        return $this->count;
    }

    // Funktion entfernt die letzte Url aus dem Array
    public function deleteLastUrl()
    {
        if($this->count > 0)
        {
            $this->count--;
            unset($this->url_arr[$this->count]);
        }
    }

    // gibt die vorletzte Url aus dem Stack zurueck
    public function getPreviousUrl()
    {
        if($this->count > 1)
        {
            $url_count = $this->count - 2;
        }
        else
        {
            // es gibt nur eine Url, dann diese nehmen
            $url_count = 0;
        }
        return $this->url_arr[$url_count];
    }

    // gibt die letzte Url aus dem Stack zurueck
    public function getUrl()
    {
        if($this->count > 0)
        {
            return $this->url_arr[$this->count-1];
        }
        else
        {
            return null;
        }
        
    }
}
?>