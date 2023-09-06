create table examples (
    id int primary key auto_increment,
    name varchar(255) not null,
    created_at timestamp default current_timestamp
);
insert into examples (name) values ('example 1');
