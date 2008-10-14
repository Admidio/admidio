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
            case "dat":
                url = gRootPath + "/adm_program/modules/dates/dates_function.php?mode=2&dat_id=" + databaseId;
                break;
            case "gbo":
                url = gRootPath + "/adm_program/modules/guestbook/guestbook_function.php?mode=2&id=" + databaseId;
                break;
            case "gbc":
                url = gRootPath + "/adm_program/modules/guestbook/guestbook_function.php?mode=5&id=" + databaseId;
                break;
            case "lnk":
                url = gRootPath + "/adm_program/modules/links/links_function.php?mode=2&lnk_id=" + databaseId;
                break;
            case "new_user":
                url = gRootPath + "/adm_program/administration/new_user/new_user_function.php?mode=4&new_user_id=" + databaseId;
                break;
        }
        if(url.length > 0)
        {
            // RequestObjekt abschicken und Eintrag loeschen
            resObject.open("GET", url, true);
            resObject.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            resObject.onreadystatechange = afterDeleteObject;
            resObject.send(null);
        }
    }
}

function afterDeleteObject()
{
    if(resObject.readyState == 4) 
    {
        Effect.DropOut(objectId);
    }
}
