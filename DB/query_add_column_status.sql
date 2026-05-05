ALTER TABLE statuses ADD COLUMN defect TINYINT ( 1 ) NOT NULL DEFAULT 0;
UPDATE statuses 
SET defect = 1 
WHERE
	id = 88;