<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$employee_id = (int)getParam('employee_id', 0);

if (!$employee_id) {
    redirect('index.php');
}

$stmt = $db->prepare("SELECT name, is_active, dismissal_date FROM Employees WHERE id = :id");
$stmt->execute([':id' => $employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    redirect('index.php');
}

$isDismissed = ($employee['is_active'] == 0 || !empty($employee['dismissal_date']));

$stmt = $db->prepare("
    SELECT
        w.id,
        w.work_date,
        w.work_time,
        w.actual_price,
        s.name as service_name,
        b.number as box_number,
        w.notes
    FROM WorkRecords w
    JOIN Services s ON w.service_id = s.id
    JOIN Boxes b ON w.box_id = b.id
    WHERE w.employee_id = :employee_id
    ORDER BY w.work_date DESC, w.work_time DESC
");
$stmt->execute([':employee_id' => $employee_id]);
$works = $stmt->fetchAll();

$total = 0;
foreach ($works as $work) {
    $total += (float)$work['actual_price'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выполненные работы - <?= h($employee['name']) ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .top-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .back-link {
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .total-info {
            background: #d4edda;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            color: #155724;
        }
        .works-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .works-table th,
        .works-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .works-table th {
            background: #ffc107;
            color: #212529;
            font-weight: 600;
        }
        .works-table tr:hover {
            background: #f8f9fa;
        }
        .price {
            font-weight: 600;
            color: #28a745;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #28a745;
            color: white;
            padding: 8px 10px;
            font-size: 0;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-edit:hover {
            background: #218838;
        }
        .btn-edit::before {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http:
            background-size: contain;
            background-repeat: no-repeat;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 8px 10px;
            font-size: 0;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .btn-delete::before {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http:
            background-size: contain;
            background-repeat: no-repeat;
        }
        .btn-add {
            background: #ffc107;
            color: #212529;
            padding: 12px 24px;
            font-size: 16px;
            margin-top: 20px;
            font-weight: 600;
        }
        .btn-add:hover {
            background: #e0a800;
        }
        .actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-links">
            <?php if ($isDismissed): ?>
                <a href="dismissed.php" class="back-link">← Назад к списку уволенных сотрудников</a>
            <?php else: ?>
                <a href="index.php" class="back-link">← Назад к списку мастеров</a>
            <?php endif; ?>
            <?php if (!$isDismissed): ?>
                <a href="works_create.php?employee_id=<?= $employee_id ?>" class="btn btn-add">Добавить выполненную работу</a>
            <?php endif; ?>
        </div>
        <h1>Выполненные работы: <?= h($employee['name']) ?></h1>

        <?php if (!empty($works)): ?>
            <div class="total-info">
                Общая сумма выполненных работ: <?= number_format($total, 2, '.', ' ') ?> ₽
            </div>
        <?php endif; ?>

        <?php if ($isDismissed): ?>
            <p style="color: #6c757d; margin-bottom: 20px; padding: 10px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">
                <strong>Примечание:</strong> Сотрудник уволен. Работы доступны только для просмотра.
            </p>
        <?php endif; ?>

        <?php if (empty($works)): ?>
            <p>Выполненных работ нет.</p>
        <?php else: ?>
            <table class="works-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Время</th>
                        <th>Услуга</th>
                        <th>Бокс</th>
                        <th>Стоимость</th>
                        <th>Примечания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($works as $work): ?>
                        <tr>
                            <td><?= formatDate($work['work_date']) ?></td>
                            <td><?= h($work['work_time']) ?></td>
                            <td><?= h($work['service_name']) ?></td>
                            <td>№<?= h($work['box_number']) ?></td>
                            <td class="price"><?= number_format($work['actual_price'], 2, '.', ' ') ?> ₽</td>
                            <td><?= h($work['notes'] ?? '-') ?></td>
                            <td class="actions">
                                <?php if (!$isDismissed): ?>
                                    <a href="works_edit.php?id=<?= $work['id'] ?>&employee_id=<?= $employee_id ?>" class="btn btn-edit" title="Редактировать">Редактировать</a>
                                    <a href="works_delete.php?id=<?= $work['id'] ?>&employee_id=<?= $employee_id ?>" class="btn btn-delete" title="Удалить">Удалить</a>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-size: 12px;">Только просмотр</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
