-- Read-only: run in the database configured in your .env. No passwords are selected.
SELECT DATABASE() AS connected_database;
SHOW CREATE TABLE users;
SHOW INDEX FROM users;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users';
-- user_id must be a PRIMARY KEY with AUTO_INCREMENT; email and phone must exist.
-- Compare with db.sql. Do not re-import db.sql over an existing database.
