<?php

namespace Admidio\Infrastructure\Utils;

use Admidio\Infrastructure\PdfDocument;

/**
 * PDF export configuration shared by web and CLI exports.
 *
 * @copyright The Admidio Team
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PdfUtils
{
    public static function createDocument(string $orientation, string $heading): PdfDocument
    {
        // tc-lib-pdf-font discovers the bundled JSON font metrics through this path.
        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', dirname(__DIR__, 3) . '/libs/pdf-fonts/');
        }

        return new PdfDocument($orientation, $heading);
    }
}
