<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Image;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class IconHtmlTest extends TestCase
{
    public static function tooltipTexts(): array
    {
        return array(
            array('x" onmouseover="alert(1)'),
            array('"><img src=x onerror=alert(1)>'),
            array('Förderer "A" & O\'Brien'),
            array('Literal &quot; and &#34; entities')
        );
    }

    /**
     * @dataProvider tooltipTexts
     */
    public function testTooltipRemainsTextAfterJsonAndHtmlParsing(string $text): void
    {
        $html = Image::getIconHtml('bi-person', $text);
        $html = json_decode(json_encode(array('data' => array(array($html)))), true)['data'][0][0];
        $icon = $this->parseIcon($html);

        $this->assertSame($text, $icon->getAttribute('title'));
        $this->assertSame(3, $icon->attributes->length);
        $this->assertSame('bi bi-person ', $icon->getAttribute('class'));
    }

    public function testCssClassCannotIntroduceAttributesWithOrWithoutTooltip(): void
    {
        $cssClass = 'text-muted" onmouseover="alert(1)';
        foreach (array('', 'Tooltip') as $text) {
            $icon = $this->parseIcon(Image::getIconHtml('person', $text, $cssClass));
            $this->assertSame('bi bi-person ' . $cssClass, $icon->getAttribute('class'));
            $this->assertFalse($icon->hasAttribute('onmouseover'));
            $this->assertSame($text === '' ? 1 : 3, $icon->attributes->length);
        }
    }

    /**
     * @dataProvider tooltipTexts
     */
    public function testProfileRadioOptionAndFallbackLabelAreEncodedOnce(string $text): void
    {
        foreach (array('bi-person|' . $text, 'bi-person') as $option) {
            $field = $this->createMock(ProfileField::class);
            $field->method('getValue')->willReturnCallback(
                static function (string $column, string $format = '') use ($option, $text) {
                    if ($column === 'usf_type') {
                        return 'RADIO_BUTTON';
                    }
                    if ($column === 'ufo_usf_options') {
                        return array(array('id' => 1, 'value' => $option));
                    }
                    if ($column === 'usf_name' && $format === 'database') {
                        return $text;
                    }
                    if ($column === 'usf_url') {
                        return '';
                    }
                    throw new \LogicException('Unexpected profile field access: ' . $column);
                }
            );
            $fields = (new ReflectionClass(ProfileFields::class))->newInstanceWithoutConstructor();
            (new ReflectionProperty(ProfileFields::class, 'mProfileFields'))->setValue($fields, array('TEST_RADIO' => $field));

            $icon = $this->parseIcon($fields->getHtmlValue('TEST_RADIO', 1));
            $this->assertSame($text, $icon->getAttribute('title'));
            $this->assertSame(3, $icon->attributes->length);
        }
    }

    private function parseIcon(string $html): DOMElement
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $this->assertSame(0, $document->getElementsByTagName('img')->length);
        $this->assertSame(1, $document->getElementsByTagName('i')->length);
        return $document->getElementsByTagName('i')->item(0);
    }
}
