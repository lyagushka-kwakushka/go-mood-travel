<?php
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: employee_login.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Сотрудник';
$message = '';
$error = '';

// ============================================
// Обработка действий
// ============================================

// 1. Взять заявку в работу
if (isset($_GET['take_order']) && is_numeric($_GET['take_order'])) {
    $order_id = (int)$_GET['take_order'];
    $stmt = $mysqli->prepare("
        UPDATE orders 
        SET employee_id = ? 
        WHERE order_id = ? AND employee_id IS NULL
    ");
    $stmt->bind_param('ii', $employee_id, $order_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Заявка №$order_id взята в работу.";
    } else {
        $error = "Не удалось взять заявку (возможно, она уже назначена).";
    }
    header('Location: employee_cabinet.php?tab=my_orders');
    exit;
}

// 2. Провести оплату
if (isset($_POST['add_payment']) && isset($_POST['order_id']) && isset($_POST['amount'])) {
    $order_id = (int)$_POST['order_id'];
    $amount = (float)$_POST['amount'];
    $method = $_POST['payment_method'] ?? 'card';
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    $mysqli->begin_transaction();
    try {
        $check = $mysqli->prepare("SELECT total FROM orders WHERE order_id = ? AND employee_id = ?");
        $check->bind_param('ii', $order_id, $employee_id);
        $check->execute();
        $order = $check->get_result()->fetch_assoc();
        if (!$order) {
            throw new Exception('Заказ не найден или не принадлежит вам.');
        }

        $stmt = $mysqli->prepare("
            INSERT INTO payments (order_id, amount, payment_method, payment_status, transaction_id) 
            VALUES (?, ?, ?, 'completed', ?)
        ");
        $stmt->bind_param('idss', $order_id, $amount, $method, $transaction_id);
        $stmt->execute();

        $total_paid_res = $mysqli->query("SELECT SUM(amount) as total FROM payments WHERE order_id = $order_id AND payment_status = 'completed'");
        $total_paid = $total_paid_res->fetch_assoc()['total'] ?? 0;

        $new_status = ($total_paid >= $order['total']) ? 'paid' : 'confirmed';
        $upd = $mysqli->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        $upd->bind_param('si', $new_status, $order_id);
        $upd->execute();

        $mysqli->commit();
        $message = "Платёж на сумму " . number_format($amount, 0, ',', ' ') . " ₽ проведён.";
    } catch (Exception $e) {
        $mysqli->rollback();
        $error = "Ошибка: " . $e->getMessage();
    }
    header('Location: employee_cabinet.php?tab=my_orders');
    exit;
}

// 3. Изменить статус заказа
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
    if (in_array($new_status, $allowed)) {
        $stmt = $mysqli->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND employee_id = ?");
        $stmt->bind_param('sii', $new_status, $order_id, $employee_id);
        $stmt->execute();
        $message = "Статус заказа №$order_id изменён.";
    }
    header('Location: employee_cabinet.php?tab=my_orders');
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';

// ============================================
// Статистика
// ============================================
$stats = $mysqli->query("
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE employee_id = $employee_id) as my_orders,
        (SELECT COUNT(*) FROM orders WHERE employee_id IS NULL AND order_status IN ('pending_contact', 'pending')) as pending_requests,
        (SELECT COUNT(*) FROM orders WHERE employee_id = $employee_id AND order_status = 'confirmed') as confirmed_orders
")->fetch_assoc();

// ============================================
// Новые заявки (без менеджера)
// ============================================
$requests = $mysqli->query("
    SELECT o.*, 
           COALESCE(c.full_name, SUBSTRING_INDEX(o.special_requirements, ',', 1)) as client_name,
           COALESCE(c.phone, 
               SUBSTRING_INDEX(SUBSTRING_INDEX(o.special_requirements, 'тел:', -1), ',', 1)) as client_phone,
           t.tour_name, td.start_date
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.client_id
    JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
    JOIN tours t ON td.tour_id = t.tour_id
    WHERE o.employee_id IS NULL AND o.order_status IN ('pending_contact', 'pending')
    ORDER BY o.order_date DESC
");

// ============================================
// Мои заказы
// ============================================
$my_orders = $mysqli->query("
    SELECT o.*, c.full_name as client_name, c.phone, c.email,
           t.tour_name, td.start_date, td.end_date,
           (SELECT SUM(amount) FROM payments WHERE order_id = o.order_id AND payment_status = 'completed') as paid_amount
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.client_id
    JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
    JOIN tours t ON td.tour_id = t.tour_id
    WHERE o.employee_id = $employee_id
    ORDER BY o.order_date DESC
");

$status_labels = [
    'pending_contact' => 'Ожидает звонка',
    'pending'         => 'Ожидает',
    'confirmed'       => 'Подтверждён',
    'paid'            => 'Оплачен',
    'cancelled'       => 'Отменён',
    'completed'       => 'Завершён'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Кабинет сотрудника | GoMoodTravel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .employee-header { background: linear-gradient(135deg, #1e3a5f, #2563eb); color: white; padding: 1.5rem 2rem; margin-bottom: 2rem; }
        .tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 2rem; }
        .tab { padding: 0.75rem 1.5rem; text-decoration: none; color: #64748b; border-radius: 8px 8px 0 0; transition: all 0.2s; }
        .tab:hover { background: #f1f5f9; color: #1e293b; }
        .tab.active { background: white; color: #2563eb; border: 1px solid #e2e8f0; border-bottom-color: white; margin-bottom: -1px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #1e293b; }
        .stat-label { color: #64748b; margin-top: 0.5rem; }
        .order-card, .request-card { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; background: #e2e8f0; color: #475569; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-info { background: #dbeafe; color: #1e40af; }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-danger { background: #fee2e2; color: #991b1b; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 6px; text-decoration: none; display: inline-block; margin-right: 0.5rem; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-outline { background: white; color: #2563eb; border: 1px solid #2563eb; }
        .order-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin: 1rem 0; }
        .detail-label { color: #64748b; font-size: 0.85rem; }
        .detail-value { font-weight: 500; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message-success { background: #d1fae5; color: #065f46; }
        .message-error { background: #fee2e2; color: #991b1b; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; }
        .logout-btn { background: #dc2626; color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; }
        .logout-btn:hover { background: #b91c1c; }
        .btn-take { background: #2563eb; color: white; padding: 0.5rem 1.5rem; border-radius: 6px; text-decoration: none; display: inline-block; }
        .btn-take:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<header class="employee-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="color: #FFD700;">Кабинет сотрудника</h1>
            <p><?= htmlspecialchars($employee_name) ?></p>
        </div>
        <nav>
            <a href="index.php" style="color: white; margin-right: 1rem;">На сайт</a>
            <a href="employee_logout.php" class="logout-btn">Выход</a>
        </nav>
    </div>
</header>

<main style="max-width: 1400px; margin: 0 auto; padding: 0 1.5rem;">
    <?php if ($message): ?>
        <div class="message message-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message message-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=dashboard" class="tab <?= $tab == 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-chart-column" style="color: rgb(36, 91, 187);"></i> Дашборд</a>
        <a href="?tab=requests" class="tab <?= $tab == 'requests' ? 'active' : '' ?>"><i class="fa-solid fa-circle-plus" style="color: rgb(36, 91, 187);"></i> Новые заявки (<?= $stats['pending_requests'] ?>)</a>
        <a href="?tab=my_orders" class="tab <?= $tab == 'my_orders' ? 'active' : '' ?>"><i class="fa-solid fa-user-plus" style="color: rgb(36, 91, 187);"></i> Мои заказы</a>
    </div>

    <?php if ($tab == 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['my_orders'] ?></div>
                <div class="stat-label">Моих заказов</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['pending_requests'] ?></div>
                <div class="stat-label">Новых заявок</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['confirmed_orders'] ?></div>
                <div class="stat-label">Подтверждённых</div>
            </div>
        </div>

        <h3><i class="fa-solid fa-bell" style="color: rgb(36, 91, 187);"></i> Последние заявки</h3>
        <?php
        $recent = $mysqli->query("
            SELECT o.*, t.tour_name, td.start_date
            FROM orders o
            JOIN tour_dates td ON o.tour_date_id = td.tour_date_id
            JOIN tours t ON td.tour_id = t.tour_id
            WHERE o.employee_id IS NULL AND o.order_status IN ('pending_contact', 'pending')
            ORDER BY o.order_date DESC
            LIMIT 5
        ");
        if ($recent->num_rows > 0):
            while($r = $recent->fetch_assoc()):
        ?>
            <div class="request-card" style="margin-bottom: 1.2rem; padding: 1.2rem 1.5rem;">
                <div class="order-header" style="margin-bottom: 0.75rem;">
                    <strong style="font-size: 1.05rem;">Заявка №<?= $r['order_id'] ?> — <?= htmlspecialchars($r['tour_name']) ?></strong>
                    <span class="status-badge status-warning"><?= $status_labels[$r['order_status']] ?? $r['order_status'] ?></span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem 2rem; margin-bottom: 1rem;">
                    <div><span style="color: #64748b;">Дата тура:</span> <?= date('d.m.Y', strtotime($r['start_date'])) ?></div>
                    <div><span style="color: #64748b;">Участников:</span> <?= $r['participants_count'] ?></div>
                    <div><span style="color: #64748b;">Сумма:</span> <?= number_format($r['total'], 0, ',', ' ') ?> ₽</div>
                </div>
                <p style="margin-bottom: 1rem; color: #334155;"><?= htmlspecialchars($r['special_requirements'] ?? 'Нет дополнительной информации') ?></p>
                <a href="?take_order=<?= $r['order_id'] ?>" class="btn-take">Взять в работу</a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Нет новых заявок.</p>
        <?php endif; ?>

    <?php elseif ($tab == 'requests'): ?>
        <h3><i class="fa-solid fa-envelope" style="color: rgb(36, 91, 187);"></i> Заявки, ожидающие обработки</h3>
        <?php if ($requests->num_rows > 0): ?>
            <?php while($r = $requests->fetch_assoc()): ?>
            <div class="request-card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
                <div class="order-header" style="margin-bottom: 1rem;">
                    <strong style="font-size: 1.05rem;">Заявка №<?= $r['order_id'] ?> — <?= htmlspecialchars($r['tour_name']) ?></strong>
                    <span class="status-badge status-warning"><?= $status_labels[$r['order_status']] ?? $r['order_status'] ?></span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem 2.5rem; margin-bottom: 1rem;">
                    <div><span style="color: #64748b;">Клиент:</span> <strong><?= htmlspecialchars($r['client_name'] ?? 'Не указан') ?></strong></div>
                    <div><span style="color: #64748b;">Телефон:</span> <?= htmlspecialchars($r['client_phone'] ?? 'Не указан') ?></div>
                    <div><span style="color: #64748b;">Дата тура:</span> <?= date('d.m.Y', strtotime($r['start_date'])) ?></div>
                    <div><span style="color: #64748b;">Участников:</span> <?= $r['participants_count'] ?></div>
                    <div><span style="color: #64748b;">Сумма:</span> <?= number_format($r['total'], 0, ',', ' ') ?> ₽</div>
                </div>
                <?php if (!empty($r['special_requirements'])): ?>
                <p style="margin-bottom: 1rem; color: #334155; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px;">
                    <i class="fa-solid fa-pencil" style="color: rgb(36, 91, 187);"></i> <?= htmlspecialchars($r['special_requirements']) ?>
                </p>
                <?php endif; ?>
                <a href="?take_order=<?= $r['order_id'] ?>" class="btn-take">Взять в работу</a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Нет новых заявок.</p>
        <?php endif; ?>

    <?php elseif ($tab == 'my_orders'): ?>
        <h3><i class="fa-solid fa-database" style="color: rgb(36, 91, 187);"></i> Мои заказы</h3>
        <?php if ($my_orders->num_rows > 0): ?>
            <?php while($o = $my_orders->fetch_assoc()):
                $status_key = $o['order_status'];
                $status_label = $status_labels[$status_key] ?? $status_key;
                $paid = $o['paid_amount'] ?? 0;
                $remaining = $o['total'] - $paid;
                $status_class = '';
                if (in_array($status_key, ['pending_contact', 'pending'])) $status_class = 'status-warning';
                elseif ($status_key == 'confirmed') $status_class = 'status-info';
                elseif ($status_key == 'paid') $status_class = 'status-success';
                elseif ($status_key == 'cancelled') $status_class = 'status-danger';
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <strong>Заказ №<?= $o['order_id'] ?></strong> — <?= htmlspecialchars($o['tour_name']) ?>
                    </div>
                    <span class="status-badge <?= $status_class ?>"><?= $status_label ?></span>
                </div>
                <div class="order-details">
                    <div><span class="detail-label">Клиент:</span> <span class="detail-value"><?= htmlspecialchars($o['client_name'] ?? '—') ?></span></div>
                    <div><span class="detail-label">Телефон:</span> <span class="detail-value"><?= htmlspecialchars($o['phone'] ?? '—') ?></span></div>
                    <div><span class="detail-label">Дата тура:</span> <span class="detail-value"><?= date('d.m.Y', strtotime($o['start_date'])) ?></span></div>
                    <div><span class="detail-label">Участников:</span> <span class="detail-value"><?= $o['participants_count'] ?></span></div>
                    <div><span class="detail-label">Сумма:</span> <span class="detail-value"><?= number_format($o['total'], 0, ',', ' ') ?> ₽</span></div>
                    <div><span class="detail-label">Оплачено:</span> <span class="detail-value"><?= number_format($paid, 0, ',', ' ') ?> ₽</span></div>
                </div>

                <?php if ($o['order_status'] == 'confirmed' && $remaining > 0): ?>
                    <button onclick="showPaymentForm(<?= $o['order_id'] ?>, <?= $o['total'] ?>, <?= $paid ?>)" class="btn-sm btn-success"><i class="fa-solid fa-piggy-bank" style="color: rgb(255, 255, 255);"></i> Провести оплату</button>
                <?php endif; ?>

                <select onchange="updateStatus(<?= $o['order_id'] ?>, this.value)" class="btn-sm btn-outline" style="margin-left: 0.5rem;">
                    <option value="">Изменить статус</option>
                    <option value="confirmed" <?= $status_key == 'confirmed' ? 'disabled' : '' ?>>Подтверждён</option>
                    <option value="cancelled" <?= $status_key == 'cancelled' ? 'disabled' : '' ?>>Отменён</option>
                    <option value="completed" <?= $status_key == 'completed' ? 'disabled' : '' ?>>Завершён</option>
                </select>

                <?php
                $payments = $mysqli->query("SELECT * FROM payments WHERE order_id = {$o['order_id']}");
                if ($payments->num_rows > 0):
                ?>
                <details style="margin-top: 1rem;">
                    <summary><i class="fa-solid fa-wallet" style="color: rgb(36, 91, 187);"></i> История платежей</summary>
                    <ul>
                    <?php while($p = $payments->fetch_assoc()): ?>
                        <li><?= number_format($p['amount'], 0, ',', ' ') ?> ₽ (<?= $p['payment_method'] ?>) — <?= $p['payment_status'] ?></li>
                    <?php endwhile; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>У вас пока нет заказов.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Модальное окно для оплаты -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3>💳 Проведение оплаты</h3>
        <form method="post" id="paymentForm">
            <input type="hidden" name="order_id" id="payment_order_id">
            <input type="hidden" name="add_payment" value="1">

            <div class="form-group">
                <label>Сумма к оплате:</label>
                <input type="number" name="amount" id="payment_amount" step="0.01" min="0" required>
                <small>Осталось оплатить: <span id="remaining_amount">0</span> ₽</small>
            </div>

            <div class="form-group">
                <label>Способ оплаты:</label>
                <select name="payment_method" required>
                    <option value="card">Банковская карта</option>
                    <option value="cash">Наличные</option>
                    <option value="bank_transfer">Банковский перевод</option>
                    <option value="online">Онлайн-оплата</option>
                </select>
            </div>

            <div class="form-group">
                <label>ID транзакции (необязательно):</label>
                <input type="text" name="transaction_id" placeholder="Например, TXN-123">
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary">Подтвердить оплату</button>
                <button type="button" onclick="closePaymentModal()" class="btn-outline">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
function showPaymentForm(orderId, total, paid) {
    document.getElementById('payment_order_id').value = orderId;
    document.getElementById('payment_amount').max = total - paid;
    document.getElementById('remaining_amount').textContent = (total - paid).toLocaleString('ru-RU');
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function updateStatus(orderId, newStatus) {
    if (!newStatus) return;
    if (!confirm('Изменить статус заказа на "' + newStatus + '"?')) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="order_id" value="${orderId}">
        <input type="hidden" name="new_status" value="${newStatus}">
    `;
    document.body.appendChild(form);
    form.submit();
}

window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target === modal) {
        closePaymentModal();
    }
}
</script>
</body>
</html>