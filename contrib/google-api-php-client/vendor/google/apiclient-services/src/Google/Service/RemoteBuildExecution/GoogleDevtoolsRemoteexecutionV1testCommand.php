<?php
/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

class Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testCommand extends Google_Collection
{
  protected $collection_key = 'environmentVariables';
  public $arguments;
  protected $environmentVariablesType = 'Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testCommandEnvironmentVariable';
  protected $environmentVariablesDataType = 'array';

  public function setArguments($arguments)
  {
    $this->arguments = $arguments;
  }
  public function getArguments()
  {
    return $this->arguments;
  }
  /**
   * @param Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testCommandEnvironmentVariable
   */
  public function setEnvironmentVariables($environmentVariables)
  {
    $this->environmentVariables = $environmentVariables;
  }
  /**
   * @return Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testCommandEnvironmentVariable
   */
  public function getEnvironmentVariables()
  {
    return $this->environmentVariables;
  }
}
