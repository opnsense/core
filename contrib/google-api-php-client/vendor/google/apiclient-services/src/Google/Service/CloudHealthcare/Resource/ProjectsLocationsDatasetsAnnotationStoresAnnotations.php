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
 * The "annotations" collection of methods.
 * Typical usage is:
 *  <code>
 *   $healthcareService = new Google_Service_CloudHealthcare(...);
 *   $annotations = $healthcareService->annotations;
 *  </code>
 */
class Google_Service_CloudHealthcare_Resource_ProjectsLocationsDatasetsAnnotationStoresAnnotations extends Google_Service_Resource
{
  /**
   * Creates a new Annotation record. It is valid to create Annotation objects for
   * the same source more than once since a unique ID is assigned to each record
   * by this service. (annotations.create)
   *
   * @param string $parent The name of the Annotation store this annotation
   * belongs to. For example, `projects/my-project/locations/us-
   * central1/datasets/mydataset/annotationStores/myannotationstore`.
   * @param Google_Service_CloudHealthcare_Annotation $postBody
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_Annotation
   */
  public function create($parent, Google_Service_CloudHealthcare_Annotation $postBody, $optParams = array())
  {
    $params = array('parent' => $parent, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('create', array($params), "Google_Service_CloudHealthcare_Annotation");
  }
  /**
   * Deletes an Annotation or returns NOT_FOUND if it does not exist.
   * (annotations.delete)
   *
   * @param string $name The resource name of the Annotation to delete.
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
   * Gets an Annotation. (annotations.get)
   *
   * @param string $name The resource name of the Annotation to retrieve.
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_Annotation
   */
  public function get($name, $optParams = array())
  {
    $params = array('name' => $name);
    $params = array_merge($params, $optParams);
    return $this->call('get', array($params), "Google_Service_CloudHealthcare_Annotation");
  }
  /**
   * Lists the Annotations in the given Annotation store for a source resource.
   * (annotations.listProjectsLocationsDatasetsAnnotationStoresAnnotations)
   *
   * @param string $parent Name of the Annotation store to retrieve Annotations
   * from.
   * @param array $optParams Optional parameters.
   *
   * @opt_param string filter Restricts Annotations returned to those matching a
   * filter. Syntax:
   * https://cloud.google.com/appengine/docs/standard/python/search/query_strings
   * Fields/functions available for filtering are: - source_version
   * @opt_param string pageToken The next_page_token value returned from the
   * previous List request, if any.
   * @opt_param int pageSize Limit on the number of Annotations to return in a
   * single response. If zero the default page size of 100 is used.
   * @return Google_Service_CloudHealthcare_ListAnnotationsResponse
   */
  public function listProjectsLocationsDatasetsAnnotationStoresAnnotations($parent, $optParams = array())
  {
    $params = array('parent' => $parent);
    $params = array_merge($params, $optParams);
    return $this->call('list', array($params), "Google_Service_CloudHealthcare_ListAnnotationsResponse");
  }
  /**
   * Updates the Annotation. (annotations.patch)
   *
   * @param string $name Output only. Resource name of the Annotation, of the form
   * `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}/annotati
   * onStores/{annotation_store_id}/annotations/{annotation_id}`.
   * @param Google_Service_CloudHealthcare_Annotation $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask The update mask applies to the resource. For the
   * `FieldMask` definition, see https://developers.google.com/protocol-
   * buffers/docs/reference/google.protobuf#fieldmask
   * @return Google_Service_CloudHealthcare_Annotation
   */
  public function patch($name, Google_Service_CloudHealthcare_Annotation $postBody, $optParams = array())
  {
    $params = array('name' => $name, 'postBody' => $postBody);
    $params = array_merge($params, $optParams);
    return $this->call('patch', array($params), "Google_Service_CloudHealthcare_Annotation");
  }
}
