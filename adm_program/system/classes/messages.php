<?php
/******************************************************************************
 * Klasse fuer die Nachrichten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Nachrichtenobjekt zu erstellen.
 * Die Nachrichten können ueber diese Klasse verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * $pm = new Pm();
 *
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * Clear()				  - Daten loeschen
 *
 * GetPm($userid)   	  - Die Anzahl der Nachrichten des aktuellen Users werden gelesen
 *							und die Ausgabevariablien gesetzt.
 * 							$userid = Die User ID des Users 
 *
 * Nix() 				  -	Eine Funktion, die nichts, rein garnichts macht.
 *
 ******************************************************************************/

class Pm
{
	// Allgemeine Variablen
	var $pm;							//Anzahl der Nachrichten
	var $pm_text;						//Nachrichtentext
	var $pm_icon;						//Icon für die Nachrichtenanzeige

	// Konstruktor
	function Pm(&$db)
	{
		$this->db =& $db;
	}

	// Daten loeschen
	function Clear()
	{
		// Session-Valid und Userdaten loeschen
		$this->pm		= 0;
		$this->pm_text	= "keine neue Nachricht";
		$this->pm_icon	= "pm_new.gif";
	}

	// Funktion prueft auf neue PM
	function GetPm($userid)
	{
		// PMs suchen
		//$sql = "SELECT count(*)
		//          FROM adm_pm 
		//         WHERE pm_user_id_to = ".$userid." 
		//           AND pm_read_date IS NULL";
		//$result = $this->db->query($sql);
		//$row = $this->db->fetch_array($result);
		//$this->pm = $row[0];
		
		$this->pm = 0;
		
		if ($this->pm == 0)
		{
		    $this->pm_text = "keine neue Nachricht";
		    $this->pm_icon = "pm_new.gif";
		}
		elseif ($this->pm == 1)
		{
		    $this->pm_text = "<b>1</b> neue Nachricht";
		    $this->pm_icon = "pm_new_ani.gif";
		}
		else
		{
		    $this->pm_text = $this->pm." neue Nachrichten";
		 	$this->pm_icon = "pm_new_ani.gif";
		}
	}

	// Eine Funktion, die nichts, rein garnichts macht. 
	// Wenn der Returncode gleich TRUE, wurde auch in der Tat das nichts machen bestaetigt
	function nix()
	{
		return TRUE;
	}
}
?>