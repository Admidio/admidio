<?php

namespace Admidio\Documents\Service;

// Loaded only in the isolated download test process: CLI PHP does not retain headers.
function header(string $header): void
{
    $GLOBALS['documentDownloadHeaders'][] = $header;
}
