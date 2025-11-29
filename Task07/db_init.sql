DROP TABLE IF EXISTS WorkRecords;
DROP TABLE IF EXISTS Appointments;
DROP TABLE IF EXISTS SalaryRates;
DROP TABLE IF EXISTS Services;
DROP TABLE IF EXISTS CarCategories;
DROP TABLE IF EXISTS Boxes;
DROP TABLE IF EXISTS Employees;


CREATE TABLE Employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    position TEXT NOT NULL CHECK(position IN ('Мастер', 'Администратор', 'Менеджер', 'Директор')),
    hire_date DATE NOT NULL DEFAULT (date('now')),
    dismissal_date DATE,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
    phone TEXT UNIQUE,
    email TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK(dismissal_date IS NULL OR dismissal_date >= hire_date),
    CHECK(phone IS NOT NULL OR email IS NOT NULL)
);


CREATE TABLE CarCategories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE Services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    car_category_id INTEGER NOT NULL,
    duration_minutes INTEGER NOT NULL CHECK(duration_minutes > 0),
    price DECIMAL(10, 2) NOT NULL CHECK(price >= 0),
    description TEXT,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (car_category_id) REFERENCES CarCategories(id) ON DELETE RESTRICT,
    UNIQUE(name, car_category_id)
);


CREATE TABLE Boxes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    number INTEGER NOT NULL UNIQUE CHECK(number > 0),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE SalaryRates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    rate_percent DECIMAL(5, 2) NOT NULL CHECK(rate_percent > 0 AND rate_percent <= 100),
    effective_from DATE NOT NULL DEFAULT (date('now')),
    effective_to DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES Employees(id) ON DELETE RESTRICT,
    CHECK(effective_to IS NULL OR effective_to >= effective_from)
);


CREATE TABLE Appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    box_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'Запланировано' CHECK(status IN ('Запланировано', 'Выполнено', 'Отменено', 'Неявка')),
    client_name TEXT NOT NULL,
    client_phone TEXT,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES Employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (box_id) REFERENCES Boxes(id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES Services(id) ON DELETE RESTRICT
);


CREATE TABLE WorkRecords (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER,
    employee_id INTEGER NOT NULL,
    box_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    work_date DATE NOT NULL DEFAULT (date('now')),
    work_time TIME NOT NULL DEFAULT (time('now')),
    actual_price DECIMAL(10, 2) NOT NULL CHECK(actual_price >= 0),
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (appointment_id) REFERENCES Appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES Employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (box_id) REFERENCES Boxes(id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES Services(id) ON DELETE RESTRICT
);


CREATE INDEX idx_employees_active ON Employees(is_active);
CREATE INDEX idx_employees_position ON Employees(position);
CREATE INDEX idx_appointments_date ON Appointments(appointment_date, appointment_time);
CREATE INDEX idx_appointments_status ON Appointments(status);
CREATE INDEX idx_appointments_employee ON Appointments(employee_id);
CREATE INDEX idx_appointments_box ON Appointments(box_id);
CREATE INDEX idx_workrecords_date ON WorkRecords(work_date);
CREATE INDEX idx_workrecords_employee ON WorkRecords(employee_id);
CREATE INDEX idx_workrecords_service ON WorkRecords(service_id);
CREATE INDEX idx_salaryrates_employee ON SalaryRates(employee_id);
CREATE INDEX idx_salaryrates_dates ON SalaryRates(effective_from, effective_to);


BEGIN TRANSACTION;


INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Иванов Иван Иванович', 'Мастер', '2023-01-15', '+7-900-123-45-67', 'ivanov@carwash.ru');
INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Петров Петр Петрович', 'Мастер', '2023-02-20', '+7-900-234-56-78', 'petrov@carwash.ru');
INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Сидорова Анна Сергеевна', 'Мастер', '2023-03-10', '+7-900-345-67-89', 'sidorova@carwash.ru');
INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Смирнова Елена Александровна', 'Администратор', '2023-01-10', '+7-900-567-89-01', 'smirnova@carwash.ru');
INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Волков Алексей Николаевич', 'Менеджер', '2023-04-01', '+7-900-678-90-12', 'volkov@carwash.ru');
INSERT INTO Employees (name, position, hire_date, phone, email) VALUES ('Новиков Сергей Павлович', 'Мастер', '2023-05-15', '+7-900-789-01-23', 'novikov@carwash.ru');
INSERT INTO Employees (name, position, hire_date, dismissal_date, is_active, phone, email) VALUES ('Козлов Дмитрий Викторович', 'Мастер', '2022-11-05', '2024-06-30', 0, '+7-900-456-78-90', 'kozlov@carwash.ru');

INSERT INTO CarCategories (name, description) VALUES ('Легковые', 'Легковые автомобили до 5 метров');
INSERT INTO CarCategories (name, description) VALUES ('Кроссоверы', 'Кроссоверы и внедорожники до 5.5 метров');
INSERT INTO CarCategories (name, description) VALUES ('Микроавтобусы', 'Микроавтобусы и минивэны до 6 метров');
INSERT INTO CarCategories (name, description) VALUES ('Грузовые', 'Грузовые автомобили свыше 6 метров');
INSERT INTO CarCategories (name, description) VALUES ('Мотоциклы', 'Мотоциклы и скутеры');

INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка кузова', 1, 15, 500.00, 'Базовая мойка кузова');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка + сушка', 1, 25, 800.00, 'Мойка с сушкой');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полная мойка', 1, 45, 1500.00, 'Мойка кузова, салона, багажника');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полировка кузова', 1, 120, 5000.00, 'Полировка кузова автомобиля');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Химчистка салона', 1, 90, 3500.00, 'Полная химчистка салона');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка кузова', 2, 20, 700.00, 'Базовая мойка кузова');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка + сушка', 2, 30, 1100.00, 'Мойка с сушкой');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полная мойка', 2, 60, 2000.00, 'Мойка кузова, салона, багажника');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полировка кузова', 2, 150, 6500.00, 'Полировка кузова автомобиля');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка кузова', 3, 25, 900.00, 'Базовая мойка кузова');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка + сушка', 3, 40, 1400.00, 'Мойка с сушкой');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полная мойка', 3, 75, 2500.00, 'Мойка кузова, салона, багажника');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка кузова', 4, 40, 1500.00, 'Базовая мойка кузова');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка + сушка', 4, 60, 2200.00, 'Мойка с сушкой');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Полная мойка', 4, 120, 4000.00, 'Мойка кузова, кабины, кузова');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка', 5, 10, 300.00, 'Мойка мотоцикла');
INSERT INTO Services (name, car_category_id, duration_minutes, price, description) VALUES ('Мойка + полировка', 5, 30, 1200.00, 'Мойка с полировкой');

INSERT INTO Boxes (number, description) VALUES (1, 'Бокс для легковых и кроссоверов');
INSERT INTO Boxes (number, description) VALUES (2, 'Бокс для легковых и кроссоверов');
INSERT INTO Boxes (number, description) VALUES (3, 'Бокс для микроавтобусов и грузовых');
INSERT INTO Boxes (number, description) VALUES (4, 'Бокс универсальный');
INSERT INTO Boxes (number, description) VALUES (5, 'Бокс для мотоциклов');

INSERT INTO SalaryRates (employee_id, rate_percent, effective_from) VALUES (1, 25.00, '2023-01-15');
INSERT INTO SalaryRates (employee_id, rate_percent, effective_from) VALUES (2, 28.00, '2023-02-20');
INSERT INTO SalaryRates (employee_id, rate_percent, effective_from) VALUES (3, 26.00, '2023-03-10');
INSERT INTO SalaryRates (employee_id, rate_percent, effective_from) VALUES (4, 24.00, '2022-11-05');
INSERT INTO SalaryRates (employee_id, rate_percent, effective_from) VALUES (7, 27.00, '2023-05-15');

INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (1, 1, 1, date('now', '+1 day'), '10:00', 'Смирнов А.В.', '+7-911-111-11-11', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (1, 1, 2, date('now', '+1 day'), '11:00', 'Кузнецов Б.С.', '+7-911-222-22-22', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (2, 2, 6, date('now', '+1 day'), '10:30', 'Лебедев В.Д.', '+7-911-333-33-33', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (3, 1, 3, date('now', '+1 day'), '14:00', 'Соколов Г.Е.', '+7-911-444-44-44', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (7, 4, 1, date('now', '+2 days'), '09:00', 'Попов Д.Ж.', '+7-911-555-55-55', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (1, 2, 5, date('now', '+2 days'), '15:00', 'Васильев Е.З.', '+7-911-666-66-66', 'Запланировано');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (2, 1, 2, date('now', '-1 day'), '10:00', 'Петров И.И.', '+7-911-777-77-77', 'Выполнено');
INSERT INTO Appointments (employee_id, box_id, service_id, appointment_date, appointment_time, client_name, client_phone, status) VALUES (3, 2, 1, date('now', '-1 day'), '11:30', 'Иванов К.Л.', '+7-911-888-88-88', 'Выполнено');

INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (7, 1, 1, 2, date('now', '-1 day'), '10:00', 800.00, 'Работа выполнена в срок');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (8, 3, 2, 1, date('now', '-1 day'), '11:30', 500.00, 'Клиент доволен');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 2, 2, 6, date('now', '-2 days'), '14:00', 1100.00, 'Работа без предварительной записи');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 1, 1, 3, date('now', '-3 days'), '16:00', 1500.00, 'Полная мойка легкового');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 7, 4, 1, date('now', '-4 days'), '12:00', 500.00, 'Первая работа нового мастера');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 2, 2, 7, date('now', '-5 days'), '10:00', 1100.00, 'Мойка кроссовера');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 3, 1, 5, date('now', '-6 days'), '13:00', 3500.00, 'Химчистка салона');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 1, 1, 1, date('now', '-7 days'), '09:00', 500.00, 'Утренняя мойка');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 2, 2, 6, date('now', '-8 days'), '15:00', 700.00, 'Мойка кроссовера');
INSERT INTO WorkRecords (appointment_id, employee_id, box_id, service_id, work_date, work_time, actual_price, notes) VALUES (NULL, 7, 4, 2, date('now', '-9 days'), '11:00', 800.00, 'Мойка с сушкой');
