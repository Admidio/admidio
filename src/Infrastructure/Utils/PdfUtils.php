<?php

namespace Admidio\Infrastructure\Utils;

use TCPDF;

/**
 * PDF export configuration shared by web and CLI exports.
 *
 * @copyright The Admidio Team
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PdfUtils
{
    public static function createDocument(string $orientation): TCPDF
    {
        // TCPDF 7 needs JSON font metrics, which its Composer package does not ship.
        // Configure our bundled core fonts before TCPDF is autoloaded.
        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', dirname(__DIR__, 3) . '/libs/pdf-fonts/');
        }

        return new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
    }
}
