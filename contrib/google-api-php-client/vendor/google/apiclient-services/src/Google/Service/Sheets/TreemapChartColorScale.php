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

class Google_Service_Sheets_TreemapChartColorScale extends Google_Model
{
  protected $maxValueColorType = 'Google_Service_Sheets_Color';
  protected $maxValueColorDataType = '';
  protected $midValueColorType = 'Google_Service_Sheets_Color';
  protected $midValueColorDataType = '';
  protected $minValueColorType = 'Google_Service_Sheets_Color';
  protected $minValueColorDataType = '';
  protected $noDataColorType = 'Google_Service_Sheets_Color';
  protected $noDataColorDataType = '';

  /**
   * @param Google_Service_Sheets_Color
   */
  public function setMaxValueColor(Google_Service_Sheets_Color $maxValueColor)
  {
    $this->maxValueColor = $maxValueColor;
  }
  /**
   * @return Google_Service_Sheets_Color
   */
  public function getMaxValueColor()
  {
    return $this->maxValueColor;
  }
  /**
   * @param Google_Service_Sheets_Color
   */
  public function setMidValueColor(Google_Service_Sheets_Color $midValueColor)
  {
    $this->midValueColor = $midValueColor;
  }
  /**
   * @return Google_Service_Sheets_Color
   */
  public function getMidValueColor()
  {
    return $this->midValueColor;
  }
  /**
   * @param Google_Service_Sheets_Color
   */
  public function setMinValueColor(Google_Service_Sheets_Color $minValueColor)
  {
    $this->minValueColor = $minValueColor;
  }
  /**
   * @return Google_Service_Sheets_Color
   */
  public function getMinValueColor()
  {
    return $this->minValueColor;
  }
  /**
   * @param Google_Service_Sheets_Color
   */
  public function setNoDataColor(Google_Service_Sheets_Color $noDataColor)
  {
    $this->noDataColor = $noDataColor;
  }
  /**
   * @return Google_Service_Sheets_Color
   */
  public function getNoDataColor()
  {
    return $this->noDataColor;
  }
}
