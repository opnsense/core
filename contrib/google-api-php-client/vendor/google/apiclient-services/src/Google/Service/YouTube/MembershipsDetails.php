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

class Google_Service_YouTube_MembershipsDetails extends Google_Collection
{
  protected $collection_key = 'accessibleLevels';
  public $accessibleLevels;
  public $memberSince;
  public $memberSinceCurrentLevel;
  public $memberTotalDuration;
  public $memberTotalDurationCurrentLevel;
  public $purchasedLevel;

  public function setAccessibleLevels($accessibleLevels)
  {
    $this->accessibleLevels = $accessibleLevels;
  }
  public function getAccessibleLevels()
  {
    return $this->accessibleLevels;
  }
  public function setMemberSince($memberSince)
  {
    $this->memberSince = $memberSince;
  }
  public function getMemberSince()
  {
    return $this->memberSince;
  }
  public function setMemberSinceCurrentLevel($memberSinceCurrentLevel)
  {
    $this->memberSinceCurrentLevel = $memberSinceCurrentLevel;
  }
  public function getMemberSinceCurrentLevel()
  {
    return $this->memberSinceCurrentLevel;
  }
  public function setMemberTotalDuration($memberTotalDuration)
  {
    $this->memberTotalDuration = $memberTotalDuration;
  }
  public function getMemberTotalDuration()
  {
    return $this->memberTotalDuration;
  }
  public function setMemberTotalDurationCurrentLevel($memberTotalDurationCurrentLevel)
  {
    $this->memberTotalDurationCurrentLevel = $memberTotalDurationCurrentLevel;
  }
  public function getMemberTotalDurationCurrentLevel()
  {
    return $this->memberTotalDurationCurrentLevel;
  }
  public function setPurchasedLevel($purchasedLevel)
  {
    $this->purchasedLevel = $purchasedLevel;
  }
  public function getPurchasedLevel()
  {
    return $this->purchasedLevel;
  }
}
