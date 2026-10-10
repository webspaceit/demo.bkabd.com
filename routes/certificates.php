<?php

use App\Controllers\CertificateController;
use App\Core\Router;

// ---------------------------------------------------------------
// Certificates (original routes/api.php + web sidebar pages)
// ---------------------------------------------------------------
Router::get('/student-certificate', [CertificateController::class, 'pageStudent'], 'auth|permission:student-certificate');
Router::get('/institute-certificate', [CertificateController::class, 'pageInstitute'], 'auth|permission:institute-certificate');
Router::get('/api/certificate/{instituteId}/{class}', [CertificateController::class, 'student'], 'auth|permission:student-certificate');
Router::get('/api/institute-certificate/{instituteId}', [CertificateController::class, 'institute'], 'auth|permission:institute-certificate');
