/******************************************************************************
 * Funktionen zum dynamischen moderieren vom Gästebuch
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Kris Reber
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

var objectModerated;

function moderateObject(type, elementId, databaseId, description)
{
    var msg_result = confirm("Willst du den Eintrag \n\n" + description + "\n\nwirklich freischalten ?");
    if(msg_result)
    {
        var url  = "";
        objectModerated = document.getElementById(elementId);
        
        switch (type)
        {
            case "gbo":
                url = gRootPath + "/adm_program/modules/guestbook/guestbook_function.php?mode=9&id=" + databaseId;
                break;
        }
        if(url.length > 0)
        {
            // RequestObjekt abschicken und Eintrag freischalten
            resObject.open("GET", url, true);
            resObject.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            resObject.onreadystatechange = afterModerateObject;
            resObject.send(null);
        }
    }
}

function afterModerateObject()
{
    if(resObject.readyState == 4 && resObject.status == 200) 
    {
        if(resObject.responseText == "done")
        {
            $(objectModerated).fadeOut("slow");
        }
        else
        {
            alert("Es ist ein Fehler aufgetreten !\n\nDer Eintrag konnte nicht freigeschaltet werden.");
        }
    }
}
