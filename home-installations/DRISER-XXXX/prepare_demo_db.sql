-- Add more examples for demo purposes
CREATE TABLE clb_leaseco_transactions (
    id int NOT NULL AUTO_INCREMENT primary key,
    -- MAC address of the device
    home_serial_num varchar(255) NOT NULL
);

INSERT INTO clb_leaseco_transactions (home_serial_num) VALUES ('FF0000000000'), ('FF0000000001');
