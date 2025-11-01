#!/bin/bash
chcp 65001

sqlite3 movies_rating.db < db_init.sql

echo "1. Для каждого фильма выведите его название, год выпуска и средний рейтинг. Дополнительно добавьте столбец rank_by_avg_rating, в котором укажите ранг фильма среди всех фильмов по убыванию среднего рейтинга (фильмы с одинаковым средним рейтингом должны получить одинаковый ранг). Используйте оконную функцию RANK() или DENSE_RANK(). В результирующем наборе данных оставить 10 фильмов с наибольшим рангом."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH movie_ratings AS (SELECT m.title, m.year, AVG(r.rating) AS avg_rating, DENSE_RANK() OVER (ORDER BY AVG(r.rating) DESC) AS rank_by_avg_rating FROM Movies m JOIN Ratings r ON m.movie_id = r.movie_id GROUP BY m.movie_id, m.title, m.year) SELECT title, year, ROUND(avg_rating, 2) AS avg_rating, rank_by_avg_rating FROM movie_ratings WHERE rank_by_avg_rating <= 10 ORDER BY rank_by_avg_rating, title;"
echo " "

echo "2. С помощью рекурсивного CTE выделить все жанры фильмов, имеющиеся в таблице movies. Для каждого жанра рассчитать средний рейтинг avg_rating фильмов в этом жанре. Выведите genre, avg_rating и ранг жанра по убыванию среднего рейтинга, используя оконную функцию RANK()."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH RECURSIVE genre_split AS (SELECT m.movie_id, CASE WHEN INSTR(mg.genres, '|') > 0 THEN SUBSTR(mg.genres, 1, INSTR(mg.genres, '|') - 1) ELSE mg.genres END AS genre, CASE WHEN INSTR(mg.genres, '|') > 0 THEN SUBSTR(mg.genres, INSTR(mg.genres, '|') + 1) ELSE '' END AS remaining_genres FROM (SELECT movie_id, GROUP_CONCAT(g.genre_name, '|') as genres FROM Movie_Genres mg JOIN Genres g ON mg.genre_id = g.genre_id GROUP BY movie_id) mg JOIN Movies m ON mg.movie_id = m.movie_id UNION ALL SELECT movie_id, CASE WHEN INSTR(remaining_genres, '|') > 0 THEN SUBSTR(remaining_genres, 1, INSTR(remaining_genres, '|') - 1) ELSE remaining_genres END AS genre, CASE WHEN INSTR(remaining_genres, '|') > 0 THEN SUBSTR(remaining_genres, INSTR(remaining_genres, '|') + 1) ELSE '' END AS remaining_genres FROM genre_split WHERE remaining_genres <> ''), genre_stats AS (SELECT TRIM(gs.genre) AS genre, AVG(r.rating) AS avg_rating FROM genre_split gs JOIN Ratings r ON gs.movie_id = r.movie_id WHERE gs.genre <> '' GROUP BY TRIM(gs.genre)) SELECT genre, ROUND(avg_rating, 2) AS avg_rating, RANK() OVER (ORDER BY avg_rating DESC) AS rating_rank FROM genre_stats ORDER BY rating_rank;"
echo " "

echo "3. Посчитайте количество фильмов в каждом жанре. Выведите два столбца: genre и movie_count, отсортировав результат по убыванию количества фильмов."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT g.genre_name AS genre, COUNT(DISTINCT mg.movie_id) AS movie_count FROM Genres g LEFT JOIN Movie_Genres mg ON g.genre_id = mg.genre_id GROUP BY g.genre_name ORDER BY movie_count DESC;"
echo " "

echo "4. Найдите жанры, в которых чаще всего оставляют теги (комментарии). Для этого подсчитайте общее количество записей в таблице tags для фильмов каждого жанра. Выведите genre, tag_count и долю этого жанра в общем числе тегов (tag_share), выраженную в процентах."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH genre_tags AS (SELECT g.genre_name AS genre, COUNT(t.tag_id) AS tag_count FROM Genres g JOIN Movie_Genres mg ON g.genre_id = mg.genre_id JOIN Tags t ON mg.movie_id = t.movie_id GROUP BY g.genre_name),total_tags AS (SELECT COUNT(*) AS total_count FROM Tags)SELECT gt.genre, gt.tag_count, ROUND((gt.tag_count * 100.0 / tt.total_count), 2) AS tag_share FROM genre_tags gt, total_tags tt ORDER BY gt.tag_count DESC;"
echo " "

echo "5. Для каждого пользователя рассчитайте: общее количество выставленных оценок, средний выставленный рейтинг, дату первой и последней оценки (по полю timestamp в таблице ratings). Выведите user_id, rating_count, avg_rating, first_rating_date, last_rating_date. Отсортируйте результат по убыванию количества оценок и выведите только 10 первых строк."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "SELECT u.user_id, COUNT(r.rating_id) AS rating_count, ROUND(AVG(r.rating), 2) AS avg_rating, datetime(MIN(r.timestamp), 'unixepoch') AS first_rating_date, datetime(MAX(r.timestamp), 'unixepoch') AS last_rating_date FROM Users u JOIN Ratings r ON u.user_id = r.user_id GROUP BY u.user_id ORDER BY rating_count DESC LIMIT 10;"
echo " "

echo "6. Сегментируйте пользователей по типу поведения:* «Комментаторы» — пользователи, у которых количество тегов (tags) больше количества оценок (ratings),* «Оценщики» — наоборот, оценок больше, чем тегов,* «Активные» — и оценок, и тегов ≥ 10,* «Пассивные» — и оценок, и тегов < 5. Выведите user_id, общее число оценок, общее число тегов и категорию поведения. Используйте CASE."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH user_stats AS (SELECT u.user_id, COUNT(DISTINCT r.rating_id) AS rating_count, COUNT(DISTINCT t.tag_id) AS tag_count FROM Users u LEFT JOIN Ratings r ON u.user_id = r.user_id LEFT JOIN Tags t ON u.user_id = t.user_id GROUP BY u.user_id) SELECT user_id, rating_count, tag_count, CASE WHEN rating_count >= 10 AND tag_count >= 10 THEN 'Активные' WHEN rating_count < 5 AND tag_count < 5 THEN 'Пассивные' WHEN tag_count > rating_count THEN 'Комментаторы' WHEN rating_count > tag_count THEN 'Оценщики' ELSE 'Сбалансированные' END AS behavior_category FROM user_stats ORDER BY user_id LIMIT 20;"
echo " "

echo "7. Для каждого пользователя выведите его имя и последний фильм, который он оценил (по времени из ratings.timestamp). Если пользователь не оценивал ни одного фильма, он всё равно должен быть в результате (с NULL в полях фильма). Результат: user_id, name, last_rated_movie_title, last_rating_timestamp."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "WITH last_user_ratings AS (SELECT r.user_id, r.movie_id, r.timestamp, m.title, ROW_NUMBER() OVER (PARTITION BY r.user_id ORDER BY r.timestamp DESC) as rn FROM Ratings r JOIN Movies m ON r.movie_id = m.movie_id)SELECT u.user_id, u.name, lur.title AS last_rated_movie_title, datetime(lur.timestamp, 'unixepoch') AS last_rating_timestamp FROM Users u LEFT JOIN last_user_ratings lur ON u.user_id = lur.user_id AND lur.rn = 1 ORDER BY u.user_id LIMIT 20;"
echo " "

pause