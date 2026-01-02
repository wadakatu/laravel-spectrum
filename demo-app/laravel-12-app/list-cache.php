<?php

$files = glob('storage/app/spectrum/cache/*.cache');
foreach ($files as $file) {
    $data = unserialize(file_get_contents($file));
    echo ($data['metadata']['key'] ?? 'unknown')."\n";
}
