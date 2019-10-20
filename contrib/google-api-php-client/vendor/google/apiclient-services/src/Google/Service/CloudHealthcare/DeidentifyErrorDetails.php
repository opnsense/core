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

class Google_Service_CloudHealthcare_DeidentifyErrorDetails extends Google_Model
{
  public $failureResourceCount;
  public $failureStoreCount;
  public $successResourceCount;
  public $successStoreCount;

  public function setFailureResourceCount($failureResourceCount)
  {
    $this->failureResourceCount = $failureResourceCount;
  }
  public function getFailureResourceCount()
  {
    return $this->failureResourceCount;
  }
  public function setFailureStoreCount($failureStoreCount)
  {
    $this->failureStoreCount = $failureStoreCount;
  }
  public function getFailureStoreCount()
  {
    return $this->failureStoreCount;
  }
  public function setSuccessResourceCount($successResourceCount)
  {
    $this->successResourceCount = $successResourceCount;
  }
  public function getSuccessResourceCount()
  {
    return $this->successResourceCount;
  }
  public function setSuccessStoreCount($successStoreCount)
  {
    $this->successStoreCount = $successStoreCount;
  }
  public function getSuccessStoreCount()
  {
    return $this->successStoreCount;
  }
}
