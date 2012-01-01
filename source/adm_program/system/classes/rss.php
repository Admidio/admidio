<?php
/******************************************************************************
 * RSS - Klasse
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse erzeugt ein RSSfeed-Objekt nach RSS 2.0.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * function RSSfeed($homepage, $title, $description)
 * Parameters:  $homepage       - Link zur Homepage
 *              $title          - Titel des RSS-Feeds
 *              $description    - Ergaenzende Beschreibung zum Titel
 *
 * Dem RSSfeed koennen ueber die Funktion addItem Inhalt zugeordnet werden:
 * function addItem($title, $description, $date, $guid)
 * Parameters:  $title          - Titel des Items
 *              $description    - der Inhalt des Items
 *              $date           - Das Erstellungsdatum des Items
 *              $link           - Ein Link zum Termin/Newsbeitrag etc.
 *
 * Wenn alle benoetigten Items zugeordnet sind, wird der RSSfeed generiert mit:
 * function buildFeed()
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/


// RSS-Klasse
class RSSfeed
{

//Konstruktor
public function __construct($homepage, $title, $description)
{
    $this->channel = array();
    $this->channel['title'] = $title;
    $this->channel['link']  = $homepage;
    $this->channel['description'] = $description;
    $this->items=array();
    $this->feed='http://'. $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
}

public function addItem($title, $description, $date, $link)
{
    $item=array('title' => $title, 'description' => $description, 'pubDate' => $date, 'link' => $link);
    $this->items[]=$item;
}

public function buildFeed()
{
    $this->rssHeader();
    $this->openChannel();
    $this->addChannelInfos();
    $this->buildItems();
    $this->closeChannel();
    $this->rssFooter();
}

public function rssHeader()
{
    header('Content-type: application/xml');
    echo '<?xml version="1.0" encoding="utf-8"?>'. chr(10). '<rss version="2.0">'. chr(10);
}

public function openChannel()
{
    echo '<channel>'. chr(10);
}


public function addChannelInfos()
{
	global $gPreferences;

    foreach (array('title', 'link', 'description') as $field)
    {
        if (isset($this->channel[$field]))
        {
            echo "<${field}>". htmlspecialchars($this->channel[$field], ENT_QUOTES). "</${field}>\n";
        }
    }
    echo "<language>".$gPreferences['system_language']."</language>\n";
    echo "<generator>Admidio RSS-Class</generator>\n\n";
    echo "<pubDate>". date('r'). "</pubDate>\n\n";
}


public function buildItems()
{
    foreach ($this->items as $item)
    {
        echo "<item>\n";
        foreach (array('title', 'description', 'link', 'pubDate') as $field)
        {
            if (isset($item[$field]))
            {
                echo "<${field}>". htmlspecialchars($item[$field], ENT_QUOTES). "</${field}>\n";
            }
        }
        echo "<guid>". str_replace('&', '&amp;', $item['link']). "</guid>\n";
        echo '<source url="'.$this->feed.'">'. htmlspecialchars($this->channel['title'], ENT_QUOTES). "</source>\n";
        echo "</item>\n\n";
    }
}

public function closeChannel()
{
    echo '</channel>'. chr(10);
}

public function rssFooter()
{
    echo '</rss>'. chr(10);
}


} //Ende der Klasse

?>