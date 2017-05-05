#!/usr/local/bin/php
<?php

require_once("config.inc");
require_once("services.inc");

foreach (services_get() as $server)
    service_control_squid_restart($server["name"], []);
