<?php
/**
 ***********************************************************************************************
 * Klasse erweitert das PHP-DateTime-Objekt um einige nuetzliche Funktionen
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class DateTimeExtended extends DateTime
{
    /**
     * Returns an array with all 7 weekdays with full name in the specific language.
     * @param int $weekday The number of the weekday for which the name should be returned (1 = Monday ...)
     * @return string|string[] with all 7 weekday or if param weekday is set than the full name of that weekday
     */
    public static function getWeekdays($weekday = 0)
    {
        global $gL10n;

        $weekdays = array(
            1 => $gL10n->get('SYS_MONDAY'),
            2 => $gL10n->get('SYS_TUESDAY'),
            3 => $gL10n->get('SYS_WEDNESDAY'),
            4 => $gL10n->get('SYS_THURSDAY'),
            5 => $gL10n->get('SYS_FRIDAY'),
            6 => $gL10n->get('SYS_SATURDAY'),
            7 => $gL10n->get('SYS_SUNDAY')
        );

        if ($weekday > 0) {
            return $weekdays[$weekday];
        }

        return $weekdays;
    }

    /**
     * The method will convert a date format with the syntax of date()
     * to a syntax that is known by the bootstrap datepicker plugin.
     * e.g.: input: 'd.m.Y' output: 'dd.mm.yyyy'
     * e.g.: input: 'j.n.y' output: 'd.m.yy'
     * @param string $format Optional a format could be given in the date() syntax that should be transformed.
     *                       If no format is set then the format of the class constructor will be used.
     * @return string Return the transformed format that is valid for the datepicker.
     */
    public static function getDateFormatForDatepicker($format = 'Y-m-d')
    {
        $destFormat  = '';
        $formatArray = str_split($format);

        $characterMapping = array(
            'd' => 'dd',
            'j' => 'd',
            'l' => 'DD',
            'D' => 'D',
            'm' => 'mm',
            'n' => 'm',
            'F' => 'MM',
            'M' => 'M',
            'Y' => 'yyyy',
            'y' => 'yy'
        );

        foreach ($formatArray as $formatChar) {
            if (array_key_exists($formatChar, $characterMapping)) {
                $destFormat .= $characterMapping[$formatChar];
            } else {
                $destFormat .= $formatChar;
            }
        }

        return $destFormat;
    }
}
