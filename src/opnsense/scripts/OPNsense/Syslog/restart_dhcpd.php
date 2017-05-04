#!/usr/local/bin/php
<?php

// Use legacy code to reconfigure and restart dhcpd.

require_once("config.inc");
require_once("util.inc");
require_once("services.inc");
require_once("interfaces.inc");

killbyname('dhcpd');
services_dhcpd_configure();
