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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && getParam('confirm') === 'yes') {
    try {
        $stmt = $db->prepare("UPDATE Employees SET is_active = 0, dismissal_date = date('now') WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect('index.php');
    } catch (PDOException $e) {
        $error = 'Ошибка при удалении мастера: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Удалить мастера</title>
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
            color: #dc3545;
            margin-bottom: 30px;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 10px;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .master-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .master-info p {
            margin: 5px 0;
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
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
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
        <h1>Удалить мастера</h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="warning">
            <strong>Внимание!</strong> Вы собираетесь удалить мастера из системы. Эта операция помечает мастера как неактивного.
        </div>

        <div class="master-info">
            <p><strong>ФИО:</strong> <?= h($master['name']) ?></p>
            <p><strong>Должность:</strong> <?= h($master['position']) ?></p>
            <p><strong>Дата найма:</strong> <?= formatDate($master['hire_date']) ?></p>
            <?php if ($master['phone']): ?>
                <p><strong>Телефон:</strong> <?= h($master['phone']) ?></p>
            <?php endif; ?>
            <?php if ($master['email']): ?>
                <p><strong>Email:</strong> <?= h($master['email']) ?></p>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-danger">Подтвердить удаление</button>
            <a href="index.php" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
</body>
</html>
