<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';
ensureSession();
$owner = currentOwnerToken();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

try {
    if ($q !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM transactions WHERE owner_token = :owner AND category LIKE :q
             ORDER BY transaction_date DESC, id DESC'
        );
        $stmt->execute([':owner' => $owner, ':q' => '%' . $q . '%']);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE owner_token = :owner ORDER BY transaction_date DESC, id DESC');
        $stmt->execute([':owner' => $owner]);
    }

    $transactions = $stmt->fetchAll();

    $balanceStmt = $pdo->prepare('SELECT SUM(amount) AS balance FROM transactions WHERE owner_token = :owner');
    $balanceStmt->execute([':owner' => $owner]);
    $balance = (float) ($balanceStmt->fetch()['balance'] ?? 0);

    echo json_encode([
        'transactions' => $transactions,
        'balance'       => $balance,
    ], JSON_UNESCAPED_UNICODE);
} catch (\PDOException $e) {
    safeErrorJson('Не вдалося завантажити список операцій.', $e, 500);
}
