<?php

namespace Admidio\Infrastructure;

use Com\Tecnick\Pdf\Tcpdf;

/**
 * List export document using the native tc-lib-pdf API.
 *
 * @copyright The Admidio Team
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PdfDocument extends Tcpdf
{
    private string $heading;

    public function __construct(string $orientation, string $heading)
    {
        $this->heading = $heading;
        parent::__construct(unit: 'mm');
        $this->setCreator('Admidio');
        $this->setAuthor('Admidio');
        $this->setTitle($heading);
        $this->font->insert($this->pon, 'helvetica', '', 10);
        $this->enableDefaultPageContent();
        $this->addPage([
            'format' => 'A4',
            'orientation' => $orientation,
            'autobreak' => true,
            'margin' => [
                'PL' => 10, 'PR' => 10, 'PT' => 0, 'HB' => 0,
                'CT' => 20, 'CB' => 25, 'FT' => 0, 'PB' => 0,
            ],
        ]);
    }

    public function defaultPageContent(int $pid = -1): string
    {
        $page = $this->page->getPage($pid);
        $font = $this->font->insert($this->pon, 'helvetica', 'B', 11);
        try {
            return $this->graph->getStartTransform()
                . $font['out']
                . $this->color->getPdfFillColor('black')
                . $this->getTextCell($this->heading, 10, 10, $page['width'] - 20, 0, 0, 0, 'T', 'L', self::ZEROCELL)
                . $this->graph->getStopTransform();
        } finally {
            $this->font->popLastFont();
        }
    }

    public function writeTable(string $html): void
    {
        $region = $this->page->getRegion();
        // Keep the existing line spacing and let HTML define the cell padding.
        $this->addHTMLCell(
            '<div style="font-family:helvetica;font-size:10pt;line-height:1.25;">' . $html . '</div>',
            $region['RX'],
            $region['RY'],
            $region['RW'],
            0,
            self::ZEROCELL
        );
    }
}
