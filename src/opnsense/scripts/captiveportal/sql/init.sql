--
-- create new Captive Portal database
--

-- connected clients
create table cp_clients (
      zoneid int
,     sessionid varchar
,     authenticated_via varchar
,     username varchar
,     ip_address varchar
,     mac_address varchar
,     created number
,     deleted integer default (0)
,     primary key (zoneid, sessionid)
);

create index cp_clients_ip ON cp_clients (ip_address);
create index cp_clients_zone ON cp_clients (zoneid);

-- multiple IPs per session (only used when allow_virtual_ips=1 for a zone)
create table cp_client_ips (
      zoneid     int not null
,     sessionid  varchar not null
,     ip_address varchar not null
,     primary key (zoneid, sessionid, ip_address)
,     foreign key (zoneid, sessionid)
        references cp_clients(zoneid, sessionid)
        on delete cascade
);

create index cp_client_ips_ip   on cp_client_ips (ip_address);
create index cp_client_ips_zone on cp_client_ips (zoneid);

-- session (accounting) info
create table session_info (
      zoneid int
,     sessionid varchar
,     prev_packets_in integer default (0)
,     prev_bytes_in   integer default (0)
,     prev_packets_out integer default (0)
,     prev_bytes_out   integer default (0)
,     packets_in integer default (0)
,     packets_out integer default (0)
,     bytes_in integer default (0)
,     bytes_out integer default (0)
,     last_accessed integer
,     primary key (zoneid, sessionid)
);

-- session (accounting) restrictions
create table session_restrictions (
      zoneid int
,     sessionid varchar
,     session_timeout int
,     primary key (zoneid, sessionid)
) ;

--  accounting state, record the state of (radius) accounting messages
create table accounting_state (
      zoneid int
,     sessionid varchar
,     state varchar
,     primary key (zoneid, sessionid)
) ;
