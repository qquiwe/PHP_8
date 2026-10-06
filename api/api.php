<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/cache.php';
ensureSession();
$owner = currentOwnerToken();
header('Content-Type: application/json; charset=utf-8');

function apiResult(bool $success, $payload, int $statusCode): array
{
    return ['success' => $success, 'payload' => $payload, 'status' => $statusCode];
}
function resultSuccess($data, int $statusCode = 200): array { return apiResult(true, $data, $statusCode); }
function resultError(string $message, int $statusCode): array { return apiResult(false, $message, $statusCode); }

function getRequestBody(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function listTransactions(PDO $pdo, string $owner): array
{
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE owner_token = :owner ORDER BY transaction_date DESC, id DESC');
    $stmt->execute([':owner' => $owner]);
    return resultSuccess($stmt->fetchAll());
}

function getTransaction(PDO $pdo, string $owner, int $id): array
{
    // перевірка власника прямо в WHERE - чужий id поверне 404, а не чужі дані
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id AND owner_token = :owner');
    $stmt->execute([':id' => $id, ':owner' => $owner]);
    $row = $stmt->fetch();

    if (!$row) {
        return resultError("Операцію з id={$id} не знайдено", 404);
    }
    return resultSuccess($row);
}

function createTransaction(PDO $pdo, string $owner): array
{
    $body = getRequestBody();

    // CSRF-перевірка перед будь-якою валідацією полів
    if (!verifyCsrfToken($body['csrf_token'] ?? null)) {
        return resultError('Недійсний CSRF-токен', 403);
    }

    $amount = $body['amount'] ?? null;
    $category = trim((string) ($body['category'] ?? ''));
    $date = trim((string) ($body['transaction_date'] ?? ''));

    $missing = [];
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $missing[] = 'amount (число)';
    }
    if ($category === '') {
        $missing[] = 'category (рядок)';
    }
    if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
        $missing[] = 'transaction_date (формат РРРР-ММ-ДД)';
    }
    if (!empty($missing)) {
        return resultError('Некоректні або відсутні поля: ' . implode(', ', $missing), 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO transactions (amount, category, transaction_date, owner_token) VALUES (:amount, :category, :date, :owner)'
    );
    $stmt->execute([
        ':amount'   => (float) $amount,
        ':category' => $category,
        ':date'     => $date,
        ':owner'    => $owner,
    ]);

    $newId = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM transactions WHERE id = :id');
    $row->execute([':id' => $newId]);

    invalidateCache('balance:' . $owner);

    return resultSuccess($row->fetch(), 201);
}

function getBalanceAction(PDO $pdo, string $owner): array
{
    $result = cachedQuery(
        $pdo,
        'balance:' . $owner,
        'SELECT SUM(amount) AS balance FROM transactions WHERE owner_token = :owner',
        [':owner' => $owner],
        45
    );

    return resultSuccess([
        'balance' => (float) ($result['balance'] ?? 0),
        'cache'   => $result['_cache'] ?? 'miss',
    ]);
}

// Маршрутизація
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

try {
    if ($resource !== 'transactions') {
        $result = resultError('Невідомий ресурс. Підтримується лише resource=transactions', 404);
    } elseif ($method === 'GET') {
        if ($id === null) {
            $result = listTransactions($pdo, $owner);
        } elseif (!ctype_digit((string) $id)) {
            $result = resultError('Параметр id має бути цілим додатним числом', 400);
        } else {
            $result = getTransaction($pdo, $owner, (int) $id);
        }
    } elseif ($method === 'POST') {
        if ($action === 'balance') {
            $result = getBalanceAction($pdo, $owner);
        } elseif ($action === null) {
            $result = createTransaction($pdo, $owner);
        } else {
            $result = resultError("Невідома дія action={$action}", 400);
        }
    } else {
        $result = resultError("Метод {$method} не підтримується для resource=transactions", 405);
    }
} catch (\PDOException $e) {
    // жоден виняток PDOException не йде користувачу напряму (крок 6)
    error_log('[api.php] PDOException: ' . $e->getMessage());
    $result = resultError('Внутрішня помилка сервера. Спробуйте пізніше.', 500);
}

$body = $result['success']
    ? ['success' => true, 'data' => $result['payload']]
    : ['success' => false, 'error' => $result['payload']];

http_response_code($result['status']);
echo json_encode($body, JSON_UNESCAPED_UNICODE);
