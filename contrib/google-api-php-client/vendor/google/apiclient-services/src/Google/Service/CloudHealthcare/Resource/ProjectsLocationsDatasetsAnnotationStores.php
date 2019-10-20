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
 * The "annotationStores" collection of methods.
 * Typical usage is:
 *  <code>
 *   $healthcareService = new Google_Service_CloudHealthcare(...);
 *   $annotationStores = $healthcareService->annotationStores;
 *  </code>
 */
class Google_Service_CloudHealthcare_Resource_ProjectsLocationsDatasetsAnnotationStores extends Google_Service_Resource
{
  /**
   * Creates a new Annotation store within the parent dataset.
   * (annotationStores.create)
   *
   * @param string $parent The name of the dataset this Annotation store belongs
   * to.
   * @param Google_Service_CloudHealthcare_AnnotationStore $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string annotationStoreId The ID of the Annotation store that is
   * being created. The string must match the following regex:
   * `[\p{L}\p{N}_\-\.]{1,256}`.
   * @return Google_Service_CloudHealthcare_AnnotationStore
   */
  public function create($parent, Google_Service_CloudHealthcare_AnnotationStore $postBody, $optParams = array())
  {
    $params = array('parent' => $parent, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('create', array($params), "Google_Service_CloudHealthcare_AnnotationStore");
  }
  /**
   * Deletes the specified Annotation store and removes all annotations that are
   * contained within it. (annotationStores.delete)
   *
   * @param string $name The resource name of the Annotation store to delete.
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_HealthcareEmpty
   */
  public function delete($name, $optParams = array())
  {
    $params = array('name' => $name);
    $params = array_merge($params, $optParams);
    return $this->call('delete', array($params), "Google_Service_CloudHealthcare_HealthcareEmpty");
  }
  /**
   * Gets the specified Annotation store or returns NOT_FOUND if it does not
   * exist. (annotationStores.get)
   *
   * @param string $name The resource name of the Annotation store to get.
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_AnnotationStore
   */
  public function get($name, $optParams = array())
  {
    $params = array('name' => $name);
    $params = array_merge($params, $optParams);
    return $this->call('get', array($params), "Google_Service_CloudHealthcare_AnnotationStore");
  }
  /**
   * Gets the access control policy for a resource. Returns NOT_FOUND error if the
   * resource does not exist. Returns an empty policy if the resource exists but
   * does not have a policy set.
   *
   * Authorization requires the Google IAM permission
   * `healthcare.AnnotationStores.getIamPolicy` on the specified resource
   * (annotationStores.getIamPolicy)
   *
   * @param string $resource REQUIRED: The resource for which the policy is being
   * requested. See the operation documentation for the appropriate value for this
   * field.
   * @param Google_Service_CloudHealthcare_GetIamPolicyRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_Policy
   */
  public function getIamPolicy($resource, Google_Service_CloudHealthcare_GetIamPolicyRequest $postBody, $optParams = array())
  {
    $params = array('resource' => $resource, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('getIamPolicy', array($params), "Google_Service_CloudHealthcare_Policy");
  }
  /**
   * Lists the Annotation stores in the given dataset for a source store.
   * (annotationStores.listProjectsLocationsDatasetsAnnotationStores)
   *
   * @param string $parent Name of the dataset.
   * @param array $optParams Optional parameters.
   *
   * @opt_param string pageToken The next_page_token value returned from the
   * previous List request, if any.
   * @opt_param int pageSize Limit on the number of Annotation stores to return in
   * a single response. If zero the default page size of 100 is used.
   * @opt_param string filter Restricts stores returned to those matching a
   * filter. Syntax:
   * https://cloud.google.com/appengine/docs/standard/python/search/query_strings
   * Only filtering on labels is supported, for example `labels.key=value`.
   * @return Google_Service_CloudHealthcare_ListAnnotationStoresResponse
   */
  public function listProjectsLocationsDatasetsAnnotationStores($parent, $optParams = array())
  {
    $params = array('parent' => $parent);
    $params = array_merge($params, $optParams);
    return $this->call('list', array($params), "Google_Service_CloudHealthcare_ListAnnotationStoresResponse");
  }
  /**
   * Updates the specified Annotation store. (annotationStores.patch)
   *
   * @param string $name Output only. Resource name of the Annotation store, of
   * the form `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}
   * /annotationStores/{annotation_store_id}`.
   * @param Google_Service_CloudHealthcare_AnnotationStore $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask The update mask applies to the resource. For the
   * `FieldMask` definition, see https://developers.google.com/protocol-
   * buffers/docs/reference/google.protobuf#fieldmask
   * @return Google_Service_CloudHealthcare_AnnotationStore
   */
  public function patch($name, Google_Service_CloudHealthcare_AnnotationStore $postBody, $optParams = array())
  {
    $params = array('name' => $name, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('patch', array($params), "Google_Service_CloudHealthcare_AnnotationStore");
  }
  /**
   * POLICIES Sets the access control policy for a resource. Replaces any existing
   * policy.
   *
   * Authorization requires the Google IAM permission
   * `healthcare.annotationStores.setIamPolicy` on the specified resource
   * (annotationStores.setIamPolicy)
   *
   * @param string $resource REQUIRED: The resource for which the policy is being
   * specified. See the operation documentation for the appropriate value for this
   * field.
   * @param Google_Service_CloudHealthcare_SetIamPolicyRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_Policy
   */
  public function setIamPolicy($resource, Google_Service_CloudHealthcare_SetIamPolicyRequest $postBody, $optParams = array())
  {
    $params = array('resource' => $resource, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('setIamPolicy', array($params), "Google_Service_CloudHealthcare_Policy");
  }
  /**
   * Returns permissions that a caller has on the specified resource. If the
   * resource does not exist, this will return an empty set of permissions, not a
   * NOT_FOUND error.
   *
   * There is no permission required to make this API call.
   * (annotationStores.testIamPermissions)
   *
   * @param string $resource REQUIRED: The resource for which the policy detail is
   * being requested. See the operation documentation for the appropriate value
   * for this field.
   * @param Google_Service_CloudHealthcare_TestIamPermissionsRequest $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_TestIamPermissionsResponse
   */
  public function testIamPermissions($resource, Google_Service_CloudHealthcare_TestIamPermissionsRequest $postBody, $optParams = array())
  {
    $params = array('resource' => $resource, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('testIamPermissions', array($params), "Google_Service_CloudHealthcare_TestIamPermissionsResponse");
  }
}
