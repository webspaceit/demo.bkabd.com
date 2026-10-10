<?php

use App\Controllers\ResultPortalController;
use App\Core\Router;

// ---------------------------------------------------------------
// Public result portal — no auth middleware (result day: anyone may look
// up a published result). CSRF is enforced automatically on the POST.
// ---------------------------------------------------------------

/** Search form (original: ResultController@showForm). */
Router::get('/scholarship-result', [ResultPortalController::class, 'showForm']);

/** Form submit — renders the result sheet or redirects back (original search()). */
Router::post('/result/search', [ResultPortalController::class, 'search']);

/** Deep link with ?data={json} (original show()). */
Router::get('/result/show', [ResultPortalController::class, 'show']);
