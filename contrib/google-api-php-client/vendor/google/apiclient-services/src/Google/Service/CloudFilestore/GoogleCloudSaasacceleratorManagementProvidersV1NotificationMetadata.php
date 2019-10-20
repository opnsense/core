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

class Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1NotificationMetadata extends Google_Model
{
  public $rescheduled;
  public $scheduledEndTime;
  public $scheduledStartTime;
  public $targetRelease;

  public function setRescheduled($rescheduled)
  {
    $this->rescheduled = $rescheduled;
  }
  public function getRescheduled()
  {
    return $this->rescheduled;
  }
  public function setScheduledEndTime($scheduledEndTime)
  {
    $this->scheduledEndTime = $scheduledEndTime;
  }
  public function getScheduledEndTime()
  {
    return $this->scheduledEndTime;
  }
  public function setScheduledStartTime($scheduledStartTime)
  {
    $this->scheduledStartTime = $scheduledStartTime;
  }
  public function getScheduledStartTime()
  {
    return $this->scheduledStartTime;
  }
  public function setTargetRelease($targetRelease)
  {
    $this->targetRelease = $targetRelease;
  }
  public function getTargetRelease()
  {
    return $this->targetRelease;
  }
}
