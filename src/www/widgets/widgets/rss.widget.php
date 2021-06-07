<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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
require_once("simplepie/autoloader.php");
require_once("simplepie/idn/idna_convert.class.php");

function textLimit($string, $length, $replacer = '...')
{
    if (strlen($string) > $length) {
        return (preg_match('/^(.*)\W.*$/', substr($string, 0, $length+1), $matches) ? $matches[1] : substr($string, 0, $length)) . $replacer;
    }
    return $string;
}

if (!empty($_POST['rssfeed'])) {
    $config['widgets']['rssfeed'] = str_replace("\n", ",", htmlspecialchars($_POST['rssfeed'], ENT_QUOTES | ENT_HTML401));
    $config['widgets']['rssmaxitems'] = str_replace("\n", ",", htmlspecialchars($_POST['rssmaxitems'], ENT_QUOTES | ENT_HTML401));
    $config['widgets']['rsswidgetheight'] = htmlspecialchars($_POST['rsswidgetheight'], ENT_QUOTES | ENT_HTML401);
    $config['widgets']['rsswidgettextlength'] = htmlspecialchars($_POST['rsswidgettextlength'], ENT_QUOTES | ENT_HTML401);
    write_config("Saved RSS Widget feed via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

// Use saved feed and max items
if (!empty($config['widgets']['rssfeed'])) {
    $rss_feed_s = explode(",", $config['widgets']['rssfeed']);
    $textarea_txt =  str_replace(",", "\n", $config['widgets']['rssfeed']);
} else {
    // Set a default feed if none exists
    $rss_feed_s = "https://opnsense.org/feed/";
    $config['widgets']['rssfeed'] = "https://opnsense.org/feed/";
    $textarea_txt = "";
}

if (!empty($config['widgets']['rssmaxitems']) && is_numeric($config['widgets']['rssmaxitems'])) {
    $max_items =  $config['widgets']['rssmaxitems'];
} else {
    $max_items = 10;
}

if (!empty($config['widgets']['rsswidgetheight']) && is_numeric($config['widgets']['rsswidgetheight'])) {
    $rsswidgetheight =  $config['widgets']['rsswidgetheight'];
} else {
    $rsswidgetheight = 300;
}

if (!empty($config['widgets']['rsswidgettextlength']) && is_numeric($config['widgets']['rsswidgettextlength'])) {
    $rsswidgettextlength =  $config['widgets']['rsswidgettextlength'];
} else {
    $rsswidgettextlength = 140;     // oh twitter, how do we love thee?
}
?>

<div id="rss-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/rss.widget.php" method="post" name="iformc">
    <table class="table table-striped">
      <tr>
        <td colspan="2">
          <textarea name="rssfeed" id="rssfeed" cols="40" rows="3" style="max-width:100%;"><?=$textarea_txt;?></textarea>
        </td>
      </tr>
      <tr>
        <td>
          <?= gettext('Display number of items:') ?>
        </td>
        <td>
          <select name='rssmaxitems' id='rssmaxitems'>
            <option value='<?= $max_items ?>'><?= $max_items ?></option>
<?php
              for ($x=100; $x<5100; $x=$x+100) {
                  echo "<option value='{$x}'>{$x}</option>\n";
              }?>
          </select>
        </td>
      </tr>
      <tr>
        <td>
          <?= gettext('Widget height:') ?>
        </td>
        <td>
          <select name='rsswidgetheight' id='rsswidgetheight'>
            <option value='<?= $rsswidgetheight ?>'><?= $rsswidgetheight ?>px</option>
<?php
            for ($x=100; $x<5100; $x=$x+100) {
                echo "<option value='{$x}'>{$x}px</option>\n";
            }?>
          </select>
        </td>
      </tr>
      <tr>
        <td>
          <?= gettext('Show how many characters from story:') ?>
        </td>
        <td>
          <select name='rsswidgettextlength' id='rsswidgettextlength'>
            <option value='<?= $rsswidgettextlength ?>'><?= $rsswidgettextlength ?></option>
<?php
            for ($x=10; $x<5100; $x=$x+10) {
                echo "<option value='{$x}'>{$x}</option>\n";
            }?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <input id="submitc" name="submitc" type="submit" class="btn btn-primary formbtn" value="<?= html_safe(gettext('Save')) ?>" />
        </td>
      </tr>
    </table>
  </form>
</div>

<div id="rss-widgets" style="padding: 5px; height: <?=$rsswidgetheight?>px; overflow:scroll;">
<?php
    @mkdir('/tmp/simplepie');
    @mkdir('/tmp/simplepie/cache');
    exec("chmod a+rw /tmp/simplepie/.");
    exec("chmod a+rw /tmp/simplepie/cache/.");
    $feed = new SimplePie();
    $feed->set_cache_location("/tmp/simplepie/");
    $feed->set_feed_url($rss_feed_s);
    $feed->init();
    $feed->handle_content_type();
    $feed->strip_htmltags();
    $counter = 1;
    foreach ($feed->get_items() as $item) {
        echo "<a target='blank' href='" . $item->get_permalink() . "'>" . $item->get_title() . "</a><br />";
        $content = $item->get_content();
        $content = strip_tags($content);
        echo textLimit($content, $rsswidgettextlength) . "<br />";
        echo "Source: <a target='_blank' href='" . $item->get_permalink() . "'>".$feed->get_title()."</a><br />";
        $counter++;
        if ($counter > $max_items) {
            break;
        }
        echo "<hr/>";
    }
?>
</div>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#rss-configure").removeClass("disabled");
//]]>
</script>
