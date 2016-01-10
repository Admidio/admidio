<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class HtmlNavbar
 * @brief Class manages display of navbar in modules
 *
 * This class manage the presentation of a module menu. You can add as many
 * items to the menu and the class tries to display them in the perfect
 * way for the module. If there are to many items to display all than it
 * will create a menu button where you can find all the other menu items.
 * The position of the items is important. Only the first items will display
 * permanently in the module. The other items are summarized in a submenu.
 * @par Examples
 * @code   // create module menu
 * $myNavbar = new HtmlNavbar('menu_my_module', 'My module');
 *
 * // show link to create new announcement
 * $myNavbar->addItem('menu_item_new_entry', $g_root_path.'/adm_program/modules/mymodule/mymodule_new.php',
 *                         $gL10n->get('SYS_CREATE'), 'add.png');
 * $myNavbar->show(); @endcode
 */
class HtmlNavbar
{
    protected $leftItems;      ///< An array with all items that should be displayed at the left part of the navbar
    protected $rightItems;     ///< An array with all items that should be displayed at the right part of the navbar
    protected $htmlPage;       ///< A HtmlPage object that will be used to add javascript code or files to the html output page.
    protected $htmlForm;       ///< Parameter that includes the html of the form that should be shown within the navbar
    protected $name;           ///< Name of the navbar that will be shown when navbar changed to vertical mode on small devices
    protected $type;           ///< Navbar type. There is the @b default and the @b filter type possible.
    protected $id;             ///< The id of the navbar.
    protected $customCssClass; ///< A css class name that should be added to the main nav tag of the navbar

    /**
     * creates the object of the module menu and initialize all class parameters
     * @param string $id       Html id of the navbar
     * @param string $name     Name of the navbar that will be shown when navbar changed to vertical mode on small devices
     * @param object $htmlPage Optional a HtmlPage object that will be used to add javascript code
     *                         or files to the html output page.
     * @param string $type     Different types of the navbar can be defined.
     *                         default: will be the standard navbar of all modules.
     *                         filter:  should be used if this navbar is used to filter data of within the script.
     */
    public function __construct($id, $name = null, $htmlPage = null, $type = 'default')
    {
        global $gL10n;

        if($type === 'default' && $name === null)
        {
            $name = $gL10n->get('SYS_MENU');
        }
        elseif($type === 'filter' && $name === null)
        {
            $name = $gL10n->get('SYS_FILTER');
        }

        if(is_object($htmlPage))
        {
            $this->htmlPage =& $htmlPage;
        }

        $this->leftItems  = array();
        $this->rightItems = array();
        $this->htmlForm   = '';
        $this->name       = $name;
        $this->type       = $type;
        $this->id         = $id;
        $this->customCssClass = '';
    }

    /**
     * Creates the html for the menu entry.
     * @param array $data An array with all data if the item. This will be @id, @url, @text and @icon.
     * @return string Returns the html for the menu entry
     */
    protected function createHtmlLink($data)
    {
        $icon = '';

        if($data['icon'] !== '')
        {
            $icon = '<img src="'.$data['icon'].'" alt="'.strip_tags($data['text']).'" />';
        }

        $html = '
            <li class="'.$data['class'].'">
                <a class="navbar-link" id="'.$data['id'].'" href="'.$data['url'].'">'.$icon.$data['text'].'</a>
            </li>';

        return $html;
    }

    /**
     * This method adds an additional css class to the main nav tag of the menu.
     * @param string $className The name of a css class that should be add to the main nav tag of the manu
     */
    public function addCssClass($className)
    {
        $this->customCssClass = ' '.$className;
    }

    /**
     * Add a form to the menu. The form will be added between the left and the right part of the navbar.
     * @param string $htmlForm A html code of a form that will be added to the menu
     */
    public function addForm($htmlForm)
    {
        $this->htmlForm = $htmlForm;
    }

    /**
     * Add a new item to the menu. This can be added to the left or right part of the navbar.
     * You can also add another item to an existing dropdown item. Therefore use the @b $parentItem parameter.
     * @param string $id          Html id of the item.
     * @param string $url         The url of the generated link of this item.
     * @param string $text        The text of the item and the generated link.
     * @param string $icon        Icon of the menu item, that will also be linked
     * @param string $orientation The item can be shown at the @b left or @b right part of the navbar.
     * @param string $parentItem  All items should be added to the @b navbar as parent. But if you
     *                            have already added a dropdown than you can add the item to that
     *                            dropdown. Just commit the id of that item.
     * @param string $class       Optional a css class that will be set for the item.
     */
    public function addItem($id, $url, $text, $icon, $orientation = 'left', $parentItem = 'navbar', $class = '')
    {
        global $g_root_path;

        // add root path to link unless the full URL is given
        if($url !== '' && $url !== '#' && preg_match('/^http(s?):\/\//', $url) === 0)
        {
            $url = $g_root_path.$url;
        }

        // add THEME_PATH to images unless the full URL is given
        if($icon !== '' && preg_match('/^http(s?):\/\//', $icon) === 0)
        {
            $icon = THEME_PATH.'/icons/'.$icon;
        }

        $item = array('id' => $id, 'text' => $text, 'icon' => $icon, 'url' => $url, 'class' => $class);

        if($orientation === 'left')
        {
            if($parentItem === 'navbar')
            {
                $this->leftItems[$id] = $item;
            }
            elseif(array_key_exists($parentItem, $this->leftItems))
            {
                $this->leftItems[$parentItem]['items'][$id] = $item;
            }
        }
        elseif($orientation === 'right')
        {
            if($parentItem === 'navbar')
            {
                $this->rightItems[$id] = $item;
            }
            elseif(array_key_exists($parentItem, $this->rightItems))
            {
                $this->rightItems[$parentItem]['items'][$id] = $item;
            }
        }
    }

    /**
     * Set the name of the navbar that will be shown when navbar changed to vertical mode on small devices.
     * @param string $name New name of the navbar.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Creates the html output of the module menu. Each added menu item will be displayed.
     * If one item has several subitems than a dropdown button will be created.
     * @param bool $directOutput If set to @b true (default) the module menu will be directly send
     *                           to the browser. If set to @b false the html will be returned.
     * @return string|void Returns the html output for the complete menu
     */
    public function show($directOutput = true)
    {
        $showNavbar     = false;
        $cssClassBrand  = '';
        $cssClassNavbar = '';

        // default navbar should not show the brand, only in xs mode
        if($this->type === 'default')
        {
            $cssClassBrand = 'visible-xs-block';
        }
        elseif($this->type === 'filter')
        {
            $cssClassNavbar = 'navbar-filter';
        }

        // add html for navbar
        $html = '
            <nav class="navbar navbar-default '.$cssClassNavbar.$this->customCssClass.'" role="navigation">
                <div class="container-fluid">
                    <!-- Brand and toggle get grouped for better mobile display -->
                    <div class="navbar-header">
                      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#'.$this->id.'">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                      </button>
                      <a class="navbar-brand '.$cssClassBrand.'" href="#">'.$this->name.'</a>
                    </div>
                    <div class="collapse navbar-collapse" id="'.$this->id.'">';

        // add left item block to navbar
        if(count($this->leftItems) > 0)
        {
            $showNavbar = true;

            $html .= '<ul class="nav navbar-nav">';

            foreach($this->leftItems as $key => $menuEntry)
            {
                if(array_key_exists('items', $menuEntry) && is_array($menuEntry['items']))
                {
                    if(count($menuEntry['items']) === 1)
                    {
                        // only one entry then add a simple link to the navbar
                        $html .= $this->createHtmlLink(current($menuEntry['items']));
                    }
                    else
                    {
                        // add a dropdown to the navbar
                        $html .= '
                            <li class="dropdown '.$menuEntry['class'].'">
                                <a id="'.$menuEntry['id'].'" href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <span class="glyphicon glyphicon-menu-hamburger"></span>'.$menuEntry['text'].'<span class="caret"></span>
                                </a>
                                <ul class="dropdown-menu" role="menu">';

                        foreach($menuEntry['items'] as $keyDropDown => $menuEntryDropDown)
                        {
                            $html .= $this->createHtmlLink($menuEntryDropDown);
                        }
                        $html .= '</ul></li>';
                    }
                }
                else
                {
                    // add a simple link to the navbar
                    $html .= $this->createHtmlLink($menuEntry);
                }
            }

            $html .= '</ul>';
        }

        // add form to navbar
        if($this->htmlForm !== '')
        {
            $showNavbar = true;
            $html .= $this->htmlForm;
        }

        // add right item block to navbar
        if(count($this->rightItems) > 0)
        {
            $showNavbar = true;
            $html .= '<ul class="nav navbar-nav navbar-right">';

            foreach($this->rightItems as $key => $menuEntry)
            {
                if(array_key_exists('items', $menuEntry) && is_array($menuEntry['items']))
                {
                    if(count($menuEntry['items']) === 1)
                    {
                        // only one entry then add a simple link to the navbar
                        $html .= $this->createHtmlLink(current($menuEntry['items']));
                    }
                    else
                    {
                        // add a dropdown to the navbar
                        $html .= '
                            <li class="dropdown '.$menuEntry['class'].'">
                                <a id="'.$menuEntry['id'].'" href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <span class="glyphicon glyphicon-menu-hamburger"></span>'.$menuEntry['text'].'<span class="caret"></span>
                                </a>
                                <ul class="dropdown-menu" role="menu">';

                        foreach($menuEntry['items'] as $keyDropDown => $menuEntryDropDown)
                        {
                            $html .= $this->createHtmlLink($menuEntryDropDown);
                        }
                        $html .= '</ul></li>';
                    }
                }
                else
                {
                    // add a simple link to the navbar
                    $html .= $this->createHtmlLink($menuEntry);
                }
            }

            $html .= '</ul>';
        }

        $html .= '</div></div></nav>';

        if($showNavbar)
        {
            // if navbar will be shown then set this flag in page object
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->hasNavbar();
            }
        }
        else
        {
            // dont show navbar if no menu item or form was added
            $html = '';
        }

        // now show the complete html of the menu
        if($directOutput)
        {
            echo $html;
        }
        else
        {
            return $html;
        }
    }
}
