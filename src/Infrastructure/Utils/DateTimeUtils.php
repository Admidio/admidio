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
     * Get the localized weekday name for a DateTime object or date string.
     *
     * @param DateTime|string $date   DateTime object or date string
     * @param string          $format 'short' (e.g. "Di" / "Tue") or 'long' (e.g. "Dienstag" / "Tuesday")
     * @param string|null     $locale (optional) Locale identifier (e.g. 'de' or 'en'). Defaults to current user language.
     * @return string
     */
    public static function getLocalizedWeekday(DateTime|string $date, string $format = 'short', ?string $locale = null): string
    {
        if ($format === 'none') {
            return '';
        }

        if (is_string($date)) {
            $parsedDate = self::parseDate($date);
            if ($parsedDate === null) {
                return '';
            }
            $date = $parsedDate;
        }

        global $gL10n;

        if ($locale === null && isset($gL10n)) {
            $locale = $gL10n->getLanguage();
        }

        if (class_exists('\IntlDateFormatter')) {
            $pattern = ($format === 'long') ? 'cccc' : 'ccc';
            $formatter = new \IntlDateFormatter(
                $locale ?: 'en',
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $date->getTimezone(),
                \IntlDateFormatter::GREGORIAN,
                $pattern
            );
            if ($formatter !== false) {
                $result = $formatter->format($date);
                if ($result !== false && $result !== '') {
                    return rtrim($result, '.');
                }
            }
        }

        // Fallback using Admidio language strings if ext-intl is not available
        $weekdays = array(
            1 => 'SYS_MONDAY',
            2 => 'SYS_TUESDAY',
            3 => 'SYS_WEDNESDAY',
            4 => 'SYS_THURSDAY',
            5 => 'SYS_FRIDAY',
            6 => 'SYS_SATURDAY',
            7 => 'SYS_SUNDAY'
        );
        $dayOfWeek = (int)$date->format('N');
        $weekdayKey = $weekdays[$dayOfWeek] ?? 'SYS_MONDAY';
        $weekdayName = isset($gL10n) ? $gL10n->get($weekdayKey) : $date->format('l');

        if ($format === 'short') {
            return mb_substr($weekdayName, 0, 2);
        }

        return $weekdayName;
    }

    /**
     * Format a date with an optional localized weekday prefix.
     *
     * @param DateTime|string $date          DateTime object or date string
     * @param string|null     $weekdayFormat 'none', 'short', 'long', or null to read from settings
     * @param string|null     $dateFormat    Date format (e.g. 'd.m.Y'). Defaults to system_date
     * @param string|null     $locale        (optional) Locale identifier
     * @param bool            $asHtml        (optional) Whether to wrap weekday in a styled span for column alignment
     * @return string
     */
    public static function formatWithWeekday(
        DateTime|string $date,
        ?string $weekdayFormat = null,
        ?string $dateFormat = null,
        ?string $locale = null,
        bool $asHtml = false
    ): string {
        global $gSettingsManager;

        if ($dateFormat === null) {
            $dateFormat = (isset($gSettingsManager) && $gSettingsManager->has('system_date'))
                ? $gSettingsManager->getString('system_date')
                : 'd.m.Y';
        }

        if (is_string($date)) {
            $dateTime = self::parseDate($date);
            if ($dateTime === null) {
                return $date;
            }
        } else {
            $dateTime = $date;
        }

        $formattedDate = $dateTime->format($dateFormat);

        if ($weekdayFormat === null) {
            $weekdayFormat = (isset($gSettingsManager) && $gSettingsManager->has('events_weekday_format'))
                ? $gSettingsManager->getString('events_weekday_format')
                : 'short';
        }

        if ($weekdayFormat === 'none') {
            return $formattedDate;
        }

        $weekday = self::getLocalizedWeekday($dateTime, $weekdayFormat, $locale);
        if ($weekday === '') {
            return $formattedDate;
        }

        if ($asHtml) {
            $weekdayClass = 'admidio-event-weekday admidio-event-weekday-' . htmlspecialchars($weekdayFormat, ENT_QUOTES, 'UTF-8');
            return '<span class="' . $weekdayClass . '">' . $weekday . ',</span> ' . $formattedDate;
        }

        return $weekday . ', ' . $formattedDate;
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
