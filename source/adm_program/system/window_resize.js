/******************************************************************************
 * Fenstergroeße an Inhalt anpassen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 

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
