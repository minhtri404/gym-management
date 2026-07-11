-- Allow member accounts without email.
-- Run this once on an existing database before registering users with blank email.

ALTER TABLE users
  MODIFY COLUMN email VARCHAR(120) NULL DEFAULT NULL;
