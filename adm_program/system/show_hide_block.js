/******************************************************************************
 * Anzeigen bzw. verstecken eines Blocks
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function showHideBlock(block_name, root_path)
{
	var block_element = block_name;
	var image_element = 'img_' + block_name;

	if(document.getElementById(block_element).style.visibility == 'hidden')
	{
		 document.getElementById(block_element).style.visibility = 'visible';
		 document.getElementById(block_element).style.display    = '';
		 document.images[image_element].src = root_path + '/adm_program/images/triangle_open.gif';
	}
	else
	{
		 document.getElementById(block_element).style.visibility = 'hidden';
		 document.getElementById(block_element).style.display    = 'none';
		 document.images[image_element].src = root_path + '/adm_program/images/triangle_close.gif';
	}
}