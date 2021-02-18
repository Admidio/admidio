<?php
/**
 ***********************************************************************************************
 * Konfigurationsdaten fuer das Admidio-Plugin Kategoriereport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */

global $gL10n, $gProfileFields;

//Standardwerte einer Neuinstallation oder beim Anfuegen einer zusaetzlichen Konfiguration

$config_default['Konfigurationen'] = array(	'col_desc' 		=> array($gL10n->get('PLG_KATEGORIEREPORT_PATTERN')),
											'col_fields' 	=> array(	'p'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').','.
																		'p'.$gProfileFields->getProperty('LAST_NAME', 'usf_id').','.
																		'p'.$gProfileFields->getProperty('STREET', 'usf_id').','.
																		'p'.$gProfileFields->getProperty('CITY', 'usf_id')),
											'col_yes'		=> array('ja'),
											'col_no'		=> array('nein'),
 											'selection_role'=> array(' '),
											'selection_cat'	=> array(' '),
											'number_col'	=> array(0)  );

$config_default['Optionen']['config_default'] = 0; 
															
$config_default['Plugininformationen']['version'] = '';
$config_default['Plugininformationen']['stand'] = '';

/*
 *  Mittels dieser Zeichenkombination werden Konfigurationsdaten, die zur Laufzeit als Array verwaltet werden,
 *  zu einem String zusammengefasst und in der Admidiodatenbank gespeichert. 
 *  Muss die vorgegebene Zeichenkombination (#_#) jedoch ebenfalls, z.B. in der Beschreibung 
 *  einer Konfiguration, verwendet werden, so kann das Plugin gespeicherte Konfigurationsdaten 
 *  nicht mehr richtig einlesen. In diesem Fall ist die vorgegebene Zeichenkombination abzuaendern (z.B. in !-!)
 *  
 *  Achtung: Vor einer Aenderung muss eine Deinstallation durchgefuehrt werden!
 *  Bereits gespeicherte Werte in der Datenbank koennen nach einer Aenderung nicht mehr eingelesen werden!
 */
$dbtoken  = '#_#';  
