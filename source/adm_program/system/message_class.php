<?php
/******************************************************************************
 * Klasse fuer die Ausgabe von Hinweistexten oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
    function addVariableContent($content, $variable_number = 0)
    {
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
        // Uebergabevariablen auswerten
        if(strlen($msg_key) > 0)
        {
            $this->key = $msg_key;
        }
        
        if(strlen($msg_variable1) > 0)
        {
            $this->variables[0] = utf8_decode($msg_variable1);
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
        $this->inline = headers_sent();
        $g_root_path  = $GLOBALS['g_root_path'];
        
        if($this->inline == false)
        {
            echo '
            <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: '. getVersion(). ' -->
            <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
            <html>
            <head>
                <title>'. $GLOBALS['g_current_organization']->longname. ' - Messagebox</title>
                <link rel="stylesheet" type="text/css" href="'. $g_root_path. '/adm_config/main.css">
        
                <!--[if lt IE 7]>
                <script language="JavaScript" src="'. $g_root_path. '/adm_program/system/correct_png.js"></script>
                <![endif]-->';
        
                if ($this->timer > 0)
                {
                    echo '<script language="JavaScript1.2" type="text/javascript"><!--
                           window.setTimeout("window.location.href=\''. $this->forward_url. '\'", '. $this->timer. ');
                           //--></script>';
                }
        
                require($GLOBALS['g_server_path']. "/adm_config/header.php");
            echo '</head>';
            require($GLOBALS['g_server_path']. "/adm_config/body_top.php");         
        }
        
        echo '
        <div style="margin-top: 10px; margin-bottom: 10px;" align="center"><br /><br />
            <div class="formHead" style="width: 350px">'. strspace($this->headline). '</div>
        
            <div class="formBody" style="width: 350px">
                <p>'. $this->content. '</p>
                <p>';
                    if($this->timer > 0)
                    {
                        echo "&nbsp;";
                    }
                    else
                    {
                        if(strlen($this->forward_url) > 0)
                        {
                            if($this->yes_no_buttons == false)
                            {
                                echo '<button id="weiter" type="button" value="weiter" onclick="window.location.href=\''. $this->forward_url. '\'">
                                <img src="'. $g_root_path. '/adm_program/images/forward.png" style="vertical-align: middle;" align="top" vspace="1" width="16" height="16" border="0" alt="Weiter">
                                &nbsp;Weiter</button>';
                            }
                            else
                            {
                                echo '<button id="ja" type="button" value="ja"
                                onclick="self.location.href=\''. $this->forward_url. '\'">
                                <img src="'. $g_root_path. '/adm_program/images/ok.png" style="vertical-align: middle;" align="top" vspace="1" width="16" height="16" border="0" alt="Ja">
                                &nbsp;&nbsp;Ja&nbsp;&nbsp;&nbsp;</button>
                                &nbsp;&nbsp;&nbsp;&nbsp;
                                <button id="nein" type="button" value="nein"
                                onclick="history.back()">
                                <img src="'. $g_root_path. '/adm_program/images/error.png" style="vertical-align: middle;" align="top" vspace="1" width="16" height="16" border="0" alt="Nein">
                                &nbsp;Nein</button>';
                            }
                        }
                        else
                        {
                            echo '<button id="zurueck" type="button" value="zurueck" onclick="history.back()">
                            <img src="'. $g_root_path. '/adm_program/images/back.png" style="vertical-align: middle;" align="top" vspace="1" width="16" height="16" border="0" alt="Zurueck">
                            &nbsp;Zur&uuml;ck</button>';
                        }
                    }
                echo '</p>
            </div>
        </div>';
        
        if($this->inline == false)
        {
            require($GLOBALS['g_server_path']. "/adm_config/body_bottom.php");
            echo '</body></html>';
            exit();
        }
    }
}
?>
