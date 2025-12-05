<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$error = '';
$master = null;
$id = (int)getParam('id', 0);

if (!$id) {
    redirect('index.php');
}

$stmt = $db->prepare("SELECT * FROM Employees WHERE id = :id");
$stmt->execute([':id' => $id]);
$master = $stmt->fetch();

if (!$master) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(getParam('name', ''));
    $hire_date = getParam('hire_date', '');
    $phone = trim(getParam('phone', ''));
    $email = trim(getParam('email', ''));

    if (empty($name)) {
        $error = 'Необходимо указать ФИО';
    } elseif (empty($phone) && empty($email)) {
        $error = 'Необходимо указать телефон или email';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE Employees
                SET name = :name, hire_date = :hire_date, phone = :phone, email = :email
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':hire_date' => $hire_date,
                ':phone' => $phone ?: null,
                ':email' => $email ?: null,
                ':id' => $id
            ]);

            redirect('index.php');
        } catch (PDOException $e) {
            $error = 'Ошибка при обновлении мастера: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать мастера</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #28a745;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"],
        input[type="date"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #28a745;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Редактировать мастера</h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">ФИО *</label>
                <input type="text" id="name" name="name" value="<?= h($master['name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="hire_date">Дата найма</label>
                <input type="date" id="hire_date" name="hire_date" value="<?= h($master['hire_date']) ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="tel" id="phone" name="phone" value="<?= h($master['phone'] ?? '') ?>" placeholder="+7-900-123-45-67">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= h($master['email'] ?? '') ?>" placeholder="example@mail.ru">
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="index.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
