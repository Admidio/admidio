<?php

namespace Admidio\UI\Component;

/**
 * Render long HTML content with a Bootstrap link that reveals the remaining content.
 */
final class CollapsibleHtml
{
    /**
     * Limit HTML by visible characters and add a collapse link at the last word boundary.
     * HTML tags do not count towards the limit. Inline elements are closed before the link;
     * enclosing paragraph and div elements are closed afterwards.
     *
     * @param string $html HTML content that should be shortened.
     * @param int $maxCharacters Maximum number of visible characters in the preview.
     * @param string $collapseId Unique HTML id for the collapsible full content.
     * @param string $showMoreText Text for the link title and accessible label.
     * @return string Original HTML or markup containing a shortened preview and the collapsible full content.
     */
    public static function render(
        string $html,
        int $maxCharacters,
        string $collapseId,
        string $showMoreText
    ): string {
        if ($html === '' || $maxCharacters < 1) {
            return $html;
        }

        $document = new \DOMDocument();
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="admidio-html-split-root">'
            . $html . '</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $root = $document->getElementById('admidio-html-split-root');
        if ($root === null) {
            return $html;
        }

        $visibleCharacters = preg_split('//u', $root->textContent, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($visibleCharacters) || count($visibleCharacters) <= $maxCharacters) {
            return $html;
        }

        $cutLength = $maxCharacters;
        for ($index = $maxCharacters - 1; $index > 0; --$index) {
            if (preg_match('/\s/u', $visibleCharacters[$index]) === 1) {
                $cutLength = $index;
                break;
            }
        }

        [$preview, $rest] = self::splitRoot($root, $cutLength);
        if ($rest === '') {
            return $html;
        }

        $encodedId = htmlspecialchars($collapseId, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $encodedShowMoreText = htmlspecialchars($showMoreText, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $showMoreLink = ' <a class="admidio-icon-link" href="#' . $encodedId
            . '" data-bs-toggle="collapse" aria-expanded="false" aria-controls="' . $encodedId
            . '" title="' . $encodedShowMoreText . '" aria-label="' . $encodedShowMoreText
            . '" onclick="this.closest(\'.admidio-collapsible-html-preview\').classList.add(\'d-none\');">»</a>';

        $preview = preg_replace_callback(
            '/((?:\s*<\/(?:p|div)>)+\s*)$/i',
            static fn (array $matches): string => $showMoreLink . $matches[1],
            $preview,
            1,
            $replacementCount
        );
        if ($replacementCount === 0) {
            $preview .= $showMoreLink;
        }

        return '<div class="admidio-collapsible-html-preview">' . $preview . '</div>'
            . '<div class="collapse" id="' . $encodedId . '">' . $html . '</div>';
    }

    /**
     * Split all child nodes of the parsed root element at the visible character limit.
     *
     * @param \DOMElement $root Wrapper element containing the HTML fragment.
     * @param int $length Number of visible characters assigned to the preview.
     * @return array{0: string, 1: string}
     */
    private static function splitRoot(\DOMElement $root, int $length): array
    {
        $remaining = $length;
        $preview = '';
        $rest = '';
        foreach ($root->childNodes as $child) {
            [$previewPart, $restPart] = self::splitNode($child, $remaining);
            $preview .= $previewPart;
            $rest .= $restPart;
        }

        return array($preview, $rest);
    }

    /**
     * Split one DOM node and recreate valid HTML for the preview and remaining content.
     *
     * @param \DOMNode $node Node that should be split.
     * @param int $remaining Number of visible characters still available in the preview.
     * @return array{0: string, 1: string}
     */
    private static function splitNode(\DOMNode $node, int &$remaining): array
    {
        if ($node instanceof \DOMText) {
            $text = $node->nodeValue;
            $textLength = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

            if ($textLength <= $remaining) {
                $remaining -= $textLength;
                return array(self::encodeText($text), '');
            }

            $previewText = function_exists('mb_substr')
                ? mb_substr($text, 0, $remaining, 'UTF-8')
                : substr($text, 0, $remaining);
            $restText = function_exists('mb_substr')
                ? mb_substr($text, $remaining, null, 'UTF-8')
                : substr($text, $remaining);
            $remaining = 0;

            return array(self::encodeText($previewText), self::encodeText($restText));
        }

        if (!$node instanceof \DOMElement) {
            return array('', '');
        }

        $preview = '';
        $rest = '';
        foreach ($node->childNodes as $child) {
            [$previewPart, $restPart] = self::splitNode($child, $remaining);
            $preview .= $previewPart;
            $rest .= $restPart;
        }

        $tag = strtolower($node->tagName);
        $openingTag = self::openingTag($node);
        if (in_array($tag, array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'), true)) {
            return $rest === '' ? array($openingTag, '') : array('', $openingTag);
        }

        $closingTag = '</' . $node->tagName . '>';
        return array(
            $preview === '' ? '' : $openingTag . $preview . $closingTag,
            $rest === '' ? '' : $openingTag . $rest . $closingTag
        );
    }

    /**
     * Create an opening HTML tag including its encoded attributes.
     *
     * @param \DOMElement $element Element whose opening tag should be created.
     * @return string Serialized opening tag.
     */
    private static function openingTag(\DOMElement $element): string
    {
        $attributes = '';
        foreach ($element->attributes as $attribute) {
            $attributes .= ' ' . $attribute->nodeName . '="'
                . htmlspecialchars($attribute->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
        }

        return '<' . $element->tagName . $attributes . '>';
    }

    /**
     * Encode a DOM text node for safe insertion into the generated HTML.
     *
     * @param string $text Plain text from a DOM text node.
     * @return string HTML-encoded text.
     */
    private static function encodeText(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
