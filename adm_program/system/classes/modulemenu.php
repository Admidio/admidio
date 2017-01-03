<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ModuleMenu
 * @brief Class manages display of menus in modules
 *
 * This class manage the presentation of a module menu. You can add as many
 * items to the menu and the class tries to display them in the perfect
 * way for the module. If there are to many items to display all than it
 * will create a menu button where you can find all the other menu items.
 * The position of the items is important. Only the first items will display
 * permanently in the module. The other items are summarized in a submenu.
 * @par Examples
 * @code   // get module menu
 * $myModuleMenu = new ModuleMenu('admMenuMyModule');
 *
 * // show link to create new announcement
 * $myModuleMenu->addItem('admMenuItemNewEntry', ADMIDIO_URL.FOLDER_MODULES.'/mymodule/mymodule_new.php',
 *                         $gL10n->get('SYS_CREATE'), 'add.png');
 * $myModuleMenu->show(); @endcode
 */
class ModuleMenu
{
    protected $id;
    protected $items;
    protected $ddItemCnt;
    protected $customCssClass;
    protected $maxMenuLinkItem;
    protected $ddJS;

    /**
     * creates the object of the module menu and initialize all class parameters
     * @param string $id              Html id of the module menu
     * @param int    $maxMenuLinkItem
     */
    public function __construct($id, $maxMenuLinkItem = 6)
    {
        $this->id        = $id;
        $this->items     = array();
        $this->ddItemCnt = 0;
        $this->customCssClass  = '';
        $this->maxMenuLinkItem = $maxMenuLinkItem;
    }

    /**
     * Creates a selectbox with all categories of a category type. If an category of this selectbox is selected
     * than the link is called and where you can select entries of this category
     * @param string $id              Html id of the element
     * @param string $categoryType    Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
     * @param string $defaultCategory Id of selected category (if id = -1 then no default category will be selected)
     * @param string $link            Link to the page that will be called if menu item is clicked. At the end of
     *                                this link the ID if the category will be added automatically, so you can add a
     *                                category parameter at last
     * @param string $text            Text of the selectbox
     * @param bool   $admin           Set to @b true if user has admin rights in this category, than a link to
     *                                administrate the categories is shown.
     */
    public function addCategoryItem($id, $categoryType, $defaultCategory, $link, $text, $admin = false)
    {
        $this->items[$id] = array(
            'id'              => $id,
            'type'            => 'category',
            'categoryType'    => $categoryType,
            'defaultCategory' => $defaultCategory,
            'link'            => $link,
            'text'            => $text,
            'admin'           => $admin,
            'subitems'        => array()
        );
    }

    /**
     * This method adds an additional css class to the main nav tag of the menu.
     * @param string $className The name of a css class that should be add to the main nav tag of the manu
     */
    public function addCssClass($className)
    {
        $this->customCssClass = ' ' . $className;
    }

    /**
     * add a drop down item
     * @param string[] $menuEntry menu entry element which was added with addItem
     * @param bool     $selected  determines if drop down element should be pre selected
     */
    private function addDropDownItem(array &$menuEntry, $selected = false)
    {
        if ($this->ddJS !== '')
        {
            $this->ddJS .= ',';
        }

        $selectedText = $selected ? 'true' : 'false';
        $this->ddJS .= '
            {
                text: "'.$menuEntry['text'].'",
                value: '.++$this->ddItemCnt.',
                selected: '.$selectedText.',
                imageSrc: "'.$menuEntry['icon'].'",
                link: "'.$menuEntry['link'].'",
                js: "'.$menuEntry['js'].'"
            }
        ';
    }

    /**
     * add a new entry to menu that contains the html as content
     * @param string $id       Html id of the element
     * @param string $formHtml A html code that will be added to the menu
     */
    public function addForm($id, $formHtml)
    {
        $this->items[$id] = array('id' => $id, 'type' => 'form', 'content' => $formHtml);
    }

    /**
     * add a new entry to menu that contains the html as content
     * @param string $id   Html id of the element
     * @param string $html A html code that will be added to the menu
     */
    public function addHtml($id, $html)
    {
        $this->items[$id] = array('id' => $id, 'type' => 'html', 'content' => $html);
    }

    /**
     * add new entry to menu
     * @param string $id   Html id of the element
     * @param string $link Link to the page that will be called if menu item is clicked
     * @param string $text Link text
     * @param string $icon Icon of the menu item, that will also be linked
     * @param string $js   Javascript to be executed
     */
    public function addItem($id, $link, $text, $icon, $js = '')
    {
        $this->items[$id] = $this->mkItem($id, 'link', $link, $text, $icon, $js);
    }

    /**
     * Count the number of menu items.
     * @return int Returns the number of menu items.
     */
    public function countItems()
    {
        return count($this->items);
    }

    /**
     * creates an text link icon
     * @param string[] $menuEntry menu entry element which was added with addItem
     * @return string HTML of created item
     */
    private function createIconTextLink(array &$menuEntry)
    {
        $html = '';

        // if javascript was set then add this script to click event of this menu item
        if(isset($menuEntry['js']) && $menuEntry['js'] !== '')
        {
            $html .= '
                <script type="text/javascript">
                    $(function() {
                        $("#'.$menuEntry['id'].'").click(function () {
                            '.$menuEntry['js'].'
                        });
                    });
                </script>';
        }

        // add html of menu item
        $html .= '
            <li id="'.$menuEntry['id'].'">
                <a class="navbar-link" href="'.$menuEntry['link'].'">
                    <img src="'.$menuEntry['icon'].'" alt="'.strip_tags($menuEntry['text']).'" />'.$menuEntry['text'].'
                </a>
            </li>';

        return $html;
    }

    /**
     * gets the position of a given ID in the menu
     * @param string $id
     * @return int|false Position of the element; Returns false of no element is found
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
     */
    public function insertItem($position, $id, $link, $text, $icon, $desc = '')
    {
        $head = array_slice($this->items, 0, $position);
        $insert = array($id => $this->mkItem($id, $link, $text, $icon, $desc));
        $tail = array_slice($this->items, $position);
        $this->items = array_merge($head, $insert, $tail);
    }

    /**
     * add new entry to array and do some checks before so that link and icon get a valid url
     * @param string $id   Html id of the element
     * @param string $type The different type of menu that should be shown: @b link normal link with icon; @b category category select box
     * @param string $link Link to the page that will be called if menu item is clicked
     * @param string $text Link text
     * @param string $icon Icon of the menu item, that will also be linked
     * @param string $js   Javascript to be executed
     * @return array<string,string|array>
     */
    private function mkItem($id, $type, $link, $text, $icon, $js = '')
    {
        if(strlen($link) > 1)
        {
            // add root path to link unless the full URL is given
            if (preg_match('/^http(s?):\/\//', $link) !== 1)
            {
                $link = ADMIDIO_URL . $link;
            }
        }
        else
        {
            $link = '#';
        }

        // add THEME_URL to images unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $icon) !== 1)
        {
            $icon = THEME_URL.'/icons/'.$icon;
        }

        return array(
            'id'       => $id,
            'type'     => $type,
            'link'     => $link,
            'text'     => $text,
            'icon'     => $icon,
            'subitems' => array(),
            'js'       => $js
        );
    }

    /**
     * Creates the html output of the module menu. Each added menu item will be displayed.
     * If there are more menu items then in @b maxMenuLinkItem defined a dropdown menu
     * will be displayed and all other items will be displayed there.
     * @return string|false Returns the html output for the complete menu
     */
    public function show()
    {
        if (count($this->items) === 0)
        {
            return false;
        }

        global $gL10n;

        $formHtml = '';

        $html = '
            <nav class="navbar navbar-default'.$this->customCssClass.'" role="navigation">
                <div class="container-fluid">
                    <!-- Brand and toggle get grouped for better mobile display -->
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                            <span class="sr-only">Toggle navigation</span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                        <a class="navbar-brand" href="#">Menu</a>
                    </div>
                    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                        <ul class="nav navbar-nav" id="'.$this->id.'">';

        $linkCnt = 0;

        foreach($this->items as $menuEntry)
        {
            ++$linkCnt;

            if($menuEntry['type'] === 'html')
            {
                $html .= $menuEntry['content'];
            }
            elseif($menuEntry['type'] === 'link')
            {
                // if the count of link elements greater equal then the maxMenuLinkItem variable add drop down entry
                if (count($this->items) > $this->maxMenuLinkItem && $linkCnt >= $this->maxMenuLinkItem)
                {
                    $this->addDropDownItem($menuEntry);
                }
                else // if not display link entry as default
                {
                    $html .= $this->createIconTextLink($menuEntry);
                }
            }
        }

        $html .= '</ul>';

        if($formHtml !== '')
        {
            $html .= $formHtml;
        }

        $html .= '</div></div></nav>';

        // now return the complete html of the menu
        return $html;
    }
}
