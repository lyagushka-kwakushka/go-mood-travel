<?php
require_once 'config.php';

// Если уже авторизован, перенаправляем в кабинет
if (isset($_SESSION['client_id'])) {
    header('Location: cabinet.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    if ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif (!preg_match('/^[78][0-9]{10}$/', $phone)) {
        $error = 'Телефон должен быть 11 цифр, начиная с 7 или 8';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } else {
        // Проверка уникальности телефона и email
        $stmt = $mysqli->prepare("SELECT client_id FROM clients WHERE phone = ? OR email = ?");
        $stmt->bind_param('ss', $phone, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Клиент с таким телефоном или email уже существует';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("
                INSERT INTO clients (full_name, phone, email, password) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('ssss', $full_name, $phone, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = 'Регистрация успешна! Теперь вы можете войти.';
            } else {
                $error = 'Ошибка при регистрации';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Регистрация</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Регистрация</h1>
    <nav>
        <a href="index.php">На главную</a> | 
        <a href="login.php">Вход</a>
    </nav>
</header>
<main>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
        <p><a href="login.php">Перейти к входу</a></p>
    <?php else: ?>
    <form method="post" class="register-form">
        <div class="form-group">
            <label>ФИО:</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Телефон (11 цифр, начиная с 7 или 8):</label>
            <input type="text" name="phone" pattern="[78][0-9]{10}" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Пароль (минимум 6 символов):</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Подтверждение пароля:</label>
            <input type="password" name="password_confirm" required>
        </div>
        <button type="submit" class="btn-primary">Зарегистрироваться</button>
    </form>
    <?php endif; ?>
</main>
</body>
</html>