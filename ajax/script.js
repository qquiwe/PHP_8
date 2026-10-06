const resultsEl = document.getElementById('results');
const balanceEl = document.getElementById('balance');
const messageEl = document.getElementById('message');
const searchEl = document.getElementById('search');
const addFormEl = document.getElementById('add-form');

let csrfToken = null;

// показати повідомлення про помилку в контейнері
function showError(text) {
    messageEl.innerHTML = `<div class="error">${escapeHtml(text)}</div>`;
}

function showSuccess(text) {
    messageEl.innerHTML = `<div class="success">${escapeHtml(text)}</div>`;
    setTimeout(() => { messageEl.innerHTML = ''; }, 2500);
}

// екранування даних перед вставкою в innerHTML - основний захист від XSS на клієнті
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

// отримати CSRF-токен поточної сесії (один раз при завантаженні сторінки) 
async function fetchCsrfToken() {
    const response = await fetch('csrf_token.php');
    const data = await response.json();
    csrfToken = data.csrf_token;
}

function renderTransactions(transactions) {
    if (!transactions || transactions.length === 0) {
        resultsEl.innerHTML = '<p>Записів не знайдено.</p>';
        return;
    }

    const rows = transactions.map(t => {
        const amountNum = parseFloat(t.amount);
        const amountClass = amountNum >= 0 ? 'amount-pos' : 'amount-neg';
        const amountText = amountNum.toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        // усі значення користувача проходять через escapeHtml() перед вставкою в innerHTML
        return `
            <tr>
                <td>${escapeHtml(t.id)}</td>
                <td>${escapeHtml(t.transaction_date)}</td>
                <td>${escapeHtml(t.category)}</td>
                <td class="${amountClass}">${amountText}</td>
            </tr>
        `;
    }).join('');

    resultsEl.innerHTML = `
        <table>
            <thead><tr><th>#</th><th>Дата</th><th>Категорія</th><th>Сума, грн</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

function renderBalance(balance) {
    const num = parseFloat(balance) || 0;
    balanceEl.textContent = 'Баланс: ' + num.toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' грн';
    balanceEl.classList.toggle('positive', num >= 0);
    balanceEl.classList.toggle('negative', num < 0);
}

async function loadList(query = '') {
    resultsEl.innerHTML = 'Завантаження...';
    try {
        const url = query ? 'api_list.php?q=' + encodeURIComponent(query) : 'api_list.php';
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('Сервер повернув помилку: ' + response.status);
        }

        const data = await response.json();
        renderTransactions(data.transactions);
        renderBalance(data.balance);
    } catch (err) {
        resultsEl.innerHTML = '';
        showError('Не вдалося завантажити список операцій: ' + err.message);
    }
}

let searchTimer = null;
searchEl.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        loadList(searchEl.value.trim());
    }, 250);
});

addFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!csrfToken) {
        showError('Токен безпеки ще не завантажено, спробуйте за секунду.');
        return;
    }

    const payload = {
        amount: document.getElementById('amount').value,
        category: document.getElementById('category').value,
        transaction_date: document.getElementById('transaction_date').value,
        csrf_token: csrfToken,
    };

    try {
        const response = await fetch('api_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Не вдалося додати операцію.');
        }

        showSuccess('Операцію додано.');
        addFormEl.reset();
        renderBalance(data.balance);
        loadList(searchEl.value.trim());
    } catch (err) {
        showError('Помилка додавання: ' + err.message);
    }
});

// спочатку отримуємо CSRF-токен, тоді вантажимо список
fetchCsrfToken().finally(() => loadList());
