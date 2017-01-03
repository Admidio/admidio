/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- delete old gender entries with 0. This was set in Admidio 1.x
Delete from %PREFIX%_user_data
 where usd_usf_id =
         (Select usf_id from %PREFIX%_user_fields
           where usf_name_intern = 'GENDER')
   and usd_value = '0';
