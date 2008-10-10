/******************************************************************************
 * Funktionen zum dynamischen Loeschen von Eintraegen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

var objectId;

function deleteObject(type, elementId, databaseId, description)
{
    var msg_result = confirm("Willst du den Eintrag \n\n" + description + "\n\nwirklich löschen ?");
    if(msg_result)
    {
        var url  = "";
        objectId = elementId;
        
        switch (type)
        {
            case "ann":
                url = gRootPath + "/adm_program/modules/announcements/announcements_function.php?mode=2&ann_id=" + databaseId;
                break;
        }
        // RequestObjekt abschicken und Eintrag loeschen
        resObject.open("GET", url, true);
        resObject.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        resObject.onreadystatechange = afterDeleteObject;
        resObject.send(null);
    }
}

function afterDeleteObject()
{
    if(resObject.readyState == 4) 
    {
        Effect.DropOut(objectId);
    }
}
