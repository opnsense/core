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

class Google_Service_Vision_GoogleCloudVisionV1p5beta1TableTableCell extends Google_Model
{
  public $colSpan;
  public $rowSpan;
  public $text;
  protected $textBlockType = 'Google_Service_Vision_GoogleCloudVisionV1p5beta1Block';
  protected $textBlockDataType = '';

  public function setColSpan($colSpan)
  {
    $this->colSpan = $colSpan;
  }
  public function getColSpan()
  {
    return $this->colSpan;
  }
  public function setRowSpan($rowSpan)
  {
    $this->rowSpan = $rowSpan;
  }
  public function getRowSpan()
  {
    return $this->rowSpan;
  }
  public function setText($text)
  {
    $this->text = $text;
  }
  public function getText()
  {
    return $this->text;
  }
  /**
   * @param Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function setTextBlock(Google_Service_Vision_GoogleCloudVisionV1p5beta1Block $textBlock)
  {
    $this->textBlock = $textBlock;
  }
  /**
   * @return Google_Service_Vision_GoogleCloudVisionV1p5beta1Block
   */
  public function getTextBlock()
  {
    return $this->textBlock;
  }
}
