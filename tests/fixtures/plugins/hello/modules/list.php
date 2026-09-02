<?php
/** A second page, so that more than one stub has to be generated. */

$GLOBALS['helloPageRuns'][] = 'list.php';
if (isset($pluginPageScopeProbe)) {
    $GLOBALS['helloPageScopeProbe'] = $pluginPageScopeProbe;
}
