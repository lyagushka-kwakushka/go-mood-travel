<?php
require_once 'config.php';

// Если уже авторизован, перенаправляем в кабинет
if (isset($_SESSION['client_id'])) {
    header('Location: cabinet.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль';
    } else {
        $stmt = $mysqli->prepare("
            SELECT client_id, full_name, password 
            FROM clients 
            WHERE (phone = ? OR email = ?)
        ");
        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($client = $result->fetch_assoc()) {
            $valid = false;
            
            if (!empty($client['password'])) {
                // Проверяем, является ли пароль хешем (начинается с $2y$)
                if (strpos($client['password'], '$2y$') === 0) {
                    $valid = password_verify($password, $client['password']);
                } else {
                    // Обычное сравнение для старых паролей
                    $valid = ($password === $client['password']);
                    
                    // Если пароль верный, автоматически хешируем его для безопасности
                    if ($valid) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $update = $mysqli->prepare("UPDATE clients SET password = ? WHERE client_id = ?");
                        $update->bind_param('si', $hashed, $client['client_id']);
                        $update->execute();
                    }
                }
            }
            
            if ($valid) {
                $_SESSION['client_id'] = $client['client_id'];
                $_SESSION['client_name'] = $client['full_name'];
                header('Location: cabinet.php');
                exit;
            } else {
                $error = 'Неверный пароль';
            }
        } else {
            $error = 'Клиент с таким телефоном или email не найден';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход в личный кабинет</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }
        .login-form {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        .login-form h2 {
            margin-bottom: 1.5rem;
            text-align: center;
            color: #2563eb;
        }
        .error {
            color: #ef4444;
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #fee;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 500;
            color: #1e293b;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 0.9rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #1d4ed8;
        }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<header>
    <h1>Вход в личный кабинет</h1>
    <nav>
        <a href="index.php">На главную</a>
        <a href="register.php">Регистрация</a>
    </nav>
</header>
<main class="login-container">
    <form method="post" class="login-form">
        <h2>Вход</h2>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <div class="form-group">
            <label>Телефон или Email:</label>
            <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Пароль:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-login">Войти</button>
        <p class="register-link">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
    </form>
</main>
</body>
</html>