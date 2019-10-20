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

class Google_Service_Vision_GoogleCloudVisionV1p5beta1KeyValuePair extends Google_Model
{
  public $key;
  protected $keyBlockType = 'Google_Service_Vision_GoogleCloudVisionV1p5beta1Block';
  protected $keyBlockDataType = '';
  public $normalizedKey;
  protected $valueBlockType = 'Google_Service_Vision_GoogleCloudVisionV1p5beta1Block';
  protected $valueBlockDataType = '';
  public $valueType;

  public function setKey($key)
  {
    $this->key = $key;
  }
  public function getKey()
  {
    return $this->key;
  }
  /**
   * @param Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function setKeyBlock(Google_Service_Vision_GoogleCloudVisionV1p5beta1Block $keyBlock)
  {
    $this->keyBlock = $keyBlock;
  }
  /**
   * @return Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function getKeyBlock()
  {
    return $this->keyBlock;
  }
  public function setNormalizedKey($normalizedKey)
  {
    $this->normalizedKey = $normalizedKey;
  }
  public function getNormalizedKey()
  {
    return $this->normalizedKey;
  }
  /**
   * @param Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function setValueBlock(Google_Service_Vision_GoogleCloudVisionV1p5beta1Block $valueBlock)
  {
    $this->valueBlock = $valueBlock;
  }
  /**
   * @return Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function getValueBlock()
  {
    return $this->valueBlock;
  }
  public function setValueType($valueType)
  {
    $this->valueType = $valueType;
  }
  public function getValueType()
  {
    return $this->valueType;
  }
}
