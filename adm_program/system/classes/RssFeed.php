<?php
/**
 ***********************************************************************************************
 * RSS - Klasse
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse erzeugt ein RssFeed-Objekt nach RSS 2.0.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * function RssFeed($homepage, $title, $description)
 * Parameters:  $homepage       - Link zur Homepage
 *              $title          - Titel des RSS-Feeds
 *              $description    - Ergaenzende Beschreibung zum Titel
 *
 * Dem RssFeed koennen ueber die Funktion addItem Inhalt zugeordnet werden:
 * function addItem($title, $description, $date, $guid)
 * Parameters:  $title          - Titel des Items
 *              $description    - der Inhalt des Items
 *              $date           - Das Erstellungsdatum des Items
 *              $link           - Ein Link zum Termin/Newsbeitrag etc.
 *
 * Wenn alle benoetigten Items zugeordnet sind, wird der RssFeed generiert mit:
 * function buildFeed()
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 */
class RssFeed
{
    /**
     * @var array
     */
    protected $channel = array();
    /**
     * @var array<int,array<string,string>>
     */
    protected $items = array();
    /**
     * @var string
     */
    protected $feed;

    /**
     * Constructor of the RSS class which needs all the information of the channel
     * @param string $title       Headline of this channel
     * @param string $link        Link to the website of this RSS feed
     * @param string $description Short description of this channel
     * @param string $copyright   Author of the channel; in our case the organization name
     */
    public function __construct($title, $link, $description, $copyright)
    {
        $this->channel['title'] = $title;
        $this->channel['link']  = $link;
        $this->channel['description'] = $description;
        $this->channel['copyright']   = $copyright;
        $this->feed = CURRENT_URL;
    }

    /**
     * Add rss item to the current feed with all necessary information about the item
     * @param string $title       Headline of this item
     * @param string $description The main content of the item which can contain html
     * @param string $link        Link to this entry on the homepage
     * @param string $author      Optional the email address of the member who creates this entry
     * @param string $pubDate     Optional the publication date of this entry
     * @param string $category    Optional the category of this entry
     */
    public function addItem($title, $description, $link, $author = '', $pubDate = '', $category = '')
    {
        if (!StringUtils::strValidCharacters(StringUtils::strToLower($author), 'email')) {
            $author = '';
        }

        $this->items[] = array('title' => $title, 'description' => $description, 'link' => $link, 'author' => $author, 'pubDate' => $pubDate, 'category' => $category);
    }

    /**
     * @return void
     */
    public function getRssFeed()
    {
        $rssFeed = '';
        $rssFeed .= $this->getRssHeader();
        $rssFeed .= $this->getChannelOpener();
        $rssFeed .= $this->getChannelInfo();
        $rssFeed .= $this->getItems();
        $rssFeed .= $this->getChannelCloser();
        $rssFeed .= $this->getRssFooter();

        header('Content-type: application/xml');
        echo $rssFeed;
    }

    /**
     * @return string Returns the RSS header
     */
    private function getRssHeader()
    {
        return '<?xml version="1.0" encoding="utf-8"?>'.chr(10).
        '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'.chr(10);
    }

    /**
     * @return string Returns the open channel
     */
    private function getChannelOpener()
    {
        return '<channel>'.chr(10).
        '<atom:link href="' . $this->feed . '" rel="self" type="application/rss+xml" />'.chr(10);
    }

    /**
     * @return string Returns channel infos
     */
    private function getChannelInfo()
    {
        global $gL10n;

        $channelInfo = '';
        foreach (array('title', 'link', 'description', 'copyright') as $field) {
            if (isset($this->channel[$field])) {
                $channelInfo .= '<'.$field.'>'.SecurityUtils::encodeHTML($this->channel[$field]).'</'.$field.'>'.chr(10);
            }
        }
        $channelInfo .= '<language>'.$gL10n->getLanguageIsoCode().'</language>'.chr(10);
        $channelInfo .= '<generator>Admidio RSS-Class</generator>'.chr(10).chr(10);
        $channelInfo .= '<pubDate>'.date('r').'</pubDate>'.chr(10).chr(10);

        return $channelInfo;
    }

    /**
     * @return string Returns the items
     */
    private function getItems()
    {
        $itemString = '';
        foreach ($this->items as $item) {
            $itemString .= '<item>'.chr(10);
            foreach (array('title', 'description', 'link', 'author', 'pubDate', 'category') as $field) {
                if (isset($item[$field]) && $item[$field] !== '') {
                    // fields should only be set if they have a value
                    $itemString .= '<'.$field.'>'.SecurityUtils::encodeHTML($item[$field]).'</'.$field.'>'.chr(10);
                }
            }
            $itemString .= '<guid>'.str_replace('&', '&amp;', $item['link']).'</guid>'.chr(10);
            $itemString .= '<source url="'.$this->feed.'">'.SecurityUtils::encodeHTML($this->channel['title']).'</source>'.chr(10);
            $itemString .= '</item>'.chr(10).chr(10);
        }

        return $itemString;
    }

    /**
     * @return string Returns the channel close
     */
    private function getChannelCloser()
    {
        return '</channel>'.chr(10);
    }

    /**
     * @return string Returns the RSS footer
     */
    private function getRssFooter()
    {
        return '</rss>'.chr(10);
    }
}
