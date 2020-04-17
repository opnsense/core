<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2014-2015 Jos Schellevis <jos@opnsense.org>
 * Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
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
require_once("interfaces.inc");

function conv_log_interface_names()
{
    global $config;

    // collect interface names
    $interface_names = array();
    $interface_names['enc0'] = gettext("IPsec");

    if (!empty($config['interfaces'])) {
        foreach (legacy_config_get_interfaces(array("virtual" => false)) as $intfkey => $interface) {
            $interface_names[$interface['if']] = !empty($interface['descr']) ? $interface['descr'] : $intfkey;
        }
    }
    return $interface_names;
}

/* format filter logs */
function conv_log_filter($logfile, $nentries, $tail = 50, $filtertext = '', $filterinterface = null)
{
    global $config;

    /* Make sure this is a number before using it in a system call */
    if (!(is_numeric($tail))) {
        return;
    }

    if ($filtertext!=""){
        $tail = 5000;
    }

    /* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
    $logarr = [];

    exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/grep 'filterlog.*:' | /usr/bin/tail -r -n {$tail}", $logarr);

    $filterlog = array();
    $counter = 0;
    $interface_names = conv_log_interface_names();
    foreach ($logarr as $logent) {
        if ($counter >= $nentries) {
            break;
        }

        $flent = parse_filter_line($logent, $interface_names);
        if (isset($flent) && is_array($flent)) {
            if ($filterinterface == null || strtoupper($filterinterface) == $flent['interface']) {
                if ( (!is_array($filtertext) && match_filter_line ($flent, $filtertext)) ||
                  ( is_array($filtertext) && match_filter_field($flent, $filtertext))
                ) {
                    $counter++;
                    $filterlog[] = $flent;
                }
            }
        }
    }
    /* Since the lines are in reverse order, flip them around if needed based on the user's preference */
    return isset($config['syslog']['reverse']) ? $filterlog : array_reverse($filterlog);
}

function escape_filter_regex($filtertext)
{
    /* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
    /* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
    return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function match_filter_line($flent, $filtertext = "")
{
    if (!$filtertext) {
        return true;
    }
    $filtertext = escape_filter_regex(str_replace(' ', '\s+', $filtertext));
    return @preg_match("/{$filtertext}/i", implode(" ", array_values($flent)));
}

function match_filter_field($flent, $fields) {
    foreach ($fields as $key => $field) {
        if ($field == "All") {
            continue;
        }
        if ((strpos($field, '!') === 0)) {
            $field = substr($field, 1);
            if (strtolower($key) == 'act') {
                if (in_arrayi($flent[$key], explode(" ", $field))) {
                    return false;
                }
            } else {
                $field_regex = escape_filter_regex($field);
                if (@preg_match("/{$field_regex}/i", $flent[$key])) {
                    return false;
                }
            }
        } else {
            if (strtolower($key) == 'act') {
                if (!in_arrayi($flent[$key], explode(" ", $field))) {
                    return false;
                }
            } else {
                $field_regex = escape_filter_regex($field);
                if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
                    return false;
                }
            }
        }
    }
    return true;
}

// Case Insensitive in_array function
function in_arrayi($needle, $haystack)
{
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function parse_filter_line($line, $interface_names = array())
{
    $flent = array();
    $log_split = '';

    if (!preg_match('/(.*)\s(.*)\sfilterlog.*:\s(.*)$/', $line, $log_split)) {
        return '';
    }

    list($all, $flent['time'], $host, $rule) = $log_split;

    if (trim($flent['time']) == '') {
        log_error(sprintf('There was an error parsing a rule: no time (%s)', $log_split));
        return '';
    }

    $rule_data = explode(',', $rule);
    $field = 0;

    $flent['rulenum'] = $rule_data[$field++];
    $flent['subrulenum'] = $rule_data[$field++];
    $flent['anchor'] = $rule_data[$field++];
    $field++; // skip field
    $flent['realint'] = $rule_data[$field++];
    $flent['interface']  = !empty($interface_names[$flent['realint']]) ? $interface_names[$flent['realint']] : $flent['realint'] ;
    $flent['reason'] = $rule_data[$field++];
    $flent['act'] = $rule_data[$field++];
    $flent['direction'] = $rule_data[$field++];
    $flent['version'] = $rule_data[$field++];

    if ($flent['version'] != '4' && $flent['version'] != '6') {
        log_error(sprintf(
          gettext('There was an error parsing rule number: %s -- not IPv4 or IPv6 (`%s\')'),
          $flent['rulenum'],
          $rule
        ));
        return '';
    }

    if ($flent['version'] == '4') {
        $flent['tos'] = $rule_data[$field++];
        $flent['ecn'] = $rule_data[$field++];
        $flent['ttl'] = $rule_data[$field++];
        $flent['id'] = $rule_data[$field++];
        $flent['offset'] = $rule_data[$field++];
        $flent['flags'] = $rule_data[$field++];
        $flent['protoid'] = $rule_data[$field++];
        $flent['proto'] = strtoupper($rule_data[$field++]);
    } else {
        $flent['class'] = $rule_data[$field++];
        $flent['flowlabel'] = $rule_data[$field++];
        $flent['hlim'] = $rule_data[$field++];
        $flent['proto'] = strtoupper($rule_data[$field++]);
        $flent['protoid'] = $rule_data[$field++];
    }

    $flent['length'] = $rule_data[$field++];
    $flent['srcip'] = $rule_data[$field++];
    $flent['dstip'] = $rule_data[$field++];

    /* bootstrap src and dst for non-port protocols */
    $flent['src'] = $flent['srcip'];
    $flent['dst'] = $flent['dstip'];

    if (trim($flent['src']) == '' || trim($flent['dst']) == '') {
        log_error(sprintf(
          gettext('There was an error parsing rule number: %s -- no src or dst (`%s\')'),
          $flent['rulenum'],
          $rule
        ));
        return '';
    }

    if ($flent['protoid'] == '6' || $flent['protoid'] == '17') { // TCP or UDP
        $flent['srcport'] = $rule_data[$field++];
        $flent['dstport'] = $rule_data[$field++];

        $flent['src'] = $flent['srcip'] . ':' . $flent['srcport'];
        $flent['dst'] = $flent['dstip'] . ':' . $flent['dstport'];

        $flent['datalen'] = $rule_data[$field++];
        if ($flent['protoid'] == '6') { // TCP
            $flent['tcpflags'] = $rule_data[$field++];
            $flent['seq'] = $rule_data[$field++];
            $flent['ack'] = $rule_data[$field++];
            $flent['window'] = $rule_data[$field++];
            $flent['urg'] = $rule_data[$field++];
            $flent['options'] = explode(";",$rule_data[$field++]);
        }
    } elseif ($flent['protoid'] == '1') { // ICMP
        $flent['icmp_type'] = $rule_data[$field++];
        switch ($flent['icmp_type']) {
            case 'request':
            case 'reply':
                $flent['icmp_id'] = $rule_data[$field++];
                $flent['icmp_seq'] = $rule_data[$field++];
                break;
            case 'unreachproto':
                $flent['icmp_dstip'] = $rule_data[$field++];
                $flent['icmp_protoid'] = $rule_data[$field++];
                break;
            case 'unreachport':
                $flent['icmp_dstip'] = $rule_data[$field++];
                $flent['icmp_protoid'] = $rule_data[$field++];
                $flent['icmp_port'] = $rule_data[$field++];
                break;
            case 'unreach':
            case 'timexceed':
            case 'paramprob':
            case 'redirect':
            case 'maskreply':
                $flent['icmp_descr'] = $rule_data[$field++];
                break;
            case 'needfrag':
                $flent['icmp_dstip'] = $rule_data[$field++];
                $flent['icmp_mtu'] = $rule_data[$field++];
                break;
            case 'tstamp':
                $flent['icmp_id'] = $rule_data[$field++];
                $flent['icmp_seq'] = $rule_data[$field++];
                break;
            case 'tstampreply':
                $flent['icmp_id'] = $rule_data[$field++];
                $flent['icmp_seq'] = $rule_data[$field++];
                $flent['icmp_otime'] = $rule_data[$field++];
                $flent['icmp_rtime'] = $rule_data[$field++];
                $flent['icmp_ttime'] = $rule_data[$field++];
                break;
            default :
                if (isset($rule_data[$field++])) {
                    $flent['icmp_descr'] = $rule_data[$field++];
                }
                break;
        }
    } elseif ($flent['protoid'] == '2') { // IGMP
        $flent['src'] = $flent['srcip'];
        $flent['dst'] = $flent['dstip'];
    } elseif ($flent['protoid'] == '112') { // CARP
        $flent['type'] = $rule_data[$field++];
        $flent['ttl'] = $rule_data[$field++];
        $flent['vhid'] = $rule_data[$field++];
        $flent['version'] = $rule_data[$field++];
        $flent['advskew'] = $rule_data[$field++];
        $flent['advbase'] = $rule_data[$field++];
    }

    return $flent;
}

$filter_logfile = '/var/log/filter.log';
$lines = 5000; // Maximum number of log entries to fetch
$entriesperblock = 10; // Maximum elements to show individually

// flush log file
if (!empty($_POST['clear'])) {
    system_clear_clog($filter_logfile);
}

// Retrieve filter log data
$filterlog = conv_log_filter($filter_logfile, $lines, $lines);
// Set total retrieved line counter
$gotlines = count($filterlog);
// Set readable fieldnames
$fields = array(
  'act'       => gettext("Actions"),
  'interface' => gettext("Interfaces"),
  'proto'     => gettext("Protocols"),
  'srcip'     => gettext("Source IPs"),
  'dstip'     => gettext("Destination IPs"),
  'srcport'   => gettext("Source Ports"),
  'dstport'   => gettext("Destination Ports"));

$summary = array();

foreach (array_keys($fields) as $f) {
  $summary[$f]  = array();
}

// Fill summary array with filterlog data
foreach ($filterlog as $fe) {
  foreach (array_keys($fields) as $field) {
    if (isset($fe[$field])) {
      if (!isset($summary[$field])) {
        $summary[$field] = array();
      }
      if (!isset($summary[$field][$fe[$field]])) {
        $summary[$field][$fe[$field]] = 0;
      }
      $summary[$field][$fe[$field]]++;
    }
  }
}

// Setup full data array for pie and table
function d3pie_data($summary, $num) {
  $data=array();
  foreach (array_keys($summary) as $stat) {
      uasort($summary[$stat], function ($a, $b) {
          if ($a == $b) {
              return 0;
          }
          return ($a < $b) ? 1 : -1;
      });

      $other=0;
      foreach(array_keys($summary[$stat]) as $key) {

          if (!isset($data[$stat])) {
            $data[$stat] = array();
          }
          if ( count($data[$stat]) < $num ) {
          $data[$stat][] = array('label' => $key, 'value' => $summary[$stat][$key]);
        } else {
          $other+=$summary[$stat][$key];
        }
      }
      if ($other > 0) {
        $data[$stat][] = array('label' => gettext("other"), 'value' => $other);
      }
  }

  return $data;
}

include("head.inc"); ?>
<body>

<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php print_service_banner('firewall'); ?>
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td>
                    <strong><?= sprintf(gettext('The following summaries have been collected from the last %s lines of the firewall log (maximum is %s).'), $gotlines, $lines)?></strong>
                  </td>
                  <td>
                    <form method="post">
                      <div class="pull-right">
                        <input name="clear" type="submit" class="btn" value="<?= html_safe(gettext('Clear log')) ?>" />
                      </div>
                    </form>
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </section>

        <section class="col-xs-12">
          <!-- retrieve full dataset for pie and table -->
          <?php $data=d3pie_data($summary, $entriesperblock) ?>
          <!-- iterate items and create pie placeholder + tabledata -->
          <?php foreach(array_keys($fields) as $field): ?>
          <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?=$fields[$field]?></h3></div>
            <div class="panel-body">
              <div class="piechart" id="<?=$field?>">
                <svg></svg>
              </div>
              <table class="table table-striped table-bordered">
                <tr>
                  <th><?=$fields[$field]?></th>
                  <th><?=gettext("Count");?></th>
                </tr>
                <?php if (isset($data[$field])):?>
                <?php foreach(array_keys($data[$field]) as $row): ?>
                <tr>
                  <td>
                    <?php if (is_ipaddr($data[$field][$row]["label"])): ?>
                      <a href="diag_dns.php?host=<?=$data[$field][$row]["label"]?>" title="<?=gettext("Reverse Resolve with DNS");?>"><i class="fa fa-search"></i></a>
                    <?php endif ?>
                    <?=$data[$field][$row]["label"]?></td>
                  <td><?=$data[$field][$row]["value"]?></td>
                </tr>
              <?php endforeach ?>
              <?php endif; ?>
              </table>
            </div>
          </div>
          <?php endforeach ?>
        </section>
      </div>
    </div>
  </section>

<script>
    // Generate Donut charts

    nv.addGraph(function() {
      // Find all piechart classes to insert the chart
      $('div[class="piechart"]').each(function(){
        var selected_id = $(this).prop("id");
        var chart = nv.models.pieChart()
            .x(function(d) { return d.label })
            .y(function(d) { return d.value })
            .showLabels(true)     //Display pie labels
            .labelThreshold(.05)  //Configure the minimum slice size for labels to show up
            .labelType("percent") //Configure what type of data to show in the label. Can be "key", "value" or "percent"
            .donut(true)          //Turn on Donut mode. Makes pie chart look tasty!
            .donutRatio(0.2)     //Configure how big you want the donut hole size to be.
            ;

          d3.select("[id='"+ selected_id + "'].piechart svg")
              .datum(<?= json_encode($data) ?>[selected_id])
              .transition().duration(350)
              .call(chart);

          // Update Chart after window resize
          nv.utils.windowResize(function(){ chart.update(); });

        return chart;
    });
});

</script>

<style>
  .piechart svg {
    height: 400px;
  }
</style>

<?php

include("foot.inc");
