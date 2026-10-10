<?php
/**
 * Mark's Entry page — shared panel with the mark-entry endpoint.
 */
echo partial('markentry.entry', [
    'heading'       => "Mark's Entry",
    'endpoint'      => '/api/markentrystudent',
    'activeSession' => $activeSession ?? '',
]);
