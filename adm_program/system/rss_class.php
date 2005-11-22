<?php
/******************************************************************************
 * RSS - Klasse
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Diese Klasse erzeugt ein RSSfeed-Objekt nach RSS 2.0.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * function RSSfeed($homepage, $title, $description)
 * Uebergaben: 	$homepage		-	Link zur Homepage
 * 				$title			-	Titel des RSS-Feeds
 * 				$description	-	Ergaenzende Beschreibung zum Titel
 *
 * Dem RSSfeed koennen ueber die Funktion addItem Inhalt zugeordnet werden:
 * function addItem($title, $description, $date, $guid)
 * Uebergaben:	$title			-	Titel des Items
 * 				$description	-	der Inhalt des Items
 * 				$date			-	Das Erstellungsdatum des Items
 * 				$link			-	Ein Link zum Termin/Newsbeitrag etc.
 *
 * Wenn alle benoetigten Items zugeordnet sind, wird der RSSfeed generiert mit:
 * function build_feed()
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *
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


// RSS-Klasse
class RSSfeed {

//Konstruktor
function RSSfeed($homepage, $title, $description) {
       $this->channel=array();
       $this->channel["title"]=$title;
       $this->channel["link"]=$homepage;
       $this->channel["description"]=$description;
       $this->items=array();
       $this->feed="http://" . $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
}

function add_Item($title, $description, $date, $link) {
       $item=array("title" => $title, "description" => $description,
               "pubDate" => $date, "link" => $link);
       $this->items[]=$item;
}

function build_feed() {
       $this->rss_header();
       $this->open_channel();
       $this->add_channel_infos();
       $this->build_items();
       $this->close_channel();
       $this->rss_footer();
}

function rss_header()
{
       header("Content-type: application/xml");
       echo '<?xml version="1.0" encoding="iso-8859-1"?>'. chr(10). '<rss version="2.0">'. chr(10);
}

function open_channel()
{
       echo '<channel>'. chr(10);
}


function add_channel_infos()
{
       foreach (array("title", "link", "description") as $field) {
               if (isset($this->channel[$field])) {
                       echo "<${field}>" . htmlspecialchars($this->channel[$field]). "</${field}>\n";
               }
       }
       echo "<language>de</language>\n";
       echo "<generator>Admidio RSS-Class</generator>\n\n";
       echo "<pubDate>". date(r). "</pubDate>\n\n";
}


function build_items() {
       foreach ($this->items as $item) {
               echo "<item>\n";
               foreach (array("title", "description", "link", "pubDate") as $field) {
                       if (isset($item[$field])) {
                               echo "<${field}>" . htmlspecialchars($item[$field]). "</${field}>\n";
                       }
               }
               echo "<guid>" .  $item["link"] . "</guid>\n";
               echo "<source url=\"$this->feed\">". htmlspecialchars($this->channel[title]). "</source>";
               echo "</item>\n\n";
       }
}

function close_channel()
{
       echo '</channel>'. chr(10);
}

function rss_footer()
{
       echo '</rss>'. chr(10);
}

}

?>