/******************************************************************************
 * Allgemeine JavaScript-Funktionen, die an diversen Stellen in Admidio 
 * benoetigt werden
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Korrigiert PNG-Transparenz fuer IE 5.5 und 6.0 
function correctPNG()
{
   var arVersion = navigator.appVersion.split("MSIE")
   var version = parseFloat(arVersion[1])
   if ((version >= 5.5) && (document.body.filters)) 
   {
		for(var i=0; i<document.images.length; i++)
		{
			var img = document.images[i]
			var imgName = img.src.toUpperCase()
			if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
			{
				var imgID = (img.id) ? "id='" + img.id + "' " : ""
				var imgClass = (img.className) ? "class='" + img.className + "' " : ""
				var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' "
				var imgStyle = "display:inline-block;" + img.style.cssText
				var imgOnClick = " onclick=\"" + img.getAttributeNode("onclick").nodeValue + "\" "
				if (img.align == "left") imgStyle = "float:left;" + imgStyle
				if (img.align == "right") imgStyle = "float:right;" + imgStyle
				if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle
				var strNewHTML = "<span " + imgID + imgClass + imgTitle + imgOnClick
				+ " style=\"/*margin: 0px; padding: 0px;*/ " + "width:" + img.width + "px; height:" 
				+ img.height + "px;" + imgStyle + ";"
				+ "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
				+ "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>"
				img.outerHTML = strNewHTML
				i = i-1
			}
		}
	}
}

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