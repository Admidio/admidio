<?php

namespace Admidio\Infrastructure;

use TCPDF;

/**
 * PDF document with a borderless page heading.
 *
 * @copyright The Admidio Team
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PdfDocument extends TCPDF
{
    public function Header()
    {
        $font = $this->getHeaderFont();
        $header = $this->getHeaderData();
        $margins = $this->getMargins();
        $this->SetXY($margins['left'], $this->getHeaderMargin());
        $this->setTextColorArray($header['text_color']);
        $this->SetFont($font[0], 'B', $font[2] + 1);
        $this->MultiCell(0, 0, $header['title'], 0, 'L');

        if ($header['string'] !== '') {
            $this->SetFont($font[0], $font[1], $font[2]);
            $this->MultiCell(0, 0, $header['string'], 0, 'L');
        }
    }
}
