<?php
/**
 * Grade Assign page — shared panel with the highest-mark endpoint.
 */
echo partial('markentry.entry', [
    'heading'       => 'Grade Assign',
    'endpoint'      => '/api/markentrystudenthighestmark',
    'activeSession' => $activeSession ?? '',
]);
