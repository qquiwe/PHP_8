<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';

ensureSession();

// видалення - навмисно ЛИШЕ через POST, не через GET-посилання (крок 5)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safeErrorHtml('Видалення можливе лише через форму (POST-запит), а не за прямим посиланням.', null, 405);
}

// CSRF-перевірка (крок 3)
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    safeErrorHtml('Недійсний CSRF-токен. Оновіть сторінку і спробуйте ще раз.', null, 403);
}

$id = (int) ($_POST['id'] ?? 0);
$owner = currentOwnerToken();

try {
    // перевірка власника прямо в WHERE - чужий запис видалити неможливо
    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = :id AND owner_token = :owner');
    $stmt->execute([':id' => $id, ':owner' => $owner]);
} catch (\PDOException $e) {
    safeErrorHtml('Не вдалося видалити запис. Спробуйте пізніше.', $e, 500);
}

header('Location: index.php');
exit;
