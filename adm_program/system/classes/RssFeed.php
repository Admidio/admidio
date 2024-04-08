<?php
/**
 ***********************************************************************************************
 * Class creates an RssFeed object according to RSS 2.0.
 * Specification of RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
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
     * @param string $title Headline of this channel
     * @param string $link Link to the website of this RSS feed
     * @param string $description Short description of this channel
     * @param string $copyright Author of the channel; in our case the organization name
     */
    public function __construct(string $title, string $link, string $description, string $copyright)
    {
        $this->channel['title'] = $title;
        $this->channel['link'] = $link;
        $this->channel['description'] = $description;
        $this->channel['copyright'] = $copyright;
        $this->feed = CURRENT_URL;
    }

    /**
     * Add rss item to the current feed with all necessary information about the item
     * @param string $title Headline of this item
     * @param string $description The main content of the item which can contain html
     * @param string $link Link to this entry on the homepage
     * @param string $author Optional the email address of the member who creates this entry
     * @param string $pubDate Optional the publication date of this entry
     * @param string $category Optional the category of this entry
     */
    public function addItem(string $title, string $description, string $link, string $author = '', string $pubDate = '', string $category = '', string $guid = '')
    {
        if (!StringUtils::strValidCharacters(StringUtils::strToLower($author), 'email')) {
            $author = '';
        }

        $this->items[] =
            array(
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'author' => $author,
                'pubDate' => $pubDate,
                'category' => $category,
                'guid' => $guid
            );
    }

    /**
     * @return void
     */
    public function getRssFeed()
    {
        $rssFeed = $this->getRssHeader();
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
    private function getRssHeader(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>' . chr(10) .
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . chr(10);
    }

    /**
     * @return string Returns the open channel
     */
    private function getChannelOpener(): string
    {
        return '<channel>' . chr(10) .
            '<atom:link href="' . $this->feed . '" rel="self" type="application/rss+xml" />' . chr(10);
    }

    /**
     * @return string Returns channel infos
     */
    private function getChannelInfo(): string
    {
        global $gL10n;

        $channelInfo = '';
        foreach (array('title', 'link', 'description', 'copyright') as $field) {
            if (isset($this->channel[$field])) {
                $channelInfo .= '<' . $field . '>' . SecurityUtils::encodeHTML($this->channel[$field]) . '</' . $field . '>' . chr(10);
            }
        }
        $channelInfo .= '<language>' . $gL10n->getLanguageIsoCode() . '</language>' . chr(10);
        $channelInfo .= '<generator>Admidio RSS-Class</generator>' . chr(10) . chr(10);
        $channelInfo .= '<pubDate>' . date('r') . '</pubDate>' . chr(10) . chr(10);

        return $channelInfo;
    }

    /**
     * @return string Returns the items
     */
    private function getItems(): string
    {
        $itemString = '';
        foreach ($this->items as $item) {
            $itemString .= '<item>' . chr(10);
            foreach (array('title', 'description', 'link', 'author', 'pubDate', 'category', 'guid') as $field) {
                if (isset($item[$field]) && $item[$field] !== '') {
                    // fields should only be set if they have a value
                    $itemString .= '<' . $field . '>' . SecurityUtils::encodeHTML($item[$field]) . '</' . $field . '>' . chr(10);
                }
            }
            if ($item['guid'] === '') {
                $itemString .= '<guid>' . str_replace('&', '&amp;', $item['link']) . '</guid>' . chr(10);
            }
            $itemString .= '<source url="' . $this->feed . '">' . SecurityUtils::encodeHTML($this->channel['title']) . '</source>' . chr(10);
            $itemString .= '</item>' . chr(10) . chr(10);
        }

        return $itemString;
    }

    /**
     * @return string Returns the channel close
     */
    private function getChannelCloser(): string
    {
        return '</channel>' . chr(10);
    }

    /**
     * @return string Returns the RSS footer
     */
    private function getRssFooter(): string
    {
        return '</rss>' . chr(10);
    }
}
