<?php
require_once 'config.php';
require_once 'helpers.php';

$db = getDbConnection();

$stmt = $db->prepare("
    SELECT
        e.id,
        e.name,
        e.position,
        e.hire_date,
        e.dismissal_date,
        e.phone,
        e.email,
        COALESCE(work_counts.works_count, 0) as works_count
    FROM Employees e
    LEFT JOIN (
        SELECT employee_id, COUNT(*) as works_count
        FROM WorkRecords
        GROUP BY employee_id
    ) work_counts ON e.id = work_counts.employee_id
    WHERE e.is_active = 0 OR e.dismissal_date IS NOT NULL
    ORDER BY e.dismissal_date DESC, e.name
");
$stmt->execute();
$dismissed = $stmt->fetchAll();

usort($dismissed, function($a, $b) {
    $aLast = getLastName($a['name']);
    $bLast = getLastName($b['name']);
    return strcasecmp($aLast, $bLast);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уволенные сотрудники</title>
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
            margin-bottom: 30px;
            border-bottom: 3px solid #6c757d;
            padding-bottom: 10px;
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
        .dismissed-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .dismissed-table th,
        .dismissed-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .dismissed-table th {
            background: #6c757d;
            color: white;
            font-weight: 600;
        }
        .dismissed-table tr:hover {
            background: #f8f9fa;
        }
        .dismissed-date {
            color: #dc3545;
            font-weight: 600;
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
        .btn-works {
            background: #ffc107;
            color: #212529;
        }
        .btn-works:hover {
            background: #e0a800;
        }
        .actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Назад к списку мастеров</a>
        <h1>Уволенные сотрудники</h1>

        <?php if (empty($dismissed)): ?>
            <p>Уволенных сотрудников нет.</p>
        <?php else: ?>
            <table class="dismissed-table">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Должность</th>
                        <th>Дата найма</th>
                        <th>Дата увольнения</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dismissed as $employee): ?>
                        <?php $worksCount = (int)$employee['works_count']; ?>
                        <tr>
                            <td><?= h($employee['name']) ?></td>
                            <td><?= h($employee['position']) ?></td>
                            <td><?= formatDate($employee['hire_date']) ?></td>
                            <td class="dismissed-date"><?= $employee['dismissal_date'] ? formatDate($employee['dismissal_date']) : 'Не указана' ?></td>
                            <td><?= h($employee['phone'] ?? '-') ?></td>
                            <td><?= h($employee['email'] ?? '-') ?></td>
                            <td class="actions">
                                <?php if ($worksCount > 0): ?>
                                    <a href="works.php?employee_id=<?= $employee['id'] ?>" class="btn btn-works">Выполненные работы (<?= $worksCount ?>)</a>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-size: 12px;">Работ нет</span>
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
