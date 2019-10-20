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

class Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1SloExclusion extends Google_Model
{
  public $exclusionDuration;
  public $exclusionStartTime;
  public $reason;
  public $sliName;

  public function setExclusionDuration($exclusionDuration)
  {
    $this->exclusionDuration = $exclusionDuration;
  }
  public function getExclusionDuration()
  {
    return $this->exclusionDuration;
  }
  public function setExclusionStartTime($exclusionStartTime)
  {
    $this->exclusionStartTime = $exclusionStartTime;
  }
  public function getExclusionStartTime()
  {
    return $this->exclusionStartTime;
  }
  public function setReason($reason)
  {
    $this->reason = $reason;
  }
  public function getReason()
  {
    return $this->reason;
  }
  public function setSliName($sliName)
  {
    $this->sliName = $sliName;
  }
  public function getSliName()
  {
    return $this->sliName;
  }
}
