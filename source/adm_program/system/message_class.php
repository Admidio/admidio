<?php
/******************************************************************************
 * Klasse fuer die Ausgabe von Hinweistexten oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Message
{
    var $key;
    var $variables;
    var $headline;
    var $content;
    var $inline;			// wird ermittelt, ob bereits eine Ausgabe an den Browser erfolgt ist
    var $forward_url;		// Url auf die durch den Weiter-Button verwiesen wird
    var $timer;				// Anzahl ms bis automatisch zu forward_url weitergeleitet wird
    var $yes_no_buttons;	// Anstelle von Weiter werden Ja/Nein-Buttons angezeigt
	var $close_button;	// Anstelle von Weiter werden Ja/Nein-Buttons angezeigt
    
    function Message()
    {
		$this->includes = true;
        $this->inline   = false;
		$this->yes_no_buttons = false;
		$this->close_button   = false;
    }
    
    // Inhalt fuer eine Variable hinzufuegen
    // wird die Variablennummer nicht uebergeben, so wird automatisch eine neue mit 
    // der aktellen Nummer genommen
    function addVariableContent($content, $variable_number = 0, $show_bold = true)
    {
        if($show_bold)
        {
            $content = "<strong>$content</strong>";
        }
        else
        {
            $content = $content;
        }


        if($variable_number > 0)
        {
            $variable_number--;
            $this->variables[$variable_number] = $content;
        }
        else
        {
            $this->variables[] = $content;
        }
    }
    
    // URL muss uebergeben werden, auf die danach automatisch weitergeleitet wird
    // ist timer > 0 wird nach x Millisec. automatisch auf die URL weitergeleitet
    function setForwardUrl($url, $timer = 0)
    {
        if ($url == "home" || strlen($url) == 0)
        {
            // auf die Startseite verweisen
            $this->forward_url = $GLOBALS['g_homepage'];
        }
        else
        {
            $this->forward_url = $url;
        }
        
        if($timer > 0 && is_numeric($timer))
        {
            $this->timer = $timer;
        }
        else
        {
            $this->timer = 0;
        }
    }
    
    // URL muss uebergeben werden
    // es werden dann 2 Buttons angezeigt, klickt der User auf "Ja", so wird auf die
    // uebergebene Url weitergeleitet, bei "Nein" geht es zurueck
    function setForwardYesNo($url)
    {
        if ($url == "home")
        {
            // auf die Startseite verweisen
            $this->forward_url = $GLOBALS['g_homepage'];
        }
        else
        {
            $this->forward_url = $url;
        }       
        $this->yes_no_buttons = true;
    }
	
	function setCloseButton()
	{
		$this->close_button = true;
	}
    
    // die Meldung wird ausgegeben
	// msg_key ist der Schluessel fuer die Nachricht, die angezeigt werden soll
	// msg_variable1 : der erste Platzhalter kann direkt gesetzt werden
	// msg_headline  : Ueberschrift der Nachricht setzen
	// msg_includes  : Flag, ob my_body_top, my_body_bottom my_header eingebunden werden sollen
    function show($msg_key = "" , $msg_variable1 = "", $msg_headline = "", $msg_includes = true)
    {
        // noetig, da dies bei den includes benoetigt wird
        global $g_forum, $g_layout, $g_db, $g_adm_con, $g_adm_db;
        global $g_valid_login, $g_root_path, $g_preferences, $g_homepage;
        global $g_organization, $g_current_organization, $g_current_user;
        
        // Uebergabevariablen auswerten
        if(strlen($msg_key) > 0)
        {
            $this->key = $msg_key;
        }
        
        if(strlen($msg_variable1) > 0)
        {
            $this->variables[0] = "<strong>$msg_variable1</strong>";
        }
        if(strlen($msg_headline) > 0)
        {
            $this->headline = $msg_headline;
        }
        else
        {
            if(strlen($this->headline) == 0)
            {
                if(strlen($this->forward_url) > 0)
                {
                    $this->headline = "Hinweis";
                }
                else
                {
                    $this->headline = "Fehlermeldung";
                }
            }
        }

        // Text auslesen und auf ISO-8859-1 konvertieren
        if(isset($GLOBALS['message_text'][$this->key]))
        {
            $this->content = $GLOBALS['message_text'][$this->key];
        }
        else
        {
            // Text nicht gefunden -> Standard-Meldung
            $this->variables[0] = $msg_key;
            $this->content = $GLOBALS['message_text']["default"];
        }
        
        // Variablen des Messagetextes (%VAR1%, %VAR2% ...) fuellen
        for($i = 0; $i < count($this->variables); $i++)
        {
            $var_name = "%VAR". ($i + 1). "%";
            $this->content = str_replace($var_name, $this->variables[$i], $this->content);
        }
                    
        // Variablen angeben
        if($this->inline == false)
        {
            // nur pruefen, wenn vorher nicht schon auf true gesetzt wurde
            $this->inline = headers_sent();
        }
        $g_root_path  = $GLOBALS['g_root_path'];
        
        if($this->inline == false)
        {
            // Html-Kopf ausgeben
            $g_layout['title']    = "Hinweis";
			$g_layout['includes'] = $msg_includes;
            if ($this->timer > 0)
            {
                $g_layout['header'] = '<script language="JavaScript1.2" type="text/javascript"><!--
                    window.setTimeout("window.location.href=\''. $this->forward_url. '\'", '. $this->timer. ');
                    //--></script>';
            }
    
            require(THEME_SERVER_PATH. "/overall_header.php");       
        }
        
        echo '
        <div class="formLayout" id="message_form" style="width: 350px; margin-top: 60px;">
            <div class="formHead">'. $this->headline. '</div>
            <div class="formBody">
                <p>'. $this->content. '</p>
                <div class="formSubmit">';
                    if(strlen($this->forward_url) > 0)
                    {
                        if($this->yes_no_buttons == true)
                        {
                            echo '
                            <button id="ja" type="button" value="ja" onclick="self.location.href=\''. $this->forward_url. '\'"><img src="'. THEME_PATH. '/icons/ok.png" alt="Ja" />&nbsp;&nbsp;Ja&nbsp;&nbsp;&nbsp;</button>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <button id="nein" type="button" value="nein" onclick="history.back()"><img src="'. THEME_PATH. '/icons/error.png" alt="Nein" />&nbsp;Nein</button>';
                        }
                        else
                        {
                            // Wenn weitergeleitet wird, dann auch immer einen Weiter-Button anzeigen
                            echo '<button id="weiter" type="button" value="weiter" onclick="window.location.href=\''. $this->forward_url. '\'"><img src="'. THEME_PATH. '/icons/forward.png" alt="Weiter" />&nbsp;Weiter</button>';
                        }
                    }
                    else
                    {
                        // Wenn nicht weitergeleitet wird, dann immer einen Zurueck-Button anzeigen 
						// bzw. ggf. einen Fenster-Schließen-Button                       
						if($this->close_button == true)
						{
							echo '<button name="close" type="button" value="schließen" onclick="window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="Schließen" />&nbsp;Schließen</button>';
						}
						else
						{
							echo '<button id="zurueck" type="button" value="zurueck" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="Zurueck" />&nbsp;Zurück</button>';
						}
                    }
                echo '</div>
            </div>
        </div>';
        
        if($this->inline == false)
        {
            require(THEME_SERVER_PATH. "/overall_footer.php");
            exit();
        }
    }
}
?>
