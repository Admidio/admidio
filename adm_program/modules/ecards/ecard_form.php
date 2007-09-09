<?php
/******************************************************************************
 * E@card Form
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * Based on     : Jochen Erkens Photogalerien &
 *                 Elmar Meuthen E-Mails verschicken &
 *                #################################################################
 *                # IBPS E-C@ard                       Version 1.01               #
 *                # Copyright 2002 IBPS Friedrichs     info@ibps-friedrichs.de    #
 *                #################################################################
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:		id der Veranstaltung deren Bilder angezeigt werden sollen
 * photo:       Name des Bildes ohne(.jpg) später -> (admidio/adm_my_files/photos/<* Gallery *>/$_GET['photo'].jpg)
 * usr_id:		Die Benutzer id an dem die E@card gesendet werden soll
 *
 *****************************************************************************/

require_once("../../system/photo_event_class.php");
require_once("../../system/common.php");
require_once("../photos/photo_function.php");
require_once("ecard_lib.php");

// Variablen die in die DB kommen und vom Admin änderbar sind
//**********************************************************
/**/	$max_w = "250";		// Maximale Breite des E-Card Bildes							
/**/	$max_h = "250";		// Maximale Höhe des E-Card Bildes	
/*		es können hier mehere Templates eingetragen werden welche dann vom Benutzt ausgewählt werden dürfen				
/**/	$templates = array ("ecard_1.tpl","ecard_2.tpl"); 
/*      es können hier mehere Briefmarken eingetragen werden welche dann vom Benutzt ausgewählt werden dürfen */
/**/	$briefmarken = array ("standard.gif","halloween.gif","kuss.gif","ostern.gif","schwein.gif","smiley1.gif","smiley2.gif","smiley3.gif","sonnenuntergang.gif","torte.gif","weihnachten.gif","winter.gif"); 
/*      es können hier mehere Hintergründe eingetragen werden welche dann vom Benutzt ausgewählt werden dürfen */
/**/	$hintergrund = array ("kein","fledermaeuse.jpg","geschenke.jpg","herzen.jpg","klee.jpg","noten.jpg","ostereier.jpg","sterne.dark.jpg"); 
/**/	$tmpl_folder = "../../layout/";						
/**/	$g_preferences['enable_e@card_module']=1;		
/**/	$max_length = 250;  // Maximale Länge des E-Card Textes
/**/	$msgTextError1 = "Es ist ein Fehler bei der Verarbeitung der E-C@rd aufgetreten. Bitte probier es zu einem späteren Zeitpunkt noch einmal.";
/**/	$msgTextError2 = "Es sind einige Eingabefelder nicht bzw. nicht richtig ausgefüllt. Bitte füll diese aus, bzw. korrigier diese.";
/**/	$ecard_PLAIN_data = "Du hast eine E-Card von einem Mitglied des Vereins ".$g_organization." erhalten.\nKlick auf das Attachment, um die E-Card zu sehen.";
/**/	$error_msg = "";
//**********************************************************

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_e@card_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
// pruefen ob User eingeloggt ist
if(!$g_valid_login)
{
 $g_message->show("invalid");
} 
//ID Pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]))
{
    $pho_id = $_GET["pho_id"];
}
else 
{
    $pho_id = NULL;
}

unset($_SESSION['photo_event_request']);

//Wurde keine Veranstaltung uebergeben kann das Navigationsstack zurückgesetzt werden
if ($pho_id == NULL)
{
    $_SESSION['navigation']->clear();
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_event']) && $_SESSION['photo_event']->getValue("pho_id") == $pho_id)
{
    $photo_event =& $_SESSION['photo_event'];
    $photo_event->db =& $g_db;
}
else
{
    // einlesen der Veranstaltung falls noch nicht in Session gespeichert
    $photo_event = new PhotoEvent($g_db);
    if($pho_id > 0)
    {
        $photo_event->getPhotoEvent($pho_id);
    }

    $_SESSION['photo_event'] =& $photo_event;
}

// pruefen, ob Veranstaltung zur aktuellen Organisation gehoert
if($pho_id > 0 && $photo_event->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("invalid");
} 


if ($g_valid_login && !isValidEmailAddress($g_current_user->getValue("E-Mail")))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->addVariableContent("$g_root_path/adm_program/modules/profile/profile.php", 1, false);
    $g_message->show("profile_mail");
}
if(!isset($_GET["photo"]))
{
    $g_message->show("invalid");
}

if (isset($_GET["usr_id"]))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_valid_login)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show("invalid");
    }

    if (is_numeric($_GET["usr_id"]) == false)
    {
        $g_message->show("invalid");
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden    
    if((  $g_current_user->editUser() == false
       && isMember($user->getValue("usr_id")) == false)
    || strlen($user->getValue("usr_id")) == 0 )
    {
        $g_message->show("usrid_not_found");
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue("E-Mail")))
    {
        $g_message->show("usrmail_not_found");
    }

    $userEmail = $user->getValue("E-Mail");
	$userName  = $user->getValue("Vorname")." ".$user->getValue("Nachname");
}


$popup_height = $g_preferences['photo_show_height']+210;
$popup_width  = $g_preferences['photo_show_width']+70;
$bild         = $_REQUEST['photo'];

if(is_numeric($bild) && isset($_GET['pho_id']))
{
    $ordner_foto      = "/adm_my_files/photos/".$photo_event->getValue("pho_begin")."_".$photo_event->getValue("pho_id");
    $ordnerurl        = $g_root_path. $ordner_foto;
    $bildfull         = "".$ordnerurl."/".$_REQUEST['photo'].".jpg";
}
if(is_numeric($bild) && !isset($_GET['pho_id']))
{
	$g_message->show("invalid");
}
if(!is_numeric($bild) || !is_numeric($_GET['pho_id']))
{
	$g_message->show("invalid");
}
if(isset($bildfull))
{
	list($width, $height) = getimagesize($bildfull);
	$propotional_size                   = getPropotionalSize($width, $height, $max_w, $max_h); 
}

getPostGetVars();
$ecard_send = false;
if (! empty($submit_action)) 
{
    if ( check_email($ecard["email_recepient"]) && check_email($ecard["email_sender"]) 
	&& ($ecard["email_recepient"] != "") && ($ecard["name_sender"] != "") )    
	{
	    if (strlen($ecard["message"]) > $max_length) 
		{
	        $ecard["message"] = substr($ecard["message"],0,$max_length-1);
	    }
		list($error,$ecard_data_to_parse) = get_ecard_template($ecard["template_name"],$tmpl_folder);
	    if ($error) 
	    {
		    $error_msg = $msgTextError1;
	    } 
	    else 
	    {
		    $ecard_HTML_data = parse_ecard_template($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$propotional_size['width'],		$propotional_size['height']);
		    $result = sendEcard($ecard,$ecard_HTML_data,$ecard_PLAIN_data);
		    if ($result) 
			{
			    $ecard_send = true;
		    } 
			else 
		    {
			    $error_msg = $msgTextError1;
		    }
	   }
	} 
	else 
	{
        $error_msg = $msgTextError2;
	}
} 
else 
{
    $ecard["image_name"] = $bildfull;
}

/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
$g_layout['title'] = "E@card";
//Lightbox-Mode
$g_layout['header'] = "";
if($g_preferences['photo_show_mode']==1)
{
    $g_layout['header'] .= "
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/prototype.js\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/lightbox/lightbox.js\"></script>
        <link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/lightbox.css\" type=\"text/css\" media=\"screen\" />";
}

$javascript='
    <script language="javascript" type="text/javascript">
        function popup_win(theURL,winName,winOptions) 
		{
             win = window.open(theURL,winName,winOptions);
             win.focus();
        }
        function sendEcard() 
        {
            if (check()) 
            { 
                document.ecard_form.action                 = "'.$HTTP_SERVER_VARS["PHP_SELF"].'?'.$_SERVER['QUERY_STRING'].'";
                document.ecard_form.target                 = "_self";
                document.ecard_form["submit_action"].value = "send";
                document.ecard_form.submit(); 
            }
            else
            {
                document.ecard_form["submit_action"].value = "";
            }
        } 
        function check() 
		{
            var error         = false;
            var error_message = "Du hast die folgenden, für die\nE-C@rd notwendigen Eingabefelder\nnicht bzw. nicht richtig ausgefüllt:\n\n";

            if (document.ecard_form["ecard[name_sender]"].value == "") 
			{
                error = true;
                error_message += "- name des Absenders\n";
            } 
 
            if ((document.ecard_form["ecard[email_sender]"].value == "") || 
               (echeck(document.ecard_form["ecard[email_sender]"].value) == false)) 
			{
                error = true;
                error_message += "- E-Mail des Absenders\n";
            }
  
            if (document.ecard_form["ecard[name_recepient]"].value == "" || document.ecard_form["ecard[name_recepient]"].value == "<Empfänger Name>") 
			{
                error = true;
                error_message += "- name des Empfängers\n";
            } 
            if ((document.ecard_form["ecard[email_recepient]"].value == "") || 
               (echeck(document.ecard_form["ecard[email_recepient]"].value) == false)) 
			{
                error = true;
                error_message += "- E-Mail des Empfängers\n";
        	}
        	if (document.ecard_form["ecard[message]"].value == "") 
			{
				error = true;
				error_message += "- eine Nachricht\n";
			}
			if (error) 
			{
				error_message += "\n\nBitte füll die genannten Eingabefelder\nvollständig aus und klick dann erneut\nauf \'Abschicken\'.";
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
			var at="@"
			var dot="."
			var lat=str.indexOf(at)
			var lstr=str.length
			var ldot=str.indexOf(dot)
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
			document.ecard_form.action = "ecard_preview.php?width='.$propotional_size['width'].'&height='.$propotional_size['height'].'&tmplfolder='.$tmpl_folder.'";
			popup_win(\''.$g_root_path.'/adm_program/ecards/templates/leer.htm\',\'ecard_preview\',\'resizable=yes,scrollbars=yes,width=800,height=600\');
			document.ecard_form.target = "ecard_preview";
			document.ecard_form.submit();
		}
        function blendout(id)
		{
		    if(document.getElementById(id).value == "<Empfänger Name>" || document.getElementById(id).value == "<Empfänger E-mail>")
			{
				document.getElementById(id).value = "";
			}
		}
		function countMax() 
		{
			max  = '.$max_length.';
			wert = max - document.ecard_form["ecard[message]"].value.length;
			if (wert < 0) 
			{
				alert("Die Nachricht darf maximal " + max + " Zeichen lang sein.!");
				document.ecard_form["ecard[message]"].value = document.ecard_form["ecard[message]"].value.substring(0,max);
				document.getElementById(\'counter\').innerHTML = \'<b> + wert + </b>\';
				wert = 0;
			} 
			else 
			{
			    var zwprodukt = max - document.ecard_form["ecard[message]"].value.length;
				document.getElementById(\'counter\').innerHTML = \'<b>\' + zwprodukt + \'</b>\';
			}
		} // Ende function countMax()

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
				if(xmlHttp.readyState==4)
				{
					document.getElementById(divId).innerHTML = xmlHttp.responseText;
				}
			}
			xmlHttp.open("GET",seite,true);
			xmlHttp.send(null);
		}
		function getMenu()
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?base=1\' , \'basedropdownmenu\' );
		}
		function getMenuRecepientName()
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?rol_id=\'+ document.ecard_form.rol_id.value , \'dropdownmenu\' );
		}
		function getMenuRecepientNameEmail(usr_id)
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=\'+ usr_id, \'dropdownmenu\' );
		}
		function getTemplate(template)
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?template=\'+ template, \'template\' );
		}
		function getBriefmarke(briefmarke)
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?briefmarke=\'+ briefmarke, \'briefmarke\' );		
		}
		function getHintergrund(hintergrund)
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?hintergrund=\'+ hintergrund, \'hintergrund\' );		
		}
		function getExtern()
		{
		   var basedropdiv = \'basedropdownmenu\';
           var dropdiv = \'dropdownmenu\';
		   var externdiv = \'extern\';
		   var switchdiv = \'exinswitch\';
		    if(document.getElementById(basedropdiv).style.display == "none")
			{
				document.getElementById(basedropdiv).style.display = \'block\';
				document.getElementById(dropdiv).style.display = \'block\';
				document.getElementById(externdiv).style.display = \'none\';
				document.getElementById(externdiv).innerHTML = "&nbsp;";
				getMenu();
				document.getElementById(switchdiv).innerHTML = \'<a href="javascript:getExtern();">externer Empf&auml;nger</a>\';
			}
			else if(document.getElementById(basedropdiv).style.display == "block") 
			{
			    macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=extern\', \'extern\' );
				document.getElementById(basedropdiv).style.display = \'none\';
				document.getElementById(dropdiv).style.display = \'none\';
				document.getElementById(externdiv).style.display = \'block\';
				document.getElementById(basedropdiv).innerHTML  = "&nbsp;";
				document.getElementById(dropdiv).innerHTML  = "&nbsp;";
				document.getElementById(switchdiv).innerHTML = \'<a href="javascript:getExtern();">interner Empf&auml;nger</a>\';
			}
		}
	</script>';
$g_layout['header'] .= $javascript;



//Photomodulspezifische CSS laden
$g_layout["header"] = $g_layout['header']."<link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/photos.css\" type=\"text/css\" media=\"screen\" />";
 

if($g_preferences['photo_show_mode']==1)
{
    $g_layout['onload'] = " onload=\"initLightbox()\" ";
}

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo '
<div class="formLayout" id="profile_form">
    <div class="formHead">';
	if(! empty($submit_action))
	{
	    echo "E@card wegschicken";
	}
	else
	{
	    echo "E@card bearbeiten";
	}
echo'
	</div>
	<div class="formBody">
	<div>';
if (empty($submit_action))
{   
	 //Popup-Mode
	if($g_preferences['photo_show_mode']==0)
	{
		echo "<img onclick=\"window.open('$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=".$_REQUEST['photo']."&pho_id=".$_REQUEST['pho_id']."','msg','height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" 
			 src=\"".$bildfull."\" width=\"".$propotional_size['width']."\" height=\"".$propotional_size['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"Ecard\" />";
	}
	//Lightbox-Mode
	if($g_preferences['photo_show_mode']==1)
	{
		echo "<a href=\"".$bildfull."\" rel=\"lightbox[roadtrip]\" title=\"".$photo_event->getValue("pho_name")."\"><img src=\"".$bildfull."\" width=\"".$propotional_size['width']."\" height=\"".$propotional_size['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"Ecard\" /></a>";
	}
	
	//Gleichesfenster-Mode
	if($g_preferences['photo_show_mode']==2)
	{
		echo "<img onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=".$_REQUEST['photo']."&pho_id=$pho_id'\" src=\"".$bildfull ."\" width=\"".$propotional_size['width']."\" height=\"".$propotional_size['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"Ecard\" />";
	}      
    if ($error_msg != "")
	{
		echo '<br /><span class="errorMsg">'.$error_msg.'</span>';
	}
	  
		echo' <form name="ecard_form" action="#" method="post">
			  <input type="hidden" name="ecard[image_name]" value="'; if (! empty($ecard["image_name"])) echo $ecard["image_name"]; echo'" />
			  <input type="hidden" name="submit_action" value="" />
			  <ul class="formFieldList">
			   <li>
                    <hr />
                </li>
               <li>
                 <dl>
                   <dt><label>An:</label>			        
				   </dt>
                   <dd>';
							if (array_key_exists("usr_id", $_GET))
                            {
                                // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
								echo '<input type="text" class="readonly" readonly="readonly" name="ecard[name_recepient]" style="margin-bottom:3px; width: 200px;" maxlength="50" value="'.$userName.'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
                                echo '<input type="text" class="readonly" readonly="readonly" name="ecard[email_recepient]" style="width: 350px;" maxlength="50" value="'.$userEmail.'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
								
                            }
                            else
                            {
							   echo '<div style="height:48px; width:370px;">
									 <div id="exinswitch" style="float:right; margin-left:5px; display:relativ;">
										 <a href="javascript:getExtern()">externer Empf&auml;nger</a>
								     </div>
									 <div id="basedropdownmenu" style="display:block; margin-bottom:3px;">
									     <script language="javascript" type="text/javascript">getMenu();</script>
									 </div>
									 <div id="dropdownmenu" style="display:block;">
								     </div>
								     <div id="extern"></div>
									 </div>';
                            }
                            echo '
                        </dd>
                    </dl>
                </li>
				<li>
                    <hr />
                </li>
			    <li>
                    <dl>
                        <dt><label>Absender:</label></dt>
                        <dd>
			              <input type="text" name="ecard[name_sender]" size="25" class="readonly" readonly="readonly" maxlength="50" style="width: 200px;" value="'; 
							if (! empty($ecard["name_sender"]) && !$g_current_user->getValue("Nachname"))
							{
							   echo $ecard["name_sender"]; 
							}
						    else
							{ 
							   echo $g_current_user->getValue("Vorname")." ".$g_current_user->getValue("Nachname");
							}
					      echo'" />
						  <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
			            </dd>
                    </dl>
                </li>
				 <li>
                    <dl>
                        <dt><label>E-Mail:</label></dt>
                        <dd>
						   <input type="text" name="ecard[email_sender]" size="25" class="readonly" readonly="readonly" maxlength="40" style="width: 350px;"  value="';
							if (! empty($ecard["email_sender"]) && !$g_current_user->getValue("E-Mail"))
							{
							  echo $ecard["email_sender"];
							}
							else 
							{
							  echo $g_current_user->getValue("E-Mail"); 
							}
						    echo'" />
							<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
			            </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
			    <li>
                    <dl>
                        <dt>
						    <label>Nachricht:</label>
							<div style="padding:70px 0px 40px 20px;">
							    noch&nbsp;<div id="counter" style="border:0px; display:inline;"><b>'; echo $max_length.'</b></div>&nbsp;Zeichen:
							</div>
						</dt>
                        <dd>
							<textarea style="width: 350px;" rows="10" cols="45" name="ecard[message]" onfocus="javascript:countMax();" onclick="javascript:countMax();" onchange="javascript:countMax();" onkeydown="javascript:countMax();" onkeyup="javascript:countMax();" onkeypress="javascript:countMax();">';
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
                    <hr />
                </li>
			    <li>
                    <dl>
                        <dt>
						    <label>Einstellungen:</label>
						</dt>
                        <dd>
						<table width="350px" summary="Einstellungen" border="0px">
						<tr>
						  <td>Template:</td>
						  <td>Briefmarke:</td>
						  <td>Hintergrund:</td>
						</tr>
						<tr>
						  <td>
						';
						$templatedata = "";
						$templatefirstvalue = "";
						echo '<select size="1" id="templates" name="templates" onchange="getTemplate(this.value)">';
						for($i=0; $i<count($templates);$i++)
						{
						    $TemplName = explode(".", $templates[$i]);
							echo '<option value="'.$templates[$i].'"';
							if ($i == 0)
							{
								 $templatedata = ' selected=\'selected\' ';
								 $templatefirstvalue = '<input type="hidden" name="ecard[template_name]" value="'.$templates[$i].'" />';
								 echo $templatedata;
							}
							echo '>'.$TemplName[0].'</option>';
						}
						echo '</select>
						</td>
			            <td>';
						$briefmarkendata = "";
						$briefmarkenfirstvalue = "";
						echo  '<select size="1" id="briefmarken" name="briefmarken" onchange="getBriefmarke(this.value)">';
						for($i=0; $i<count($briefmarken);$i++)
						{
						    $BriefmName = explode(".", $briefmarken[$i]);
							echo '<option value="'.$briefmarken[$i].'"';
							if ($i == 0)
							{
								 $briefmarkendata = ' selected=\'selected\' ';
								 $briefmarkenfirstvalue = '<input type="hidden" name="ecard[briefmarken_name]" value="'.$briefmarken[$i].'" />';
								 echo $briefmarkendata;
							}
							echo '>'.$BriefmName[0].'</option>';
						}
						echo  '</select>
						</td>
						<td>';
						$hintergrunddata = "";
						$hintergrundfirstvalue = "";
						echo  '<select size="1" id="briefmarken" name="briefmarken" onchange="getHintergrund(this.value)">';
						for($i=0; $i<count($hintergrund);$i++)
						{
						    $HintergName = explode(".", $hintergrund[$i]);
							echo '<option value="'.$hintergrund[$i].'"';
							if ($i == 0)
							{
								 $hintergrunddata = ' selected=\'selected\' ';
								 $hintergrundfirstvalue = '<input type="hidden" name="ecard[hintergrund_name]" value="'.$hintergrund[$i].'" />';
								 echo $hintergrunddata;
							}
							echo '>'.$HintergName[0].'</option>';
						}
						echo  '</select>
						</td>
						</tr>
						</table>
						<div id="briefmarke"  style="display:none;">';
						if( $briefmarkenfirstvalue != "")
						{
							echo $briefmarkenfirstvalue;
						}
						else
						{
							echo '<input type="hidden" name="ecard[briefmarken_name]" value="" />';
						}
						echo '</div>
						<div id="template"  style="display:none;">';
						if( $templatefirstvalue != "")
						{
							echo $templatefirstvalue;
						}
						else
						{
							echo '<input type="hidden" name="ecard[template_name]" value="" />';
						}
						echo '</div>
						<div id="hintergrund" style="display:none;">';
						if( $hintergrundfirstvalue != "")
						{
							echo $hintergrundfirstvalue;
						}
						else
						{
							echo '<input type="hidden" name="ecard[hintergrund_name]" value="" />';
						}
						echo '</div>
						</dd>
                    </dl>
                </li>
			</ul> 
			<hr />
			</form>
			<div style="display:inline;">
				<button onclick="makePreview()" value="vorschau">
					<img src="'.$g_root_path.'/adm_program/images/eye.png" alt="Vorschau" />&nbsp;Vorschau
				</button>
			</div>
			<div style="display:inline;">
				<button onclick="sendEcard()" value="abschicken">
					<img src="'.$g_root_path.'/adm_program/images/email.png" alt="Abschicken" />&nbsp;Abschicken
				</button>
			</div>';
} 
else 
{     
	echo'<br />
	<span style="font-size:16px; font-weight:bold">Deine E-C@ard wurde erfolgreich versendet.</span>
	<br /><br />
	<table cellpadding="0" cellspacing="0" border="0" summary="Erfolg">
	  <tr>
		<td class="TextBlack12">
			<span style="font-weight:bold;">Absender:</span><br />'; echo $ecard["name_sender"].", ".$ecard["email_sender"]; 
   echo'</td>
	  </tr>
	  <tr>
		<td>&nbsp;</td>
	  </tr>
	  <tr>
		<td class="TextBlack12"><span style="font-weight:bold;" >Empfänger:</span><br />'; echo $ecard["name_recepient"].", ".$ecard["email_recepient"]; 
  echo '</td>
      </tr>
	</table>
	<br /><br/>';
}  
echo "</div></div></div>";
/************************Buttons********************************/
//Uebersicht
if($photo_event->getValue("pho_id") > 0)
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
            </span>
        </li>
    </ul>";
}

/***************************Seitenende***************************/
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");


/***************************Funktinen***************************/

//rechnet die propotionale Größe eines Bildes aus
// dh. wenn man ein Bild mit der max Auflösung 600x400 haben will
// übergibt mann der Funktion die max_w und max_h und bekommt die propotionale Größe zurück
function getPropotionalSize($src_w, $src_h, $max_w, $max_h)
{
	$return_val['width']=$src_w;
	$return_val['height']=$src_h;
	if($max_w < $src_w || $max_h < $src_h)
	{
		$return_val['width']=$max_w;
		$return_val['height']=$max_h;
		if($src_w >= $src_h)
		{ 
			$return_val['height'] = round(($max_w*$src_h)/$src_w);
		}
		else 
		{
			$return_val['width']  = ($max_h*$src_w)/$src_h;
		}
	}
	return $return_val;
}

?>