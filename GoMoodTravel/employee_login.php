<?php
require_once 'config.php';

// Если сотрудник уже авторизован, перенаправляем в кабинет
if (isset($_SESSION['employee_id'])) {
    header('Location: employee_cabinet.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль';
    } else {
        // Поиск сотрудника по логину (email или phone, в зависимости от структуры)
        $stmt = $mysqli->prepare("
            SELECT employee_id, full_name, position, password 
            FROM employees 
            WHERE email = ? OR phone = ?
        ");
        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($employee = $result->fetch_assoc()) {
            // Проверка пароля (простое сравнение, можно заменить на password_verify если пароли хешированы)
            if ($password === $employee['password']) {
                $_SESSION['employee_id'] = $employee['employee_id'];
                $_SESSION['employee_name'] = $employee['full_name'];
                $_SESSION['employee_position'] = $employee['position'];
                header('Location: employee_cabinet.php');
                exit;
            } else {
                $error = 'Неверный пароль';
            }
        } else {
            $error = 'Сотрудник не найден';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход для сотрудников | GoMoodTravel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .login-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .login-header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .login-header h1 {
            color: #2563eb;
            font-size: 1.5rem;
            margin: 0;
        }
        .login-header a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
        }
        .login-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .employee-login-form {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 400px;
        }
        .employee-login-form h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: #1e293b;
            text-align: center;
        }
        .error {
            color: #ef4444;
            background: #fee;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.8rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: #64748b;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #2563eb;
        }
    </style>
</head>
<body>
<div class="login-page">
    <header class="login-header">
        <h1>👔 GoMoodTravel · Сотрудники</h1>
        <nav>
            <a href="index.php">← На главную</a>
        </nav>
    </header>

    <main class="login-container">
        <form method="post" class="employee-login-form">
            <h2>Вход в кабинет сотрудника</h2>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="form-group">
                <label>Email или телефон</label>
                <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary">Войти</button>
            <div class="back-link">
                <a href="index.php">Вернуться на сайт</a>
            </div>
        </form>
    </main>
</div>
</body>
</html>