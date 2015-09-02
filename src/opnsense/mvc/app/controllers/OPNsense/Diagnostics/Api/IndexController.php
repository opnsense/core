<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Diagnostics\Api;

use \OPNsense\Base\ApiControllerBase;

class IndexController extends ApiControllerBase
{
  /**
   * @return array
   */
  public function loadroutingtableAction()
  {
    $types = array("IPv6" => "inet6", "IPv4" => "inet");
    $output = array();
    foreach ($types as $key => $value)
    {
      $output[$key] = $this->parse_output(shell_exec("netstat -rnW -f " . $value));
    }
    return $output;
  }
  /*
   * parses the string and returns an array of hashes
   * */
  private function parse_output($output)
  {
    $f = explode("\n",$output);
    $tablecontent = array_filter(array_slice($f, 4));
    $tableheader = explode(" ", preg_replace("/\\s+/"," ",$f[3]));
    $arr = array();
    foreach ($tablecontent as $key1 => $val1)
    {
      $tmp = explode(" ", preg_replace("/\\s+/"," ",$val1));
      if (!isset($tmp[6])) $tmp[6] = '';
      $arr[] = array_combine($tableheader,$tmp);
    }

    return $arr;
  }
}
