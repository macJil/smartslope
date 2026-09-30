-- Only for databases where the old finish_contact_migration.sql already
-- dropped email. Back up first. Ask users for genuine emails; do not invent one.
USE smartslope_mvp;
ALTER TABLE users ADD COLUMN email VARCHAR(254) NULL AFTER username;
CREATE UNIQUE INDEX uq_users_email ON users (email);
-- Fill existing users' verified email values before running the updated
-- sql/finish_contact_migration.sql. Fresh installations do not run this script.
