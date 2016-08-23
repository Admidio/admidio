<?php

// PHP-CS-Fixer v1.12

$level = Symfony\CS\FixerInterface::NONE_LEVEL;

$fixers = array(
    //'psr0',                                       // [PSR-0] will not used in future (PSR-0 is deprecated, use PSR-4 instead)
    'encoding',                                     // [PSR-1]
    'short_tag',                                    // [PSR-1]
    //'braces',                                     // [PSR-2] maybe used in future
    'class_definition',                             // [PSR-2]
    'elseif',                                       // [PSR-2]
    'eof_ending',                                   // [PSR-2]
    'function_call_space',                          // [PSR-2]
    'function_decleration',                         // [PSR-2]
    'indentation',                                  // [PSR-2]
    //'line_after_namespace',                       // [PSR-2] <Namespaces> not necessary now
    'linefeed',                                     // [PSR-2]
    'lowercase_constant',                           // [PSR-2]
    'lowercase_keywords',                           // [PSR-2]
    'method_argument_space',                        // [PSR-2]
    //'multiple_use',                               // [PSR-2] <Namespaces> not necessary now
    'parenthesis',                                  // [PSR-2]
    'no_trailing_whitespace_in_comment',            // [PSR-2]
    'php_closing_tag',                              // [PSR-2]
    //'single_line_after_imports',                  // [PSR-2] not necessary now
    'switch_case_semicolon_to_colon',               // [PSR-2]
    'switch_case_space',                            // [PSR-2]
    'trailing_spaces',                              // [PSR-2]
    'visibility',                                   // [PSR-2]
    'array_element_no_space_before_comma',          // [symfony]
    'array_element_white_space_after_comma',        // [symfony]
    'blankline_after_open_tag',                     // [symfony]
    //'concat_without_spaces',                      // [symfony] {concat_with_spaces} not specified now
    'declare_equal_normalize',                      // [symfony]
    'double_arrow_multiline_whitespaces',           // [symfony]
    'duplicate_semicolon',                          // [symfony]
    'extra_empty_lines',                            // [symfony]
    'function_typehint_space',                      // [symfony]
    'hash_to_slash_comment',                        // [symfony]
    'heredoc_to_nowdoc',                            // [symfony]
    //'include',                                    // [symfony] otherwise specified (maybe used in future)
    'join_function',                                // [symfony]
    'lowercase_cast',                               // [symfony]
    //'method_argument_default_value',              // [symfony] will be used in future
    'list_commas',                                  // [symfony]
    //'multiline_array_trailing_comma',             // [symfony] maybe used in future
    //'namespace_no_leading_whitespace',            // [symfony] <Namespaces> not necessary now
    'native_function_casing',                       // [symfony]
    'new_with_braces',                              // [symfony]
    'no_blank_lines_after_class_opening',           // [symfony]
    'no_empty_comment',                             // [symfony]
    'no_empty_lines_after_phpdocs',                 // [symfony] <PHPDoc>
    'no_empty_phpdoc',                              // [symfony] <PHPDoc>
    'no_empty_statement',                           // [symfony]
    'object_operator',                              // [symfony]
    //'operators_spaces',                           // [symfony] maybe used in future (in combination with unary_operators_spaces)
    //'phpdoc_annotation_without_dot',              // [symfony] <PHPDoc> maybe used in future
    'phpdoc_indent',                                // [symfony] <PHPDoc>
    //'phpdoc_inline_tag',                          // [symfony] <PHPDoc> not specified now
    'phpdoc_no_access',                             // [symfony] <PHPDoc>
    //'phpdoc_no_empty_return',                     // [symfony] <PHPDoc> maybe used in future
    'phpdoc_no_package',                            // [symfony] <PHPDoc>
    //'phpdoc_params',                              // [symfony] <PHPDoc> maybe used in future
    'phpdoc_scalar',                                // [symfony] <PHPDoc>
    //'phpdoc_separation',                          // [symfony] <PHPDoc> maybe used in future
    //'phpdoc_short_description',                   // [symfony] <PHPDoc> maybe used in future
    'phpdoc_single_line_var_spacing',               // [symfony] <PHPDoc>
    'phpdoc_to_comment',                            // [symfony] <PHPDoc>
    'phpdoc_trim',                                  // [symfony] <PHPDoc>
    //'phpdoc_type_to_var',                         // [symfony] <PHPDoc> {phpdoc_var_to_type} not specified now
    'phpdoc_types',                                 // [symfony] <PHPDoc>
    //'phpdoc_var_without_name',                    // [symfony] <PHPDoc> not specified now
    //'pre_increment',                              // [symfony] not necessary now
    //'print_to_echo',                              // [symfony] {echo_to_print}
    //'remove_leading_slash_use',                   // [symfony] <Namespaces> not necessary now
    //'remove_lines_between_uses',                  // [symfony] <Namespaces> not necessary now
    //'return',                                     // [symfony] maybe used in future
    'self_accessor',                                // [symfony]
    'short_bool_cast',                              // [symfony]
    'short_scalar_cast',                            // [symfony]
    'single_array_no_trailing_comma',               // [symfony]
    //'single_blank_line_before_namespace',         // [symfony] <Namespaces> {no_blank_lines_before_namespace} not necessary now
    'single_quote',                                 // [symfony]
    'spaces_after_semicolon',                       // [symfony]
    'spaces_before_semicolon',                      // [symfony]
    'spaces_cast',                                  // [symfony]
    'standardize_not_equal',                        // [symfony]
    'ternary_spaces',                               // [symfony]
    'trim_array_spaces',                            // [symfony]
    //'unalign_double_arrow',                       // [symfony] {align_double_arrow} otherwise specified
    //'unalign_equals',                             // [symfony] {align_equals} not specified now
    //'unary_operators_spaces',                     // [symfony] maybe used in future (in combination with operators_spaces)
    'unneeded_control_parentheses',                 // [symfony]
    //'unused_use',                                 // [symfony] <Namespaces> not necessary now
    'whitespacy_lines',                             // [symfony]
    'align_double_arrow',                           // [contrib] {unalign_double_arrow}
    //'align_equals',                               // [contrib] {unalign_equals} not specified now
    'class_keyword_remove',                         // [contrib]
    'combine_consecutive_unsets',                   // [contrib]
    //'concat_with_spaces',                         // [contrib] {concat_without_spaces} not specified now
    //'echo_to_print',                              // [contrib] {print_to_echo}
    //'empty_return',                               // [contrib] not specified now
    'ereg_to_preg',                                 // [contrib]
    //'header_comment',                             // [contrib] maybe used in future (config needed)
    //'logical_not_operators_with_spaces',          // [contrib] will not used in future
    //'logical_not_operators_with_successor_space', // [contrib] will not used in future
    //'long_array_syntax',                          // [contrib] {short_array_syntax} will be deprecated in future
    //'mb_str_functions',                           // [contrib] not specified now
    'multiline_spaces_before_semicolon',            // [contrib]
    'newline_after_open_tag',                       // [contrib]
    //'no_blank_lines_before_namespace',            // [contrib] <Namespaces> {single_blank_line_before_namespace} not necessary now
    //'no_useless_else',                            // [contrib] will be used in future
    'no_useless_return',                            // [contrib]
    //'ordered_use',                                // [contrib] <Namespaces> not necessary now
    'php4_constructor',                             // [contrib]
    //'php_unit_construct',                         // [contrib] <PHPUnit> not necessary now
    //'php_unit_dedicate_assert',                   // [contrib] <PHPUnit> not necessary now
    //'php_unit_strict',                            // [contrib] <PHPUnit> not necessary now
    'phpdoc_order',                                 // [contrib] <PHPDoc>
    //'phpdoc_var_to_type',                         // [contrib] <PHPDoc> {phpdoc_type_to_var} not specified now
    //'short_array_syntax',                         // [contrib] {long_array_syntax} will be used in future
    //'short_echo_tag',                             // [contrib] not specified now
    //'silenced_deprecation_error',                 // [contrib]
    //'strict',                                     // [contrib] maybe used in future
    //'strict_param',                               // [contrib] should not be used automatically because of problems in code
);

$finder = Symfony\CS\Finder::create()
    ->in(__DIR__)
    ->exclude('adm_program/libs')
    ->exclude('node_modules')
;

return Symfony\CS\Config::create()
    ->level($level)
    ->fixers($fixers)
    ->finder($finder)
;
