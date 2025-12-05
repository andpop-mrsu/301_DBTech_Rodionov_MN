<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$error = '';
$id = (int)getParam('id', 0);
$employee_id = (int)getParam('employee_id', 0);

if (!$id || !$employee_id) {
    redirect('index.php');
}

$stmt = $db->prepare("
    SELECT w.*, s.name as service_name, b.number as box_number, e.name as employee_name
    FROM WorkRecords w
    JOIN Services s ON w.service_id = s.id
    JOIN Boxes b ON w.box_id = b.id
    JOIN Employees e ON w.employee_id = e.id
    WHERE w.id = :id
");
$stmt->execute([':id' => $id]);
$work = $stmt->fetch();

if (!$work) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && getParam('confirm') === 'yes') {
    try {
        $stmt = $db->prepare("DELETE FROM WorkRecords WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect('works.php?employee_id=' . $employee_id);
    } catch (PDOException $e) {
        $error = 'Ошибка при удалении работы: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Удалить выполненную работу</title>
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
        .work-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .work-info p {
            margin: 5px 0;
        }
        .price {
            font-weight: 600;
            color: #28a745;
            font-size: 18px;
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
        <h1>Удалить выполненную работу</h1>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="warning">
            <strong>Внимание!</strong> Вы собираетесь удалить запись о выполненной работе. Эта операция необратима.
        </div>

        <div class="work-info">
            <p><strong>Дата:</strong> <?= formatDate($work['work_date']) ?></p>
            <p><strong>Время:</strong> <?= h($work['work_time']) ?></p>
            <p><strong>Услуга:</strong> <?= h($work['service_name']) ?></p>
            <p><strong>Бокс:</strong> №<?= h($work['box_number']) ?></p>
            <p><strong class="price">Стоимость:</strong> <span class="price"><?= number_format($work['actual_price'], 2, '.', ' ') ?> ₽</span></p>
            <?php if ($work['notes']): ?>
                <p><strong>Примечания:</strong> <?= h($work['notes']) ?></p>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-danger">Подтвердить удаление</button>
            <a href="works.php?employee_id=<?= $employee_id ?>" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
</body>
</html>
