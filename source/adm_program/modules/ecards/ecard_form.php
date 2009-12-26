<?php
/******************************************************************************
 * Grußkarte Form
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:      id des Albums dessen Bilder angezeigt werden sollen
 * photo:       Name des Bildes ohne(.jpg) spaeter -> (admidio/adm_my_files/photos/<* Gallery *>/$_GET['photo'].jpg)
 * usr_id:      Die Benutzer id an dem die Grußkarte gesendet werden soll
 *
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('ecard_function.php');
if ($g_preferences['enable_bbcode'] == 1)
{
    require('../../system/bbcode.php');
}

$funcClass 					= new FunctionClass($g_l10n);
$email_versand_liste        = array(); // Array wo alle Empfaenger aufgelistet werden (jedoch keine zusaetzlichen);
$email_versand_liste_cc     = array(); // Array wo alle CC Empfaenger aufgelistet werden;
$font_sizes                 = array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30');
$font_colors                = $funcClass->getElementsFromFile('../../system/schriftfarben.txt');
$fonts                      = $funcClass->getElementsFromFile('../../system/schriftarten.txt');
$templates                  = $funcClass->getfilenames(THEME_SERVER_PATH. '/ecard_templates/');
$template                   = THEME_SERVER_PATH. '/ecard_templates/';
$error_msg                  = '';
$msg_send_error             = $g_l10n->get('ECA_SEND_ERROR');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}
// pruefen ob User eingeloggt ist
if(!$g_valid_login)
{
 $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
// Uebergaben pruefen
if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']))
{
    $pho_id = $_GET['pho_id'];
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['photo']) && is_numeric($_GET['photo']))
{
    $photo_nr = $_GET['photo'];
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

unset($_SESSION['photo_album_request']);

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue("pho_id") == $pho_id)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    // einlesen der Veranstaltung falls noch nicht in Session gespeichert
    $photo_album = new TablePhotos($g_db);
    if($pho_id > 0)
    {
        $photo_album->readData($pho_id);
    }

    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Veranstaltung zur aktuellen Organisation gehoert
if($pho_id > 0 && $photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if ($g_valid_login && !isValidEmailAddress($g_current_user->getValue('E-Mail')))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->show($g_l10n->get('SYS_PHR_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

if (isset($_GET['usr_id']))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_valid_login)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if (is_numeric($_GET['usr_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden
    if((  $g_current_user->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0 )
    {
        $g_message->show($g_l10n->get('SYS_PHR_USER_ID_NOT_FOUND'));
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue('E-Mail')))
    {
        $g_message->show($g_l10n->get('SYS_PHR_USER_NO_EMAIL', $user->getValue('Vorname').' '.$user->getValue('Nachname')));
    }
}

// Wenn der übergebene Bildernamen und die daszugehörige Photogallerie Id
// den kompletten Pfad für das Bild generiert
$bild_server_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin').'_'.$photo_album->getValue('pho_id').'/'.$photo_nr.'.jpg';

// ruf die Funktion auf die alle Post und Get Variablen parsed
$funcClass->getVars();
$ecard_send = false;
// Wenn versucht wird die Grußkarte zu versenden werden die notwendigen FElder geprüft und wenn alles okay ist wird das Template geparsed und die Grußkarte weggeschickt
if (! empty($submit_action))
{
    // Wenn die Felder Name E-mail von dem Empaenger und Sender nicht leer und gültig sind
    if ( isValidEmailAddress($ecard['email_recipient']) && isValidEmailAddress($ecard['email_sender'])
    && ($ecard['email_recipient'] != '') && ($ecard['name_sender'] != '') )
    {
        // Wenn die Nachricht größer ist als die maximal Laenge wird sie zurückgestutzt
        if (strlen($ecard['message']) > $g_preferences['ecard_text_length'])
        {
            $ecard['message'] = substr($ecard['message'],0,$g_preferences['ecard_text_length']-1);
        }
        // Template wird geholt
        list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($ecard['template_name'],$template);
        // Wenn es einen Error gibt ihn ausgeben
        if ($error)
        {
            $error_msg = $msg_send_error;
        }
        // Wenn nicht dann die Grußkarte versuchen zu versenden
        else
        {
            // Es wird geprüft ob der Benutzer der ganzen Rolle eine Grußkarte schicken will
            $rolle = str_replace(array('Rolle_','@rolle.com'),'',$ecard['email_recipient']);
            // Wenn nicht dann Name und Email des Empfaengers zur versand Liste hinzufügen
            if(!is_numeric($rolle))
            {
                array_push($email_versand_liste,array($ecard['name_recipient'],$ecard['email_recipient']));
                $email_versand_liste_cc = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
                $ecard_html_data = $funcClass->parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue('usr_id'),$ecard['name_recipient'],$ecard['email_recipient'],$g_preferences['enable_bbcode']);
                $result = $funcClass->sendEcard($ecard,$ecard_html_data,$ecard['name_recipient'],$ecard['email_recipient'],$email_versand_liste_cc, $bild_server_path);
                // Wenn die Grußkarte erfolgreich gesendet wurde
                if ($result)
                {
                    $ecard_send = true;
                }
                // Wenn nicht dann die dementsprechende Error Nachricht ausgeben
                else
                {
                    $error_msg = $msg_send_error;
                }
            }
            // Wenn schon dann alle Namen und die duzugehörigen Emails auslesen und in die versand Liste hinzufügen
            else
            {
                $sql = 'SELECT first_name.usd_value as first_name, last_name.usd_value as last_name,
                               email.usd_value as email, rol_name
                          FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
                         RIGHT JOIN '. TBL_USER_DATA. ' as email
                            ON email.usd_usr_id = usr_id
                           AND email.usd_usf_id = '. $g_current_user->getProperty('E-Mail', 'usf_id'). '
                           AND LENGTH(email.usd_value) > 0
                          LEFT JOIN '. TBL_USER_DATA. ' as last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = '. $g_current_user->getProperty('Nachname', 'usf_id'). '
                          LEFT JOIN '. TBL_USER_DATA. ' as first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = '. $g_current_user->getProperty('Vorname', 'usf_id'). '
                         WHERE rol_id           = '. $rolle. '
                           AND rol_cat_id       = cat_id
                           AND cat_org_id       = '. $g_current_organization->getValue('org_id'). '
                           AND mem_rol_id       = rol_id
                           AND mem_begin       <= "'.DATE_NOW.'"
                           AND mem_end          > "'.DATE_NOW.'"
                           AND mem_usr_id       = usr_id
                           AND usr_valid        = 1
                           AND email.usd_usr_id = email.usd_usr_id
                         ORDER BY last_name, first_name';

                $result             = $g_db->query($sql);
                $firstvalue_name    = '';
                $firstvalue_email   = '';
                $i  = 0 ;
                while ($row = $g_db->fetch_object($result))
                {
                    if($i<1)
                    {
                        $firstvalue_name  = 'Rolle: '.$row->rol_name;
                        $firstvalue_email = '-';

                    }
                    if($row->first_name != '' && $row->last_name != '' && $row->email !='')
                    {
                        array_push($email_versand_liste,array(''.$row->first_name.' '.$row->last_name.'',$row->email));
                    }
                    $i++;
                }
                $email_versand_liste_cc = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
                $ecard_html_data = $funcClass->parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$firstvalue_name,$firstvalue_email,$g_preferences['enable_bbcode']);
                $b=0;
                foreach($email_versand_liste as $item)
                {                       
                    if($b<1)
                    {
                        $result = $funcClass->sendEcard($ecard,$ecard_html_data,$email_versand_liste[$b][0],$email_versand_liste[$b][1],$email_versand_liste_cc,$bild_server_path);
                    }
                    else
                    {
                        $result = $funcClass->sendEcard($ecard,$ecard_html_data,$email_versand_liste[$b][0],$email_versand_liste[$b][1],array(), $bild_server_path);
                    }
                    // Wenn die Grußkarte erfolgreich gesendet wurde
                    if ($result)
                    {
                        $ecard_send = true;
                    }
                    // Wenn nicht dann die dementsprechende Error Nachricht ausgeben
                    else
                    {
                        $error_msg = $msg_send_error;
                    }
                    $b++;               
                }

            }
       }
    }
    // Wenn die Felder leer sind oder ungültig dann eine dementsprechente Error Nachricht ausgeben
    else
    {
        $error_msg = $g_l10n->get('ECA_FIELD_ERROR');
    }
}
// Wenn noch keine Anfrage zum versenden der Grusskarte vorhanden ist das Grusskarten Bild setzten
else
{
    $ecard['image_name'] = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;pic_nr='.$photo.'&amp;pho_begin='.$photo_album->getValue('pho_begin').'&amp;max_width='.$g_preferences['ecard_card_picture_width'].'&amp;max_height='.$g_preferences['ecard_card_picture_height'];
}

/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
if(! empty($submit_action))
{
    $g_layout['title'] = $g_l10n->get("ECA_GREETING_CARD_SEND");
}
else
{
    $g_layout['title'] = $g_l10n->get("ECA_GREETING_CARD_EDIT");
}

$javascript = '
    <script type="text/javascript"><!--
        var basedropdiv = "basedropdownmenu";
        var dropdiv = "dropdownmenu";
        var externdiv = "extern";
        var switchdiv = "externSwitch";
        var textinputid = "Nachricht";
        var counterdiv  = "counter"
        var max_recipients = '.$g_preferences['ecard_cc_recipients'].';
        var now_recipients = 0;
        var ecardformid = "ecard_form";

		function addEvent(obj, evType, fn, useCapture)
		{
			if (obj.addEventListener)
			{
				obj.addEventListener(evType, fn, useCapture);
				return true;
			} 
			else if (obj.attachEvent)
			{
				var r = obj.attachEvent("on"+evType, fn);
				return r;
			}
		} 
        function popup_win(theURL,winName,winOptions)
        {
             win = window.open(theURL,winName,winOptions);
             win.focus();
        }
        function sendEcard()
        {
            if (check())
            {
				document.getElementById(ecardformid).onsubmit = "";
                document.getElementById(ecardformid).action                 = "'.CURRENT_URL.'";
                document.getElementById(ecardformid).target                 = "_self";
                document.getElementById(ecardformid)["submit_action"].value = "send";
                document.getElementById(ecardformid).submit();
            }
            else
            {
				document.getElementById(ecardformid).onsubmit = "";
                document.getElementById(ecardformid)["submit_action"].value = "";
            }
        }
        function check()
        {
            var error         = false;
            var error_message = "'.str_replace("<br />",'\n',$g_l10n->get("ECA_PHR_INPUT_INCORRECT")).'\n\n";

            if (document.getElementById(ecardformid)["ecard[name_sender]"] && document.getElementById(ecardformid)["ecard[name_sender]"].value == "")
            {
                error = true;
                error_message += "- '.$g_l10n->get("ECA_NAME_OF_SENDER").'\n";
            }

            if (document.getElementById(ecardformid)["ecard[email_sender]"] && (document.getElementById(ecardformid)["ecard[email_sender]"].value == "") ||
               (echeck(document.getElementById(ecardformid)["ecard[email_sender]"].value) == false))
            {
                error = true;
                error_message += "- '.$g_l10n->get("ECA_EMAIL_OF_SENDER").'\n";
            }

            if (document.getElementById(ecardformid)["ecard[name_recipient]"] && (document.getElementById(ecardformid)["ecard[name_recipient]"].value == "" || document.getElementById(ecardformid)["ecard[name_recipient]"].value == "< '.$g_l10n->get("ECA_RECIPIENT_NAME").' >"))
            {
                error = true;
                error_message += "- '.$g_l10n->get("ECA_NAME_OF_RECIPIENT", $var1=" ").'\n";
            }
            if ((document.getElementById(ecardformid)["ecard[email_recipient]"].value == "") ||
               (echeck(document.getElementById(ecardformid)["ecard[email_recipient]"].value) == false))
            {
                error = true;
                error_message += "- '.$g_l10n->get("ECA_EMAIL_OF_RECIPIENT", $var1=" ").'\n";
            }
            if (document.getElementById(ecardformid)["ecard[message]"].value == "")
            {
                error = true;
                error_message += "- '.$g_l10n->get("ECA_THE_MESSAGE").'\n";
            }
            for(var i=1; i <= now_recipients; i++)
            {
                var namedoc = document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[i]+"]"];
                var emaildoc =  document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[i]+"]"];
                var message = "";
                var goterror = false;
                if(namedoc)
                {
                    if(namedoc.value == "")
                    {
                        message += " - '.$g_l10n->get("ECA_NAME_OF_RECIPIENT", $var1=" \"+[i]+\". CC - ").' \n";
                        error = true;
                        goterror = true;
                    }
                }
                if(emaildoc)
                {
                    if(emaildoc.value == "" || !echeck(emaildoc.value))
                    {
                        message += " - '.$g_l10n->get("ECA_EMAIL_OF_RECIPIENT", $var1=" \"+[i]+\". CC - ").' \n";
                        error = true;
                        goterror = true;
                    }
                }
                if(goterror && i==1)
                {
                    error_message += \'\nCC - '.$g_l10n->get("SYS_RECIPIENT").'\n_________________________________\n\n\'+message;
                }
                else if(goterror)
                {
                    error_message += \'_________________________________\n\n\'+message;
                }
            }
            if (error)
            {
                error_message += \'\n\n'.str_replace("<br />",'\n',$g_l10n->get("ECA_PHR_FILL_INPUTS")).'\';
                alert(error_message);
                return false;  // Formular wird nicht abgeschickt.
            }
            else
            {
                return true;  // Formular wird abgeschickt.
            }
            return false;
        } // Ende function check()
        function echeck(str)
        {
            var zeichen=Array("<",">")
            var at="@"
            var dot="."
            var lat=str.indexOf(at)
            var lstr=str.length
            var ldot=str.indexOf(dot)

            for(var i=0;i<zeichen.length;i++)
            {
                if (str.indexOf(zeichen[i])!=-1){
                return false
                }
            }

            if (str.indexOf(at)==-1){
            return false
            }

            if (str.indexOf(at)==-1 || str.indexOf(at)==0 || str.indexOf(at)==lstr){
            return false
            }

            if (str.indexOf(dot)==-1 || str.indexOf(dot)==0 || str.indexOf(dot)==lstr){
            return false
            }

            if (str.indexOf(at,(lat+1))!=-1){
            return false
            }

            if (str.substring(lat-1,lat)==dot || str.substring(lat+1,lat+2)==dot){
            return false
            }

            if (str.indexOf(dot,(lat+2))==-1){
            return false
            }

            if (str.indexOf(" ")!=-1){
            return false
            }

            return true
        }
        function makePreview()
        {
            document.getElementById(ecardformid).action = "ecard_preview.php";
            popup_win("","ecard_preview","resizable=yes,scrollbars=yes,width=1024,height=1024");
            document.getElementById(ecardformid).target = "ecard_preview";
            document.getElementById(ecardformid).submit();
        }
		function tb_sendform(f,c)
		{
 			f.action.match(/(\bkeepThis=(true|false)&TB_iframe=true.+$)/);
  			tb_show(c, "about:blank?" + RegExp.$1);
 			f.target=$("#TB_iframeContent").attr("name")
  			return true;
		}
		function calculateWidthHeightForThickBox()
		{
			var viewportwidth		= 0;
			var viewportheight		= 0;
			var tb_widthheight = new Array(0,0);

			if( typeof( window.innerWidth ) == "number" ) 
			{
				//Non-IE
				viewportwidth = window.innerWidth;
				viewportheight = window.innerHeight;
			} 
			else if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) 
			{
				//IE 6+ in "standards compliant mode"
				viewportwidth = document.documentElement.clientWidth;
				viewportheight = document.documentElement.clientHeight;
			} 
			else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) 
			{
				//IE 4 compatible
				viewportwidth = document.body.clientWidth;
				viewportheight = document.body.clientHeight;
			}
			
			tb_widthheight[0] 	= viewportheight * 0.8;
			tb_widthheight[1] 	= viewportwidth * 0.8;
			
			return tb_widthheight;
		}
		function makeThickBoxPreview()
		{
			tb_widthheight = calculateWidthHeightForThickBox();
			document.getElementById("ecard_form").action = "ecard_preview.php?keepThis=true&TB_iframe=true&width="+tb_widthheight[1].toFixed(0)+"&heigth="+tb_widthheight[0].toFixed(0);
		}
        function blendout(id)
        {
            if(document.getElementById(id).value == "< '.$g_l10n->get("ECA_RECIPIENT_NAME").' >" || document.getElementById(id).value == "< '.$g_l10n->get("ECA_RECIPIENT_EMAIL").' >")
            {
                document.getElementById(id).value = "";
            }
        }
        function blendin(id,type)
        {
            if(document.getElementById(id).value == "" && type == 1)
            {
                document.getElementById(id).value = "< '.$g_l10n->get("ECA_RECIPIENT_NAME").' >";
            }
            else if(document.getElementById(id).value == "" && type == 2)
            {
                document.getElementById(id).value = "< '.$g_l10n->get("ECA_RECIPIENT_EMAIL").' >";
                document.getElementById(id).style.color = "black";
                document.getElementById(\'Menue\').style.height = "49px";
                document.getElementById(\'wrong\').style.display = "none";
                document.getElementById(\'wrong\').innerHTML = "";
            }
            else if(document.getElementById(id).value != "" && document.getElementById(id).value != "< '.$g_l10n->get("ECA_RECIPIENT_EMAIL").' >"&& type == 2)
            {
                if(!echeck(document.getElementById(id).value))
                {
                    document.getElementById(id).style.color = "red";
                    document.getElementById(\'wrong\').style.display = "block";
                    document.getElementById(\'Menue\').style.height = "75px";
                    document.getElementById(\'wrong\').innerHTML = "'.$g_l10n->get("ECA_EMAIL_LOOKS_INVALID").'";
                }
                else
                {
                    document.getElementById(id).style.color = "black";
                    document.getElementById(\'Menue\').style.height = "49px";
                    document.getElementById(\'wrong\').style.display = "none";
                    document.getElementById(\'wrong\').innerHTML = "";
                }
            }
        }

        function macheRequest(seite,divId)
        {
            var xmlHttp;
            try
            {
                // Firefox, Opera 8.0+, Safari
                xmlHttp=new XMLHttpRequest();
            }
            catch (e)
            {
                // Internet Explorer
                try
                {
                    xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
                }
                catch (e)
                {
                    try
                    {
                        xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
                    }
                    catch (e)
                    {
                        alert("Your browser does not support AJAX!");
                        return false;
                    }
                }
            }
            xmlHttp.onreadystatechange=function()
            {
                if(xmlHttp.readyState==1 && document.getElementById(divId))
                {
                    document.getElementById(divId).innerHTML = "'.$g_l10n->get("ECA_CONTENT_LOADING").'";
                }
                if(xmlHttp.readyState==4 && document.getElementById(divId))
                {
                    document.getElementById(divId).innerHTML = xmlHttp.responseText;
                }
            }
            xmlHttp.open("GET",seite,true);
            xmlHttp.send(null);
        }
        function getMenu()
        {
			window.setTimeout("macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?base=1\' , basedropdiv );", 500);
        }
        function getMenuRecepientName()
        {
            if(document.getElementById(ecardformid).rol_id.value != "externMail")
            {
                macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?rol_id=\'+ document.getElementById(ecardformid).rol_id.value , \'dropdownmenu\' );
            }
            else
            {
                getExtern()
            }
        }
        function getMenuRecepientNameEmail(usr_id)
        {
            if(usr_id != "bw")
            {
                macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=\'+ usr_id + \'&rol_id=\'+ document.getElementById(ecardformid).rol_id.value, externdiv );
            }
            else
            {
                document.getElementById(externdiv).innerHTML = \'<input type="hidden" name="ecard[email_recipient]" value="" \/><input type="hidden" name="ecard[name_recipient]"  value="" \/>\';
            }
        }
        function saveData()
        {
            var savedata = new Array();
            for(var i=1; i <= now_recipients; i++)
            {
                var namedoc = document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[i]+"]"];
                var emaildoc =  document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[i]+"]"];
                if(namedoc)
                {
                    namedoc = document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[i]+"]"].value;
                }
                else
                {
                    namedoc = "";
                }
                if(emaildoc)
                {
                    emaildoc =  document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[i]+"]"].value;
                }
                else
                {
                    emaildoc = "";
                }
                savedata[i] = new Array();
                savedata[i][0] = emaildoc;
                savedata[i][1]  = namedoc;
            }
            return savedata;
        }
        function restoreSavedData(saved_data)
        {
            var i = 0;
            for (var i = 0; i < saved_data.length; i++)
            {
                var namedoc = document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[i]+"]"];
                var emaildoc =  document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[i]+"]"];
                if(emaildoc)
                {
                    emaildoc.value = saved_data[i][0];
                }
                if(namedoc)
                {
                    namedoc.value = saved_data[i][1];
                }
            }
            saved_data = "";
        }
        function addRecipient()
        {
            if (now_recipients < max_recipients)
            {
                now_recipients++;
                var data    = \'<div id="\'+ [now_recipients] +\'">\';
                data += \'<table id="table_\'+ [now_recipients] +\'" border="0" summary="data\'+ [now_recipients] +\'">\';
                data += \'<tr>\';
                data += \'<td style="width:150px;"><input name="ecard[name_ccrecipient_\'+ [now_recipients] +\']" size="15" maxlength="50" style="width: 150px;" value="" type="text" /><\/td>\';
                data += \'<td style="width:200px; padding-left:10px;"><input name="ecard[email_ccrecipient_\'+ [now_recipients] +\']" size="15" maxlength="50" style="width: 200px;" value="" type="text" /><\/td><td><span class="iconTextLink"><a href="javascript:delRecipient(\'+ [now_recipients] +\');"><img src="'.THEME_PATH.'/icons/delete.png" alt="Inhalt löschen" \/><\/a><\/span><\/td>\';
                data += \'<\/tr><\/table>\';
                data += \'<\/div>\';
                var saved_data = new Array();
                saved_data = saveData();
                document.getElementById(\'ccrecipientContainer\').innerHTML += data ;
                restoreSavedData(saved_data);
                saved_data = "";
                if (now_recipients > 0)
                {
                    document.getElementById(\'moreRecipient\').style.display = "block";
                    document.getElementById(\'getmoreRecipient\').innerHTML = "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">'.$g_l10n->get("ECA_NO_MORE_RECIPIENTS").'<\/a>";
                }
            }
        }
        function delContent(id)
        {
            var namedoc = document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[id]+"]"];
            var emaildoc =  document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[id]+"]"];
            if(namedoc)
            {
                document.getElementById(ecardformid)["ecard[name_ccrecipient_"+[id]+"]"].value = "";
            }
            if(emaildoc)
            {
                document.getElementById(ecardformid)["ecard[email_ccrecipient_"+[id]+"]"].value = "";
            }
        }
        function delRecipient(id)
        {
            if (now_recipients > 0)
            {

                var d = document.getElementById(\'ccrecipientContainer\');
                var olddiv = "";
                if(typeof(id) == "number")
                {
                    var olddiv = document.getElementById(id);
                }
                else
                {
                    var olddiv = document.getElementById(now_recipients );
                }
                d.removeChild(olddiv);
                now_recipients = now_recipients - 1;
            }
            else
            {
                now_recipients = 0;
            }
            if (now_recipients == 0)
            {
                if(document.getElementById(\'getmoreRecipient\').innerHTML == "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">'.$g_l10n->get("ECA_NO_MORE_RECIPIENTS").'<\/a>")
                {
                    showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');
                }
                document.getElementById(\'moreRecipient\').style.display = "none";
                document.getElementById(\'getmoreRecipient\').innerHTML = "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">'.$g_l10n->get("ECA_MORE_RECIPIENTS").'<\/a>";
            }
        }
        function delAllRecipients()
        {
            var anzrecipients = now_recipients;
			for (var i = 0; i < anzrecipients; i++)
			{
				delRecipient();
			}
        }
        function getSetting(name,input_value)
        {
            document.getElementById(ecardformid)[name].value = input_value;
            getTextStyle(textinputid);
        }
        function showHideMoreRecipient(divLayer,divMenu)
        {
            if($("#" + divLayer).css("display") == "none")
            {
                $("#" + divLayer).show("slow");
                document.getElementById(divMenu).innerHTML = "<a href=\"javascript:showHideMoreRecipient(divLayer,divMenu);\">'.$g_l10n->get("ECA_NO_MORE_RECIPIENTS").'<\/a>";
                addRecipient();
            }
            else
            {
                $("#" + divLayer).hide("slow");
                document.getElementById(divMenu).innerHTML = "<a href=\"javascript:showHideMoreRecipient(divLayer,divMenu);\">'.$g_l10n->get("ECA_MORE_RECIPIENTS").'<\/a>";
                delAllRecipients();
            }
        }
        function showHideMoreSettings(divLayerSetting,divMenuSetting)
        {
            if($("#" + divLayerSetting).css("display") == "none")
            {
                $("#" + divLayerSetting).show("slow");
                document.getElementById(divMenuSetting).innerHTML = "<a href=\"javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');\">'.$g_l10n->get("ECA_BLEND_OUT_SETTINGS").'<\/a>";
            }
            else
            {
                $("#" + divLayerSetting).hide("slow");
                document.getElementById(divMenuSetting).innerHTML = "<a href=\"javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');\">'.$g_l10n->get("ECA_BLEND_IN_SETTINGS").'<\/a>";
            }
        }
        function getExtern()
        {
            if(document.getElementById(basedropdiv).style.display == "none")
            {
                document.getElementById(basedropdiv).style.display = \'block\';
                document.getElementById(dropdiv).style.display = \'block\';
                document.getElementById(externdiv).style.display = \'none\';
                document.getElementById(externdiv).innerHTML = \'<input type="hidden" name="ecard[email_recipient]" value="< '.$g_l10n->get("ECA_RECIPIENT_EMAIL").' >" /><input type="hidden" name="ecard[name_recipient]"  value="< '.$g_l10n->get("ECA_RECIPIENT_NAME").' >" />\';
                getMenu();
                document.getElementById(switchdiv).innerHTML = \'&nbsp;\';
            }
            else if(document.getElementById(basedropdiv).style.display == "block")
            {
                macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=extern\', \'extern\' );
                document.getElementById(basedropdiv).style.display = \'none\';
                document.getElementById(dropdiv).style.display = \'none\';
                document.getElementById(externdiv).style.display = \'block\';
                document.getElementById(basedropdiv).innerHTML  = "&nbsp;";
                document.getElementById(dropdiv).innerHTML  = "&nbsp;";
                document.getElementById(switchdiv).innerHTML = \'<a href="javascript:getExtern();">'.$g_l10n->get("ECA_INTERNAL_RECIPIENT").'<\/a>\';
            }

            if(document.getElementById(\'wrong\'))
            {
                document.getElementById(\'wrong\').style.display = "none";
                document.getElementById(\'wrong\').innerHTML = "";
                document.getElementById(\'Menue\').style.height = "49px";
            }
        }

        function countMax()
        {
            max  = '.$g_preferences['ecard_text_length'].';
            var text = document.getElementById(ecardformid)["ecard[message]"].value;
            for(var i=0;i<bbcodes.length;i++)
            {
                text = text.replace(/\[.*?\]/gi,"").replace (/^\s+/,"").replace (/\s+$/,"");
            }
            var textlenght = text.length;
            wert = max - textlenght;
            if(textlenght > max)
            {
                var txtvalue = document.getElementById(ecardformid)["ecard[message]"].value;
                document.getElementById(ecardformid)["ecard[message]"].value = txtvalue.substr(0, max);
            }
            if (wert < 0)
            {
                alert("'.$g_l10n->get("ECA_PHR_MESSAGE_TOO_LONG",$var1="\" + max + \"").'");
                wert = 0;
                document.getElementById(ecardformid)["ecard[message]"].value = document.getElementById(ecardformid)["ecard[message]"].value.substring(0,max);
                document.getElementById(counterdiv).innerHTML = \'<b>\' + wert + \'<\/b>\';
                wert = 0;
            }
            else
            {
                var zwprodukt = max - textlenght;
                document.getElementById(counterdiv).innerHTML = \'<b>\' + zwprodukt + \'<\/b>\';
            }
        } // Ende function countMax()

        function getTextStyle(textdiv)
        {
            var schrift_size = document.getElementById(ecardformid)["ecard[schrift_size]"].value;
            var schrift = document.getElementById(ecardformid)["ecard[schriftart_name]"].value;
            var schrift_farbe = document.getElementById(ecardformid)["ecard[schrift_farbe]"].value;
            var schrift_bold = "";
            var schrift_italic = "";
            if(document.getElementById(ecardformid).Bold.checked)
            {
                schrift_bold = "bold";
                document.getElementById(ecardformid)["ecard[schrift_style_bold]"].value = "bold";
            }
            else
            {
                document.getElementById(ecardformid)["ecard[schrift_style_bold]"].value = "";
            }
            if(document.getElementById(ecardformid).Italic.checked)
            {
                schrift_italic = "italic";
                document.getElementById(ecardformid)["ecard[schrift_style_italic]"].value = "italic";
            }
            else
            {
                document.getElementById(ecardformid)["ecard[schrift_style_italic]"].value = "";
            }
            var schrift_farbe = document.getElementById(ecardformid)["ecard[schrift_farbe]"].value;
            document.getElementById(textdiv).style.font = schrift_bold + \' \'+ schrift_italic + \' \'+ schrift_size + \'px \'+schrift;
            document.getElementById(textdiv).style.color = schrift_farbe;
        }
		window.onload=getMenu();
    //--></script>';

if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript .= getBBcodeJS('Nachricht');
}

if (empty($submit_action))
{
	$g_layout['header'] = $javascript;
}

require(THEME_SERVER_PATH. "/overall_header.php");

echo '

<div class="formLayout" id="profile_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
    <div>
	<noscript>
    <div style="text-align: center;">
        <div style="background-image: url(\''.THEME_PATH.'/images/error.png\');
                    background-repeat: no-repeat;
                    background-position: 5px 5px;
                    border:1px solid #ccc;
                    padding:5px;
                    background-color: #FFFFE0;
                    padding-left: 28px;
                    text-align:left;">
         '.$g_l10n->get("ECA_PHR_NEED_JAVASCRIPT").'
         </div>
    </div>
</noscript>';
if (empty($submit_action))
{
    // das Bild kann in Vollgroesse ueber die Thickbox dargestellt werden
    echo '<a class="thickbox" href="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;pic_nr='.$photo.'&amp;pho_begin='.$photo_album->getValue("pho_begin").'&amp;max_width='.$g_preferences['photo_show_width'].'&amp;max_height='.$g_preferences['photo_show_height'].'&amp;KeepThis=true&amp;TB_iframe=true&amp;height='.($g_preferences['photo_show_height']+37).'&amp;width='.$g_preferences['photo_show_width'].'"><img 
            src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;pic_nr='.$photo.'&amp;pho_begin='.$photo_album->getValue("pho_begin").'&amp;max_width='.$g_preferences['ecard_view_width'].'&amp;max_height='.$g_preferences['ecard_view_height'].'" 
            class="imageFrame" alt="'.$g_l10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'"  title="'.$g_l10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'" />
          </a>';

    if ($error_msg != '')
    {
        $g_message->show($error_msg);
    }

    echo '<form id="ecard_form" action="" onsubmit="return tb_sendform(this,\''.$g_l10n->get("ECA_GREETING_CARD_PREVIEW").'\')" method="post">
            <input type="hidden" name="ecard[image_name]" value="'; if (! empty($ecard["image_name"])) echo $ecard["image_name"]; echo'" />
            <input type="hidden" name="submit_action" value="" />
            <ul class="formFieldList">
            <li>
                <hr />
            </li>
            <li>
                <dl>
                    <dt>
                        <label>'.$g_l10n->get("SYS_TO").':</label>
                        ';
                        if($g_preferences['enable_ecard_cc_recipients'])
                        {
                            echo '<div id="getmoreRecipient" style="padding-top:20px; height:1px;">
                            <a href="javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');">'.$g_l10n->get("ECA_MORE_RECIPIENTS").'</a>
                            </div>';
                        }
                       echo'
                    </dt>
                    <dd id="Menue" style="height:49px; width:370px;">';
                        if (array_key_exists("usr_id", $_GET))
                        {
                            // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                            echo '<div id="extern">
                                    <input type="text" readonly="readonly" name="ecard[name_recipient]" style="margin-bottom:3px; width: 200px;" maxlength="50" value="'.$user->getValue('Vorname').' '.$user->getValue('Nachname').'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
                            echo '<input type="text" readonly="readonly" name="ecard[email_recipient]" style="width: 350px;" maxlength="50" value="'.$user->getValue('E-Mail').'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                                 </div>';

                        }
                        else
                        {
                           echo '<div id="externSwitch" style="float:right; padding-left:5px; position:relative;">
                                 </div>
                                 <div id="basedropdownmenu" style="display:block; padding-bottom:3px;">
                                 </div>
                                 <div id="dropdownmenu" style="display:block;">
                                 </div>
                                 <div id="extern">
                                    <input type="hidden" name="ecard[email_recipient]" value="" />
                                    <input type="hidden" name="ecard[name_recipient]"  value="" />
                                 </div>
                                  <div id="wrong" style="width:300px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:5px; border:1px solid #ccc;padding:5px;background-color: #FFFFE0; padding-left: 28px;display:none;"></div>';
                        }
                        echo '
                    </dd>
                </dl>
            </li>
            <li>
                <div id="moreRecipient" style="display:none;">
                <hr />
                    <dl>
                        <dt>'.$g_l10n->get("ECA_MORE_RECIPIENTS").':</dt>
                        <dd>
                            <table summary="TableccContainer" border="0" >
                                <tr>
                                    <td style="width:150px; text-align: left;">'.$g_l10n->get("SYS_NAME").'</td>
                                    <td style="width:200px; padding-left:14px; text-align: left;">'.$g_l10n->get("SYS_EMAIL").'</td>
                                </tr>
                            </table>
                            <div id="ccrecipientContainer" style="width:490px; border:0px; text-align: left;"></div>
                            <table summary="TableCCRecipientSettings" border="0">
                                    <tr>
                                        <td style="text-align: left;"><span class="iconTextLink"><a href="javascript:addRecipient()"><img src="'. THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get("SYS_ADD_RECIPIENTS").'" /></a><a href="javascript:addRecipient()">'.$g_l10n->get("SYS_ADD_RECIPIENTS").'</a></span></td>
                                    </tr>
                            </table>
                        </dd>
                    </dl>
                </div>
            </li>
            <li>
                <hr />
            </li>
            <li>
                <dl>
                    <dt><label>'.$g_l10n->get("SYS_SENDER").':</label></dt>
                    <dd>
                      <input type="text" name="ecard[name_sender]" size="25" readonly="readonly" maxlength="50" style="width: 200px;" value="';
                        if (! empty($ecard["name_sender"]) && !$g_current_user->getValue("Nachname"))
                        {
                           echo $ecard["name_sender"];
                        }
                        else
                        {
                           echo $g_current_user->getValue("Vorname")." ".$g_current_user->getValue("Nachname");
                        }
                      echo'" />
                    </dd>
                </dl>
            </li>
             <li>
                <dl>
                    <dt><label>'.$g_l10n->get("SYS_EMAIL").':</label></dt>
                    <dd>
                       <input type="text" name="ecard[email_sender]" size="25" readonly="readonly" maxlength="40" style="width: 350px;"  value="';
                        if (! empty($ecard["email_sender"]) && !$g_current_user->getValue("E-Mail"))
                        {
                          echo $ecard["email_sender"];
                        }
                        else
                        {
                          echo $g_current_user->getValue("E-Mail");
                        }
                        echo'" />
                    </dd>
                </dl>
            </li>
            <li>
                <hr />
            </li>'; 
            if ($g_preferences['enable_bbcode'] == 1)
            {
                printBBcodeIcons();
            }                
            echo '
            <li>
                <dl>
                    <dt>
                        <label>'.$g_l10n->get("SYS_MESSAGE").':</label>';
                        if($g_preferences['enable_ecard_text_length'])
                        {
                            echo '<div style="width:125px; padding:5px 0px 5px 35px; background-image: url(\''.THEME_PATH.'/icons/warning.png\'); background-repeat: no-repeat;background-position: 5px 5px;border:1px solid #ccc; margin:70px 0px 28px 0px;  background-color: #FFFFE0;">'.$g_l10n->get("ECA_STILL_XCHARS_AVAILABLE",$var1="&nbsp;<div id=\"counter\" style=\"border:0px; display:inline;\"><b>".$g_preferences['ecard_text_length']."</b></div>&nbsp;").'</div>';
                        }
                        echo '<div id="getmoreSettings" style="';
                        if($g_preferences['enable_ecard_text_length'])
                        {
                            echo 'padding-top:28px;';
                        }
                        else
                        {
                            echo 'padding-top:155px;';
                        }
                        echo '  height:1px;">
                            <a href="javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');">'.$g_l10n->get("ECA_BLEND_IN_SETTINGS").'</a>
                        </div>
                    </dt>
                    <dd>
                        <textarea id="Nachricht" style="width: 350px; height: 180px; overflow:auto; font:'.$g_preferences['ecard_text_size'].'px '.$g_preferences['ecard_text_font'].'; color:'.$g_preferences['ecard_text_color'].'; wrap:virtual;" rows="10" cols="45" name="ecard[message]"';
                        if($g_preferences['enable_ecard_text_length'])
                        {
                        echo' onfocus="javascript:countMax();" onclick="javascript:countMax();" onchange="javascript:countMax();" onkeydown="javascript:countMax();" onkeyup="javascript:countMax();" onkeypress="javascript:countMax();"';
                        }
                        echo' >';
                        if (! empty($ecard["message"]))
                        {
                            echo ''.$ecard["message"].'';
                        }
                   echo'</textarea>
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                   </dd>
                </dl>
            </li>
            <li>
                <div id="moreSettings" style="display:none;">
                <hr />
                <dl>
                    <dt>
                        <label>'.$g_l10n->get("SYS_SETTINGS").':</label>
                    </dt>
                    <dd>';
                        $first_value_array = array();
                        echo'<table cellpadding="5" cellspacing="0" summary="Einstellungen" style="width:350px;"  border="0px">
                            <tr>
                              <td>'.$g_l10n->get("ECA_TEMPLATE").':</td>
                              <td>'.$g_l10n->get("SYS_FONT").':</td>
                              <td>'.$g_l10n->get("SYS_FONT_SIZE").':</td>
                            </tr>
                            <tr>
                                <td>';
                                    array_push($first_value_array,array($funcClass->getMenueSettings($templates,"ecard[template_name]",$g_preferences['ecard_template'],"120","false"),"ecard[template_name]"));
                                echo '</td>
                                <td>';
                                    array_push($first_value_array,array($funcClass->getMenueSettings($fonts,"ecard[schriftart_name]",$g_preferences['ecard_text_font'],"120","true"),"ecard[schriftart_name]"));
                                echo '</td>
                                <td>';
                                    array_push($first_value_array,array($funcClass->getMenueSettings($font_sizes,"ecard[schrift_size]",$g_preferences['ecard_text_size'],"50","false"),"ecard[schrift_size]"));
                                echo  '</td>
                            </tr>
                            <tr>
                              <td>'.$g_l10n->get("SYS_FONT_COLOR").':</td>
                              <td style="padding-left:40px;">'.$g_l10n->get("SYS_FONT_STYLE").':</td>
                              <td></td>
                            </tr>
                            <tr>
                                <td>';
                                    array_push($first_value_array,array($funcClass->getColorSettings($font_colors,"ecard[schrift_farbe]","8",$g_preferences['ecard_text_color']),"ecard[schrift_farbe]"));
                                echo '</td>
                                <td colspan="2" style="padding-left:40px;">
                                    <b>'.$g_l10n->get("SYS_BOLD").': </b><input name="Bold" value="bold" onclick="javascript: getSetting(\'ecard[schrift_style_bold]\',this.value);" type="checkbox" />
                                    <i>'.$g_l10n->get("SYS_ITALIC").': </i><input name="Italic" value="italic" onclick="javascript: getSetting(\'ecard[schrift_style_italic]\',this.value);" type="checkbox" />
                                </td>
                            </tr>
                        </table>';
                        $funcClass->getFirstSettings($first_value_array);
                        echo '<input type="hidden" name="ecard[schrift_style_bold]" value="" />
                        <input type="hidden" name="ecard[schrift_style_italic]" value="" />
                    </dd>
                </dl>
                </div>
            </li>
        </ul>
        <hr />
        <div class="formSubmit">
            <button onclick="javascript:makeThickBoxPreview();" type="submit" value="'.$g_l10n->get("SYS_PREVIEW").'"><img 
            	src="'. THEME_PATH. '/icons/eye.png" alt="'.$g_l10n->get("SYS_PREVIEW").'" />&nbsp;'.$g_l10n->get("SYS_PREVIEW").'</button>&nbsp;&nbsp;&nbsp;&nbsp;
            <button onclick="javascript:sendEcard();" type="button" value="'.$g_l10n->get("SYS_SEND").'"><img 
            	src="'. THEME_PATH. '/icons/email.png" alt="'.$g_l10n->get("SYS_SEND").'" />&nbsp;'.$g_l10n->get("SYS_SEND").'</button>
        </div>
    </form>';
}
else
{
    echo'<br />
    <div style="text-align: center;">
        <div style="text-align:center;
        width:380px;
        height:30px;
        margin-top:5px;
        border:1px solid #ccc;
        padding:20px 0px 5px 5px;
        background-color: #FFFFE0;
        vertical-align:middle;">
            <span style="font-size:16px; font-weight:bold">'.$g_l10n->get("ECA_PHR_SUCCESSFULLY_SEND").'</span>
        </dv>
    </div>
    <br /><br />

    <table cellpadding="0" cellspacing="0" border="0" summary="Erfolg" style="text-align: center;">
    <tr>
        <td style="text-align: left;" colspan="2"><b>'.$g_l10n->get("SYS_SENDER").':</b></td>
    </tr>
    <tr>
        <td style="padding-right:5px; text-align: left;">'. $ecard['name_sender'].',</td><td style="text-align: left;">'.$ecard['email_sender'].'</td>
    </tr>
    <tr>
        <td style="text-align: left;">&nbsp;</td>
    </tr>
    <tr>
        <td style="text-align: left;" colspan="2"><b>'.$g_l10n->get("SYS_RECIPIENT").':</b></td>
    </tr><tr>';
    foreach($email_versand_liste as $item)
    {
            $i=0;
            foreach($item as $item2)
            {
                    if (!is_integer(($i+1)/2))
                    {
                        echo '<td style="padding-right:5px; text-align: left;">'. $item2.',</td></td>';
                    }
                    else
                    {
                        echo'<td style="padding-right:5px; text-align: left;">'. $item2.'</td></tr><tr>';
                    }
                    $i++;
            }
    }
    echo '</tr>';
    $Liste = array();
    $Liste = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
    if(count($Liste)>0)
    {
        echo '<tr><td>&nbsp;</td></tr><tr><td colspan="2"><b>'.$g_l10n->get("ECA_MORE_RECIPIENTS").':</b></td></tr><tr>';
        foreach($Liste as $item)
        {
            $i=0;
            foreach($item as $item2)
            {
                if (!is_integer(($i+1)/2))
                {
                    echo '<td style="text-align: left;">'.$item2.',</td>';
                }
                else
                {
                    echo'<td style="text-align: left;">'.$item2.'</td></tr><tr>';
                }
                $i++;
            }
        }
    }
    echo '</tr></table><br /><br/></div>';
}
echo '</div></div></div>';
/************************Buttons********************************/
//Uebersicht
if($photo_album->getValue('pho_id') > 0)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                src="'.THEME_PATH.'/icons/back.png" alt="'.$g_l10n->get("SYS_BACK").'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get("SYS_BACK").'</a>
            </span>
        </li>
    </ul>';
}

/***************************Seitenende***************************/
require(THEME_SERVER_PATH. '/overall_footer.php');
?>