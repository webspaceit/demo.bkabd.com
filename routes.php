<?php

/**
 * Route loader: requires every routes/*.php file (sorted).
 * Each module owns its own file — no merge conflicts.
 */

$files = glob(BASE_PATH . '/routes/*.php') ?: [];
sort($files);
foreach ($files as $file) {
    require $file;
}
