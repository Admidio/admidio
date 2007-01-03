<?php
/******************************************************************************
 * Script mit HTML-Code fuer ein Feld der Eigenen-Liste-Konfiguration
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * query : hier steht der Suchstring drin
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

// Zur Vereinfachung nur statische Werte
$options= array(
            'Hund', 'Katze', 'Maus',
            'Auto', 'Haus', 'Sommer',
            'Winter','Sonne', 'Fahrrad',
            'Kind','Familie', 'Spass', 'Urlaub',
            'Spielzeug','Computer','Internet',
            'PHP','JavaScript','Mozilla','XML'
           );


$xml='<?xml version="1.0" encoding="iso-8859-1" ?>';

if (!$_GET['query'])
{
    // keine Daten - kein Query
    $xml .= '<empty />';
}
else
{
    // Passende Einträge finden
    $match=array();
    foreach ($options as $opt)
    {
        $q=strtolower($_GET['query']);
        if (strpos(strtolower($opt),$q)===0)
        {
            $match[]="<li>$opt</li>";
        }
    }
    sort($match);
    $xml .= "<suggestions xmlns='http://www.w3.org/1999/xhtml'><ul>\n".join("\n",$match)."</ul></suggestions>";
}

header('Content-Type: text/xml');
echo $xml;

?>