<?php 
/******************************************************************************
 * Klasse fuer Zuruecknavigation in den einzelnen Modulen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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
 * $_SESSION['navigation']->addUrl($g_current_url) aufgerufen werde
 *
 * Will man nun an einer Stelle zuruecksurfen, so muss die Funktion
 * $_SESSION['navigation']->getUrl() aufgerufen werden
 *
 * Mit $_SESSION['navigation']->deleteLastUrl() kann man die letzte eingetragene
 * Url aus dem Stack loeschen
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

class Navigation
{
    var $url_arr = array();
    var $count;

    function Navigation()
    {
        $this->count = 0;
    }

    // entfernt alle Urls aus dem Array
    function clear()
    {
        for($i = 0; $i < $this->count; $i++)
        {
            unset($this->url_arr[$i]);
        }
        $this->count = 0;
    }

    // Funktion entfernt die letzte Url aus dem Array
    function deleteLastUrl()
    {
        $this->count--;
        unset($this->url_arr[$this->count]);
    }

    // fuegt eine Seite zum Navigationsstack hinzu
    function addUrl($url)
    {
        // Url nur hinzufuegen, wenn sie nicht schon als letzte im Array steht
        if($this->count == 0 || $url != $this->url_arr[$this->count-1])
        {
            $this->url_arr[$this->count] = $url;
            $this->count++;
        }
    }

    // gibt die vorletzte Url aus dem Stack zurueck
    function getPreviousUrl()
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
    function getUrl()
    {
        if($this->count == 0)
        {
            return null;
        }
        return $this->url_arr[$this->count-1];
    }
}
?>