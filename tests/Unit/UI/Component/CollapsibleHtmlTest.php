<?php

namespace Admidio\Tests\Unit\UI\Component;

use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\UI\Component\CollapsibleHtml;

class CollapsibleHtmlTest extends AdmidioTestCase
{
    /**
     * @testdox Short HTML remains unchanged
     */
    public function testKeepsShortContent(): void
    {
        $html = '<p><em>Kurz</em> und vollständig.</p>';

        $this->assertSame($html, CollapsibleHtml::render($html, 200, 'details-test', 'Show more'));
    }

    /**
     * @testdox Content is cut at the last space before the character limit
     */
    public function testCutsAtLastWordBoundary(): void
    {
        $html = CollapsibleHtml::render(
            '<p>one two three four five</p>',
            19,
            'details-test',
            'Show more'
        );

        $this->assertStringContainsString('<p>one two three four <a ', $html);
        $this->assertStringContainsString(
            '<div class="collapse" id="details-test"><p>one two three four five</p></div>',
            $html
        );
        $this->assertStringNotContainsString('one two three four f<a ', $html);
    }

    /**
     * @testdox Tags do not count towards the character limit
     */
    public function testDoesNotCountHtmlTags(): void
    {
        $html = CollapsibleHtml::render(
            '<p><strong>12345</strong> 67890 next</p>',
            12,
            'details-test',
            'Show more'
        );

        $this->assertStringContainsString('<strong>12345</strong> 67890 <a ', $html);
        $this->assertStringContainsString(
            '<div class="collapse" id="details-test"><p><strong>12345</strong> 67890 next</p></div>',
            $html
        );
    }

    /**
     * @testdox The link follows inline tags but stays inside paragraphs and divs
     */
    public function testPositionsLinkAtCut(): void
    {
        $html = CollapsibleHtml::render(
            '<div><p>Hello <strong>world and friends</strong> afterwards</p></div>',
            12,
            'details-test',
            'Show more'
        );

        $strongEnd = strpos($html, '</strong>');
        $showMoreLink = strpos($html, '<a class="admidio-icon-link"');
        $paragraphEnd = strpos($html, '</p>');
        $outerDivEnd = strpos($html, '</div>');
        $collapsedContent = strpos($html, '<div class="collapse" id="details-test">');

        $this->assertNotFalse($strongEnd);
        $this->assertNotFalse($showMoreLink);
        $this->assertNotFalse($paragraphEnd);
        $this->assertNotFalse($outerDivEnd);
        $this->assertNotFalse($collapsedContent);
        $this->assertLessThan($showMoreLink, $strongEnd);
        $this->assertLessThan($paragraphEnd, $showMoreLink);
        $this->assertLessThan($outerDivEnd, $paragraphEnd);
        $this->assertLessThan($collapsedContent, $outerDivEnd);
        $this->assertStringContainsString('title="Show more"', $html);
        $this->assertStringContainsString(
            'onclick="this.closest(\'.admidio-collapsible-html-preview\').classList.add(\'d-none\');"',
            $html
        );
        $this->assertStringContainsString(
            '<div class="collapse" id="details-test"><div><p>Hello <strong>world and friends</strong> afterwards</p></div></div>',
            $html
        );
    }
}
