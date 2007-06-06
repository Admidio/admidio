/******************************************************************************
 * Anzeigen bzw. verstecken eines Blocks
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

function showHideBlock(block_name, root_path)
{
	var block_element = 'cat_' + block_name;
	var link_element  = 'lnk_' + block_name;
	var image_element = 'img_' + block_name;

	if(document.getElementById(block_element).style.visibility == 'hidden')
	{
		 document.getElementById(block_element).style.visibility = 'visible';
		 document.getElementById(block_element).style.display    = '';
		 document.getElementById(link_element).innerHTML         = 'ausblenden';
		 document.images[image_element].src = root_path + '/adm_program/images/bullet_toggle_minus.png';
	}
	else
	{
		 document.getElementById(block_element).style.visibility = 'hidden';
		 document.getElementById(block_element).style.display    = 'none';
		 document.getElementById(link_element).innerHTML         = 'einblenden';
		 document.images[image_element].src = root_path + '/adm_program/images/bullet_toggle_plus.png';
	}
}