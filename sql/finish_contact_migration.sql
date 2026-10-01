-- Keep BOTH email and contact_number. Back up before running on an existing DB.
USE smartslope_mvp;
DROP PROCEDURE IF EXISTS smartslope_guard_contacts;
DELIMITER //
CREATE PROCEDURE smartslope_guard_contacts()
BEGIN
    IF EXISTS(SELECT 1 FROM users WHERE contact_number IS NULL
              OR contact_number NOT REGEXP '^[+]?[0-9]{7,15}$'
              OR email IS NULL OR TRIM(email) = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Supply genuine emails and phone numbers for all users first';
    END IF;
END//
DELIMITER ;
CALL smartslope_guard_contacts();
DROP PROCEDURE smartslope_guard_contacts;
ALTER TABLE users MODIFY COLUMN contact_number VARCHAR(20) NOT NULL,
                  MODIFY COLUMN email VARCHAR(254) NOT NULL;
-- Preserve the existing unique indexes on username, email and contact_number.
