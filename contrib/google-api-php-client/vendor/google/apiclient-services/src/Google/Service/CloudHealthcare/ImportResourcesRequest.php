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

class Google_Service_CloudHealthcare_ImportResourcesRequest extends Google_Model
{
  public $contentStructure;
  protected $gcsErrorDestinationType = 'Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsErrorDestination';
  protected $gcsErrorDestinationDataType = '';
  protected $gcsSourceType = 'Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsSource';
  protected $gcsSourceDataType = '';

  public function setContentStructure($contentStructure)
  {
    $this->contentStructure = $contentStructure;
  }
  public function getContentStructure()
  {
    return $this->contentStructure;
  }
  /**
   * @param Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsErrorDestination
   */
  public function setGcsErrorDestination(Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsErrorDestination $gcsErrorDestination)
  {
    $this->gcsErrorDestination = $gcsErrorDestination;
  }
  /**
   * @return Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsErrorDestination
   */
  public function getGcsErrorDestination()
  {
    return $this->gcsErrorDestination;
  }
  /**
   * @param Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsSource
   */
  public function setGcsSource(Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsSource $gcsSource)
  {
    $this->gcsSource = $gcsSource;
  }
  /**
   * @return Google_Service_CloudHealthcare_GoogleCloudHealthcareV1alpha2FhirRestGcsSource
   */
  public function getGcsSource()
  {
    return $this->gcsSource;
  }
}
