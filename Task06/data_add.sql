-- 1. Добавление новых пользователей
INSERT INTO users (name, email, gender, register_date, occupation_id)
VALUES 
('Родионов Михаил', 'bnf90@bk.ru', 'male', date('now'), 
    (SELECT id FROM occupations WHERE name = 'administrator')),
('Роман Пьянов', 'roman.pyanov@aboba.com', 'male', date('now'), 
    (SELECT id FROM occupations WHERE name = 'other')),
('Вадим Орлов', 'vadim.orlov@aboba.com', 'male', date('now'), 
    (SELECT id FROM occupations WHERE name = 'programmer')),
('Максим Самылкин', 'maxim.samylkin@aboba.com', 'male', date('now'), 
    (SELECT id FROM occupations WHERE name = 'writer')),
('Максим Сарайкин', 'maxim.saraykin@aboba.com', 'male', date('now'), 
    (SELECT id FROM occupations WHERE name = 'engineer'));


INSERT INTO movies (title, year)
VALUES 
('Бойцовский клуб', 1999),
('Легенда', 2015),
('Легенда №17', 2012);


INSERT INTO movies_genres (movie_id, genre_id)
VALUES 
-- Бойцовский клуб: Thriller, Drama, Crime
((SELECT id FROM movies WHERE title = 'Бойцовский клуб'), 
 (SELECT id FROM genres WHERE name = 'Thriller')),
((SELECT id FROM movies WHERE title = 'Бойцовский клуб'), 
 (SELECT id FROM genres WHERE name = 'Drama')),
((SELECT id FROM movies WHERE title = 'Бойцовский клуб'), 
 (SELECT id FROM genres WHERE name = 'Crime')),

-- Легенда: Crime, Thriller, Drama
((SELECT id FROM movies WHERE title = 'Легенда'), 
 (SELECT id FROM genres WHERE name = 'Crime')),
((SELECT id FROM movies WHERE title = 'Легенда'), 
 (SELECT id FROM genres WHERE name = 'Thriller')),
((SELECT id FROM movies WHERE title = 'Легенда'), 
 (SELECT id FROM genres WHERE name = 'Drama')),

-- Легенда №17: Drama, Sport
((SELECT id FROM movies WHERE title = 'Легенда №17'), 
 (SELECT id FROM genres WHERE name = 'Drama')),
((SELECT id FROM movies WHERE title = 'Легенда №17'), 
 (SELECT id FROM genres WHERE name = 'Sport'));

-- 4. Добавление отзывов
INSERT INTO ratings (user_id, movie_id, rating, timestamp)
VALUES 
((SELECT id FROM users WHERE email = 'bnf90@bk.ru'), 
 (SELECT id FROM movies WHERE title = 'Бойцовский клуб'), 5.0, strftime('%s', 'now')),
((SELECT id FROM users WHERE email = 'bnf90@bk.ru'), 
 (SELECT id FROM movies WHERE title = 'Легенда'), 4.9, strftime('%s', 'now')),
((SELECT id FROM users WHERE email = 'bnf90@bk.ru'), 
 (SELECT id FROM movies WHERE title = 'Легенда №17'), 4.8, strftime('%s', 'now'));
