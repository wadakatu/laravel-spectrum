<?php

$cacheDir = 'storage/app/spectrum/cache/';
foreach (glob($cacheDir.'*.cache') as $file) {
    $content = file_get_contents($file);
    $data = @unserialize($content);
    if (! is_array($data)) {
        continue;
    }

    // Check if this is parameter data (array of arrays with 'name' key)
    if (! isset($data['data']) || ! is_array($data['data'])) {
        continue;
    }

    foreach ($data['data'] as $item) {
        if (is_array($item) && isset($item['name']) && $item['name'] === 'avatar') {
            echo "File: $file\n";
            echo json_encode($item, JSON_PRETTY_PRINT)."\n";
            break 2;
        }
    }
}
