<?php
/**
 * A class whose method no longer matches the interface it implements - what a plugin written for an
 * older Admidio looks like after an Admidio class changed a signature. Including this file is a fatal
 * E_COMPILE_ERROR that no try/catch can intercept.
 */

namespace AdmidioPlugin\Crashing;

final class Incompatible implements \Countable
{
    public function count(string $mode): int
    {
        return 0;
    }
}
