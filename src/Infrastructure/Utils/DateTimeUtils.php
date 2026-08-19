<?php
namespace Admidio\Infrastructure\Utils;

use DateTime;
use InvalidArgumentException;

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class DateTimeUtils
{
    /**
     * Parse a date using the internal ISO date format or the configured Admidio
     * date format.
     *
     * If the date cannot be parsed and a fallback date is supplied, the
     * fallback date is returned. Without a fallback, null is returned.
     *
     * @param string      $date         Date that should be parsed
     * @param string|null $fallbackDate Optional fallback date in ISO format (Y-m-d)
     * @return DateTime|null
     */
    public static function parseDate(string $date, ?string $fallbackDate = null): ?DateTime 
    {
        global $gSettingsManager;

        $dateObject = self::createDateFromFormat('Y-m-d', $date);

        if ($dateObject === null) {
            $dateObject = self::createDateFromFormat(
                $gSettingsManager->getString('system_date'),
                $date
            );
        }

        if ($dateObject !== null) {
            return $dateObject;
        }

        if ($fallbackDate === null) {
            return null;
        }

        $fallbackDateObject = self::createDateFromFormat('Y-m-d', $fallbackDate);

        if ($fallbackDateObject === null) {
            throw new InvalidArgumentException('Invalid fallback date "' . $fallbackDate . '".');
        }

        return $fallbackDateObject;
    }

    /**
     * Parse a date and time using the internal Admidio formats or the configured
     * system date and time formats.
     *
     * The following formats are supported:
     * - Y-m-d H:i:s
     * - Y-m-d H:i
     * - Y-m-d\TH:i
     * - configured system_date + system_time
     *
     * If the value cannot be parsed and a fallback value is supplied, the fallback
     * must use the internal format Y-m-d H:i:s or Y-m-d H:i.
     *
     * @param string      $dateTime         Date and time that should be parsed
     * @param string|null $fallbackDateTime Optional fallback date and time
     * @return DateTime|null
     */
    public static function parseDateTime(string $dateTime, ?string $fallbackDateTime = null): ?DateTime 
    {
        global $gSettingsManager;

        $formats = array(
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i',
            $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')
        );

        foreach ($formats as $format) {
            $dateTimeObject = self::createDateFromFormat($format, $dateTime);
            if ($dateTimeObject !== null) {
                return $dateTimeObject;
            }
        }

        if ($fallbackDateTime === null) {
            return null;
        }

        foreach (array('Y-m-d H:i:s', 'Y-m-d H:i') as $format) {
            $fallbackDateTimeObject = self::createDateFromFormat($format, $fallbackDateTime);
            if ($fallbackDateTimeObject !== null) {
                return $fallbackDateTimeObject;
            }
        }

        throw new InvalidArgumentException('Invalid fallback date and time "' . $fallbackDateTime . '".');
    }

    /**
     * Create a date from a specific format and reject parsing warnings/errors.
     *
     * @param string $format Date format
     * @param string $date   Date value
     * @return DateTime|null
     */
    private static function createDateFromFormat(string $format, string $date): ?DateTime 
    {
        $dateObject = DateTime::createFromFormat($format, $date);

        if ($dateObject === false) {
            return null;
        }

        $errors = DateTime::getLastErrors();

        if ($errors !== false
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $dateObject;
    }
}
