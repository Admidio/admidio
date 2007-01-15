/******************************************************************************
 * Funktionen zum Handling mit Ajax
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
