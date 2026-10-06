<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';
ensureSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safeErrorJson('Дозволено лише POST-запити', null, 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

// CSRF-перевірка - токен приходить у тілі запиту (форма чи JSON),
// бо для fetch-запитів з іншого сайту немає штатного способу дізнатися
// csrf_token поточної сесії користувача (крок 3)
if (!verifyCsrfToken($input['csrf_token'] ?? null)) {
    safeErrorJson('Недійсний CSRF-токен', null, 403);
}

$amount = trim((string) ($input['amount'] ?? ''));
$category = trim((string) ($input['category'] ?? ''));
$date = trim((string) ($input['transaction_date'] ?? ''));

$errors = [];
if ($amount === '' || !is_numeric($amount)) {
    $errors[] = 'Сума має бути числом.';
}
if ($category === '') {
    $errors[] = 'Вкажіть категорію.';
}
if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
    $errors[] = 'Вкажіть коректну дату у форматі РРРР-ММ-ДД.';
}

if (!empty($errors)) {
    safeErrorJson(implode(' ', $errors), null, 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO transactions (amount, category, transaction_date, owner_token) VALUES (:amount, :category, :date, :owner)'
    );
    $stmt->execute([
        ':amount'   => (float) $amount,
        ':category' => $category,
        ':date'     => $date,
        ':owner'    => currentOwnerToken(),
    ]);

    $newId = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM transactions WHERE id = :id');
    $row->execute([':id' => $newId]);

    $balanceStmt = $pdo->prepare('SELECT SUM(amount) AS balance FROM transactions WHERE owner_token = :owner');
    $balanceStmt->execute([':owner' => currentOwnerToken()]);
    $balance = (float) ($balanceStmt->fetch()['balance'] ?? 0);

    http_response_code(201);
    echo json_encode([
        'transaction' => $row->fetch(),
        'balance'     => $balance,
    ], JSON_UNESCAPED_UNICODE);
} catch (\PDOException $e) {
    safeErrorJson('Не вдалося додати операцію.', $e, 500);
}
