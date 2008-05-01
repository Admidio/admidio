/******************************************************************************
 * Funktionen zum Handling mit Ajax
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
function createXMLHttpRequest()
{
 	var resObject = null;
 	
 	try
 	{
 		resObject = new XMLHttpRequest();
 	}
 	catch(Error)
 	{
	 	try
	 	{
	 		resObject = new ActiveXObject("MSXML2.XMLHTTP");
	 	}
	 	catch(Error)
	 	{
	 		resObject = new ActiveXObject("Microsoft.XMLHTTP");
	 	}
 	}
 	return resObject;
}
