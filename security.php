<?php
/**
 * Практична робота №8, варіант 13 "Трекер особистих витрат"
 * Спільні функції безпеки, які підключаються в усіх трьох "обличчях"
 * застосунку (classic/, ajax/, api/) - щоб захист був однаковий усюди,
 * а не продубльований і, можливо, забутий десь один раз.
 *
 * Важливе застереження (озвучити і в усному захисті, і в SECURITY_REPORT.md):
 * курс не передбачав повноцінної реєстрації/логіну користувачів. Замість
 * цього тут використано "анонімний" ownership-токен, прив'язаний до PHPсесії відвідувача: кожен новий відвідувач отримує свій випадковий токен,
 * усі створені ним транзакції позначаються цим токеном, і надалі бачить/
 * редагує/видаляє лише свої записи. Це навчальна демонстрація принципу
 * "перевіряй власника ресурсу перед видачею за id", а не заміна реальної
 * автентифікації (логін/пароль, довготривалі облікові записи).
 */

/** Ініціалізувати сесію й видати відвідувачу ownership- та csrf-токени, якщо їх ще нема */
function ensureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['owner_token'])) {
        $_SESSION['owner_token'] = bin2hex(random_bytes(16));
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Токен "власника" поточної сесії - усі SELECT/UPDATE/DELETE фільтруються за ним */
function currentOwnerToken(): string
{
    ensureSession();
    return $_SESSION['owner_token'];
}

/** CSRF-токен для вставки в приховане поле форми або в тіло AJAX/API-запиту */
function csrfToken(): string
{
    ensureSession();
    return $_SESSION['csrf_token'];
}

/** Порівняння CSRF-токена запиту з токеном сесії через hash_equals (захист від timing-атак) */
function verifyCsrfToken(?string $token): bool
{
    ensureSession();
    return $token !== null && $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
}

/** Екранування даних користувача перед виводом у HTML (захист від XSS) */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Безпечна відповідь на помилку для HTML-сторінок (classic/): деталі
 * винятку пишуться в лог, користувач бачить лише загальне повідомлення -
 * жодного "сирого" тексту PDOException (Крок 6 методички).
 */
function safeErrorHtml(string $userMessage, ?\Throwable $exception = null, int $statusCode = 400): never
{
    if ($exception !== null) {
        error_log($userMessage . ' | ' . $exception->getMessage());
    } else {
        error_log($userMessage);
    }
    http_response_code($statusCode);
    echo '<!DOCTYPE html><html lang="uk"><head><meta charset="UTF-8"><title>Помилка</title></head><body>';
    echo '<p>' . e($userMessage) . '</p>';
    echo '<p><a href="index.php">&larr; Назад</a></p>';
    echo '</body></html>';
    exit;
}

/**
 * Те саме, але для JSON-ендпоінтів (ajax/, api/).
 */
function safeErrorJson(string $userMessage, ?\Throwable $exception = null, int $statusCode = 400): never
{
    if ($exception !== null) {
        error_log($userMessage . ' | ' . $exception->getMessage());
    } else {
        error_log($userMessage);
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $userMessage], JSON_UNESCAPED_UNICODE);
    exit;
}
