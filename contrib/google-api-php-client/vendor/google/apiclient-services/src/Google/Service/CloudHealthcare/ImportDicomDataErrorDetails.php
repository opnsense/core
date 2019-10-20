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

class Google_Service_CloudHealthcare_ImportDicomDataErrorDetails extends Google_Collection
{
  protected $collection_key = 'sampleErrors';
  protected $sampleErrorsType = 'Google_Service_CloudHealthcare_ErrorDetail';
  protected $sampleErrorsDataType = 'array';

  /**
   * @param Google_Service_CloudHealthcare_ErrorDetail
   */
  public function setSampleErrors($sampleErrors)
  {
    $this->sampleErrors = $sampleErrors;
  }
  /**
   * @return Google_Service_CloudHealthcare_ErrorDetail
   */
  public function getSampleErrors()
  {
    return $this->sampleErrors;
  }
}
