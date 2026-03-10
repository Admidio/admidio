<?php

// settings.php laden (definiert $defaultSettings)
require __DIR__ . '/settings.php';

if (!isset($defaultSettings) || !is_array($defaultSettings)) {
    die("Array \$defaultSettings nicht gefunden.\n");
}

$result = [];

foreach ($defaultSettings as $key => $value) {
    // Falls Wert kein einfacher String ist (z.B. PHP-Ausdruck),
    // wandeln wir ihn vorsichtshalber in String um
    if (!is_scalar($value)) {
        $value = strval($value);
    }

    $result[] = [
        'name' => $key,
        'value' => (string) $value,
        'datatype' => '',
        'user-defined' => false
    ];
}

// JSON erzeugen (schön formatiert)
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Datei schreiben
file_put_contents(__DIR__ . '/settings.json', $json);

echo "settings.json wurde erzeugt.\n";
