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
 * The "agentUsers" collection of methods.
 * Typical usage is:
 *  <code>
 *   $homegraphService = new Google_Service_HomeGraphService(...);
 *   $agentUsers = $homegraphService->agentUsers;
 *  </code>
 */
class Google_Service_HomeGraphService_Resource_AgentUsers extends Google_Service_Resource
{
  /**
   * Unlinks an agent user from Google. As a result, all data related to this user
   * will be deleted.
   *
   * Here is how the agent user is created in Google:
   *
   * 1.  When a user opens their Google Home App, they can begin linking a 3p
   * partner. 2.  User is guided through the OAuth process. 3.  After entering the
   * 3p credentials, Google gets the 3p OAuth token and     uses it to make a Sync
   * call to the 3p partner and gets back all of the     user's data, including
   * `agent_user_id` and devices. 4.  Google creates the agent user and stores a
   * mapping from the     `agent_user_id` -> Google ID mapping. Google also
   * stores all of the user's devices under that Google ID.
   *
   * The mapping from `agent_user_id` to Google ID is many to many, since one
   * Google user can have multiple 3p accounts, and multiple Google users can map
   * to one `agent_user_id` (e.g., a husband and wife share one Nest account
   * username/password).
   *
   * The third-party user's identity is passed in as `agent_user_id`. The agent is
   * identified by the JWT signed by the partner's service account.
   *
   * Note: Special characters (except "/") in `agent_user_id` must be URL-encoded.
   * (agentUsers.delete)
   *
   * @param string $agentUserId Required. Third-party user ID.
   * @param array $optParams Optional parameters.
   *
   * @opt_param string requestId Request ID used for debugging.
   * @return Google_Service_HomeGraphService_HomegraphEmpty
   */
  public function delete($agentUserId, $optParams = array())
  {
    $params = array('agentUserId' => $agentUserId);
    $params = array_merge($params, $optParams);
    return $this->call('delete', array($params), "Google_Service_HomeGraphService_HomegraphEmpty");
  }
}
