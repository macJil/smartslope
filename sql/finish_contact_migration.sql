-- Stage 2, only after genuine contact numbers have been supplied for legacy users.
-- Back up database first. This removes the old email column after verification.
USE smartslope_mvp;
DELIMITER //
CREATE PROCEDURE smartslope_guard_contacts()
BEGIN
    IF EXISTS(SELECT 1 FROM users WHERE contact_number IS NULL
              OR contact_number NOT REGEXP '^[+]?[0-9]{7,15}$') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A user still lacks a valid genuine contact number';
    END IF;
END//
DELIMITER ;
CALL smartslope_guard_contacts();
DROP PROCEDURE smartslope_guard_contacts;
ALTER TABLE users MODIFY COLUMN contact_number VARCHAR(20) NOT NULL;
ALTER TABLE users DROP INDEX uq_users_email;
ALTER TABLE users DROP COLUMN email;
