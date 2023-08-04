create table clb_company_driver_affiliation
(
    id                      int auto_increment
        primary key,
    driver_id               int                                               null,
    company_id              int                                               null,
    connection_id           int                                               not null,
    status                  enum ('PENDING', 'ACTIVE', 'DELETED', 'REJECTED') null,
    affiliation_field       varchar(255)                                      null,
    affiliation_field_value varchar(255)                                      null,
    status_change_date      datetime                                          null,
    create_date             timestamp default CURRENT_TIMESTAMP               not null,
    created_by              int                                               null,
    has_home_requested      tinyint   default 0                               null comment 'whether user has requested a home charger or not',
    constraint unique_DriverGroup_ConnectionId
        unique (driver_id, connection_id, status)
) collate = utf8mb4_unicode_ci;
