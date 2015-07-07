<?php
/******************************************************************************
 * Class manages display of menus
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * Description  : Create, modify and display menus. Each menu item is defined by
 *
 *      - $id   : identifier of the menu item
 *      - $link : URL, relative to the admidio root directory, starting with a /
 *                or full URL with http or https protocol
 *      - $text : menu text
 *      - $icon : URL, relative to the theme plugin, starting with a /
 *              : or full URL with http or https protocol
 *      - $desc : (optional) long description of the menu item
 *
 *****************************************************************************/

class Menu
{
    /**
     * constructor
     * @param string $id
     * @param string $title
     */
    public function __construct($id, $title)
    {
        global $g_root_path;

        $this->id        = $id;
        $this->title     = $title;
        $this->items     = array();
        $this->root_path = $g_root_path;
    }

    /**
     * @param  string $id
     * @param  string $link
     * @param  string $text
     * @param  string $icon
     * @param  string $desc
     * @return array
     */
    private function mkItem($id, $link, $text, $icon, $desc = '')
    {
        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $link) === 0)
        {
            $link = $this->root_path . $link;
        }
        // add THEME_PATH to images unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $icon) === 0)
        {
            $icon = THEME_PATH . $icon;
        }

        return array('id'=>$id, 'link'=>$link, 'text'=>$text, 'icon'=>$icon, 'desc'=>$desc, 'subitems'=>array());
    }

    /**
     * @param string $id
     * @param string $link
     * @param string $text
     * @param string $icon
     * @param string $desc
     */
    public function addItem($id, $link, $text, $icon, $desc = '')
    {
        $this->items[$id] = $this->mkItem($id, $link, $text, $icon, $desc);
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
            $link = $this->root_path . $link;
        }

        $this->items[$parentId]['subitems'][$id] = array('link'=>$link, 'text'=>$text);
    }

    /**
     * gets the position of a given ID in the menu
     * @param  string    $id
     * @return int|false
     */
    public function getPosition($id)
    {
        $keys = array_keys($this->items);
        $key = array_search($id, $keys, true);

        return $key;
    }

    /**
     * inserts a new menu entry before the named position
     * @param  int    $position
     * @param  string $id
     * @param  string $link
     * @param  string $text
     * @param  string $icon
     * @param  string $desc
     * @return bool
     */
    public function insertItem($position, $id, $link, $text, $icon, $desc = '')
    {
        if (!is_numeric($position))
        {
            return false;
        }
        else
        {
            $insert = array($id => $this->mkItem($id, $link, $text, $icon, $desc));
            $this->items = array_splice($this->items, $position, 0, $insert);

            return true;
        }
    }

    /**
     * Create the html menu from the internal array that must be filled before.
     * You have the option to create a simple menu with icon and link or
     * a more complex menu with submenu and description text.
     * @param  bool   $complex Create a @b simple menu as default. If you set the param to @b true
     *                         then you will create a menu with submenus and description
     * @return string Return the html code of the form.
     */
    public function show($complex = false)
    {
        $html = '';

        if ($complex)
        {
            $html .= '<h2 id="head_'.$this->id.'">'.$this->title.'</h2>';
        }
        else
        {
            $html .= '<h3 id="head_'.$this->id.'">'.$this->title.'</h3>';
            $html .= '<div class="btn-group-vertical admidio-menu" role="group" id="menu_'.$this->id.'">';
        }

        // now create each menu item
        foreach($this->items as $item)
        {
            if ($complex)
            {
                $html .= '
                    <div class="media">
                        <div class="media-left">
                            <a id="menu_'.$this->id.'_' .$item['id'].'" href="'.$item['link'].'">
                                <img class="media-object" src="'.$item['icon'].'" alt="'.strip_tags($item['text']).'" />
                            </a>
                        </div>
                        <div class="media-body">
                            <h4 class="media-heading">
                                <a id="lmenu_'.$this->id.'_' .$item['id'].'" href="'.$item['link'].'">'.$item['text'].'</a>
                            </h4>';

                // adding submenus if any
                if ($item['subitems'])
                {
                    $html .= '<div class="admidio-media-submenu">&#91; ';
                    $separator = '';

                    foreach($item['subitems'] as $subitem)
                    {
                        $html .= $separator . '<a href="'.$subitem['link'].'">'.$subitem['text'].'</a>';
                        $separator = '&nbsp;| ';
                    }

                    $html .= ' &#93;</div>';
                }

                $html .= $item['desc'];
                $html .= '</div></div>';
            }
            else
            {
                $html .= '
                <a id="lmenu_'.$this->id.'_' .$item['id'].'" class="btn " href="'.$item['link'].'">
                    <img src="'.$item['icon'].'" alt="'.strip_tags($item['text']).'" />'.$item['text'].'
                </a>';
            }
        }

        if (!$complex)
        {
            $html .= '</div>'; // End Wraps all menu items
        }

        if (count($this->items) > 0)
        {
            return $html;
        }
        else
        {
            return '';
        }
    }
}
?>
