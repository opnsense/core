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
 * The "indexes" collection of methods.
 * Typical usage is:
 *  <code>
 *   $datastoreService = new Google_Service_Datastore(...);
 *   $indexes = $datastoreService->indexes;
 *  </code>
 */
class Google_Service_Datastore_Resource_ProjectsIndexes extends Google_Service_Resource
{
  /**
   * Gets an index. (indexes.get)
   *
   * @param string $projectId Project ID against which to make the request.
   * @param string $indexId The resource ID of the index to get.
   * @param array $optParams Optional parameters.
   * @return Google_Service_Datastore_GoogleDatastoreAdminV1Index
   */
  public function get($projectId, $indexId, $optParams = array())
  {
    $params = array('projectId' => $projectId, 'indexId' => $indexId);
    $params = array_merge($params, $optParams);
    return $this->call('get', array($params), "Google_Service_Datastore_GoogleDatastoreAdminV1Index");
  }
  /**
   * Lists the indexes that match the specified filters.  Datastore uses an
   * eventually consistent query to fetch the list of indexes and may occasionally
   * return stale results. (indexes.listProjectsIndexes)
   *
   * @param string $projectId Project ID against which to make the request.
   * @param array $optParams Optional parameters.
   *
   * @opt_param string pageToken The next_page_token value returned from a
   * previous List request, if any.
   * @opt_param int pageSize The maximum number of items to return.  If zero, then
   * all results will be returned.
   * @opt_param string filter
   * @return Google_Service_Datastore_GoogleDatastoreAdminV1ListIndexesResponse
   */
  public function listProjectsIndexes($projectId, $optParams = array())
  {
    $params = array('projectId' => $projectId);
    $params = array_merge($params, $optParams);
    return $this->call('list', array($params), "Google_Service_Datastore_GoogleDatastoreAdminV1ListIndexesResponse");
  }
}
