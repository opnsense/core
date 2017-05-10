#!/usr/local/bin/php
<?php

require_once("config.inc");
require_once("plugins.inc");

if (isset($argv[1])) {
    plugins_configure("webproxy_" . trim($argv[1], " \n"));
}
