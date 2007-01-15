/******************************************************************************
 * Anzeigen und Ausblenden der Organisationseinstellungesgruppen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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

// Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
ids = new Array("general", "register", "announcement-module", "download-module", "mail-module",
                "photo-module", "guestbook-module", "dates-module", "links-module");


// Die eigentliche Funktion: Schaltet die Einstellungsdialoge durch
function toggleDiv(element_id)
{
    var i;
    for (i=0;i<ids.length;i++)
    {
        // Erstmal alle DIVs aus unsichtbar setzen
        document.getElementById(ids[i]).style.visibility = "hidden";
        document.getElementById(ids[i]).style.display    = "none";
    }
    // Angeforderten Bereich anzeigen
    document.getElementById(element_id).style.visibility = "visible";
    document.getElementById(element_id).style.display    = "block";
    // window.blur();
}