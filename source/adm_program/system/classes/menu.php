<?php
/******************************************************************************
 * Class manages display of menus
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/ 

class Menu
{
	// constructor
    public function __construct($id, $title)
    {
        $this->id		= $id;
		$this->title	= $title;
		$this->items	= array();
    }
	
	private function mkItem($link, $text, $smallIcon, $bigIcon='', $longDesc='')
	{
		$bigIcon = ($bigIcon == '')? $smallIcon : $bigIcon;
        return array('link'=>$link, 'text'=>$text, 'smallIcon'=>$smallIcon, 'bigIcon'=>$bigIcon, 'longDesc'=>$longDesc, 'subitems'=>array());
	}

    public function addItem($id, $link, $text, $smallIcon, $bigIcon='', $longDesc='')
    {
		$this->items[$id] = $this->mkItem($link, $text, $smallIcon, $bigIcon, $longDesc);
    }
	
	public function addSubItem($parentId, $id, $link, $text)
	{
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
	public function insertItem($position, $id, $link, $text, $smallIcon, $bigIcon='', $longDesc='')
	{
		if (!is_numeric($position))
		{
			return false;
		}
		else
		{
			$head = array_slice($this->items, 0, $position);
			$insert=array($id=>$this->mkItem($link, $text, $smallIcon, $bigIcon, $longDesc));
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
				$html .= '<li><dl>';											// Wraps each item
				
				$html .= '<dt><a href="'.$this->items[$key]['link'].'"><img src="'.$this->items[$key]['bigIcon'].'" alt="'.$this->items[$key]['text'].'"
						  title="'.$this->items[$key]['text'].'"></a></dt>
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
				
				$html .= '<br><span class="smallFontSize">'.$this->items[$key]['longDesc'].'</span></dd>';
				
				$html .= '</dl></li>';											// End Wraps each item
			}
			else
			{
				$html .= '<span class="menu">';									// Wraps each item
				
				$html .= '<a href="'.$this->items[$key]['link'].'"><img style="vertical-align: middle;" src="'.$this->items[$key]['smallIcon'].'"
						  alt="'.$this->items[$key]['text'].'" title="'.$this->items[$key]['text'].'"></a>
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
