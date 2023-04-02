<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2010 Ermal Luçi
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
require_once("filter.inc");
require_once("system.inc");
require_once("interfaces.inc");

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 *   (or if $bits is specified, where an IP within the subnet is defined)
 */
function find_ip_interface($ip, $bits = null)
{
    if (!is_ipaddr($ip))
        return false;

    $isv6ip = is_ipaddrv6($ip);

    foreach (array_keys(get_configured_interface_with_descr()) as $ifname) {
        $ifip = ($isv6ip) ? get_interface_ipv6($ifname) : get_interface_ip($ifname);
        if (is_null($ifip))
            continue;
        if (is_null($bits)) {
            if ($ip == $ifip) {
                $int = get_real_interface($ifname);
                return $int;
            }
        } else {
            if (ip_in_subnet($ifip, $ip . "/" . $bits)) {
                $int = get_real_interface($ifname);
                return $int;
            }
        }
    }

    return false;
}


$stepid = '0';

if (isset($_POST['stepid'])) {
    $stepid = htmlspecialchars($_POST['stepid']);
} elseif (isset($_GET['stepid'])) {
    $stepid = htmlspecialchars($_GET['stepid']);
}

$xml = '';
if (isset($_GET['xml'])) {
    $xml = htmlspecialchars($_GET['xml']);
} elseif (isset($_POST['xml'])) {
    $xml = htmlspecialchars($_POST['xml']);
}

switch ($xml) {
    case 'openvpn':
    case 'system':
        break;
    default:
        print_info_box(gettext('ERROR: Could not find wizard file.'));
        die;
}

$listtags = array_flip(array(
    'additional_files_needed',
    'alias',
    'build_port_path',
    'columnitem',
    'depends_on_package',
    'field',
    'file',
    'item',
    'menu',
    'onetoone',
    'option',
    'package',
    'package',
    'queue',
    'rowhelperfield',
    'rule',
    'servernat',
    'service',
    'step',
    'tab',
    'template',
));

$pkg = parse_xml_config_raw("/usr/local/wizard/{$xml}.xml", 'wizard', false);
if (!is_array($pkg)) {
    print_info_box(sprintf(gettext("ERROR: Could not parse %s wizard file."), $xml));
    die;
}

$totalsteps = $pkg['totalsteps'];

if ($pkg['includefile']) {
    require_once($pkg['includefile']);
}

if ($pkg['step'][$stepid]['stepsubmitbeforesave']) {
    eval($pkg['step'][$stepid]['stepsubmitbeforesave']);
}

if ($_POST && !$input_errors) {
    foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
        if (!empty($field['bindstofield']) && $field['type'] != 'submit') {
            $fieldname = $field['name'];
            $fieldname = str_replace(' ', '', $fieldname);
            $fieldname = strtolower($fieldname);
            // update field with posted values.
            if ($field['unsetfield'] != '')
                $unset_fields = 'yes';
            else
                $unset_fields = '';
            if ($field['arraynum'] != '')
                $arraynum = $field['arraynum'];
            else
                $arraynum = '';

            update_config_field($field['bindstofield'], $_POST[$fieldname], $unset_fields, $arraynum, $field['type']);
        }

    }

    // run custom php code embedded in xml config.
    if ($pkg['step'][$stepid]['stepsubmitphpaction'] != '') {
        eval($pkg['step'][$stepid]['stepsubmitphpaction']);
    }

    if (!$input_errors) {
        write_config();
    }

    $stepid++;

    if ($stepid > $totalsteps) {
        $stepid = $totalsteps;
    }
}

$extraBreadcrumb = $pkg['step'][$stepid]['title'];

function update_config_field($field, $updatetext, $unset, $arraynum, $field_type)
{
    global $config;
    $field_split = explode('->', $field);
    $field_conv = '';
    foreach ($field_split as $f)
        $field_conv .= "['" . $f . "']";
    if ($field_conv == '')
        return;
    if ($arraynum != '')
        $field_conv .= "[" . $arraynum . "]";
    if (($field_type == 'checkbox' && $updatetext != '1') || $updatetext == '') {
        /*
         * item is a checkbox, it should have the value "1"
         * if it was checked
         */
        $var = "\$config{$field_conv}";
        $text = "if (isset({$var})) unset({$var});";
        eval($text);
        return;
    }

    if ($field_type == "interfaces_selection") {
        $var = "\$config{$field_conv}";
        $text = "if (isset({$var})) unset({$var});";
        $text .= "\$config" . $field_conv . " = '" . addslashes($updatetext) . "';";
        eval($text);
        return;
    }

    if ($unset == "yes") {
        $text = "unset(\$config" . $field_conv . ");";
        eval($text);
    }
    $text = "\$config" . $field_conv . " = '" . addslashes($updatetext) . "';";
    eval($text);
}

function redirect_url()
{
    global $config, $title;
    $urlport = '';

    switch ($config['system']['webgui']['protocol']) {
        case 'http':
            $proto = 'http';
            break;
        case 'https':
            $proto = 'https';
            break;
        default:
            $proto = 'http';
            break;
    }
    $port = $config['system']['webgui']['port'];
    if ($port != '') {
        if (($port == '443' && $proto != 'https') || ($port == '80' && $proto != 'http')) {
            $urlport = ':' . $port;
        } elseif ($port != '80' && $port != '443') {
            $urlport = ':' . $port;
        }
    }
    $http_host = $_SERVER['SERVER_NAME'];
    $urlhost = $http_host;
    // If finishing the setup wizard, check if accessing on a LAN or WAN address that changed
    if ($title == 'Reload in progress') {
        if (is_ipaddr($urlhost)) {
            $host_if = find_ip_interface($urlhost);
            if ($host_if) {
                $host_if = convert_real_interface_to_friendly_interface_name($host_if);
                if ($host_if && is_ipaddr($config['interfaces'][$host_if]['ipaddr']))
                    $urlhost = $config['interfaces'][$host_if]['ipaddr'];
            }
        } else if ($urlhost == $config['system']['hostname']) {
            $urlhost = $config['wizardtemp']['system']['hostname'];
        } else if ($urlhost == $config['system']['hostname'] . '.' . $config['system']['domain']) {
            $urlhost = $config['wizardtemp']['system']['hostname'] . '.' . $config['wizardtemp']['system']['domain'];
        }
    }

    return $proto . '://' . $urlhost . $urlport;
}

// handle before form display event.
do {
    $oldstepid = $stepid;
    if ($pkg['step'][$stepid]['stepbeforeformdisplay'] != '') {
        eval($pkg['step'][$stepid]['stepbeforeformdisplay']);
    }
} while ($oldstepid != $stepid);


include("head.inc");

?>
    <body>
<script src="/javascript/wizard/autosuggest.js"></script>
<script src="/javascript/wizard/disablekeys.js"></script>
<script src="/javascript/wizard/suggestions.js"></script>

<?php include("fbegin.inc"); ?>

<?php if ($pkg['step'][$stepid]['fields']['field'] != '') { ?>
    <script>
        //<![CDATA[

        function FieldValidate(userinput, regexp, message) {
            if (!userinput.match(regexp))
                alert(message);
        }

        function enablechange() {
            <?php
            foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
                if (isset($field['enablefields']) || isset($field['checkenablefields'])) {
                    print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked) {' . "\n";
                    if (isset($field['enablefields'])) {
                        $enablefields = explode(',', $field['enablefields']);
                        foreach ($enablefields as $enablefield) {
                            $enablefield = strtolower($enablefield);
                            print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 0;' . "\n";
                        }
                    }
                    if (isset($field['checkenablefields'])) {
                        $checkenablefields = explode(',', $field['checkenablefields']);
                        foreach ($checkenablefields as $checkenablefield) {
                            $checkenablefield = strtolower($checkenablefield);
                            print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 0;' . "\n";
                        }
                    }
                    print "\t" . '} else {' . "\n";
                    if (isset($field['enablefields'])) {
                        $enablefields = explode(',', $field['enablefields']);
                        foreach ($enablefields as $enablefield) {
                            $enablefield = strtolower($enablefield);
                            print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 1;' . "\n";
                        }
                    }
                    if (isset($field['checkenablefields'])) {
                        $checkenablefields = explode(',', $field['checkenablefields']);
                        foreach ($checkenablefields as $checkenablefield) {
                            $checkenablefield = strtolower($checkenablefield);
                            print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 1;' . "\n";
                        }
                    }
                    print "\t" . '}' . "\n";
                }
            }
            ?>
        }

        function disablechange() {
            <?php
            foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
                if (isset($field['disablefields']) || isset($field['checkdisablefields'])) {
                    print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked) {' . "\n";
                    if (isset($field['disablefields'])) {
                        $enablefields = explode(',', $field['disablefields']);
                        foreach ($enablefields as $enablefield) {
                            $enablefield = strtolower($enablefield);
                            print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 1;' . "\n";
                        }
                    }
                    if (isset($field['checkdisablefields'])) {
                        $checkenablefields = explode(',', $field['checkdisablefields']);
                        foreach ($checkenablefields as $checkenablefield) {
                            $checkenablefield = strtolower($checkenablefield);
                            print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 1;' . "\n";
                        }
                    }
                    print "\t" . '} else {' . "\n";
                    if (isset($field['disablefields'])) {
                        $enablefields = explode(',', $field['disablefields']);
                        foreach ($enablefields as $enablefield) {
                            $enablefield = strtolower($enablefield);
                            print "\t\t" . 'document.iform.' . $enablefield . '.disabled = 0;' . "\n";
                        }
                    }
                    if (isset($field['checkdisablefields'])) {
                        $checkenablefields = explode(',', $field['checkdisablefields']);
                        foreach ($checkenablefields as $checkenablefield) {
                            $checkenablefield = strtolower($checkenablefield);
                            print "\t\t" . 'document.iform.' . $checkenablefield . '.checked = 0;' . "\n";
                        }
                    }
                    print "\t" . '}' . "\n";
                }
            }
            ?>
        }

        function showchange() {
            <?php
            foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
                if (isset($field['showfields'])) {
                    print "\t" . 'if (document.iform.' . strtolower($field['name']) . '.checked == false) {' . "\n";
                    if (isset($field['showfields'])) {
                        $showfields = explode(',', $field['showfields']);
                        foreach ($showfields as $showfield) {
                            $showfield = strtolower($showfield);
                            //print "\t\t" . 'document.iform.' . $showfield . ".display =\"none\";\n";
                            print "\t\t jQuery('#" . $showfield . "').hide();";
                        }
                    }
                    print "\t" . '} else {' . "\n";
                    if (isset($field['showfields'])) {
                        $showfields = explode(',', $field['showfields']);
                        foreach ($showfields as $showfield) {
                            $showfield = strtolower($showfield);
                            #print "\t\t" . 'document.iform.' . $showfield . ".display =\"\";\n";
                            print "\t\t jQuery('#" . $showfield . "').show();";
                        }
                    }
                    print "\t" . '}' . "\n";
                }
            }
            ?>
        }

        //]]>
    </script>
<?php } ?>

<section class="page-content-main">
    <div class="container-fluid">

        <?php
        if (isset($input_errors) && count($input_errors) > 0)
            print_input_errors($input_errors);
        if (isset($savemsg))
            print_info_box($savemsg);
        if ($_GET['message'] != '')
            print_info_box(htmlspecialchars($_GET['message']));
        if ($_POST['message'] != '')
            print_info_box(htmlspecialchars($_POST['message']));
        ?>

        <section class="col-xs-12">
            <div class="content-box">

                <form method="post" name="iform" id="iform">
                    <input type="hidden" name="xml" value="<?= htmlspecialchars($xml) ?>"/>
                    <input type="hidden" name="stepid" value="<?= htmlspecialchars($stepid) ?>"/>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <?php
                            $inputaliases = array();
                            if ($pkg['step'][$stepid]['fields']['field'] != '') {
                                foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {

                                    $value = $field['value'];
                                    $name = $field['name'];

                                    $name = preg_replace("/\s+/", "", $name);
                                    $name = strtolower($name);

                                    if (!empty($field['bindstofield']) && !isset($field['value'])) {
                                        $arraynum = '';
                                        $field_conv = '';
                                        $field_split = explode("->", $field['bindstofield']);
                                        // arraynum is used in cases where there is an array of the same field
                                        // name such as dnsserver (2 of them)
                                        if ($field['arraynum'] != '')
                                            $arraynum = "[" . $field['arraynum'] . "]";
                                        foreach ($field_split as $f)
                                            $field_conv .= "['" . $f . "']";
                                        if ($field['type'] == "checkbox")
                                            $toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) { \$value = \$config" . $field_conv . $arraynum . "; if (empty(\$value)) \$value = true; }";
                                        else
                                            $toeval = "if (isset(\$config" . $field_conv . $arraynum . ")) \$value = \$config" . $field_conv . $arraynum . ";";
                                        eval($toeval);
                                    }

                                    if (!$field['combinefieldsend']) {
                                        echo "<tr>";
                                    }

                                    switch ($field['type']) {
                                        case "input":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>\n";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">\n";

                                            echo "<input class='form-control unknown' type='text' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\"";
                                            if ($field['size'])
                                                echo " size='" . $field['size'] . "' ";
                                            if ($field['validate'])
                                                echo " onchange='FieldValidate(this.value, \"{$field['validate']}\", \"{$field['message']}\");'";
                                            echo " />\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }
                                            break;
                                        case "text":
                                            echo "<td colspan=\"2\" style=\"text-align:center\">\n";
                                            if ($field['description'] != '') {
                                                echo "<div class='text-center'><br /> " . gettext($field['description']) . "</div>";
                                            }
                                            break;
                                        case "inputalias":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>\n";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">\n";

                                            $inputaliases[] = $name;
                                            echo "<input class='form-control alias' autocomplete='off' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\"";
                                            if ($field['size'])
                                                echo " size='" . $field['size'] . "' ";
                                            if ($field['validate'])
                                                echo " onchange='FieldValidate(this.value, \"{$field['validate']}\", \"{$field['message']}\");'";
                                            echo " />\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }
                                            break;
                                        case "interfaces_selection":
                                            $size = '';
                                            $multiple = '';
                                            $name = strtolower($name);
                                            echo "<td style=\"width:22%; text-align:right\">\n";
                                            echo ($field['displayname'] ? gettext($field['displayname']) : gettext($field['name'])) . ":\n";
                                            echo "</td>";
                                            echo "<td class=\"vtable\">\n";
                                            if ($field['size'] != '') $size = "size=\"{$field['size']}\"";
                                            if ($field['multiple'] != '' and $field['multiple'] != '0') {
                                                $multiple = "multiple=\"multiple\"";
                                                $name .= "[]";
                                            }
                                            echo "<select class='form-control' id='{$name}' name='{$name}' {$size} {$multiple}>\n";
                                            if ($field['add_to_interfaces_selection'] != '') {
                                                $SELECTED = '';
                                                if ($field['add_to_interfaces_selection'] == $value) $SELECTED = " selected=\"selected\"";
                                                echo "<option value='" . $field['add_to_interfaces_selection'] . "'" . $SELECTED . ">" . $field['add_to_interfaces_selection'] . "</option>\n";
                                            }
                                            $interfaces = get_configured_interface_with_descr();
                                            if (!empty($field['subtype']) && $field['subtype'] == 'openvpn') {
                                                $interfaces['lo0'] = 'Localhost';
                                                $interfaces['any'] = 'any';
                                            }
                                            foreach ($interfaces as $ifname => $iface) {
                                                $SELECTED = '';
                                                if ($value == $ifname) $SELECTED = " selected=\"selected\"";
                                                $to_echo = "<option value='" . $ifname . "'" . $SELECTED . ">" . $iface . "</option>\n";
                                                $to_echo .= "<!-- {$value} -->";
                                                $canecho = 0;
                                                if ($field['interface_filter'] != '') {
                                                    if (stristr($ifname, $field['interface_filter']) == true)
                                                        $canecho = 1;
                                                } else
                                                    $canecho = 1;
                                                if ($canecho == 1)
                                                    echo $to_echo;
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "password":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>\n";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">";
                                            echo "<input class='form-control pwd' id='" . $name . "' name='" . $name . "' value=\"" . htmlspecialchars($value) . "\" type='password' ";
                                            if ($field['size'])
                                                echo " size='" . $field['size'] . "' ";
                                            echo " />\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "certca_selection":
                                            $size = '';
                                            $multiple = '';
                                            $name = strtolower($name);
                                            echo "<td style=\"width:22%; text-align:right\">\n";
                                            echo ($field['displayname'] ? gettext($field['displayname']) : gettext($field['name'])) . ":\n";
                                            echo "</td>";
                                            echo "<td class=\"vtable\">\n";
                                            if ($field['size'] != '') $size = "size=\"{$field['size']}\"";
                                            echo "<select id='{$name}' name='{$name}' {$size}>\n";
                                            if ($field['add_to_certca_selection'] != '') {
                                                $SELECTED = '';
                                                if ($field['add_to_certca_selection'] == $value) $SELECTED = " selected=\"selected\"";
                                                echo "<option value='" . $field['add_to_certca_selection'] . "'" . $SELECTED . ">" . $field['add_to_certca_selection'] . "</option>\n";
                                            }
                                            foreach ($config['ca'] as $ca) {
                                                if (!empty($field['internal']) && empty($ca['prv'])) {
                                                    continue;
                                                }
                                                $name = htmlspecialchars($ca['descr']);
                                                $SELECTED = '';
                                                if ($value == $name) $SELECTED = " selected=\"selected\"";
                                                $to_echo = "<option value='" . $ca['refid'] . "'" . $SELECTED . ">" . $name . "</option>\n";
                                                $to_echo .= "<!-- {$value} -->";
                                                $canecho = 0;
                                                if ($field['certca_filter'] != '') {
                                                    if (stristr($name, $field['certca_filter']) == true)
                                                        $canecho = 1;
                                                } else {
                                                    $canecho = 1;
                                                }
                                                if ($canecho == 1)
                                                    echo $to_echo;
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "cert_selection":
                                            $size = '';
                                            $multiple = '';
                                            $name = strtolower($name);
                                            echo "<td style=\"width:22%; text-align:right\">\n";
                                            echo ($field['displayname'] ? gettext($field['displayname']) : gettext($field['name'])) . ":\n";
                                            echo "</td>";
                                            echo "<td class=\"vtable\">\n";
                                            if ($field['size'] != '') $size = "size=\"{$field['size']}\"";
                                            echo "<select id='{$name}' name='{$name}' {$size}>\n";
                                            if ($field['add_to_cert_selection'] != '') {
                                                $SELECTED = '';
                                                if ($field['add_to_cert_selection'] == $value) $SELECTED = " selected=\"selected\"";
                                                echo "<option value='" . $field['add_to_cert_selection'] . "'" . $SELECTED . ">" . $field['add_to_cert_selection'] . "</option>\n";
                                            }
                                            foreach ($config['cert'] as $ca) {
                                                if (cert_get_purpose($ca['crt'])['server'] == 'No')
                                                    continue;
                                                $name = htmlspecialchars($ca['descr']);
                                                $SELECTED = '';
                                                if ($value == $name) $SELECTED = " selected=\"selected\"";
                                                $to_echo = "<option value='" . $ca['refid'] . "'" . $SELECTED . ">" . $name . "</option>\n";
                                                $to_echo .= "<!-- {$value} -->";
                                                $canecho = 0;
                                                if ($field['cert_filter'] != '') {
                                                    if (stristr($name, $field['cert_filter']) == true)
                                                        $canecho = 1;
                                                } else {
                                                    $canecho = 1;
                                                }
                                                if ($canecho == 1)
                                                    echo $to_echo;
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "select":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>\n";
                                            }
                                            if ($field['size']) $size = " size='" . $field['size'] . "' ";
                                            if ($field['multiple'] == "yes") $multiple = "multiple=\"multiple\" ";
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">\n";
                                            $onchange = '';
                                            foreach ($field['options']['option'] as $opt) {
                                                if ($opt['enablefields'] != '') {
                                                    $onchange = "onchange=\"enableitems(this.selectedIndex);\" ";
                                                }
                                            }
                                            echo "<select class='form-control' " . $onchange . $multiple . $size . "id='" . $name . "' name='" . $name . "'>\n";
                                            foreach ($field['options']['option'] as $opt) {
                                                $selected = '';
                                                if ($value == $opt['value'])
                                                    $selected = " selected=\"selected\"";
                                                echo "\t<option value='" . $opt['value'] . "'" . $selected . ">";
                                                if ($opt['displayname'])
                                                    echo $opt['displayname'];
                                                else
                                                    echo $opt['name'];
                                                echo "</option>\n";
                                            }
                                            echo "</select>\n";
                                            echo "<!-- {$value} -->\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "textarea":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">";
                                            echo "<textarea class='formpre' id='" . $name . "' name='" . $name . "'";
                                            if ($field['rows'])
                                                echo " rows='" . $field['rows'] . "' ";
                                            if ($field['cols'])
                                                echo " cols='" . $field['cols'] . "' ";
                                            echo ">" . $value . "</textarea>\n";


                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "submit":
                                            echo "<td colspan=\"2\" style=\"text-align:center\">";
                                            echo "<input type='submit' class=\"btn btn-primary\" name='" . $name . "' value=\"" . htmlspecialchars(gettext($field['name'])) . "\" />\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "listtopic":
                                            echo "<td colspan=\"2\"><strong>" . gettext($field['name']) . "</strong>\n";

                                            break;
                                        case "subnet_select":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">";
                                            echo "<select class='form-control' id='{$name}' name='{$name}'>\n";
                                            $CHECKED = ' selected="selected"';
                                            for ($x = 1; $x <= 32; $x++) {
                                                if ($value == $x) $CHECKED = " selected=\"selected\"";
                                                echo "<option value='{$x}'";
                                                if ($value == $x || $x == 32) {
                                                    echo $CHECKED;
                                                    /* only used once */
                                                    $CHECKED = '';
                                                }
                                                echo ">{$x}</option>\n";
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "language_select":
                                            $languagelist = get_locale_list();

                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">";
                                            echo "<select class='form-control' id='{$name}' name='{$name}'>\n";
                                            foreach ($languagelist as $langkey => $langval) {
                                                $SELECTED = '';
                                                if ($value == $langkey) $SELECTED = " selected=\"selected\"";
                                                echo "<option value=\"" . htmlspecialchars($langkey) . "\" {$SELECTED}>";
                                                echo htmlspecialchars($langval);
                                                echo "</option>\n";
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "timezone_select":
                                            $timezonelist = get_zoneinfo();

                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>";
                                            }
                                            if (!$field['dontcombinecells'])
                                                echo "<td class=\"vtable\">";
                                            echo "<select class='form-control' id='{$name}' name='{$name}'>\n";
                                            foreach ($timezonelist as $tz) {
                                                if (strstr($tz, "GMT"))
                                                    continue;
                                                $SELECTED = '';
                                                if ($value == $tz) $SELECTED = " selected=\"selected\"";
                                                echo "<option value=\"" . htmlspecialchars($tz) . "\" {$SELECTED}>";
                                                echo htmlspecialchars($tz);
                                                echo "</option>\n";
                                            }
                                            echo "</select>\n";

                                            if ($field['description'] != '') {
                                                echo "<br /> " . gettext($field['description']);
                                            }

                                            break;
                                        case "checkbox":
                                            if ($field['displayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['displayname']);
                                                echo ":</td>\n";
                                            } else if (!$field['dontdisplayname']) {
                                                echo "<td style=\"width:22%; text-align:right\">\n";
                                                echo gettext($field['name']);
                                                echo ":</td>";
                                            }
                                            $checked = '';
                                            if ($value != '')
                                                $checked = " checked=\"checked\"";
                                            echo "<td class=\"vtable\"><input value=\"1\" type='checkbox' id='" . $name . "' name='" . $name . "' " . $checked;
                                            if (isset($field['enablefields']) || isset($field['checkenablefields']))
                                                echo " onclick=\"enablechange()\"";
                                            else if (isset($field['disablefields']) || isset($field['checkdisablefields']))
                                                echo " onclick=\"disablechange()\"";
                                            echo " />\n";

                                            if ($field['typehint'] != '') {
                                                echo gettext($field['typehint']);
                                            }

                                            if ($field['description'] != '') {
                                                echo '<br/><br/>' . gettext($field['description']);
                                            }

                                            break;
                                    }

                                    if ($field['type'] != 'checkbox' && $field['typehint'] != '') {
                                        echo gettext($field['typehint']);
                                    }

                                    if (!$field['combinefieldsbegin']) {
                                        if (!$field['dontcombinecells'])
                                            echo "</td>";

                                        echo "</tr>\n";
                                    }

                                }
                            }
                            ?>

                        </table>
                        <?php
                        if ($pkg['step'][$stepid]['fields']['field'] != '') {
                            foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
                                switch ($field['type']) {
                                    case "refresh":
                                        if ($field['page'] != '' && $field['time'] != '') {
                                            echo '<meta http-equiv="refresh" content="' . $field['time'] . ';url=' . redirect_url() . '/' . $field['page'] . '">';
                                        }
                                        break 2;
                                }
                            }
                        }
                        ?>
                    </div>
                </form>
            </div>
        </section>
    </div>
</section>


<script>
    //<![CDATA[
    if (typeof ext_change != 'undefined') {
        ext_change();
    }
    if (typeof proto_change != 'undefined') {
        ext_change();
    }
    if (typeof proto_change != 'undefined') {
        proto_change();
    }

    <?php
    $isfirst = 0;
    $aliases = '';
    $addrisfirst = 0;
    $aliasesaddr = '';
    foreach ((new \OPNsense\Firewall\Alias())->aliasIterator() as $alias_name) {
        if ($isfirst == 1) {
            $aliases .= ",";
        }
        $aliases .= "'" . $alias_name['name'] . "'";
        $isfirst = 1;
    }
    ?>

    var customarray = new Array(<?= $aliases ?>);

    window.onload = function () {

        <?php
        $counter = 0;
        foreach ($inputaliases as $alias) {
            echo "var oTextbox$counter = new AutoSuggestControl(document.getElementById(\"$alias\"), new StateSuggestions(customarray));\n";
            $counter++;
        }
        ?>

    }

    //]]>
</script>

<?php

$fieldnames_array = Array();
if ($pkg['step'][$stepid]['disableallfieldsbydefault'] != '') {
    // create a fieldname loop that can be used with javascript
    // hide and enable features.
    echo "\n<script>\n";
    echo "//<![CDATA[\n";
    echo "function disableall() {\n";
    foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
        if ($field['type'] != 'submit' && $field['type'] != 'listtopic') {
            if (!$field['donotdisable'] != '') {
                array_push($fieldnames_array, $field['name']);
                $fieldname = preg_replace("/\s+/", "", $field['name']);
                $fieldname = strtolower($fieldname);
                //echo "\tdocument.forms[0]." . $fieldname . ".disabled = 1;\n";
                echo "\tjQuery('#" . $fieldname . "').prop('disabled', true);\n";
            }
        }
    }
    echo "}\ndisableall();\n";
    echo "function enableitems(selectedindex) {\n";
    echo "disableall();\n";
    $idcounter = 0;
    if ($pkg['step'][$stepid]['fields']['field'] != '') {
        echo "\tswitch(selectedindex) {\n";
        foreach ($pkg['step'][$stepid]['fields']['field'] as $field) {
            if ($field['options']['option'] != '') {
                foreach ($field['options']['option'] as $opt) {
                    if ($opt['enablefields'] != '') {
                        echo "\t\tcase " . $idcounter . ":\n";
                        $enablefields_split = explode(",", $opt['enablefields']);
                        foreach ($enablefields_split as $efs) {
                            $fieldname = preg_replace("/\s+/", "", $efs);
                            $fieldname = strtolower($fieldname);
                            if ($fieldname != '') {
                                //$onchange = "\t\t\tdocument.forms[0]." . $fieldname . ".disabled = 0; \n";
                                $onchange = "\t\t\tjQuery('#" . $fieldname . "').prop('disabled',false)\n";
                                echo $onchange;
                            }
                        }
                        echo "\t\t\tbreak;\n";
                    }
                    $idcounter = $idcounter + 1;
                }
            }
        }
        echo "\t}\n";
    }
    echo "}\n";
    echo "//]]>\n";
    echo "</script>\n\n";
}
?>

<script>
    //<![CDATA[

    // After reload/redirect functions are not loaded, so check first.
    if (typeof enablechange == 'function') {
        enablechange();
        disablechange();
        showchange();
    }
    //]]>
</script>

<?php
if ($pkg['step'][$stepid]['stepafterformdisplay'] != '') {
    // handle after form display event.
    eval($pkg['step'][$stepid]['stepafterformdisplay']);
}

if ($pkg['step'][$stepid]['javascriptafterformdisplay'] != '') {
    // handle after form display event.
    echo "\n<script>\n";
    echo "//<![CDATA[\n";
    echo $pkg['step'][$stepid]['javascriptafterformdisplay'] . "\n";
    echo "//]]>\n";
    echo "</script>\n\n";
}

include('foot.inc');
