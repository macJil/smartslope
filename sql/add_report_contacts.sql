-- Existing smartslope_mvp databases: back up first and run this once.
-- Existing reports remain readable; new submissions require a contact number.
USE smartslope_mvp;

ALTER TABLE reports
    ADD COLUMN contact_number VARCHAR(20) NULL AFTER reported_by_user_id,
    ADD COLUMN email VARCHAR(254) NULL AFTER contact_number;
