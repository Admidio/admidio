/******************************************************************************
 * Funktionen zum entfernen von Rollen im Profil
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
var resObject     = createXMLHttpRequest();
var id;

function deleteFormerRole(rol_id, rol_name, usr_id, root_path) 
{
    var msg_result = confirm('Willst du den Verweis auf die ehemalige Mitgliedschaft bei der Rolle ' + rol_name + ' wirklich entfernen ?');
    if(msg_result)
    {
        id      = 'former_role_' + rol_id;

        resObject.open('POST', root_path + '/adm_program/modules/profile/profile_function.php', true);
        resObject.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        resObject.onreadystatechange = handleResponse;
        resObject.send('mode=3&user_id=' + usr_id + '&rol_id=' + rol_id);
    }
}

function deleteRole(rol_id, rol_name, rol_valid, usr_id, cat_name, mem_begin, mem_leader, b_webmaster, root_path, theme)
{
    var msg_result = confirm('Willst du die Mitgliedschaft bei der Rolle ' + rol_name + ' wirklich beenden ?');
    if(msg_result)
    {
        var newListElement = document.createElement('li');
        var now       = new Date();
        var end_date  = now.getDate() + '.' + now.getMonth() + '.' + now.getFullYear();
        var leader    = '';
        var webmaster = '';
        
        id      = 'role_' + rol_id;

        if(mem_leader == 1)
        {
            leader = ' - Leiter';
        }
        if(b_webmaster)
        {
            webmaster = ' <a class=\"iconLink\" href=\"javascript:deleteFormerRole(' + rol_id + ', \'' + rol_name + '\', \'' + usr_id + '\', \'' + root_path + '\')\"><img ' +
            'src=\"' + root_path + '/adm_themes/' + theme + '/icons/cross.png\" alt=\"Rolle löschen\" title=\"Rolle löschen\"></a>';
        }
        var html = '<dl><dt>' + cat_name + ' - ' + '<a href=\"' + root_path + '/adm_program/modules/lists/lists_show.php?type=address&mode=html&rol_id=' + rol_id + '\">' + rol_name + '</a>' + 
                    leader + '</dt><dd>vom ' + mem_begin + ' bis ' + 
                    end_date + webmaster + '</dd></dl>';
        
        // Listenelement mit Unterelemten einfuegen
        document.getElementById('profile_former_roles_box').style.display = 'block';
        newListElement.setAttribute('id', 'former_role_' + rol_id);
        newListElement.innerHTML = html;
        document.getElementById('former_role_list').appendChild(newListElement);

        resObject.open('POST', root_path + '/adm_program/modules/profile/profile_function.php', true);
        resObject.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        resObject.onreadystatechange = handleResponse;
        resObject.send('mode=2&user_id=' + usr_id + '&rol_id=' + rol_id);
    }
}

function handleResponse() 
{
    if(resObject.readyState == 4) 
    {
    	Effect.DropOut(id);
    }
}