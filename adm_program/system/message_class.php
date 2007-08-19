<?php
/******************************************************************************
 * Klasse fuer die Ausgabe von Hinweistexten oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 *****************************************************************************/

class Message
{
    var $key;
    var $variables;
    var $headline;
    var $content;
    var $inline;
    var $forward_url;
    var $timer;
    var $yes_no_buttons;
    
    function Message()
    {
        $this->inline = false;      
    }
    
    // Inhalt fuer eine Variable hinzufuegen
    // wird die Variablennummer nicht uebergeben, so wird automatisch eine neue mit 
    // der aktellen Nummer genommen
    function addVariableContent($content, $variable_number = 0, $show_bold = true)
    {
        if($show_bold)
        {
            $content = "<strong>". utf8_decode($content). "</strong>";
        }
        else
        {
            $content = utf8_decode($content);
        }


        if($variable_number > 0)
        {
            $variable_number--;
            $this->variables[$variable_number] = utf8_decode($content);
        }
        else
        {
            $this->variables[] = utf8_decode($content);
        }
    }
    
    // URL muss uebergeben werden, auf die danach automatisch weitergeleitet wird
    // ist timer > 0 wird nach x Millisec. automatisch auf die URL weitergeleitet
    function setForwardUrl($url, $timer = 0)
    {
        if ($url == "home")
        {
            // auf die Startseite verweisen
            $this->forward_url = $GLOBALS['g_root_path']. "/". $GLOBALS['g_main_page'];
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
            $this->forward_url = $GLOBALS['g_root_path']. "/". $GLOBALS['g_main_page'];
        }
        else
        {
            $this->forward_url = $url;
        }       
        $this->yes_no_buttons = true;
    }
    
    // die Meldung wird ausgegeben
    function show($msg_key = "" , $msg_variable1 = "", $msg_headline = "")
    {
        // noetig, da dies bei den includes benoetigt wird
        global $g_forum, $g_layout;
        global $g_valid_login, $g_root_path, $g_preferences;
        global $g_db, $g_adm_con, $g_adm_db, $g_current_url;
        global $g_organization, $g_current_organization, $g_current_user;
        
        // Uebergabevariablen auswerten
        if(strlen($msg_key) > 0)
        {
            $this->key = $msg_key;
        }
        
        if(strlen($msg_variable1) > 0)
        {
            $this->variables[0] = "<strong>". utf8_decode($msg_variable1). "</strong>";
        }
        if(strlen($msg_headline) > 0)
        {
            $this->headline = utf8_decode($msg_headline);
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
            $this->content = utf8_decode($GLOBALS['message_text'][$this->key]);
        }
        else
        {
            // Text nicht gefunden -> Standard-Meldung
            $this->variables[0] = $msg_key;
            $this->content = utf8_decode($GLOBALS['message_text']["default"]);
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
            $g_layout['title']  = "Hinweis";
            if ($this->timer > 0)
            {
                $g_layout['header'] = '<script language="JavaScript1.2" type="text/javascript"><!--
                    window.setTimeout("window.location.href=\''. $this->forward_url. '\'", '. $this->timer. ');
                    //--></script>';
            }
    
            require(SERVER_PATH. "/adm_program/layout/overall_header.php");       
        }
        
        echo '
        <br /><br />
        <div class="formLayout" id="message_form" style="width: 350px">
            <div class="formHead">'. $this->headline. '</div>
            <div class="formBody">
                <p>'. $this->content. '</p>
                <p>';
                    if(strlen($this->forward_url) > 0)
                    {
                        if($this->yes_no_buttons == true)
                        {
                            echo '
                            <button id="ja" type="button" value="ja" onclick="self.location.href=\''. $this->forward_url. '\'">
                                <img src="'. $g_root_path. '/adm_program/images/ok.png" alt="Ja">
                                &nbsp;&nbsp;Ja&nbsp;&nbsp;&nbsp;
                            </button>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <button id="nein" type="button" value="nein" onclick="history.back()">
                                <img src="'. $g_root_path. '/adm_program/images/error.png" alt="Nein">
                                &nbsp;Nein
                            </button>';
                        }
                        else
                        {
                            // Wenn weitergeleitet wird, dann auch immer einen Weiter-Button anzeigen
                            echo '<button id="weiter" type="button" value="weiter" onclick="window.location.href=\''. $this->forward_url. '\'">
                            <img src="'. $g_root_path. '/adm_program/images/forward.png" alt="Weiter">
                            &nbsp;Weiter</button>';
                        }
                    }
                    else
                    {
                        // Wenn nicht weitergeleitet wird, dann immer einen Zurueck-Button anzeigen
                        echo '<button id="zurueck" type="button" value="zurueck" onclick="history.back()">
                        <img src="'. $g_root_path. '/adm_program/images/back.png" alt="Zurueck">
                        &nbsp;Zur&uuml;ck</button>';
                    }
                echo '</p>
            </div>
        </div>';
        
        if($this->inline == false)
        {
            require(SERVER_PATH. "/adm_program/layout/overall_footer.php");
            exit();
        }
    }
}
?>
