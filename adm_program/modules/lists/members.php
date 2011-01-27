<?php
/******************************************************************************
 * Mitglieder einer Rolle zuordnen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.result
 *
 * Uebergaben:
 *
 * rol_id   : Rolle der Mitglieder hinzugefuegt oder entfernt werden sollen
 * restrict : Begrenzte Userzahl:
 *            m - (Default) nur Mitglieder
 *            u - alle in der Datenbank gespeicherten user
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');

// Uebergabevariablen pruefen

if(isset($_GET['rol_id']) && is_numeric($_GET['rol_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
else
{
    $role_id = $_GET['rol_id'];
}
$_SESSION['set_rol_id'] = $role_id;

//URL auf Navigationstack ablegen, wenn werder selbstaufruf der Seite, noch interner Ankeraufruf
if(!isset($_GET['restrict']))
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}

$restrict = 'm';
if(isset($_GET['restrict']) && $_GET['restrict'] == 'u')
{
    $restrict = 'u';
}

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($g_db, $role_id);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen Mitglied der richtigen Gliedgemeinschaft sein
if(  (!$g_current_user->assignRoles()
   && !isGroupLeader($g_current_user->getValue('usr_id'), $role_id))
|| (  !$g_current_user->isWebmaster()
   && $role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
|| $role->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// result-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('LST_MEMBER_ASSIGNMENT').' - '. $role->getValue('rol_name');

$g_layout['header'] ='
<script type="text/javascript">
    //Erstmal warten bis Dokument fertig geladen ist
    $(document).ready(function(){       
        //Bei Seitenaufruf Daten laden
        $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$role_id.'", $("#memserach_form").serialize(), function(result){
            $("form#memlist_form").append(result).show();
            $("#list_load_animation").hide();
            return false;
        });
        
        //Checkbox alle Benutzer anzeigen
        $("input[type=checkbox]#mem_show_all").live("click", function(){
            $("#list_load_animation").show();
            $("form#memlist_form").hide().empty();
            $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$role_id.'", $("#memsearch_form").serialize(), function(result){
                $("form#memlist_form").append(result).show();               
                $("#list_load_animation").hide();
                return false;
            });
            //Link zum Benutzer hinzufügen anzeigen oder verstecken
            if($(this).is(":checked")){
                $("#add_user_link").show();
            }
            else{
                $("#add_user_link").hide();
            }
        });
        
        //Suchfeldeingabe
        $("input[type=text]#mem_search").keyup(function(){
            $("#list_load_animation").show();
            $("form#memlist_form").hide().empty();
            $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$role_id.'", $("#memsearch_form").serialize(), function(result){
                $("form#memlist_form").append(result).show();
                $("#list_load_animation").hide();               
                return false;
            });
        });
    
        //Enter abfangen
        $("input[type=text]#mem_search").keydown(function(e) {
            if(e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return;
            }
        });
        
        //Buchstabennavigation
        $(".pageNavigationLink").live("click", function(){
            var letter = $(this).attr("letter");            
            //Alle anzeigen
            if(letter == "all"){
                $(".letterBlockBody").show();
                $(".letterBlockHead").show();
            }
            else{
	            $(".letterBlockBody[block_body_id!="+letter+"]").hide();
	            $(".letterBlockHead[block_head_id!="+letter+"]").hide();
	            $(".letterBlockBody[block_body_id="+letter+"]").show();
	            $(".letterBlockHead[block_head_id="+letter+"]").show();
	        }
	        return false;
        });
        
        //beim anklicken einer Checkbox
        $("input[type=checkbox].memlist_checkbox").live("click", function(){
                 
            //Checkbox ID
            var checkboxtype = $(this).attr("checkboxtype");            
            var checkbox_id = $(this).attr("id");
            var userid = $(this).parent().parent().attr("user_id");

            var member_checked = $("input[type=checkbox]#member_"+userid).attr("checked");
            var leader_checked = $("input[type=checkbox]#leader_"+userid).attr("checked");
              
            //Bei Leiter Checkbox setzten, muss Member mit gesetzt werden
            if(checkboxtype=="leader" && leader_checked){                
                $("input[type=checkbox]#member_"+userid).attr("checked", "checked");
                member_checked = true;
            }
            
            //Bei entfernen der Mitgliedschaft endet auch das Leiterdasein
            if(checkboxtype=="member" && member_checked==false){                
                $("input[type=checkbox]#leader_"+userid).removeAttr("checked");
                leader_checked = false;
            }';
            
            //Bei der Rolle Webmaster muss konrolliert werden ob noch mindestend ein User Mitglied bleibt
            if($role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
            {
            	$g_layout['header'] .='
            	if($("input[name^=\'member_\'].memlist_checkbox:checked").size()<1){
                   //Checkbox wieder setzen. 
            	   $("input[type=checkbox]#member_"+userid).attr("checked", "checked");
            	   //Alarm schlagen
            	   alert("'.$g_l10n->get('LST_MUST_HAVE_WEBMASTER').'");
            	   return false;
                }';
            }                
            
            $g_layout['header'] .='                     
            //Ladebalken an checkbox
            $("#loadindicator_" + checkbox_id).append("<img src=\''.THEME_PATH.'/icons/loader_inline.gif\' alt=\'loadindicator\'  \' />").show();
                                 
            //Datenbank schreiben
            $.ajax({
                    url: "'.$g_root_path.'/adm_program/modules/lists/members_save.php?rol_id='.$role_id.'&uid="+userid,
                    type: "POST",
                    data: "member_"+userid+"="+member_checked+"&leader_"+userid+"="+leader_checked,
                    async: false,
                    success: function(result){                    
                       $("#loadindicator_" + checkbox_id).hide().empty();

                       //Fehler Maximale Mitrgliederzahl überschritten
                       if(result=="max_mem_reached")
                       {
                            //Bei Leiter Checkbox deaktiviert, muss Member und Leiter wieder gesetzt werden                            
                            if(checkboxtype=="leader" && $("input[type=checkbox]#leader_"+userid).attr("checked")==false){                
                                $("input[type=checkbox]#leader_"+userid).attr("checked", "checked");
                            }
                            else{
                                $("input[type=checkbox]#member_"+userid).removeAttr("checked");
                            }                           
                           alert("'.$g_l10n->get('SYS_ROLE_MAX_MEMBERS', $role->getValue('rol_name')).'");
                       }
                       if(result=="SYS_NO_RIGHTS")
                       {
                           alert("'.$g_l10n->get('SYS_NO_RIGHTS').'");
                       }
                       if(result=="SYS_INVALID_PAGE_VIEW")
                       {
                           alert("'.$g_l10n->get('SYS_INVALID_PAGE_VIEW').'");
                       }
                       if(result=="success")
                       {}                    
                       return false;
                    }
            });
        });

        $("a[rel=\'lnkNewUser\']").colorbox({rel:\'nofollow\',onComplete:function(){$("#lastname").focus();}});
     });            
</script>';
        
require(THEME_SERVER_PATH. '/overall_header.php');
echo '<h1>'. $g_layout['title']. '</h1>';

//Suchleiste
echo '
<form id="memsearch_form">
    <ul class="iconTextLinkList">
        <li>Suche: <input type="text" name="mem_search" id="mem_search" /></li>
        <li><input type="checkbox" name="mem_show_all" id="mem_show_all" /><label for="mem_show_all">'.$g_l10n->get('MEM_SHOW_ALL_USERS').'</lable></li>
        <li>
	        <span class="iconTextLink" id="add_user_link" style="display: none;">
		        <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('MEM_CREATE_USER').'" /></a>
		        <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php">'.$g_l10n->get('MEM_CREATE_USER').'</a>
	        </span>
        </li>
    </ul>
</form>';

//ladebalken
echo '<img src="'.THEME_PATH.'/images/loading_animation.gif" alt="'.$g_l10n->get('SYS_PROGRESS_BAR').'" id="list_load_animation"/>';

//Liste mit Namen zu abhaken
echo '<form id="memlist_form"></form>';

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($_SESSION['navigation']->count() > 1)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}


require(THEME_SERVER_PATH. '/overall_footer.php');

?>