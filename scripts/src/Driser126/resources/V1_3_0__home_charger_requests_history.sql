DROP TABLE IF EXISTS dms_home_charger_requests_history;

CREATE TABLE dms_home_charger_requests_history LIKE dms_home_charger_requests;

ALTER TABLE dms_home_charger_requests_history
    CHANGE COLUMN id request_id INT(11) UNSIGNED NOT NULL,
    DROP PRIMARY KEY,
    ADD id INT(11) NOT NULL AUTO_INCREMENT FIRST,
    ADD KEY `id` (id);

ALTER TABLE dms_home_charger_requests_history
    ADD FOREIGN KEY `FK_dms_home_charger_requests_id` (`request_id`) REFERENCES dms_home_charger_requests (`id`),
    ADD PRIMARY KEY (request_id, id);

DROP TRIGGER IF EXISTS dms_home_charger_requests__au;

CREATE TRIGGER dms_home_charger_requests__au
    AFTER UPDATE
    ON dms_home_charger_requests
    FOR EACH ROW
    INSERT INTO dms_home_charger_requests_history
    SELECT NULL, d.*
    FROM dms_home_charger_requests AS d
    WHERE d.id = NEW.id;

DROP TRIGGER IF EXISTS dms_home_charger_requests__ai;

CREATE TRIGGER dms_home_charger_requests__ai
    AFTER INSERT
    ON dms_home_charger_requests
    FOR EACH ROW
    INSERT INTO dms_home_charger_requests_history
    SELECT NULL, d.*
    FROM dms_home_charger_requests AS d
    WHERE d.id = NEW.id;
