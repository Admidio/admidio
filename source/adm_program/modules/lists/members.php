<?php
/******************************************************************************
 * Assign or remove members to role
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.result
 *
 * Parameters:
 *
 * rol_id   : role to which members should be assigned or removed
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', null, true);

$_SESSION['set_rol_id'] = $getRoleId;

//URL auf Navigationstack ablegen, wenn werder selbstaufruf der Seite, noch interner Ankeraufruf
$gNavigation->addUrl(CURRENT_URL);

// create object of the commited role
$role = new TableRoles($gDb, $getRoleId);

// roles of other organizations can't be edited
if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check if user is allowed to assign members to this role
if($role->allowedToAssignMembers($gCurrentUser) == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// output of html header
$gLayout['title']  = $gL10n->get('LST_MEMBER_ASSIGNMENT').' - '. $role->getValue('rol_name');

$gLayout['header'] ='
<script type="text/javascript"><!--
    //Erstmal warten bis Dokument fertig geladen ist
    $(document).ready(function(){       
        //Bei Seitenaufruf Daten laden
        $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$getRoleId.'", $("#memserach_form").serialize(), function(result){
            $("form#memlist_form").append(result).show();
            $("#list_load_animation").hide();
            return false;
        });
        
        //Checkbox alle Benutzer anzeigen
        $("input[type=checkbox]#mem_show_all").live("click", function(){
            $("#list_load_animation").show();
            $("form#memlist_form").hide().empty();
            $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$getRoleId.'", $("#memsearch_form").serialize(), function(result){
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
            $.post("'.$g_root_path.'/adm_program/modules/lists/members_get.php?rol_id='.$getRoleId.'", $("#memsearch_form").serialize(), function(result){
                $("form#memlist_form").empty().append(result).show();
                $("#list_load_animation").hide();                               
            });
            return false;
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

            var member_checked = $("input[type=checkbox]#member_"+userid).prop("checked");
            var leader_checked = $("input[type=checkbox]#leader_"+userid).prop("checked");

            //Bei Leiter Checkbox setzten, muss Member mit gesetzt werden
            if(checkboxtype=="leader" && leader_checked){                
                $("input[type=checkbox]#member_"+userid).prop("checked", true);
                member_checked = true;
            }
            
            //Bei entfernen der Mitgliedschaft endet auch das Leiterdasein
            if(checkboxtype=="member" && member_checked==false){                
                $("input[type=checkbox]#leader_"+userid).prop("checked", false);
                leader_checked = false;
            }';
            
            //Bei der Rolle Webmaster muss konrolliert werden ob noch mindestend ein User Mitglied bleibt
            if($role->getValue('rol_webmaster') == 1)
            {
                $gLayout['header'] .='
                if($("input[name^=\'member_\'].memlist_checkbox:checked").size()<1){
                   //Checkbox wieder setzen. 
                   $("input[type=checkbox]#member_"+userid).prop("checked", true);
                   //Alarm schlagen
                   jQueryAlert("LST_MUST_HAVE_WEBMASTER");
                   return false;
                }';
            }                
            
            $gLayout['header'] .='                     
            //Ladebalken an checkbox
            $("#loadindicator_" + checkbox_id).append("<img src=\''.THEME_PATH.'/icons/loader_inline.gif\' alt=\'loadindicator\' />").show();
                                 
            //Datenbank schreiben
            $.ajax({
                    url: "'.$g_root_path.'/adm_program/modules/lists/members_save.php?rol_id='.$getRoleId.'&usr_id="+userid,
                    type: "POST",
                    data: "member_"+userid+"="+member_checked+"&leader_"+userid+"="+leader_checked,
                    async: false,
                    success: function(result){                    
                       $("#loadindicator_" + checkbox_id).hide().empty();

                       //Fehler Maximale Mitgliederzahl überschritten
                       if(result=="max_mem_reached")
                       {
                            //Bei Leiter Checkbox deaktiviert, muss Member und Leiter wieder gesetzt werden                            
                            if(checkboxtype=="leader" && $("input[type=checkbox]#leader_"+userid).prop("checked")==false){                
                                $("input[type=checkbox]#leader_"+userid).prop("checked", true);
                            }
                            else{
                                $("input[type=checkbox]#member_"+userid).prop("checked", false);
                            }                           
                           jQueryAlert("SYS_ROLE_MAX_MEMBERS", "'.$role->getValue('rol_name').'");
                       }
                       else if(result=="success")
                       {}                    
                       else
                       {
                           jQueryAlert("SYS_INVALID_PAGE_VIEW");
                       }
                       return false;
                    }
            });
        });

        $("a[rel=\'lnkNewUser\']").colorbox({rel:\'nofollow\',onComplete:function(){$("#lastname").focus();}});
     });            
//--></script>';
        
require(SERVER_PATH. '/adm_program/system/overall_header.php');
echo '<h1>'. $gLayout['title']. '</h1>';

//Suchleiste
echo '
<form id="memsearch_form">
    <ul class="iconTextLinkList">
        <li>'.$gL10n->get('SYS_SEARCH').': <input type="text" name="mem_search" id="mem_search" /></li>
        <li><input type="checkbox" name="mem_show_all" id="mem_show_all" /><label for="mem_show_all">'.$gL10n->get('MEM_SHOW_ALL_USERS').'</label></li>
        <li>
	        <span class="iconTextLink" id="add_user_link" style="display: none;">
		        <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php"><img src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('MEM_CREATE_USER').'" /></a>
		        <a rel="lnkNewUser" href="'.$g_root_path.'/adm_program/administration/members/members_new.php">'.$gL10n->get('MEM_CREATE_USER').'</a>
	        </span>
        </li>
    </ul>
</form>';

//ladebalken
echo '<img src="'.THEME_PATH.'/images/loading_animation.gif" alt="'.$gL10n->get('SYS_PROGRESS_BAR').'" id="list_load_animation"/>';

//Liste mit Namen zu abhaken
echo '<form id="memlist_form"></form>';

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($gNavigation->count() > 1)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}


require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>