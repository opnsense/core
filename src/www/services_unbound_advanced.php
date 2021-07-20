<?php

/*
 * Copyright (C) 2014-2021 Deciso B.V.
 * Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("system.inc");
require_once("plugins.inc.d/unbound.inc");

config_read_array('unbound');

$copy_fields = array(
    'cache_max_ttl',
    'cache_min_ttl',
    'incoming_num_tcp',
    'infra_cache_numhosts',
    'infra_host_ttl',
    'jostle_timeout',
    'log_verbosity',
    'msgcachesize',
    'num_queries_per_thread',
    'outgoing_num_tcp',
    'unwanted_reply_threshold',
);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    // set defaults
    $pconfig['incoming_num_tcp'] = 10;
    $pconfig['infra_cache_numhosts'] = 10000;
    $pconfig['infra_host_ttl'] = 900;
    $pconfig['jostle_timeout'] = 200;
    $pconfig['log_verbosity'] = '1';
    $pconfig['num_queries_per_thread'] = 4096;
    $pconfig['outgoing_num_tcp'] = 10;

    // boolean fields
    $pconfig['dnssecstripped'] = isset($config['unbound']['dnssecstripped']);
    $pconfig['extended_statistics'] = isset($config['unbound']['extended_statistics']);
    $pconfig['hideidentity'] = isset($config['unbound']['hideidentity']);
    $pconfig['hideversion'] = isset($config['unbound']['hideversion']);
    $pconfig['log_queries'] = isset($config['unbound']['log_queries']);
    $pconfig['prefetch'] = isset($config['unbound']['prefetch']);
    $pconfig['prefetchkey'] = isset($config['unbound']['prefetchkey']);
    $pconfig['qnameminstrict'] = isset($config['unbound']['qnameminstrict']);
    $pconfig['serveexpired'] = isset($config['unbound']['serveexpired']);

    // text fields
    foreach ($copy_fields as $fieldname) {
        if (isset($config['unbound'][$fieldname])) {
            $pconfig[$fieldname] = $config['unbound'][$fieldname];
        } elseif (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    // boolean fields
    $config['unbound']['dnssecstripped'] = !empty($pconfig['dnssecstripped']);
    $config['unbound']['extended_statistics'] = !empty($pconfig['extended_statistics']);
    $config['unbound']['hideidentity'] = !empty($pconfig['hideidentity']);
    $config['unbound']['hideversion'] = !empty($pconfig['hideversion']);
    $config['unbound']['log_queries'] = !empty($pconfig['log_queries']);
    $config['unbound']['prefetch'] = !empty($pconfig['prefetch']);
    $config['unbound']['prefetchkey'] = !empty($pconfig['prefetchkey']);
    $config['unbound']['qnameminstrict'] = !empty($pconfig['qnameminstrict']);
    $config['unbound']['serveexpired'] = !empty($pconfig['serveexpired']);

    // text fields
    foreach ($copy_fields as $fieldname) {
        $config['unbound'][$fieldname] = $pconfig[$fieldname];
    }

    write_config('Unbound advanced configuration changed.');

    unbound_configure_do();
    plugins_configure('dhcp');

    header(url_safe('Location: /services_unbound_advanced.php'));
    exit;
}


$service_hook = 'unbound';
legacy_html_escape_form_data($pconfig);
include_once("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tbody>
                      <tr>
                        <td style="width:22%"><strong><?=gettext("Advanced Resolver Options");?></strong></td>
                        <td style="width:78%; text-align:right">
                          <small><?=gettext("full help"); ?> </small>
                          <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_hideidentity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Hide Identity") ?></td>
                        <td>
                          <input name="hideidentity" type="checkbox" id="hideidentity" value="yes" <?= empty($pconfig['hideidentity']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_hideidentity">
                            <?=gettext("If enabled, id.server and hostname.bind queries are refused.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_hideversion" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hide Version");?></td>
                        <td>
                          <input name="hideversion" type="checkbox" id="hideversion" value="yes" <?= empty($pconfig['hideversion']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_hideversion">
                            <?= gettext("If enabled, version.server and version.bind queries are refused.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_prefetch" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Prefetch Support") ?></td>
                        <td>
                          <input name="prefetch" type="checkbox" id="prefetch" value="yes" <?= empty($pconfig['prefetch']) ? '': 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_prefetch">
                            <?= gettext("Message cache elements are prefetched before they expire to help keep the cache up to date. When enabled, this option can cause an increase of around 10% more DNS traffic and load on the server, but frequently requested items will not expire from the cache.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_prefetchkey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Prefetch DNS Key Support") ?></td>
                        <td>
                          <input name="prefetchkey" type="checkbox" id="prefetchkey" value="yes" <?= empty($pconfig['prefetchkey']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_prefetchkey">
                            <?= sprintf(gettext("DNSKEY's are fetched earlier in the validation process when a %sDelegation signer%s is encountered. This helps lower the latency of requests but does utilize a little more CPU."), "<a href='http://en.wikipedia.org/wiki/List_of_DNS_record_types'>", "</a>") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_dnssecstripped" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Harden DNSSEC data");?></td>
                        <td>
                          <input name="dnssecstripped" type="checkbox" id="dnssecstripped" value="yes" <?= empty($pconfig['dnssecstripped']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_dnssecstripped">
                            <?= gettext("DNSSEC data is required for trust-anchored zones. If such data is absent, the zone becomes bogus. If this is disabled and no DNSSEC data is received, then the zone is made insecure.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_serveexpired" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Serve expired responses') ?></td>
                        <td>
                          <input name="serveexpired" type="checkbox" id="serveexpired" value="yes" <?= empty($pconfig['serveexpired']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_serveexpired">
                            <?= gettext('Serve expired responses from the cache with a TTL of 0 without waiting for the actual resolution to finish.') ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_qnameminstrict" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Strict QNAME minimisation') ?></td>
                        <td>
                          <input name="qnameminstrict" type="checkbox" id="qnameminstrict" value="yes" <?= empty($pconfig['qnameminstrict']) ? '' : 'checked="checked"' ?> />
                          <div class="hidden" data-for="help_for_qnameminstrict">
                            <?= gettext('Send minimum amount of information to upstream servers to enhance privacy. ' .
                                  'Do not fall-back to sending full QNAME to potentially broken nameservers. ' .
                                  'A lot of domains will not be resolvable when this option in enabled. ' .
                                  'Only use if you  know  what you are doing.') ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_msgcachesize" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Message Cache Size") ?></td>
                        <td>
                          <select id="msgcachesize" name="msgcachesize" class="selectpicker">
<?php
                          foreach (array("4", "10", "20", "50", "100", "250", "512") as $size) :?>
                            <option value="<?= $size ?>" <?= $pconfig['msgcachesize'] == $size ? 'selected="selected"' : '' ?>>
                              <?= sprintf(gettext("%s MB"), $size) ?>
                            </option>
<?php
                          endforeach;?>
                          </select>
                          <div class="hidden" data-for="help_for_msgcachesize">
                            <?= gettext("Size of the message cache. The message cache stores DNS rcodes and validation statuses. The RRSet cache will automatically be set to twice this amount. The RRSet cache contains the actual RR data. The default is 4 megabytes.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_outgoing_num_tcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Outgoing TCP Buffers") ?></td>
                        <td>
                          <select id="outgoing_num_tcp" name="outgoing_num_tcp" class="selectpicker">
<?php
                          for ($num_tcp = 0; $num_tcp <= 50; $num_tcp += 10):?>
                            <option value="<?= $num_tcp ?>" <?= $pconfig['outgoing_num_tcp'] == $num_tcp ? 'selected="selected"' : '' ?>>
                              <?= $num_tcp ?>
                            </option>
<?php
                          endfor;?>
                          </select>
                          <div class="hidden" data-for="help_for_outgoing_num_tcp">
                            <?=gettext("The number of outgoing TCP buffers to allocate per thread. The default value is 10. If 0 is selected then no TCP queries, to authoritative servers, are done.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_incoming_num_tcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Incoming TCP Buffers");?></td>
                        <td>
                          <select id="incoming_num_tcp" name="incoming_num_tcp" class="selectpicker">
<?php
                          for ($num_tcp = 0; $num_tcp <= 50; $num_tcp += 10):?>
                            <option value="<?= $num_tcp ?>" <?= $pconfig['incoming_num_tcp'] == $num_tcp ? 'selected="selected"' : '' ?>>
                              <?= $num_tcp ?>
                            </option>
<?php
                          endfor;?>
                          </select>
                          <div class="hidden" data-for="help_for_incoming_num_tcp">
                            <?=gettext("The number of incoming TCP buffers to allocate per thread. The default value is 10. If 0 is selected then no TCP queries, from clients, are accepted.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_num_queries_per_thread" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Number of queries per thread");?></td>
                        <td>
                          <select id="num_queries_per_thread" name="num_queries_per_thread" class="selectpicker">
<?php
                          foreach (array('512', '1024', '2048', '4096', '8192') as $queries) :?>
                            <option value="<?= $queries ?>" <?= $pconfig['num_queries_per_thread'] == $queries ? 'selected="selected"' : '' ?>>
                              <?= $queries ?>
                            </option>
<?php
                          endforeach;?>
                          </select>
                          <div class="hidden" data-for="help_for_num_queries_per_thread">
                            <?=gettext("The number of queries that every thread will service simultaneously. If more queries arrive that need to be serviced, and no queries can be jostled, then these queries are dropped.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_jostle_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Jostle Timeout");?></td>
                        <td>
                          <select id="jostle_timeout" name="jostle_timeout" class="selectpicker">
<?php
                          foreach (array("100", "200", "500", "1000") as $timeout) :?>
                            <option value="<?= $timeout ?>" <?= $pconfig['jostle_timeout'] == $timeout ? 'selected="selected"' : ''; ?>>
                              <?= $timeout ?>
                            </option>
<?php
                          endforeach;?>
                          </select>
                          <div class="hidden" data-for="help_for_jostle_timeout">
                            <?= gettext("This timeout is used for when the server is very busy. This protects against denial of service by slow queries or high query rates. The default value is 200 milliseconds.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_cache_max_ttl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Maximum TTL for RRsets and messages") ?></td>
                        <td>
                          <input type="text" id="cache_max_ttl" name="cache_max_ttl" size="5" value="<?= $pconfig['cache_max_ttl'] ?>" />
                          <div class="hidden" data-for="help_for_cache_max_ttl">
                            <?= gettext("Configure a maximum Time to live for RRsets and messages in the cache. The default is 86400 seconds (1 day). When the internal TTL expires the cache item is expired. This can be configured to force the resolver to query for data more often and not trust (very large) TTL values.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_cache_min_ttl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Minimum TTL for RRsets and messages") ?></td>
                        <td>
                          <input type="text" id="cache_min_ttl" name="cache_min_ttl" size="5" value="<?= $pconfig['cache_min_ttl'] ?>" />
                          <div class="hidden" data-for="help_for_cache_min_ttl">
                            <?= gettext("Configure a minimum Time to live for RRsets and messages in the cache. The default is 0 seconds. If the minimum value kicks in, the data is cached for longer than the domain owner intended, and thus less queries are made to look up the data. The 0 value ensures the data in the cache is as the domain owner intended. High values can lead to trouble as the data in the cache might not match up with the actual data anymore.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_infra_host_ttl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("TTL for Host cache entries") ?></td>
                        <td>
                          <select id="infra_host_ttl" name="infra_host_ttl" class="selectpicker">
                            <option value="60"  <?=$pconfig['infra_host_ttl'] == "60" ? "selected=\"selected\"" : ""; ?>><?=gettext('1 minute') ?></option>
                            <option value="120" <?=$pconfig['infra_host_ttl'] == "120" ? "selected=\"selected\"" : ""; ?>><?=gettext('2 minutes') ?></option>
                            <option value="300" <?=$pconfig['infra_host_ttl'] == "300" ? "selected=\"selected\"" : ""; ?>><?=gettext('5 minutes') ?></option>
                            <option value="600" <?=$pconfig['infra_host_ttl'] == "600" ? "selected=\"selected\"" : ""; ?>><?=gettext('10 minutes') ?></option>
                            <option value="900" <?=$pconfig['infra_host_ttl'] == "900" ? "selected=\"selected\"" : ""; ?>><?=gettext('15 minutes') ?></option>
                          </select>
                          <div class="hidden" data-for="help_for_infra_host_ttl">
                            <?=gettext("Time to live for entries in the host cache. The host cache contains roundtrip timing and EDNS support information. The default is 15 minutes.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_infra_cache_numhosts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Number of Hosts to cache");?></td>
                        <td>
                          <select id="infra_cache_numhosts" name="infra_cache_numhosts" class="selectpicker">
                            <option value="1000"  <?=$pconfig['infra_cache_numhosts'] == "1000" ?  "selected=\"selected\"" : ""; ?>>1000</option>
                            <option value="5000"  <?=$pconfig['infra_cache_numhosts'] == "5000" ? "selected=\"selected\"" : ""; ?>>5000</option>
                            <option value="10000" <?=$pconfig['infra_cache_numhosts'] == "10000" ? "selected=\"selected\"" : ""; ?>>10000</option>
                            <option value="20000" <?=$pconfig['infra_cache_numhosts'] == "20000" ? "selected=\"selected\"" : ""; ?>>20000</option>
                            <option value="50000" <?=$pconfig['infra_cache_numhosts'] == "50000" ? "selected=\"selected\"" : ""; ?>>50000</option>
                          </select>
                          <div class="hidden" data-for="help_for_infra_cache_numhosts">
                            <?= gettext("Number of hosts for which information is cached. The default is 10000.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_unwanted_reply_threshold" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Unwanted Reply Threshold");?></td>
                        <td>
                          <select id="unwanted_reply_threshold" name="unwanted_reply_threshold" class="selectpicker">
                            <option value="" <?=empty($pconfig['unwanted_reply_threshold']) ? "selected=\"selected\"" : ""; ?>><?=gettext('disabled') ?></option>
                            <option value="5000000"  <?=$pconfig['unwanted_reply_threshold'] == "5000000" ? "selected=\"selected\"" : ""; ?>><?=gettext('5 million') ?></option>
                            <option value="10000000" <?=$pconfig['unwanted_reply_threshold'] == "10000000" ? "selected=\"selected\"" : ""; ?>><?=gettext('10 million') ?></option>
                            <option value="20000000" <?=$pconfig['unwanted_reply_threshold'] == "20000000" ? "selected=\"selected\"" : ""; ?>><?=gettext('20 million') ?></option>
                            <option value="40000000" <?=$pconfig['unwanted_reply_threshold'] == "40000000" ? "selected=\"selected\"" : ""; ?>><?=gettext('40 million') ?></option>
                            <option value="50000000" <?=$pconfig['unwanted_reply_threshold'] == "50000000" ? "selected=\"selected\"" : ""; ?>><?=gettext('50 million') ?></option>
                          </select>
                          <div class="hidden" data-for="help_for_unwanted_reply_threshold">
                            <?= gettext("If enabled, a total number of unwanted replies is kept track of in every thread. When it reaches the threshold, a defensive action is taken and a warning is printed to the log file. This defensive action is to clear the RRSet and message caches, hopefully flushing away any poison. The default is disabled, but if enabled a value of 10 million is suggested.") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_log_verbosity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Log level verbosity");?></td>
                        <td>
                          <select id="log_verbosity" name="log_verbosity" class="selectpicker">
<?php
                          for ($level = 0; $level <= 5; $level++):?>
                            <option value="<?= $level; ?>" <?=$pconfig['log_verbosity'] == $level ? 'selected="selected"' : ''; ?>>
                              <?= sprintf(gettext("Level %s"), $level) ?>
                            </option>
<?php
                          endfor;?>
                          </select>
                          <div class="hidden" data-for="help_for_log_verbosity">
                            <?= gettext("Select the log verbosity. Level 0 means no verbosity, only errors. Level 1 gives operational information. Level 2 gives detailed operational information. Level 3 gives query level information, output per query. Level 4 gives algorithm level information. Level 5 logs client identification for cache misses. Default is level 1. ") ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                          <td><a id="help_for_extended_statistics" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Extended statistics') ?></td>
                          <td>
                              <input name="extended_statistics" type="checkbox" id="extended_statistics" value="yes" <?= empty($pconfig['extended_statistics']) ? '' : 'checked="checked"' ?> />
                              <div class="hidden" data-for="help_for_extended_statistics">
                                  <?= gettext("If enabled, extended statistics are printed.") ?>
                              </div>
                          </td>
                      </tr>
                      <tr>
                          <td><a id="help_for_log_queries" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Log Queries') ?></td>
                          <td>
                              <input name="log_queries" type="checkbox" id="log_queries" value="yes" <?= empty($pconfig['log_queries']) ? '' : 'checked="checked"' ?> />
                              <div class="hidden" data-for="help_for_log_queries">
                                  <?= gettext("If enabled, prints one line per query to the log, with the log timestamp and IP address, name, type and class.") ?>
                              </div>
                          </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <button type="submit" name="Save" class="btn btn-primary" id="save" value="Save"><?= gettext("Save") ?></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
