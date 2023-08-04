DROP TABLE IF EXISTS dms_installer_jobs_history;

CREATE TABLE dms_installer_jobs_history LIKE dms_installer_jobs;

ALTER TABLE dms_installer_jobs_history
    CHANGE COLUMN id job_id INT(11) UNSIGNED NOT NULL,
    DROP PRIMARY KEY,
    ADD id INT(11) NOT NULL AUTO_INCREMENT FIRST,
    ADD KEY `id` (id);

ALTER TABLE dms_installer_jobs_history
    ADD FOREIGN KEY `FK_dms_installer_jobs_id` (`job_id`) REFERENCES dms_installer_jobs (`id`),
    ADD FOREIGN KEY `FK_dms_home_charger_requests_request_id` (`request_id`) REFERENCES dms_home_charger_requests (`id`),
    ADD PRIMARY KEY (job_id, id);

DROP TRIGGER IF EXISTS dms_installer_jobs__au;

CREATE TRIGGER dms_installer_jobs__au
    AFTER UPDATE
    ON dms_installer_jobs
    FOR EACH ROW
    INSERT INTO dms_installer_jobs_history
    SELECT NULL, d.*
    FROM dms_installer_jobs AS d
    WHERE d.id = NEW.id;

DROP TRIGGER IF EXISTS dms_installer_jobs__ai;

CREATE TRIGGER dms_installer_jobs__ai
    AFTER INSERT
    ON dms_installer_jobs
    FOR EACH ROW
    INSERT INTO dms_installer_jobs_history
    SELECT NULL, d.*
    FROM dms_installer_jobs AS d
    WHERE d.id = NEW.id;
