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
 * The "instances" collection of methods.
 * Typical usage is:
 *  <code>
 *   $healthcareService = new Google_Service_CloudHealthcare(...);
 *   $instances = $healthcareService->instances;
 *  </code>
 */
class Google_Service_CloudHealthcare_Resource_ProjectsLocationsDatasetsDicomStoresDicomWebStudiesSeriesInstances extends Google_Service_Resource
{
  /**
   * DeleteInstance deletes an instance associated with the given study, series,
   * and SOP Instance UID. Delete requests are equivalent to the GET requests
   * specified in the WADO-RS standard. (instances.delete)
   *
   * @param string $parent The name of the DICOM store that is being accessed
   * (e.g., `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}/d
   * icomStores/{dicom_store_id}`).
   * @param string $dicomWebPath The path of the DeleteInstance request (e.g.,
   * `studies/{study_id}/series/{series_id}/instances/{instance_id}`).
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_HealthcareEmpty
   */
  public function delete($parent, $dicomWebPath, $optParams = array())
  {
    $params = array('parent' => $parent, 'dicomWebPath' => $dicomWebPath);
    $params = array_merge($params, $optParams);
    return $this->call('delete', array($params), "Google_Service_CloudHealthcare_HealthcareEmpty");
  }
  /**
   * RetrieveInstanceMetadata returns instance associated with the given study,
   * series, and SOP Instance UID presented as metadata with the bulk data
   * removed. See http://dicom.nema.org/medical/dicom/current/output/html/part18.h
   * tml#sect_10.4. (instances.metadata)
   *
   * @param string $parent The name of the DICOM store that is being accessed
   * (e.g., `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}/d
   * icomStores/{dicom_store_id}`).
   * @param string $dicomWebPath The path of the RetrieveInstanceMetadata DICOMweb
   * request (e.g.,
   * `studies/{study_id}/series/{series_id}/instances/{instance_id}/metadata`).
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_HttpBody
   */
  public function metadata($parent, $dicomWebPath, $optParams = array())
  {
    $params = array('parent' => $parent, 'dicomWebPath' => $dicomWebPath);
    $params = array_merge($params, $optParams);
    return $this->call('metadata', array($params), "Google_Service_CloudHealthcare_HttpBody");
  }
  /**
   * RetrieveRenderedInstance returns instance associated with the given study,
   * series, and SOP Instance UID in an acceptable Rendered Media Type. See http:/
   * /dicom.nema.org/medical/dicom/current/output/html/part18.html#sect_10.4.
   * (instances.rendered)
   *
   * @param string $parent The name of the DICOM store that is being accessed
   * (e.g., `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}/d
   * icomStores/{dicom_store_id}`).
   * @param string $dicomWebPath The path of the RetrieveRenderedInstance DICOMweb
   * request (e.g.,
   * `studies/{study_id}/series/{series_id}/instances/{instance_id}/rendered`).
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_HttpBody
   */
  public function rendered($parent, $dicomWebPath, $optParams = array())
  {
    $params = array('parent' => $parent, 'dicomWebPath' => $dicomWebPath);
    $params = array_merge($params, $optParams);
    return $this->call('rendered', array($params), "Google_Service_CloudHealthcare_HttpBody");
  }
  /**
   * RetrieveInstance returns instance associated with the given study, series,
   * and SOP Instance UID. See http://dicom.nema.org/medical/dicom/current/output/
   * html/part18.html#sect_10.4. (instances.retrieveInstance)
   *
   * @param string $parent The name of the DICOM store that is being accessed
   * (e.g., `projects/{project_id}/locations/{location_id}/datasets/{dataset_id}/d
   * icomStores/{dicom_store_id}`).
   * @param string $dicomWebPath The path of the RetrieveInstance DICOMweb request
   * (e.g., `studies/{study_id}/series/{series_id}/instances/{instance_id}`).
   * @param array $optParams Optional parameters.
   * @return Google_Service_CloudHealthcare_HttpBody
   */
  public function retrieveInstance($parent, $dicomWebPath, $optParams = array())
  {
    $params = array('parent' => $parent, 'dicomWebPath' => $dicomWebPath);
    $params = array_merge($params, $optParams);
    return $this->call('retrieveInstance', array($params), "Google_Service_CloudHealthcare_HttpBody");
  }
}
