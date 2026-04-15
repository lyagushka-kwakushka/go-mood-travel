<?php
require_once 'config.php';

// Проверка авторизации
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['client_id'];
$message = '';
$error = '';

// Получение текущих данных клиента
$stmt = $mysqli->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    die("Клиент не найден.");
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passport_series = trim($_POST['passport_series'] ?? '');
    $passport_number = trim($_POST['passport_number'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $password_new = $_POST['password_new'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Валидация
    if (empty($full_name) || empty($phone) || empty($email)) {
        $error = 'ФИО, телефон и email обязательны для заполнения.';
    } elseif (!preg_match('/^[78][0-9]{10}$/', $phone)) {
        $error = 'Телефон должен быть 11 цифр, начиная с 7 или 8.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email.';
    } elseif (!empty($passport_series) && !preg_match('/^[0-9]{4}$/', $passport_series)) {
        $error = 'Серия паспорта должна содержать 4 цифры.';
    } elseif (!empty($passport_number) && !preg_match('/^[0-9]{6}$/', $passport_number)) {
        $error = 'Номер паспорта должен содержать 6 цифр.';
    } elseif (!empty($password_new) && strlen($password_new) < 6) {
        $error = 'Пароль должен быть не менее 6 символов.';
    } elseif (!empty($password_new) && $password_new !== $password_confirm) {
        $error = 'Пароли не совпадают.';
    } else {
        // Проверка уникальности телефона и email (исключая текущего клиента)
        $stmt = $mysqli->prepare("SELECT client_id FROM clients WHERE (phone = ? OR email = ?) AND client_id != ?");
        $stmt->bind_param('ssi', $phone, $email, $client_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Клиент с таким телефоном или email уже существует.';
        } else {
            // Обновление данных
            $update_fields = [];
            $params = [];
            $types = '';

            $update_fields[] = "full_name = ?";
            $params[] = $full_name;
            $types .= 's';

            $update_fields[] = "phone = ?";
            $params[] = $phone;
            $types .= 's';

            $update_fields[] = "email = ?";
            $params[] = $email;
            $types .= 's';

            $update_fields[] = "passport_series = ?";
            $params[] = $passport_series ?: null;
            $types .= 's';

            $update_fields[] = "passport_number = ?";
            $params[] = $passport_number ?: null;
            $types .= 's';

            $update_fields[] = "birth_date = ?";
            $params[] = $birth_date ?: null;
            $types .= 's';

            $update_fields[] = "issue_date = ?";
            $params[] = $issue_date ?: null;
            $types .= 's';

            if (!empty($password_new)) {
                $hashed = password_hash($password_new, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $hashed;
                $types .= 's';
            }

            $params[] = $client_id;
            $types .= 'i';

            $sql = "UPDATE clients SET " . implode(', ', $update_fields) . " WHERE client_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['client_name'] = $full_name;
                $message = 'Данные успешно обновлены!';
                // Обновляем данные клиента для отображения в форме
                $client['full_name'] = $full_name;
                $client['phone'] = $phone;
                $client['email'] = $email;
                $client['passport_series'] = $passport_series;
                $client['passport_number'] = $passport_number;
                $client['birth_date'] = $birth_date;
                $client['issue_date'] = $issue_date;
            } else {
                $error = 'Ошибка при обновлении данных.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование профиля | GoMoodTravel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .edit-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }
        .edit-form {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: #1e293b;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group small {
            color: #64748b;
            font-size: 0.85rem;
        }
        .btn-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover {
            background: #1d4ed8;
        }
        .btn-cancel {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            margin-left: 1rem;
        }
        .message-success {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .message-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<header>
    <h1>Редактирование профиля</h1>
    <nav>
        <a href="cabinet.php">← В кабинет</a>
        <a href="index.php">На главную</a>
    </nav>
</header>

<main class="edit-container">
    <?php if ($message): ?>
        <div class="message-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="edit-form">
        <div class="form-group">
            <label>ФИО *</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($client['full_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Телефон * (11 цифр, начиная с 7 или 8)</label>
            <input type="text" name="phone" pattern="[78][0-9]{10}" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Серия паспорта (4 цифры)</label>
            <input type="text" name="passport_series" pattern="[0-9]{4}" value="<?= htmlspecialchars($client['passport_series'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Номер паспорта (6 цифр)</label>
            <input type="text" name="passport_number" pattern="[0-9]{6}" value="<?= htmlspecialchars($client['passport_number'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Дата выдачи паспорта</label>
            <input type="date" name="issue_date" value="<?= htmlspecialchars($client['issue_date'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Дата рождения</label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($client['birth_date'] ?? '') ?>">
        </div>

        <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #e2e8f0;">

        <h3>Сменить пароль</h3>
        <p><small>Оставьте поля пустыми, если не хотите менять пароль.</small></p>

        <div class="form-group">
            <label>Новый пароль (мин. 6 символов)</label>
            <input type="password" name="password_new">
        </div>

        <div class="form-group">
            <label>Подтверждение пароля</label>
            <input type="password" name="password_confirm">
        </div>

        <div style="display: flex; align-items: center; margin-top: 2rem;">
            <button type="submit" class="btn-save">Сохранить изменения</button>
            <a href="cabinet.php" class="btn-cancel">Отмена</a>
        </div>
    </form>
</main>
</body>
</html>