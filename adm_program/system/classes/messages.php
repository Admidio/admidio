<?php
/******************************************************************************
 * Klasse fuer die Nachrichten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Nachrichtenobjekt zu erstellen.
 * Die Nachrichten können ueber diese Klasse verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $messages = new Messages($g_db);
 *
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * Clear()				  - Daten loeschen
 *
 * GetMessages($userid)   - Die Anzahl der Nachrichten des aktuellen Users werden gelesen
 *							und die Ausgabevariablien gesetzt.
 * 							$userid = Die User ID des Users 
 *
 * Nix() 				  -	Eine Funktion, die nichts, rein garnichts macht.
 *
 ******************************************************************************/

class Messages
{
	// Allgemeine Variablen
	var $msg;							//Anzahl der Nachrichten
	var $msg_text;						//Nachrichtentext
	var $msg_icon;						//Icon für die Nachrichtenanzeige

	// Konstruktor
	function messages(&$db)
	{
		$this->db =& $db;
	}

	// Daten loeschen
	function Clear()
	{
		// Session-Valid und Userdaten loeschen
		$this->msg		= 0;
		$this->msg_text	= "keine neue Nachricht";
		$this->msg_icon	= "pm_new.gif";
	}

	// Funktion prueft auf neue PM
	function GetMessages($userid)
	{
		// Nachrichten suchen
		//$sql = "SELECT count(*)
		//          FROM adm_messages 
		//         WHERE msg_user_id_to = ".$userid." 
		//           AND msg_read_date IS NULL";
		//$result = $this->db->query($sql);
		//$row = $this->db->fetch_array($result);
		//$this->msg = $row[0];
		
		$this->msg = 0;
		
		if ($this->msg == 0)
		{
		    $this->msg_text = "keine neue Nachricht";
		    $this->msg_icon = "message_new.gif";
		}
		elseif ($this->msg == 1)
		{
		    $this->msg_text = "<b>1</b> neue Nachricht";
		    $this->msg_icon = "message_new_ani.gif";
		}
		else
		{
		    $this->msg_text = $this->msg." neue Nachrichten";
		 	$this->msg_icon = "message_new_ani.gif";
		}
	}

	// Eine Funktion, die nichts, rein garnichts macht. 
	// Wenn der Returncode gleich TRUE, wurde auch in der Tat das nichts machen bestaetigt
	function Nix()
	{
		return TRUE;
	}
}
?>