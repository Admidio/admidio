<?php
/******************************************************************************
 * Class manages display of menus
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
	// constructor
	public function __construct($id, $title)
	{
		global $g_root_path;
		$this->id		= $id;
		$this->title	= $title;
		$this->items	= array();
		$this->root_path= $g_root_path;
	}
	
	private function mkItem($id, $link, $text, $icon, $desc='')
	{
		// add root path to link unless the full URL is given
		if (preg_match('/^http(s?):\/\//', $link)==0)
		{
			$link = $this->root_path . $link;
		}
		// add THEME_PATH to images unless the full URL is given
		if (preg_match('/^http(s?):\/\//', $icon)==0)
		{
			$icon = THEME_PATH . $icon;
		}
		return array('id'=>$id, 'link'=>$link, 'text'=>$text, 'icon'=>$icon, 'desc'=>$desc, 'subitems'=>array());
	}

	public function addItem($id, $link, $text, $icon, $desc='')
	{
		$this->items[$id] = $this->mkItem($id, $link, $text, $icon, $desc);
	}
	
	public function addSubItem($parentId, $id, $link, $text)
	{
		// add root path to link unless the full URL is given
		if (preg_match('/^http(s?):\/\//', $link)==0)
		{
			$link = $this->root_path . $link;
		}
		$this->items[$parentId]['subitems'][$id] = array('link'=>$link, 'text'=>$text);
	}
	
	// gets the position of a given ID in the menu
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
	
    /** Create the html menu from the internal array that must be filled before.
     *  You have the option to create a simple menu with icon and link or 
     *  a more complex menu with submenu and description text.
     *  @param $type         Create a @b simple menu as default. If you set the param to @b complex 
     *                       then you will create a menu with submenus and description
     *  @param $directOutput If set to @b true (default) the form html will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the form.
     */
	public function show($type='simple', $directOutput = true)
	{
        $html         = '';
        $cssMenuClass = '';
        $cssFontClass = '';

		if ($type == 'complex')
		{
			$html .= '<h2 class="admHeadline2" id="head_'.$this->id.'">'.$this->title.'</h2>';			// Title of the menu
            $cssMenuClass = ' admMenuLargeIcons';
            $cssFontClass = ' admBigFont';
		}
		else
		{
			$html .= '<h3 class="admHeadline3" id="head_'.$this->id.'">'.$this->title.'</h3>';			// Title of the menu
		}

        $html .= '<ul class="admIconTextLinkList admMenu'.$cssMenuClass.'" id="menu_'.$this->id.'">';		// Wraps all menu items
		
        // now create each menu item
		foreach($this->items as $key => $value)
		{
            $html .= '
            <li id="lmenu_'.$this->id.'_' .$this->items[$key]['id'].'">
                <span class="admIconTextLink'.$cssFontClass.'"><a href="'.$this->items[$key]['link'].'"><img src="'.$this->items[$key]['icon'].'"
                    alt="'.$this->items[$key]['text'].'" title="'.$this->items[$key]['text'].'" /></a>
                    <a href="'.$this->items[$key]['link'].'">'.$this->items[$key]['text'].'</a></span>';

			if ($type == 'complex')
			{
				// adding submenus if any
				if ($this->items[$key]['subitems'])
				{
					$separator = '';
					$html .= '<div class="admMenuSubmenu">&#91; ';
					foreach($this->items[$key]['subitems'] as $subkey => $subvalue)
					{
						$html .= $separator . '<a href="'.$this->items[$key]['subitems'][$subkey]['link'].'">'.$this->items[$key]['subitems'][$subkey]['text'].'</a>';
						$separator = '&nbsp;| ';
					}
					$html .= ' &#93;</div>';
				}
				
				$html .= '<div class="admMenuDescription admSmallFont">'.$this->items[$key]['desc'].'</div>';
			}
            $html .= '</li>';
		}
		
        $html .= '</ul>';												// End Wraps all menu items

		if (count($this->items) > 0)
		{
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
}
?>