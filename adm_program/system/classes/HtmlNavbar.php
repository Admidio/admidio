<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class manages display of navbar in modules
 *
 * This class manage the presentation of a module menu. You can add as many
 * items to the menu and the class tries to display them in the perfect
 * way for the module. If there are to many items to display all than it
 * will create a menu button where you can find all the other menu items.
 * The position of the items is important. Only the first items will display
 * permanently in the module. The other items are summarized in a submenu.
 *
 * **Code example**
 * ```
 * // create module menu
 * $myNavbar = new HtmlNavbar('menu_my_module', 'My module');
 *
 * // show link to create new announcement
 * $myNavbar->addItem(
 *     'menu_item_new_entry', ADMIDIO_URL . FOLDER_MODULES . '/mymodule/mymodule_new.php',
 *     $gL10n->get('SYS_CREATE'), 'fa-plus-circle'
 * );
 * $myNavbar->show();
 * ```
 */
class HtmlNavbar
{
    /**
     * @var array<string,array<string,string|array<string,array<string,string>>>> An array with all items that should be displayed at the left part of the navbar
     */
    protected $leftItems = array();
    /**
     * @var array<string,array<string,string|array<string,array<string,string>>>> An array with all items that should be displayed at the right part of the navbar
     */
    protected $rightItems = array();
    /**
     * @var HtmlPage A HtmlPage object that will be used to add javascript code or files to the html output page.
     */
    protected $htmlPage;
    /**
     * @var string Parameter that includes the html of the form that should be shown within the navbar
     */
    protected $htmlForm = '';
    /**
     * @var string|null Name of the navbar that will be shown when navbar changed to vertical mode on small devices
     */
    protected $name;
    /**
     * @var string Navbar type. There is the  and the **filter** type possible.
     */
    protected $type;
    /**
     * @var string The id of the navbar.
     */
    protected $id;
    /**
     * @var string A css class name that should be added to the main nav tag of the navbar
     */
    protected $customCssClass = '';

    /**
     * creates the object of the module menu and initialize all class parameters
     * @param string   $id       Html id of the navbar
     * @param string   $name     Name of the navbar that will be shown when navbar changed to vertical mode on small devices
     * @param HtmlPage $htmlPage Optional a HtmlPage object that will be used to add javascript code
     *                           or files to the html output page.
     * @param string   $type     Different types of the navbar can be defined.
     *                           default: will be the standard navbar of all modules.
     *                           filter:  should be used if this navbar is used to filter data of within the script.
     */
    public function __construct($id, $name = null, HtmlPage $htmlPage = null, $type = 'default')
    {
        global $gL10n;

        if ($name === null) {
            if ($type === 'default') {
                $name = $gL10n->get('SYS_MENU');
            } elseif ($type === 'filter') {
                $name = $gL10n->get('SYS_FILTER');
            }
        }

        if ($htmlPage instanceof HtmlPage) {
            $this->htmlPage =& $htmlPage;
        }

        $this->name = $name;
        $this->type = $type;
        $this->id   = $id;
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
     * You can also add another item to an existing dropdown item. Therefore use the **$parentItem** parameter.
     * @param string $id          Html id of the item.
     * @param string $url         The url of the generated link of this item.
     * @param string $text        The text of the item and the generated link.
     * @param string $icon        Icon of the menu item, that will also be linked
     * @param string $orientation The item can be shown at the **left** or **right** part of the navbar.
     * @param string $parentItem  All items should be added to the **navbar** as parent. But if you
     *                            have already added a dropdown than you can add the item to that
     *                            dropdown. Just commit the id of that item.
     * @param string $class       Optional a css class that will be set for the item.
     */
    public function addItem($id, $url, $text, $icon = '', $orientation = 'left', $parentItem = 'navbar', $class = '')
    {
        $urlStartRegex = '/^(https?:)?\/\//i';

        // add root path to link unless the full URL is given
        if ($url !== '' && $url !== '#' && preg_match($urlStartRegex, $url) === 0) {
            $url = ADMIDIO_URL . $url;
        }

        $item = array('id' => $id, 'text' => $text, 'icon' => $icon, 'url' => $url, 'class' => $class);

        if ($orientation === 'left') {
            if ($parentItem === 'navbar') {
                $this->leftItems[$id] = $item;
            } elseif (array_key_exists($parentItem, $this->leftItems)) {
                $this->leftItems[$parentItem]['items'][$id] = $item;
            }
        } elseif ($orientation === 'right') {
            if ($parentItem === 'navbar') {
                $this->rightItems[$id] = $item;
            } elseif (array_key_exists($parentItem, $this->rightItems)) {
                $this->rightItems[$parentItem]['items'][$id] = $item;
            }
        }
    }

    /**
     * Creates the html for the menu entry.
     * @param array<string,string> $data An array with all data if the item. This will be **id**, **url**, **text** and **icon**.
     * @return string Returns the html for the menu entry
     */
    protected function createHtmlLink(array $data)
    {
        $iconHtml = '';

        if ($data['icon'] !== '') {
            $iconHtml = Image::getIconHtml($data['icon'], $data['text']);
        }

        $html = '
            <li class="nav-item ' . $data['class'] . '">
                <a class="nav-link" id="' . $data['id'] . '" href="' . $data['url'] . '">' . $iconHtml . $data['text'] . '</a>
            </li>';

        return $html;
    }

    /**
     * Creates the html for the menu entry.
     * @param array<string,string> $data An array with all data if the item. This will be **id**, **url**, **text** and **icon**.
     * @return string Returns the html for the menu entry
     */
    protected function createHtmlDropdownLink(array $data)
    {
        $iconHtml = '';

        if ($data['icon'] !== '') {
            $iconHtml = Image::getIconHtml($data['icon'], $data['text']);
        }

        $html = '<a class="dropdown-item" id="' . $data['id'] . '" href="' . $data['url'] . '">' . $iconHtml . $data['text'] . '</a>';

        return $html;
    }

    /**
     * @param array<string,array<string,string|array<string,array<string,string>>>> $items
     * @param string                                                                $class
     * @return string
     */
    private function getNavHtml(array $items, $class = '')
    {
        $html = '<ul class="navbar-nav mr-auto ' . $class . '">';

        foreach ($items as $menuEntry) {
            if (array_key_exists('items', $menuEntry) && is_array($menuEntry['items'])) {
                if (count($menuEntry['items']) === 1) {
                    // only one entry then add a simple link to the navbar
                    $html .= $this->createHtmlLink(current($menuEntry['items']));
                } else {
                    // add a dropdown to the navbar
                    $html .= '
                        <li class="nav-item dropdown ' . $menuEntry['class'] . '">
                            <a id="' . $menuEntry['id'] . '" href="#" class="nav-link dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bars"></i>' . $menuEntry['text'] . '
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown">';

                    foreach ($menuEntry['items'] as $menuEntryDropDown) {
                        $html .= $this->createHtmlDropdownLink($menuEntryDropDown);
                    }
                    $html .= '</div></li>';
                }
            } else {
                // add a simple link to the navbar
                $html .= $this->createHtmlLink($menuEntry);
            }
        }

        $html .= '</ul>';

        return $html;
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
     * @return string Returns the html output for the complete menu
     */
    public function show()
    {
        $showNavbar = false;
        $navHtml = '';

        // add left item block to navbar
        if (count($this->leftItems) > 0) {
            $showNavbar = true;
            $navHtml .= $this->getNavHtml($this->leftItems, 'navbar-left');
        }

        // add form to navbar
        if ($this->htmlForm !== '') {
            $showNavbar = true;
            $navHtml .= $this->htmlForm;
        }

        // add right item block to navbar
        if (count($this->rightItems) > 0) {
            $showNavbar = true;
            $navHtml .= $this->getNavHtml($this->rightItems, 'navbar-right');
        }

        if (!$showNavbar) {
            // dont show navbar if no menu item or form was added
            return '';
        }

        $cssClassBrand = '';
        $cssClassNavbar = '';

        // default navbar should not show the brand, only in xs mode
        if ($this->type === 'default') {
            $cssClassBrand = 'd-block d-md-none';
            $cssClassNavbar = 'navbar-menu';
        } elseif ($this->type === 'filter') {
            $cssClassNavbar = 'navbar-filter';
        }

        // add html for navbar
        $html = '
            <nav class="navbar navbar-expand-md ' . $cssClassNavbar . $this->customCssClass . '">
                <a class="navbar-brand ' . $cssClassBrand . '" href="#">' . $this->name . '</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#' . $this->id . '" aria-controls="' . $this->id . '" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon fas fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="' . $this->id . '">';

        $html .= $navHtml;
        $html .= '</div></nav>';

        // now show the complete html of the menu
        return $html;
    }
}
