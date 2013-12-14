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
 *  @code   // create module menu
 *  $myModuleMenu = new ModuleMenu('admMenuMyModule');
 *  
 *  // show link to create new announcement
 *  $myModuleMenu->addItem('admMenuItemNewEntry', $g_root_path.'/adm_program/modules/mymodule/mymodule_new.php', 
 *  						$gL10n->get('SYS_CREATE'), 'add.png');
 *  $myModuleMenu->show(); @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. '/adm_program/system/classes/form_elements.php');

class ModuleMenu
{
	/** creates the object of the module menu and initialize all class parameters
	 *  @param $id Html id of the module menu
	 */
	public function __construct($id, $maxMenuLinkItem = 3)
	{
		global $g_root_path;
		$this->id		= $id;
		$this->items	= array();
		$this->root_path = $g_root_path;
		$this->maxMenuLinkItem = $maxMenuLinkItem;
		$this->ddJS = '';
		$this->ddItemCnt = 0;				
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
	    if(strlen($link) > 0)
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
	
	/** Creates a selectbox with all categories of a category type. If an category of this selectbox is selected
     *  than the link is called and where you can select entries of this category
	 *  @param $id Html         id of the element
	 *  @param $categoryType    Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
	 *  @param $defaultCategory	Id of selected category (if id = -1 then no default category will be selected)
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
	
	/** gets the position of a given ID in the menu
	 *  @return Position of the element; Returns false of no elemnt is found
	 */
	public function getPosition($id)
	{
		$keys=array_keys($this->items);
		$keyfound=array_keys($keys,$id);
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
		              <span class="iconTextLink">
				          <a href="'.$menuEntry['link'].'"><img 
				              src="'.$menuEntry['icon'].'" alt="'.$menuEntry['text'].'" title="'.$menuEntry['text'].'" /></a>
				          <a href="'.$menuEntry['link'].'">'.$menuEntry['text'].'</a>
				      </span>
				  </li>';
        return $html;
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


	/** creates a drop down menu
	 *  @param $ddIdName     html id name of drop down menu
	 *  @param $ddSelectText pre select text of drop down menu 
	 *  @param $ddImagePos   position of image might be "left" or "right"
	 *  @param $ddWidth      width in px of drop down menu "auto" means width will be calculated
	 *  @param $ddMaxWidth   maximum width of drop down if $ddWidth is "auto"
	 *  @return HTML drop down menu
	 */
	private function createDropDown($ddIdName, $ddSelectText, $ddImagePos = "left", $ddWidth = '"auto"', $ddMaxWidth = 188)
	{
		if ($this->ddItemCnt == 0)
			return '';

		return '<li><span class="iconTextLink"><div id="'.$ddIdName.'"></div></span></li>
						<script type="text/javascript"><!--					
						var '.$ddIdName.'DDData = ['. $this->ddJS .'];
						$("#'.$ddIdName.'").ddslick({
							data:'.$ddIdName.'DDData,
							width: '.$ddWidth.',
							maxWidth: '.$ddMaxWidth.',
							selectText: "<img class=\"dd-selected-image\" src=\"'.THEME_PATH.'/icons/list-point.png\" /> '.$ddSelectText.'",
							imagePosition:"'.$ddImagePos.'",
							background: "none",
							updateSelectedIndex: false,
							onSelected: function(selectedData) {
								if (selectedData["selectedData"]["js"])
									eval(selectedData["selectedData"]["js"]);
								else if (selectedData["selectedData"]["link"])
									window.location = selectedData["selectedData"]["link"];
								else
									jQueryAlert("SYS_ERROR");
							}
						});
						//--></script>';
	}
	
	/** Creates the html output of the module menu. Each added menu item will be displayed.
	 *  If there are more menu items then in @b maxMenuLinkItem defined a drowdown menu
	 *  will be displayed and all other items will be displayed there.
	 *  @return Returns the html output for the complete menu
	 */
	public function show()
	{
		if(count($this->items) == 0)
			return;

		global $gL10n;
		
		$html = '<ul id="'.$this->id.'" class="iconTextLinkList">';

		$linkCnt = 0;		

		foreach($this->items as $key => $menuEntry)
		{
			++$linkCnt;

			if($menuEntry['type'] == 'category')
			{
				// create select box with all categories that have links
				$calendarSelectBox = FormElements::generateCategorySelectBox($menuEntry['categoryType'], $menuEntry['defaultCategory'], 
																			 $menuEntry['id'].'SelectBox', $gL10n->get('SYS_ALL'), true);
									
				// dates have other calendar as name for categories
				if($menuEntry['categoryType'] == 'DAT')
				{
    				$textManageCategories = $gL10n->get('DAT_MANAGE_CALENDARS');
				}
				else
				{
    				$textManageCategories = $gL10n->get('SYS_MAINTAIN_CATEGORIES');
				}
						
				if(strlen($calendarSelectBox) == 0)
				{
				    // if no category was found then show link to manage categories if user has the right
				    if($menuEntry['admin'] == true)
				    {
    				    $menuEntry['icon'] = THEME_PATH.'/icons/edit.png';
    				    $menuEntry['text'] = $textManageCategories;
    				    $menuEntry['link'] = $this->root_path.'/adm_program/administration/categories/categories.php?type='.$menuEntry['categoryType'].'&title='.$menuEntry['text'];
    				    $html .= $this->createIconTextLink($menuEntry);
                    }
    				continue;
				}

				// show category select box with link to calendar preferences
				$html .= '
				<script type="text/javascript"><!--
					$(document).ready(function() {
						$("#'.$menuEntry['id'].'SelectBox").change(function () {
							self.location.href = "'.$menuEntry['link'].'" + $(this).val();
						});
					}); 
				//--></script>

			   <li id="'.$menuEntry['id'].'">'.$menuEntry['text'].':&nbsp;&nbsp;'.$calendarSelectBox;

					if($menuEntry['admin'] == true)
					{
    				    // show link to manage categorie
						$html .= '&nbsp;<a class="iconLink" href="'.$this->root_path.'/adm_program/administration/categories/categories.php?type='.$menuEntry['categoryType'].'&title='.$menuEntry['text'].'"><img
							src="'. THEME_PATH. '/icons/edit.png" alt="'.$textManageCategories.'" title="'.$textManageCategories.'" /></a>';
					}
				$html .= '</li>';
			}
			else if($menuEntry['type'] == 'link')
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

		// if drop down elements exists create DropDown menu 
		if($this->ddItemCnt > 0)
		{
			$dropDownText = $this->maxMenuLinkItem > 0 ? $gL10n->get('SYS_MORE_FEATURES') : $gL10n->get('SYS_FEATURES');
			$html .= $this->createDropDown('linkItemDropDown', $dropDownText);
		}
			
		
		$html .= '</ul>';
		echo $html;
	}	
}
?>