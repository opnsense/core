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

class Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testActionResult extends Google_Collection
{
  protected $collection_key = 'outputFiles';
  public $exitCode;
  protected $outputDirectoriesType = 'Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputDirectory';
  protected $outputDirectoriesDataType = 'array';
  protected $outputFilesType = 'Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputFile';
  protected $outputFilesDataType = 'array';
  protected $stderrDigestType = 'Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest';
  protected $stderrDigestDataType = '';
  public $stderrRaw;
  protected $stdoutDigestType = 'Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest';
  protected $stdoutDigestDataType = '';
  public $stdoutRaw;

  public function setExitCode($exitCode)
  {
    $this->exitCode = $exitCode;
  }
  public function getExitCode()
  {
    return $this->exitCode;
  }
  /**
   * @param Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputDirectory
   */
  public function setOutputDirectories($outputDirectories)
  {
    $this->outputDirectories = $outputDirectories;
  }
  /**
   * @return Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputDirectory
   */
  public function getOutputDirectories()
  {
    return $this->outputDirectories;
  }
  /**
   * @param Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputFile
   */
  public function setOutputFiles($outputFiles)
  {
    $this->outputFiles = $outputFiles;
  }
  /**
   * @return Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testOutputFile
   */
  public function getOutputFiles()
  {
    return $this->outputFiles;
  }
  /**
   * @param Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest
   */
  public function setStderrDigest(Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest $stderrDigest)
  {
    $this->stderrDigest = $stderrDigest;
  }
  /**
   * @return Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest
   */
  public function getStderrDigest()
  {
    return $this->stderrDigest;
  }
  public function setStderrRaw($stderrRaw)
  {
    $this->stderrRaw = $stderrRaw;
  }
  public function getStderrRaw()
  {
    return $this->stderrRaw;
  }
  /**
   * @param Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest
   */
  public function setStdoutDigest(Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest $stdoutDigest)
  {
    $this->stdoutDigest = $stdoutDigest;
  }
  /**
   * @return Google_Service_RemoteBuildExecution_GoogleDevtoolsRemoteexecutionV1testDigest
   */
  public function getStdoutDigest()
  {
    return $this->stdoutDigest;
  }
  public function setStdoutRaw($stdoutRaw)
  {
    $this->stdoutRaw = $stdoutRaw;
  }
  public function getStdoutRaw()
  {
    return $this->stdoutRaw;
  }
}
