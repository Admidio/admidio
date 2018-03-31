<?php
/**
 ***********************************************************************************************
 * Class manages display of menus
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create, modify and display menus. Each menu item is defined by
 *
 *      - $id   : identifier of the menu item
 *      - $link : URL, relative to the admidio root directory, starting with a /
 *                or full URL with http or https protocol
 *      - $text : menu text
 *      - $icon : URL, relative to the theme plugin, starting with a /
 *              : or full URL with http or https protocol
 *      - $desc : (optional) long description of the menu item
 */
class Menu
{
    /**
     * @var string
     */
    protected $id;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var array<string,array<string,string|array<string,string>>>
     */
    protected $items = array();

    /**
     * constructor
     * @param string $id
     * @param string $title
     */
    public function __construct($id, $title)
    {
        $this->id    = $id;
        $this->title = $title;
    }

    /**
     * @param string $id
     * @param string $link
     * @param string $text
     * @param string $icon
     * @param string $desc
     * @throws AdmException
     * @return array<string,string|array>
     */
    private function buildItem($id, $link, $text, $icon, $desc = '')
    {
        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $link) === 0)
        {
            $link = ADMIDIO_URL . $link;
        }

        // if icon is imagefile or imageurl then show image
        if (preg_match('/^http(s?):\/\//', $icon) === 0 && admStrIsValidFileName($icon, true)
        && (StringUtils::strEndsWith($icon, '.png', false) || StringUtils::strEndsWith($icon, '.jpg', false)))
        {
            $icon = THEME_URL . '/icons/' . $icon;
        }

        return array(
            'id'       => $id,
            'link'     => $link,
            'text'     => $text,
            'icon'     => $icon,
            'desc'     => $desc,
            'subitems' => array()
        );
    }

    /**
     * @param string $id
     * @param string $link
     * @param string $text
     * @param string $icon
     * @param string $desc
     * @throws AdmException
     */
    public function addItem($id, $link, $text, $icon, $desc = '')
    {
        $this->items[$id] = $this->buildItem($id, $link, $text, $icon, $desc);
    }

    /**
     * @param string $parentId
     * @param string $id
     * @param string $link
     * @param string $text
     */
    public function addSubItem($parentId, $id, $link, $text)
    {
        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $link) === 0)
        {
            $link = ADMIDIO_URL . $link;
        }

        $this->items[$parentId]['subitems'][$id] = array('link' => $link, 'text' => $text);
    }

    /**
     * gets the position of a given ID in the menu
     * @param string $id
     * @return int|false
     */
    public function getPosition($id)
    {
        $keys = array_keys($this->items);
        return array_search($id, $keys, true);
    }

    /**
     * inserts a new menu entry before the named position
     * @param int    $position
     * @param string $id
     * @param string $link
     * @param string $text
     * @param string $icon
     * @param string $desc
     * @throws AdmException
     */
    public function insertItem($position, $id, $link, $text, $icon, $desc = '')
    {
        $item = $this->buildItem($id, $link, $text, $icon, $desc);
        $insert = array($id => $item);
        $this->items = array_splice($this->items, $position, 0, $insert);
    }

    /**
     * Create the html menu from the internal array that must be filled before.
     * You have the option to create a simple menu with icon and link or
     * a more complex menu with submenu and description text.
     * @param bool $complex Create a @b simple menu as default. If you set the param to **true**
     *                      then you will create a menu with submenus and description
     * @return string Return the html code of the form.
     */
    public function show($complex = false)
    {
        if (count($this->items) === 0)
        {
            return '';
        }

        $html = '';

        if ($complex)
        {
            $html .= '<h2 id="head_'.$this->id.'">'.$this->title.'</h2>';
            $html .= '<menu id="menu_'.$this->id.'" class="list-unstyled admidio-media-menu">'; // or class="media-list"
        }
        else
        {
            $html .= '<h3 id="head_'.$this->id.'">'.$this->title.'</h3>';
            $html .= '<menu id="menu_'.$this->id.'" class="list-unstyled admidio-menu btn-group-vertical">';
        }

        // now create each menu item
        foreach($this->items as $item)
        {
            if ($complex)
            {
                $html .= '
                    <li class="media">
                        <div class="media-left">
                            <a id="menu_'.$this->id.'_'.$item['id'].'" href="'.$item['link'].'">
                                <img class="media-object" src="'.$item['icon'].'" alt="'.strip_tags($item['text']).'" />
                            </a>
                        </div>
                        <div class="media-body">
                            <h4 class="media-heading">
                                <a id="lmenu_'.$this->id.'_'.$item['id'].'" href="'.$item['link'].'">'.$item['text'].'</a>
                            </h4>';

                // adding submenus if any
                if ($item['subitems'])
                {
                    $html .= '<menu id="lsubmenu_'.$this->id.'_'.$item['id'].'" class="list-inline admidio-media-submenu">';

                    foreach($item['subitems'] as $subitem)
                    {
                        $html .= '<li><a href="'.$subitem['link'].'">'.$subitem['text'].'</a></li>';
                    }

                    $html .= '</menu>'; // closes sub-menu "menu.admidio-media-submenu"
                }

                $html .= '<p>'.$item['desc'].'</p>';
                $html .= '</div></li>'; // closes "div.media-body" and "li.media"
            }
            else
            {
                $html .= '
                    <li>
                        <a id="lmenu_'.$this->id.'_'.$item['id'].'" class="btn" href="'.$item['link'].'">
                            <img src="'.$item['icon'].'" alt="'.strip_tags($item['text']).'" />'.$item['text'].'
                        </a>
                    </li>';
            }
        }

        $html .= '</menu>'; // closes main-menu "menu.list-unstyled"

        return $html;
    }
}
