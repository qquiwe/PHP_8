<?php
require_once __DIR__ . '/../security.php';
ensureSession();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => ['csrf_token' => csrfToken()]], JSON_UNESCAPED_UNICODE);
