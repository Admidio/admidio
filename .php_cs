<?php

$level = Symfony\CS\FixerInterface::NONE_LEVEL;

$fixers = array('encoding', 'short_tag', 'elseif', 'function_call_space', 'function_decleration', 'indentation',
    'line_after_namespace', 'lowercase_constant', 'lowercase_keywords', 'method_argument_space', 'multiple_use',
    'parenthesis', 'trailing_spaces', 'visibility', 'double_arrow_multiline_whitespaces', 'duplicate_semicolon',
    'extra_empty_lines', 'new_with_braces', 'object_operator', //'phpdoc_params',
    'spaces_before_semicolon', 'standardize_not_equal', 'ternary_spaces')
;

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__)
    ->exclude('adm_program/libs')
    ->exclude('node_modules')
;

return Symfony\CS\Config\Config::create()
    ->level($level)
    ->fixers($fixers)
    ->finder($finder)
;
