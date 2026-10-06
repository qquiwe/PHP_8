<?php
require_once __DIR__ . '/../security.php';
ensureSession();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['csrf_token' => csrfToken()]);
