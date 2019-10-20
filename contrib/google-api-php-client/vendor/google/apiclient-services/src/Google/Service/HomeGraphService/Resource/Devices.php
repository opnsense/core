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

/**
 * The "devices" collection of methods.
 * Typical usage is:
 *  <code>
 *   $homegraphService = new Google_Service_HomeGraphService(...);
 *   $devices = $homegraphService->devices;
 *  </code>
 */
class Google_Service_HomeGraphService_Resource_Devices extends Google_Service_Resource
{
  /**
   * Gets the device states for the devices in QueryRequest. The third-party
   * user's identity is passed in as `agent_user_id`. The agent is identified by
   * the JWT signed by the third-party partner's service account. (devices.query)
   *
   * @param Google_Service_HomeGraphService_QueryRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_HomeGraphService_QueryResponse
   */
  public function query(Google_Service_HomeGraphService_QueryRequest $postBody, $optParams = array())
  {
    $params = array('postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('query', array($params), "Google_Service_HomeGraphService_QueryResponse");
  }
  /**
   * Reports device state and optionally sends device notifications. Called by an
   * agent when the device state of a third-party changes or the agent wants to
   * send a notification about the device. See [Implement Report
   * State](/actions/smarthome/report-state) for more information. This method
   * updates a predefined set of states for a device, which all devices have
   * according to their prescribed traits (for example, a light will have the
   * [OnOff](/actions/smarthome/traits/onoff) trait that reports the state `on` as
   * a boolean value). A new state may not be created and an INVALID_ARGUMENT code
   * will be thrown if so. It also optionally takes in a list of Notifications
   * that may be created, which are associated to this state change.
   *
   * The third-party user's identity is passed in as `agent_user_id`. The agent is
   * identified by the JWT signed by the partner's service account.
   * (devices.reportStateAndNotification)
   *
   * @param Google_Service_HomeGraphService_ReportStateAndNotificationRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_HomeGraphService_ReportStateAndNotificationResponse
   */
  public function reportStateAndNotification(Google_Service_HomeGraphService_ReportStateAndNotificationRequest $postBody, $optParams = array())
  {
    $params = array('postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('reportStateAndNotification', array($params), "Google_Service_HomeGraphService_ReportStateAndNotificationResponse");
  }
  /**
   * Requests a `SYNC` call from Google to a 3p partner's home control agent for a
   * user.
   *
   * The third-party user's identity is passed in as `agent_user_id` (see
   * RequestSyncDevicesRequest) and forwarded back to the agent. The agent is
   * identified by the API key or JWT signed by the partner's service account.
   * (devices.requestSync)
   *
   * @param Google_Service_HomeGraphService_RequestSyncDevicesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_HomeGraphService_RequestSyncDevicesResponse
   */
  public function requestSync(Google_Service_HomeGraphService_RequestSyncDevicesRequest $postBody, $optParams = array())
  {
    $params = array('postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('requestSync', array($params), "Google_Service_HomeGraphService_RequestSyncDevicesResponse");
  }
  /**
   * Gets all the devices associated with the given third-party user. The third-
   * party user's identity is passed in as `agent_user_id`. The agent is
   * identified by the JWT signed by the third-party partner's service account.
   * (devices.sync)
   *
   * @param Google_Service_HomeGraphService_SyncRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_HomeGraphService_SyncResponse
   */
  public function sync(Google_Service_HomeGraphService_SyncRequest $postBody, $optParams = array())
  {
    $params = array('postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('sync', array($params), "Google_Service_HomeGraphService_SyncResponse");
  }
}
