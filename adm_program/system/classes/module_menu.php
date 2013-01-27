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
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
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
		$this->root_path= $g_root_path;
		$this->maxMenuLinkItem = $maxMenuLinkItem;
	}
	
	/** add new entry to array and do some checks before so that link and icon get
	 *  a valid url
	 *  @param $id Html id of the element
	 *  @param $type The different type of menu that should be shown: @b link normal link with icon; @b category category select box
	 *  @param $link Link to the page that will be called if menu item is clicked
	 *  @param $text Link text
	 *  @param $icon Icon of the menu item, that will also be linked
	 *  @param $desc Optional description of the menu item
	 *  @param $js   Javascript to be executed
	 */
	private function mkItem($id, $type, $link, $text, $icon, $js = '')
	{
		// add root path to link unless the full URL is given
		if (preg_match('/^http(s?):\/\//', $link)==0)
		{
			$link = $this->root_path . $link;
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
	 *  @param $id Html id of the element
	 *  @param $categoryType Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
	 *  @param $defaultCategory	Id of selected category (if id = -1 then no default category will be selected)
	 *  @param $link Link to the page that will be called if menu item is clicked. At the end of this link the ID if the category will be added automatically, so you can add a category parameter at last
	 *  @param $text Text of the selectbox
	 *  @param $admin Set to @b true if user has admin rights in this category, than a link to administrate the catories is shown.
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

	/** gets an array of menu entries of a given type
	 *  @return array of menu entrues
	 */
	private function getSpecificMenueEntry($type)
	{
		$retArray = array();
		foreach($this->items as $key => $menuEntry)
		{
			if ($menuEntry['type'] == $type)
				array_push($retArray, $menuEntry);
		}
		return $retArray;
	}

	/** creates an text link icon
	 *  @return HTML of created item
	 */
	private function createIconTextLink(&$menuEntry)
	{
		return '<li id="'.$menuEntry['id'].'">
						<span class="iconTextLink">
							<a href="'.$menuEntry['link'].'">
								<img src="'.$menuEntry['icon'].'" alt="'.$menuEntry['text'].'" title="'.$menuEntry['text'].'" />
							</a>
							<a href="'.$menuEntry['link'].'">'.$menuEntry['text'].'</a>
						</span>
					</li>';
	}

	/** creates text link icons from given array
	 *  @return HTML of created items
	 */
	private function createIconTextLinkFromArray(&$menuEntryArray, $start = 0, $stop = -1 )
	{
		$arrayCount = count($menuEntryArray);
		if ($arrayCount == 0)
			return '';
		if ($start > $arrayCount)
			return '';
		if ($stop > 0 && $stop < $arrayCount)
			$arrayCount = $stop;

		$retHTML = '';
		for ($i = $start; $i < $arrayCount; ++$i)
			$retHTML .= $this->createIconTextLink($menuEntryArray[$i]);
		return $retHTML;
	}

	/** creates a drop down menu
	 *  @return HTML drop down menu
	 */
	private function createDropDown(&$itemArray, $selectedItemId = '', $ddIdName, $ddText, $start = 0, $stop = -1, $ddImagePos = "left", $ddWidth = '"auto"', $ddMaxWidth = 1000)
	{
		$arrayCount = count($itemArray);
		if ($arrayCount == 0)
			return '';
		if ($start > $arrayCount)
			return '';
		if ($stop > 0 && $stop < $arrayCount)
			$arrayCount = $stop;

		$ddJS = '';

		$itemCount = 0;			
		for ($i = $start; $i < $arrayCount; ++$i)
		{
			$menuEntry = $itemArray[$i];

			$selected = $selectedItemId == $menuEntry['id'] ? 'true' : 'false';
			$ddJS .= '
			{
				text: "'.$menuEntry['text'].'",
				value: '.++$itemCount.',
				selected: '.$selected.',
				imageSrc: "'.$menuEntry['icon'].'",
				link: "'.$menuEntry['link'].'",
				js: "'.$menuEntry['js'].'"
			}
			';

			if ($i + 1 < $arrayCount)
				$ddJS .= ',';			
		}

		return '<li><div id="'.$ddIdName.'"></div></li>
						<script type="text/javascript"><!--					
						var '.$ddIdName.'DDData = ['. $ddJS .'];
						$("#'.$ddIdName.'").ddslick({
							data:'.$ddIdName.'DDData,
							width: '.$ddWidth.',
							maxWidth: '.$ddMaxWidth.',
							selectText: "'.$ddText.'",
							imagePosition:"'.$ddImagePos.'",
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
	
	/** Creates the html output of the module menu
	 */
	public function show()
	{
		if(count($this->items) == 0)
			return;

		global $gL10n;
		
		$html = '<ul id="'.$this->id.'" class="iconTextLinkList">';

		// get entries with type of link
		$linkArray = $this->getSpecificMenueEntry('link');
		$linkArrayCount = count($linkArray);

		if ($linkArrayCount > 0)
		{
			// if the count of link elements greater then the maxMenuLinkItem variable create drop down with further items in it
			if ($linkArrayCount > $this->maxMenuLinkItem)
			{
				$html .= $this->createIconTextLinkFromArray($linkArray, 0, $this->maxMenuLinkItem - 1);

				$selectedId = "";
				$html .= $this->createDropDown($linkArray, $selectedId, 'linkItemDropDown', 'Weitere Funktionen', $this->maxMenuLinkItem - 1);
			}
			else // if not display link entries as usual
				$html .= $this->createIconTextLinkFromArray($linkArray);
		}			

		foreach($this->items as $key => $menuEntry)
		{
			if($menuEntry['type'] == 'category')
			{
				// create select box with all categories that have links
				$calendarSelectBox = FormElements::generateCategorySelectBox($menuEntry['categoryType'], $menuEntry['defaultCategory'], 
																			 $menuEntry['id'].'SelectBox', $gL10n->get('SYS_ALL'), true);
						
				if(strlen($calendarSelectBox) == 0)
					continue;

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
						$html .= '<a class="iconLink" href="'.$this->root_path.'/adm_program/administration/categories/categories.php?type='.$menuEntry['categoryType'].'"><img
							src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" title="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>';
					}
				$html .= '</li>';
			}
		}
		
		$html .= '</ul>';
		echo $html;
	}	
}
?>