/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- manipulate data
UPDATE %PREFIX%_preferences SET prf_value = 'postcard.tpl' WHERE prf_value like 'brief_standard.tpl';
UPDATE %PREFIX%_preferences SET prf_value = 'postcard_separate_photo.tpl' WHERE prf_value like 'brief_grosses_foto.tpl';
UPDATE %PREFIX%_preferences SET prf_value = 'greeting_card.tpl' WHERE prf_value like 'grusskarte.tpl';
