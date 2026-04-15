<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tour_id = (int)$_POST['tour_id'];
$tour_date_id = (int)$_POST['tour_date_id'];
$is_request = isset($_POST['is_request']) || isset($_POST['simple_booking']);

$mysqli->begin_transaction();
try {
    if ($is_request) {
        // ============================================
        // Упрощённая заявка от неавторизованного клиента
        // ============================================
        $client_name = trim($_POST['client_name'] ?? '');
        $client_phone = trim($_POST['client_phone'] ?? '');
        $client_email = trim($_POST['client_email'] ?? '');
        $participants_count = (int)($_POST['participants_count'] ?? 1);
        $special = trim($_POST['special_requirements'] ?? '');

        if (empty($client_name) || empty($client_phone)) {
            throw new Exception('Не заполнены обязательные поля (ФИО, телефон)');
        }

        $stmt = $mysqli->prepare("SELECT current_price FROM tour_dates WHERE tour_date_id = ?");
        $stmt->bind_param('i', $tour_date_id);
        $stmt->execute();
        $td = $stmt->get_result()->fetch_assoc();
        if (!$td) {
            throw new Exception('Выбранная дата не найдена.');
        }

        $total = $td['current_price'] * $participants_count;

        $contact_info = "Клиент: $client_name, тел: $client_phone";
        if ($client_email) {
            $contact_info .= ", email: $client_email";
        }
        if ($special) {
            $contact_info .= "\nПожелания: $special";
        }

        // Вставляем заявку, employee_id = NULL (сотрудник будет назначен позже)
$stmt = $mysqli->prepare("
    INSERT INTO orders (tour_date_id, participants_count, total, special_requirements, order_status, employee_id, client_id) 
    VALUES (?, ?, ?, ?, 'pending_contact', NULL, NULL)
");
        $stmt->bind_param('iids', $tour_date_id, $participants_count, $total, $contact_info);
        $stmt->execute();
        $order_id = $mysqli->insert_id;

        $mysqli->commit();
        $message = "Заявка №$order_id принята! Наш менеджер свяжется с вами в ближайшее время.";
        $show_cabinet_link = false;
    } else {
        // ============================================
        // Полное бронирование от авторизованного клиента
        // ============================================
        if (!isset($_SESSION['client_id'])) {
            throw new Exception('Для бронирования необходимо авторизоваться.');
        }

        $client_id = $_SESSION['client_id'];
        $special = trim($_POST['special_requirements'] ?? '');

        // Проверяем, участвует ли сам клиент в туре
        $client_not_participating = isset($_POST['client_not_participating']);

        // Получаем цену даты
        $stmt = $mysqli->prepare("SELECT current_price FROM tour_dates WHERE tour_date_id = ?");
        $stmt->bind_param('i', $tour_date_id);
        $stmt->execute();
        $td = $stmt->get_result()->fetch_assoc();
        if (!$td) {
            throw new Exception('Выбранная дата не найдена.');
        }
        $base_price = $td['current_price'];

        // Обновляем данные клиента из формы (если они были изменены)
        $client_name = trim($_POST['client_name'] ?? '');
        $client_phone = trim($_POST['client_phone'] ?? '');
        $client_email = trim($_POST['client_email'] ?? '');
        $passport_series = trim($_POST['passport_series'] ?? '');
        $passport_number = trim($_POST['passport_number'] ?? '');
        $issue_date = trim($_POST['issue_date'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');

        if (!empty($client_phone)) {
            $stmt = $mysqli->prepare("
                UPDATE clients 
                SET full_name = ?, phone = ?, email = ?, 
                    passport_series = ?, passport_number = ?, 
                    issue_date = ?, birth_date = ?
                WHERE client_id = ?
            ");
            $stmt->bind_param('sssssssi', 
                $client_name, $client_phone, $client_email,
                $passport_series, $passport_number, $issue_date, $birth_date,
                $client_id
            );
            $stmt->execute();
        }

        // Собираем участников (из формы и сохранённых)
        $participants = [];
        $total_price = 0;

        // 1. Участники, добавленные вручную
        if (isset($_POST['participants']) && is_array($_POST['participants'])) {
            foreach ($_POST['participants'] as $p) {
                if (empty($p['full_name']) || empty($p['birth_date'])) continue;

                $age = date_diff(date_create($p['birth_date']), date_create('today'))->y;
                if ($age < 2) {
                    $price = 0;
                } elseif ($age < 12) {
                    $price = $base_price * 0.7;
                } else {
                    $price = $base_price;
                }
                $total_price += $price;

                $participants[] = [
                    'full_name' => $p['full_name'],
                    'birth_date' => $p['birth_date'],
                    'passport_series' => $p['passport_series'] ?? null,
                    'passport_number' => $p['passport_number'] ?? null,
                    'oms_number' => $p['oms_number'] ?? null,
                    'relation' => $p['relation'] ?? 'other',
                ];
            }
        }

        // 2. Сохранённые участники (из чекбоксов)
        if (isset($_POST['saved_participants']) && is_array($_POST['saved_participants'])) {
            foreach ($_POST['saved_participants'] as $encoded) {
                $p = json_decode(base64_decode($encoded), true);
                if (!$p) continue;

                $age = date_diff(date_create($p['birth_date']), date_create('today'))->y;
                if ($age < 2) {
                    $price = 0;
                } elseif ($age < 12) {
                    $price = $base_price * 0.7;
                } else {
                    $price = $base_price;
                }
                $total_price += $price;

                $participants[] = [
                    'full_name' => $p['full_name'],
                    'birth_date' => $p['birth_date'],
                    'passport_series' => $p['passport_series'] ?? null,
                    'passport_number' => $p['passport_number'] ?? null,
                    'oms_number' => $p['oms_number'] ?? null,
                    'relation' => $p['client_relation'] ?? 'other',
                ];
            }
        }

        if (empty($participants) && !$client_not_participating) {
            throw new Exception('Добавьте хотя бы одного участника.');
        }

        // Создаём заказ, employee_id = NULL
$stmt = $mysqli->prepare("
    INSERT INTO orders (client_id, tour_date_id, participants_count, total, special_requirements, order_status, employee_id) 
    VALUES (?, ?, ?, ?, ?, 'pending', NULL)
");
        $participants_count = count($participants);
        $stmt->bind_param('iiids', $client_id, $tour_date_id, $participants_count, $total_price, $special);
        $stmt->execute();
        $order_id = $mysqli->insert_id;

        // Добавляем участников в tour_participants с проверкой наличия столбца oms_number
        if (!empty($participants)) {
            // Проверяем наличие поля oms_number
            $has_oms = false;
            $check = $mysqli->query("SHOW COLUMNS FROM tour_participants LIKE 'oms_number'");
            if ($check->num_rows > 0) $has_oms = true;

            if ($has_oms) {
                $stmt = $mysqli->prepare("
                    INSERT INTO tour_participants 
                    (order_id, full_name, birth_date, passport_series, passport_number, oms_number, client_relation) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($participants as $p) {
                    $stmt->bind_param('issssss',
                        $order_id,
                        $p['full_name'],
                        $p['birth_date'],
                        $p['passport_series'],
                        $p['passport_number'],
                        $p['oms_number'],
                        $p['relation']
                    );
                    $stmt->execute();
                }
            } else {
                $stmt = $mysqli->prepare("
                    INSERT INTO tour_participants 
                    (order_id, full_name, birth_date, passport_series, passport_number, client_relation) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($participants as $p) {
                    $stmt->bind_param('isssss',
                        $order_id,
                        $p['full_name'],
                        $p['birth_date'],
                        $p['passport_series'],
                        $p['passport_number'],
                        $p['relation']
                    );
                    $stmt->execute();
                }
            }
        }

        $mysqli->commit();
        $message = "Заказ №$order_id успешно оформлен!";
        $show_cabinet_link = true;
    }
} catch (Exception $e) {
    $mysqli->rollback();
    $message = "Ошибка при оформлении: " . $e->getMessage();
    $show_cabinet_link = isset($_SESSION['client_id']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= isset($is_request) && $is_request ? 'Заявка принята' : 'Бронирование' ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-container {
            max-width: 600px;
            margin: 4rem auto;
            text-align: center;
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .result-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .result-links a {
            display: inline-block;
            margin: 0 0.5rem;
            padding: 0.7rem 1.5rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }
        .result-links a:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
<header>
    <h1><?= isset($is_request) && $is_request ? 'Заявка' : 'Бронирование' ?></h1>
    <nav><a href="index.php">На главную</a></nav>
</header>
<main>
    <div class="result-container">
        <div class="result-message"><?= htmlspecialchars($message) ?></div>
        <div class="result-links">
            <a href="index.php">На главную</a>
            <?php if ($show_cabinet_link): ?>
                <a href="cabinet.php">Личный кабинет</a>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>