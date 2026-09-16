<?php
/**
 * A plain global function, not namespaced, so HooksTest can exercise Hooks::callableId() for a
 * callback given as a bare function name string - the one identity shape a class method cannot
 * stand in for.
 */
if (!function_exists('admHooksTestProbeListener')) {
    function admHooksTestProbeListener(): void
    {
        $GLOBALS['adm_hooks_test_calls'][] = 'admHooksTestProbeListener';
    }
}
