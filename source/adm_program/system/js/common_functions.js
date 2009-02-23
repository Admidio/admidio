/******************************************************************************
 * Allgemeine JavaScript-Funktionen, die an diversen Stellen in Admidio 
 * benoetigt werden
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Fenstergroeße an Inhalt anpassen
function windowresize()
{
	if(document.all)
	{
		breite = self.document.body.scrollWidth;
		hoehe  = self.document.body.scrollHeight+80;
	}
	
	else
	{
		breite = self.document.body.offsetWidth;
		hoehe  = self.document.body.offsetHeight+80;
	}
	window.resizeTo(breite,hoehe);
}

// Anzeigen bzw. verstecken eines Blocks
function showHideBlock(block_name)
{
	var block_element = block_name;
	var image_element = 'img_' + block_name;

	if(document.getElementById(block_element).style.visibility == 'hidden')
	{
		 document.getElementById(block_element).style.visibility = 'visible';
		 document.getElementById(block_element).style.display    = '';
		 document.getElementById(image_element).src = gThemePath + '/icons/triangle_open.gif';
	}
	else
	{
		 document.getElementById(block_element).style.visibility = 'hidden';
		 document.getElementById(block_element).style.display    = 'none';
		 document.getElementById(image_element).src = gThemePath + '/icons/triangle_close.gif';
	}
}