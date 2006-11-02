<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Userobjekt zu erstellen. 
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $user = new User($g_adm_con);
 *
 * Mit der Funktion getUser($user_id) kann nun der gewuenschte User ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * update($login_user_id) - User wird mit den geaenderten Daten in die Datenbank 
 *                          zurueckgeschrieben
 * insert($login_user_id) - Ein neuer User wird in die Datenbank geschrieben
 * delete()               - Der gewaehlte User wird aus der Datenbank geloescht
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * getVCard()             - Es wird eine vCard des Users als String zurueckgegeben
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

include("common.php");

// die letzte Url aus dem Stack loeschen, da dies die aktuelle Seite ist
$_SESSION['navigation']->deleteLastUrl();

// Jetzt die "neue" letzte Url aufrufen
header("Location: ". $_SESSION['navigation']->getUrl());
 
?>