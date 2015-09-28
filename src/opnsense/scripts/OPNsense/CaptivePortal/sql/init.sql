--
-- create new Captive Portal database
--

-- connected clients
create table cp_clients (
      zoneid int
,     sessionid varchar
,     username varchar
,     ip_address varchar
,     mac_address varchar
,     created number
,     primary key (zoneid, sessionid)
);

create index cp_clients_ip ON cp_clients (ip_address);
create index cp_clients_zone ON cp_clients (zoneid);

-- session (accounting) info
create table session_info (
      zoneid int
,     sessionid varchar
,     primary key (zoneid, sessionid)
);

