<?php

// PHP-CS-Fixer v1.11

$level = Symfony\CS\FixerInterface::NONE_LEVEL;

$fixers = array(
    //'psr0',                                       // [PSR-0] will not used in future (PSR-0 is deprecated, use PSR-4 instead)
    'encoding',                                     // [PSR-1]
    'short_tag',                                    // [PSR-1]
    //'braces',                                     // [PSR-2] maybe used in future
    'elseif',                                       // [PSR-2]
    'eof_ending',                                   // [PSR-2]
    'function_call_space',                          // [PSR-2]
    'function_decleration',                         // [PSR-2]
    'indentation',                                  // [PSR-2]
    'line_after_namespace',                         // [PSR-2]
    'linefeed',                                     // [PSR-2]
    'lowercase_constant',                           // [PSR-2]
    'lowercase_keywords',                           // [PSR-2]
    'method_argument_space',                        // [PSR-2]
    'multiple_use',                                 // [PSR-2]
    'parenthesis',                                  // [PSR-2]
    'php_closing_tag',                              // [PSR-2]
    //'single_line_after_imports',                  // [PSR-2] not necessary now
    'trailing_spaces',                              // [PSR-2]
    'visibility',                                   // [PSR-2]
    'array_element_no_space_before_comma',          // [symfony]
    'array_element_white_space_after_comma',        // [symfony]
    'blankline_after_open_tag',                     // [symfony]
    //'concat_without_spaces',                      // [symfony] {concat_with_spaces} not specified now
    'double_arrow_multiline_whitespaces',           // [symfony]
    'duplicate_semicolon',                          // [symfony]
    //'empty_return',                               // [symfony] not specified now
    'extra_empty_lines',                            // [symfony]
    //'function_typehint_space',                    // [symfony] not necessary now
    //'include',                                    // [symfony] otherwise specified (maybe used in future)
    'join_function',                                // [symfony]
    'list_commas',                                  // [symfony]
    //'multiline_array_trailing_comma',             // [symfony] maybe used in future
    //'namespace_no_leading_whitespace',            // [symfony] not necessary now
    'new_with_braces',                              // [symfony]
    'no_blank_lines_after_class_opening',           // [symfony]
    'no_empty_lines_after_phpdocs',                 // [symfony]
    'object_operator',                              // [symfony]
    //'operators_spaces',                           // [symfony] maybe used in future (in combination with unary_operators_spaces)
    'phpdoc_indent',                                // [symfony]
    //'phpdoc_inline_tag',                          // [symfony] not specified now
    'phpdoc_no_access',                             // [symfony]
    //'phpdoc_no_empty_return',                     // [symfony] maybe used in future
    'phpdoc_no_package',                            // [symfony]
    //'phpdoc_params',                              // [symfony] maybe used in future
    'phpdoc_scalar',                                // [symfony]
    //'phpdoc_separation',                          // [symfony] maybe used in future
    //'phpdoc_short_description',                   // [symfony] maybe used in future
    'phpdoc_to_comment',                            // [symfony]
    'phpdoc_trim',                                  // [symfony]
    //'phpdoc_type_to_var',                         // [symfony] {phpdoc_var_to_type} not specified now
    'phpdoc_types',                                 // [symfony]
    //'phpdoc_var_without_name',                    // [symfony] not specified now
    //'pre_increment',                              // [symfony] not necessary now
    //'print_to_echo',                              // [symfony] {echo_to_print}
    //'remove_leading_slash_use',                   // [symfony] not necessary now
    //'remove_lines_between_uses',                  // [symfony] not necessary now
    //'return',                                     // [symfony] maybe used in future
    'self_accessor',                                // [symfony]
    'short_bool_cast',                              // [symfony]
    'single_array_no_trailing_comma',               // [symfony]
    //'single_blank_line_before_namespace',         // [symfony] {no_blank_lines_before_namespace} not necessary now
    //'single_quote',                               // [symfony] maybe used in future
    'spaces_before_semicolon',                      // [symfony]
    'spaces_cast',                                  // [symfony]
    'standardize_not_equal',                        // [symfony]
    'ternary_spaces',                               // [symfony]
    'trim_array_spaces',                            // [symfony]
    //'unalign_double_arrow',                       // [symfony] {align_double_arrow} otherwise specified
    //'unalign_equals',                             // [symfony] {align_equals} not specified now
    //'unary_operators_spaces',                     // [symfony] maybe used in future (in combination with operators_spaces)
    'unneeded_control_parentheses',                 // [symfony]
    //'unused_use',                                 // [symfony] not necessary now
    'whitespacy_lines',                             // [symfony]
    'align_double_arrow',                           // [contrib] {unalign_double_arrow}
    //'align_equals',                               // [contrib] {unalign_equals} not specified now
    //'concat_with_spaces',                         // [contrib] {concat_without_spaces} not specified now
    //'echo_to_print',                              // [contrib] {print_to_echo}
    'ereg_to_preg',                                 // [contrib]
    //'header_comment',                             // [contrib] maybe used in future (config needed)
    //'logical_not_operators_with_spaces',          // [contrib] will not used in future
    //'logical_not_operators_with_successor_space', // [contrib] will not used in future
    //'long_array_syntax',                          // [contrib] {short_array_syntax} will be deprecated in future
    'multiline_spaces_before_semicolon',            // [contrib]
    'newline_after_open_tag',                       // [contrib]
    //'no_blank_lines_before_namespace',            // [contrib] {single_blank_line_before_namespace} not necessary now
    //'ordered_use',                                // [contrib] not necessary now
    'php4_constructor',                             // [contrib]
    //'php_unit_construct',                         // [contrib] not necessary now
    //'php_unit_strict',                            // [contrib] not necessary now
    'phpdoc_order',                                 // [contrib]
    //'phpdoc_var_to_type',                         // [contrib] {phpdoc_type_to_var} not specified now
    //'short_array_syntax',                         // [contrib] {long_array_syntax} will be used in future
    //'short_echo_tag',                             // [contrib] not specified now
    //'strict',                                     // [contrib] maybe used in future
    //'strict_param',                               // [contrib] should not be used automatically because of problems in code
);

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
