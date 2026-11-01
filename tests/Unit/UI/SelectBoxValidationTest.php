<?php

namespace Admidio\Tests\Unit\UI;

use Admidio\UI\Presenter\FormPresenter;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

class SelectBoxValidationTest extends TestCase
{
    /**
     * @dataProvider selectBoxes
     */
    public function testFirstOptionAndSelection(array $values, array $options, array $expectedIds, ?string $selectedValue, array $formOptions = array()): void
    {
        $hadLanguage = array_key_exists('gL10n', $GLOBALS);
        $previousLanguage = $GLOBALS['gL10n'] ?? null;
        $GLOBALS['gL10n'] = new class {
            public function get(string $id): string
            {
                return $id;
            }

            public function getLanguageLibs(): string
            {
                return 'en';
            }
        };

        try {
            $form = new FormPresenter('select_test', 'form.tpl', 'index.php', null, $formOptions);
            $form->addSelectBox('choice', 'Choice', $values, $options);
            $element = $form->getElement('choice');
        } finally {
            if ($hadLanguage) {
                $GLOBALS['gL10n'] = $previousLanguage;
            } else {
                unset($GLOBALS['gL10n']);
            }
        }

        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->registerPlugin('function', 'array_key_exists', 'Admidio\Infrastructure\Plugins\Smarty::arrayKeyExists');
        $smarty->registerPlugin('function', 'is_translation_string_id', static fn(array $params): bool => false);
        $smarty->assign(array('data' => $element, 'formType' => 'default'));

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($smarty->fetch('sys-template-parts/form.select.tpl'));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $select = $xpath->query('//select')->item(0);
        $renderedOptions = $xpath->query('.//option', $select);
        $ids = array();
        foreach ($renderedOptions as $option) {
            $ids[] = $option->getAttribute('value');
        }
        $this->assertSame($expectedIds, $ids);
        if ($expectedIds[0] === '') {
            // HTML's placeholder label option must be the first option and a direct
            // child of select, not a member of an optgroup.
            $first = $renderedOptions->item(0);
            $this->assertSame('', $first->getAttribute('value'));
            $this->assertSame($select, $first->parentNode);
            $this->assertTrue($select->hasAttribute('required'));
        }

        $selected = $xpath->query('.//option[@selected]', $select);
        if ($selectedValue === null) {
            $this->assertCount(0, $selected);
        } else {
            $this->assertCount(1, $selected);
            $this->assertSame($selectedValue, $selected->item(0)->getAttribute('value'));
        }
        $this->assertCount(1, $xpath->query('//label[@for="choice"]'));
    }

    public static function selectBoxes(): array
    {
        $values = array('a' => 'Alpha', 'b' => 'Bravo');
        $required = array('property' => FormPresenter::FIELD_REQUIRED);

        return array(
            'required placeholder' => array($values, $required, array('', 'a', 'b'), ''),
            'required preselected' => array($values, $required + array('defaultValue' => 'b'), array('', 'a', 'b'), 'b'),
            'custom text' => array($values, $required + array('firstEntry' => 'Choose'), array('', 'a', 'b'), ''),
            'custom empty value' => array($values, $required + array('firstEntry' => array('', 'Choose')), array('', 'a', 'b'), ''),
            'explicit entry with automatic entry disabled' => array($values, $required + array('firstEntry' => 'Choose', 'showContextDependentFirstEntry' => false), array('', 'a', 'b'), ''),
            'explicit empty array entry with automatic entry disabled' => array($values, $required + array('firstEntry' => array('', 'Choose'), 'showContextDependentFirstEntry' => false), array('', 'a', 'b'), ''),
            'supplied empty entry without automatic entry' => array(array('' => 'Choose', 'a' => 'Alpha', 'b' => 'Bravo'), $required + array('showContextDependentFirstEntry' => false), array('', 'a', 'b'), ''),
            'no automatic entry' => array($values, $required + array('showContextDependentFirstEntry' => false, 'defaultValue' => 'a'), array('placeholder', 'a', 'b'), 'a'),
            'no automatic entry or default' => array($values, $required + array('showContextDependentFirstEntry' => false), array('placeholder', 'a', 'b'), null),
            'custom zero value' => array($values, $required + array('firstEntry' => array('0', 'All'), 'defaultValue' => '0'), array('placeholder', '0', 'a', 'b'), '0'),
            'zero is a valid selection' => array(array(0 => 'Zero', 1 => 'One'), $required + array('defaultValue' => 0), array('', '0', '1'), '0'),
            'single option automatically selected' => array(array('a' => 'Alpha'), $required, array('placeholder', 'a'), 'a'),
            'empty list' => array(array(), $required, array(''), ''),
            'empty list without automatic entry' => array(array(), $required + array('showContextDependentFirstEntry' => false), array(''), ''),
            'grouped options' => array(array(array('a', 'Alpha', 'Group'), array('b', 'Bravo', 'Group')), $required, array('', 'a', 'b'), ''),
            'required styling disabled' => array($values, $required, array('', 'a', 'b'), '', array('showRequiredFields' => false)),
            'optional' => array($values, array(), array('placeholder', '', 'a', 'b'), ''),
            'Select2 search' => array($values, $required + array('search' => true), array('placeholder', '', 'a', 'b'), ''),
            'Select2 multiple' => array($values, $required + array('multiselect' => true), array('placeholder', 'a', 'b'), null)
        );
    }
}
