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

class Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1RolloutMetadata extends Google_Model
{
  protected $notificationType = 'Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1NotificationMetadata';
  protected $notificationDataType = '';
  public $releaseName;
  public $rolloutName;

  /**
   * @param Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1NotificationMetadata
   */
  public function setNotification(Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1NotificationMetadata $notification)
  {
    $this->notification = $notification;
  }
  /**
   * @return Google_Service_CloudFilestore_GoogleCloudSaasacceleratorManagementProvidersV1NotificationMetadata
   */
  public function getNotification()
  {
    return $this->notification;
  }
  public function setReleaseName($releaseName)
  {
    $this->releaseName = $releaseName;
  }
  public function getReleaseName()
  {
    return $this->releaseName;
  }
  public function setRolloutName($rolloutName)
  {
    $this->rolloutName = $rolloutName;
  }
  public function getRolloutName()
  {
    return $this->rolloutName;
  }
}
