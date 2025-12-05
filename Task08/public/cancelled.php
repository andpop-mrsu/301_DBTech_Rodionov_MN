<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();
$employee_id = (int)getParam('employee_id', 0);

if (!$employee_id) {
    redirect('index.php');
}

$stmt = $db->prepare("SELECT name FROM Employees WHERE id = :id");
$stmt->execute([':id' => $employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    redirect('index.php');
}

$stmt = $db->prepare("
    SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.client_name,
        a.client_phone,
        s.name as service_name,
        b.number as box_number,
        a.notes
    FROM Appointments a
    JOIN Services s ON a.service_id = s.id
    JOIN Boxes b ON a.box_id = b.id
    WHERE a.employee_id = :employee_id AND (a.status = 'Отменено' OR a.status = 'Неявка')
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':employee_id' => $employee_id]);
$cancelled = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отмененные заказы и неявки - <?= h($employee['name']) ?></title>
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
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .cancelled-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .cancelled-table th,
        .cancelled-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .cancelled-table th {
            background: #6c757d;
            color: white;
            font-weight: 600;
        }
        .cancelled-table tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-отменено {
            background: #f8d7da;
            color: #721c24;
        }
        .status-no-show,
        .status-неявка {
            background: #fff3cd;
            color: #856404;
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
        .actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="schedule.php?employee_id=<?= $employee_id ?>" class="back-link">← Назад к графику работы</a>
        <h1>Отмененные заказы и неявки: <?= h($employee['name']) ?></h1>

        <?php if (empty($cancelled)): ?>
            <p>Отмененных заказов и неявок нет.</p>
        <?php else: ?>
            <table class="cancelled-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Время</th>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Услуга</th>
                        <th>Бокс</th>
                        <th>Статус</th>
                        <th>Примечания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cancelled as $appointment): ?>
                        <tr>
                            <td><?= formatDate($appointment['appointment_date']) ?></td>
                            <td><?= h($appointment['appointment_time']) ?></td>
                            <td><?= h($appointment['client_name']) ?></td>
                            <td><?= h($appointment['client_phone'] ?? '-') ?></td>
                            <td><?= h($appointment['service_name']) ?></td>
                            <td><?= h($appointment['box_number']) ?></td>
                            <td>
                                <?php
                                $statusStyle = '';
                                if ($appointment['status'] === 'Отменено') {
                                    $statusStyle = 'background: #f8d7da; color: #721c24;';
                                } elseif ($appointment['status'] === 'Неявка') {
                                    $statusStyle = 'background: #fff3cd; color: #856404;';
                                }
                                ?>
                                <span class="status" style="<?= $statusStyle ?>">
                                    <?= h($appointment['status']) ?>
                                </span>
                            </td>
                            <td><?= h($appointment['notes'] ?? '-') ?></td>
                            <td class="actions">
                                <a href="schedule_edit.php?id=<?= $appointment['id'] ?>&employee_id=<?= $employee_id ?>" class="btn btn-edit" title="Редактировать">Редактировать</a>
                                <a href="schedule_delete.php?id=<?= $appointment['id'] ?>&employee_id=<?= $employee_id ?>" class="btn btn-delete" title="Удалить">Удалить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
