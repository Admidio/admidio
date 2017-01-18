/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- Kategorienreihenfolge anpassen
UPDATE %PREFIX%_categories SET cat_sequence = cat_sequence + 1
 WHERE cat_type = 'USF';
