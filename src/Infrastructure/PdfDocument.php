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

    public function __construct(string $orientation, string $heading, ?string $hyphenationLanguage = null)
    {
        $this->heading = $heading;
        parent::__construct(unit: 'mm');
        $this->setCreator('Admidio');
        $this->setAuthor('Admidio');
        $this->setTitle($heading);
        $this->font->insert($this->pon, 'helvetica', '', 10);

        // tc-lib-pdf ships no language patterns. The bundled TeX patterns add soft hyphens only
        // at valid positions, so narrow PDF columns remain readable without cutting words.
        $patternFiles = array(
            'de' => 'hyph-de-1996.tex',
            'en' => 'hyph-en-us.tex',
            'fr' => 'hyph-fr.tex'
        );
        $language = strtolower((string)$hyphenationLanguage);
        if (array_key_exists($language, $patternFiles)) {
            $patternsFile = dirname(__DIR__, 2) . '/libs/pdf-hyphenation/' . $patternFiles[$language];
            if (is_file($patternsFile)) {
                // tc-lib-pdf accepts only explicitly trusted local paths. Retain its default
                // paths and add the bundled pattern directory instead of widening the trust scope.
                $this->file->setAllowedPaths(array_merge(
                    $this->defaultFileAllowedPaths(),
                    [dirname($patternsFile)]
                ));
                $this->setTexHyphenPatterns($this->loadTexHyphenPatterns($patternsFile));
            }
        }

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
