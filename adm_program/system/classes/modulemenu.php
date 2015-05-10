<?php
/*****************************************************************************/
/** @class ModuleMenu
 *  @brief Class manages display of menus in modules
 *
 *  This class manage the presentation of a module menu. You can add as many
 *  items to the menu and the class tries to display them in the perfect
 *  way for the module. If there are to many items to display all than it
 *  will create a menu button where you can find all the other menu items.
 *  The position of the items is important. Only the first items will display
 *  permanently in the module. The other items are summarized in a submenu.
 *  @par Examples
 *  @code   // get module menu
 *  $myModuleMenu = new ModuleMenu('admMenuMyModule');
 *
 *  // show link to create new announcement
 *  $myModuleMenu->addItem('admMenuItemNewEntry', $g_root_path.'/adm_program/modules/mymodule/mymodule_new.php',
 *                          $gL10n->get('SYS_CREATE'), 'add.png');
 *  $myModuleMenu->show(); @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class ModuleMenu
{
    /** creates the object of the module menu and initialize all class parameters
     *  @param $id Html id of the module menu
     */
    public function __construct($id, $maxMenuLinkItem = 6)
    {
        global $g_root_path;
        $this->id        = $id;
        $this->items     = array();
        $this->root_path = $g_root_path;
        $this->maxMenuLinkItem = $maxMenuLinkItem;
    }

    /** Creates a selectbox with all categories of a category type. If an category of this selectbox is selected
     *  than the link is called and where you can select entries of this category
     *  @param $id Html         id of the element
     *  @param $categoryType    Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
     *  @param $defaultCategory Id of selected category (if id = -1 then no default category will be selected)
     *  @param $link            Link to the page that will be called if menu item is clicked. At the end of
     *                          this link the ID if the category will be added automatically, so you can add a
     *                          category parameter at last
     *  @param $text            Text of the selectbox
     *  @param $admin           Set to @b true if user has admin rights in this category, than a link to administrate the catories is shown.
     */
    public function addCategoryItem($id, $categoryType, $defaultCategory, $link, $text, $admin = false)
    {
        $this->items[$id] = array('id'=>$id, 'type'=>'category', 'categoryType'=>$categoryType, 'defaultCategory'=>$defaultCategory,
                                  'link'=>$link, 'text'=>$text, 'admin'=>$admin, 'subitems'=>array());
    }

    /** add a drop down item
     *  @param $menuEntry menu entry element which was added with addItem
     *  @param $selected  determines if drop down element should be pre selected
     */
    private function addDropDownItem(&$menuEntry, $selected = false)
    {
        if (!empty($this->ddJS))
            $this->ddJS .= ',';

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

    /** add a new entry to menu that contains the html as content
     *  @param $id   Html id of the element
     *  @param $html A html code that will be added to the menu
     */
    public function addForm($id, $formHtml)
    {
        $this->items[$id] = array('id' => $id, 'type' => 'form', 'content' => $formHtml);
    }

    /** add a new entry to menu that contains the html as content
     *  @param $id   Html id of the element
     *  @param $html A html code that will be added to the menu
     */
    public function addHtml($id, $html)
    {
        $this->items[$id] = array('id' => $id, 'type' => 'html', 'content' => $html);
    }

    /** add new entry to menu
     *  @param $id Html id of the element
     *  @param $link Link to the page that will be called if menu item is clicked
     *  @param $text Link text
     *  @param $icon Icon of the menu item, that will also be linked
     *  @param $desc Optional description of the menu item
     *  @param $js   Javascript to be executed
     */
    public function addItem($id, $link, $text, $icon, $js = '')
    {
        $this->items[$id] = $this->mkItem($id, 'link', $link, $text, $icon, $js);
    }

    /** Count the number of menu items.
     *  @return Returns the number of menu items.
     */
    public function countItems()
    {
        return count($this->items);
    }

    /** creates an text link icon
     *  @param $menuEntry menu entry element which was added with addItem
     *  @return HTML of created item
     */
    private function createIconTextLink(&$menuEntry)
    {
        $html = '';

        // if javascipt was set then add this script to click event of this menu item
        if(isset($menuEntry['js']) && strlen($menuEntry['js']) > 0)
        {
            $html .= '<script type="text/javascript"><!--
                          $(document).ready(function() {
                              $("#'.$menuEntry['id'].'").click(function () {
                                  '.$menuEntry['js'].'
                              });
                          });
                      //--></script>';
        }

        // add html of menu item
        $html .= '<li id="'.$menuEntry['id'].'">
                          <a class="navbar-link" href="'.$menuEntry['link'].'"><img
                              src="'.$menuEntry['icon'].'" alt="'.strip_tags($menuEntry['text']).'" />'.$menuEntry['text'].'</a>
                  </li>';
        return $html;
    }

    /** gets the position of a given ID in the menu
     *  @return Position of the element; Returns false of no elemnt is found
     */
    public function getPosition($id)
    {
        $keys=array_keys($this->items);
        $keyfound=array_keys($keys, $id);
        if (count($keyfound)==1)
        {
            return $keyfound[0];
        }
        else
        {
            return false;
        }
    }

    // inserts a new menu entry before the named position
    public function insertItem($position, $id, $link, $text, $icon, $desc='')
    {
        if (!is_numeric($position))
        {
            return false;
        }
        else
        {
            $head = array_slice($this->items, 0, $position);
            $insert=array($id=>$this->mkItem($id, $link, $text, $icon, $desc));
            $tail = array_slice($this->items, $position);
            $this->items = array_merge($head, $insert, $tail);
            return true;
        }
    }

    /** add new entry to array and do some checks before so that link and icon get
     *  a valid url
     *  @param $id   Html id of the element
     *  @param $type The different type of menu that should be shown: @b link normal link with icon; @b category category select box
     *  @param $link Link to the page that will be called if menu item is clicked
     *  @param $text Link text
     *  @param $icon Icon of the menu item, that will also be linked
     *  @param $desc Optional description of the menu item
     *  @param $js   Javascript to be executed
     */
    private function mkItem($id, $type, $link, $text, $icon, $js = '')
    {
        if(strlen($link) > 1)
        {
            // add root path to link unless the full URL is given
            if (preg_match('/^http(s?):\/\//', $link)==0)
            {
                $link = $this->root_path . $link;
            }
        }
        else
        {
            $link = '#';
        }

        // add THEME_PATH to images unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $icon) == 0)
        {
            $icon = THEME_PATH.'/icons/'.$icon;
        }

        return array('id'=>$id, 'type'=>$type, 'link'=>$link, 'text'=>$text, 'icon'=>$icon, 'subitems'=>array(), 'js' => $js);
    }

    /** Creates the html output of the module menu. Each added menu item will be displayed.
     *  If there are more menu items then in @b maxMenuLinkItem defined a drowdown menu
     *  will be displayed and all other items will be displayed there.
     *  @param $directOutput If set to @b true (default) the module menu will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return Returns the html output for the complete menu
     */
    public function show($directOutput = true)
    {
        if(count($this->items) == 0)
            return;

        global $gL10n;
        $formHtml = '';

        $html = '
        <nav class="navbar navbar-default" role="navigation">
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

        foreach($this->items as $key => $menuEntry)
        {
            ++$linkCnt;

            if($menuEntry['type'] == 'html')
            {
                $html .= $menuEntry['content'];
            }
            elseif($menuEntry['type'] == 'link')
            {
                // if the count of link elements greater equal then the maxMenuLinkItem variable add drop down entry
                if (count($this->items) > $this->maxMenuLinkItem
                && $linkCnt >= $this->maxMenuLinkItem)
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
?>
