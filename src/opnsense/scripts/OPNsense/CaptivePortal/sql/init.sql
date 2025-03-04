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

-- session (accounting) info
create table session_info (
      zoneid int
,     sessionid varchar
,     prev_packets_in integer
,     prev_bytes_in   integer
,     prev_packets_out integer
,     prev_bytes_out   integer
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
