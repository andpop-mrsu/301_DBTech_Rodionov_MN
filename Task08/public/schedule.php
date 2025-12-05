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
        b.number as box_number
    FROM Appointments a
    JOIN Services s ON a.service_id = s.id
    JOIN Boxes b ON a.box_id = b.id
    WHERE a.employee_id = :employee_id AND a.status = 'Запланировано'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([':employee_id' => $employee_id]);
$appointments = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM Appointments
    WHERE employee_id = :employee_id AND (status = 'Отменено' OR status = 'Неявка')
");
$stmt->execute([':employee_id' => $employee_id]);
$cancelledCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>График работы - <?= h($employee['name']) ?></title>
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
        .btn-cancelled {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-cancelled:hover {
            background: #5a6268;
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
        .btn-add {
            background: #17a2b8;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            text-decoration: none;
        }
        .btn-add:hover {
            background: #138496;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .schedule-table th {
            background: #17a2b8;
            color: white;
            font-weight: 600;
        }
        .schedule-table tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-planned {
            background: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
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
        <div class="top-links">
            <a href="index.php" class="back-link">← Назад к списку мастеров</a>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="cancelled.php?employee_id=<?= $employee_id ?>" class="btn-cancelled">Отмененные заказы и неявки<?= $cancelledCount > 0 ? ' (' . $cancelledCount . ')' : '' ?></a>
                <a href="schedule_create.php?employee_id=<?= $employee_id ?>" class="btn btn-add">Добавить запись в график</a>
            </div>
        </div>
        <h1>График работы: <?= h($employee['name']) ?></h1>
        <p style="color: #6c757d; margin-bottom: 20px; padding: 10px; background: #e9ecef; border-radius: 4px;">
            <strong>Примечание:</strong> Выполненные записи автоматически переносятся в раздел <a href="works.php?employee_id=<?= $employee_id ?>" style="color: #007bff;">"Выполненные работы"</a>, а отмененные и неявки - в раздел <a href="cancelled.php?employee_id=<?= $employee_id ?>" style="color: #007bff;">"Отмененные заказы и неявки"</a>. Они не отображаются в активном графике.
        </p>

        <?php if (empty($appointments)): ?>
            <p>Записей в графике нет.</p>
        <?php else: ?>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Время</th>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Услуга</th>
                        <th>Бокс</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= formatDate($appointment['appointment_date']) ?></td>
                            <td><?= h($appointment['appointment_time']) ?></td>
                            <td><?= h($appointment['client_name']) ?></td>
                            <td><?= h($appointment['client_phone'] ?? '-') ?></td>
                            <td><?= h($appointment['service_name']) ?></td>
                            <td><?= h($appointment['box_number']) ?></td>
                            <td>
                                <span class="status status-<?= strtolower($appointment['status']) ?>">
                                    <?= h($appointment['status']) ?>
                                </span>
                            </td>
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
