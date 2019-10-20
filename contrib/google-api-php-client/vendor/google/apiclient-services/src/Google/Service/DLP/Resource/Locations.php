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
 * The "locations" collection of methods.
 * Typical usage is:
 *  <code>
 *   $dlpService = new Google_Service_DLP(...);
 *   $locations = $dlpService->locations;
 *  </code>
 */
class Google_Service_DLP_Resource_Locations extends Google_Service_Resource
{
  /**
   * Returns a list of the sensitive information types that the DLP API supports.
   * See https://cloud.google.com/dlp/docs/infotypes-reference to learn more.
   * (locations.infoTypes)
   *
   * @param string $location The geographic location to list info types. Reserved
   * for future extensions.
   * @param Google_Service_DLP_GooglePrivacyDlpV2ListInfoTypesRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_DLP_GooglePrivacyDlpV2ListInfoTypesResponse
   */
  public function infoTypes($location, Google_Service_DLP_GooglePrivacyDlpV2ListInfoTypesRequest $postBody, $optParams = array())
  {
    $params = array('location' => $location, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('infoTypes', array($params), "Google_Service_DLP_GooglePrivacyDlpV2ListInfoTypesResponse");
  }
}
