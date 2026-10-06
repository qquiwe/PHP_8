<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';

ensureSession();
$owner = currentOwnerToken();

// SQLi-аудит (Крок 1)

$stmt = $pdo->prepare('SELECT * FROM transactions WHERE owner_token = :owner ORDER BY transaction_date DESC, id DESC');
$stmt->execute([':owner' => $owner]);
$transactions = $stmt->fetchAll();

$balanceStmt = $pdo->prepare('SELECT SUM(amount) AS balance FROM transactions WHERE owner_token = :owner');
$balanceStmt->execute([':owner' => $owner]);
$totalBalance = (float) ($balanceStmt->fetch()['balance'] ?? 0);

$categoriesStmt = $pdo->prepare('SELECT DISTINCT category FROM transactions WHERE owner_token = :owner ORDER BY category');
$categoriesStmt->execute([':owner' => $owner]);
$categories = array_column($categoriesStmt->fetchAll(), 'category');

function totalByCategoryOwned(PDO $pdo, string $owner, string $category): float
{
    $stmt = $pdo->prepare('SELECT SUM(amount) AS total FROM transactions WHERE owner_token = :owner AND category = :category');
    $stmt->execute([':owner' => $owner, ':category' => $category]);
    return (float) ($stmt->fetch()['total'] ?? 0);
}

$categorySummary = [];
foreach ($categories as $cat) {
    $categorySummary[$cat] = totalByCategoryOwned($pdo, $owner, $cat);
}

// Фільтр за категорією
$filterCategory = $_GET['category'] ?? '';
if ($filterCategory !== '') {
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE owner_token = :owner AND category = :category ORDER BY transaction_date DESC, id DESC');
    $stmt->execute([':owner' => $owner, ':category' => $filterCategory]);
    $transactions = $stmt->fetchAll();
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Трекер особистих витрат</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 15px; color: #222; }
        h1 { margin-bottom: 5px; }
        .balance { font-size: 1.3em; margin-bottom: 20px; }
        .balance.positive { color: #1a7f37; }
        .balance.negative { color: #c0392b; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
        tr:nth-child(even) { background: #fafafa; }
        .amount-pos { color: #1a7f37; font-weight: bold; }
        .amount-neg { color: #c0392b; font-weight: bold; }
        .actions { display: flex; gap: 10px; align-items: center; }
        .actions a, .actions button { text-decoration: none; }
        .actions form { display: inline; margin: 0; }
        .actions button.delete { color: #c0392b; background: none; border: none; cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
        .actions a.edit { color: #2563eb; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .btn-add { background: #2563eb; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        form.filter { margin: 15px 0; }
        select, button { padding: 6px 10px; }
        .summary { margin-top: 20px; }
        .summary h2 { font-size: 1.1em; margin-bottom: 8px; }
        .summary table { margin-top: 0; }
        .summary td.amount-pos, .summary td.amount-neg { text-align: right; }
        .note { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>Трекер особистих витрат</h1>
    <a class="btn-add" href="add.php">+ Додати операцію</a>
</div>

<p class="note">Показано лише операції, додані з вашого браузера (сесії). Це навчальна ізоляція
без повноцінного логіну - див. README.</p>

<p class="balance <?= $totalBalance >= 0 ? 'positive' : 'negative' ?>">
    Загальний баланс: <?= number_format($totalBalance, 2, ',', ' ') ?> грн
</p>

<div class="summary">
    <h2>Суми за категоріями</h2>
    <?php if (empty($categorySummary)): ?>
        <p>Немає даних для підрахунку.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Категорія</th><th>Сума, грн</th></tr></thead>
            <tbody>
            <?php foreach ($categorySummary as $cat => $sum): ?>
                <tr>
                    <td><?= e($cat) ?></td>
                    <td class="<?= $sum >= 0 ? 'amount-pos' : 'amount-neg' ?>"><?= number_format($sum, 2, ',', ' ') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form class="filter" method="get" action="index.php">
    <label for="category">Фільтр за категорією:</label>
    <select name="category" id="category" onchange="this.form.submit()">
        <option value="">— усі категорії —</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Застосувати</button></noscript>
</form>

<?php if (empty($transactions)): ?>
    <p>Записів поки немає.</p>
<?php else: ?>
    <table>
        <thead>
        <tr><th>#</th><th>Дата</th><th>Категорія</th><th>Сума, грн</th><th>Дії</th></tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= (int) $t['id'] ?></td>
                <td><?= e($t['transaction_date']) ?></td>
                <td><?= e($t['category']) ?></td>
                <td class="<?= $t['amount'] >= 0 ? 'amount-pos' : 'amount-neg' ?>">
                    <?= number_format((float) $t['amount'], 2, ',', ' ') ?>
                </td>
                <td class="actions">
                    <a class="edit" href="edit.php?id=<?= (int) $t['id'] ?>">Редагувати</a> 
                    <form method="post" action="delete.php" onsubmit="return confirm('Видалити цю операцію?');"> 
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="delete">Видалити</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
