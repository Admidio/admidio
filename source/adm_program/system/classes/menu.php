<?php
/******************************************************************************
 * Class manages display of menus
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * Description  : Create, modify and display menus. Each menu item is defined by
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
	
	// actual method for generating the menu
	public function show($type='short')
	{
		if ($type == 'long')
		{
			$html  = '<div class="formLayout" id="'.$this->id.'_list_form">';	// Wraps the whole menu
			$html .= '<div class="formHead">'.$this->title.'</div>';			// Title of the menu
			$html .= '<div class="formBody"><ul class="formFieldList">';		// Wraps all menu items
		}
		else
		{
			$html  = '';														// Wraps the whole menu
			$html .= '<h3>'.$this->title.'</h3>';								// Title of the menu
			$html .= '';														// Wraps all menu items
		}
		
		foreach($this->items as $key => $value)
		{
			if ($type == 'long')
			{
				$html .= '<li id="lmenu_'.$this->id.'_' .
				          $this->items[$key]['id'].'"><dl>';					// Wraps each item
				
				$html .= '<dt><a href="'.$this->items[$key]['link'].'"><img src="'.$this->items[$key]['icon'].'" alt="'.$this->items[$key]['text'].'"
						  title="'.$this->items[$key]['text'].'" /></a></dt>
						  <dd><span class="veryBigFontSize"><a href="'.$this->items[$key]['link'].'">'.$this->items[$key]['text'].'</a></span>';
						  
				// adding submenus if any
				if ($this->items[$key]['subitems'])
				{
					$separator = '';
					$html .= '&nbsp;&nbsp;&#91; ';
					foreach($this->items[$key]['subitems'] as $subkey => $subvalue)
					{
						$html .= $separator . '<a href="'.$this->items[$key]['subitems'][$subkey]['link'].'">'.$this->items[$key]['subitems'][$subkey]['text'].'</a>';
						$separator = '&nbsp;| ';
					}
					$html .= ' &#93;';
				}
				
				$html .= '<br><span class="smallFontSize">'.$this->items[$key]['desc'].'</span></dd>';
				
				$html .= '</dl></li>';											// End Wraps each item
			}
			else
			{
				$html .= '<span id="smenu_'.$this->id.'_' .
						  $this->items[$key]['id'].'" class="menu">';			// Wraps each item
				
				$html .= '<a href="'.$this->items[$key]['link'].'"><img style="vertical-align: middle;" src="'.$this->items[$key]['icon'].'"
						  alt="'.$this->items[$key]['text'].'" title="'.$this->items[$key]['text'].'" /></a>
						  <a href="'.$this->items[$key]['link'].'">'.$this->items[$key]['text'].'</a>';
				
				$html .= '</span>';												// End Wraps each item
			}
		}
		
		if ($type == 'long')
		{
			$html .= '</ul></div>';												// End Wraps all menu items
			$html .= '</div>';													// End Wraps the whole menu
		}
		else
		{
			$html .= '';														// End Wraps all menu items
			$html .= '';														// End Wraps the whole menu
		}
		if (count($this->items)>0)
		{
			echo "$html";
		}
	}	
}
?>