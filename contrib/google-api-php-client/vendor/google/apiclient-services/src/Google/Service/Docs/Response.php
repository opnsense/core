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

class Google_Service_Docs_Response extends Google_Model
{
  protected $createNamedRangeType = 'Google_Service_Docs_CreateNamedRangeResponse';
  protected $createNamedRangeDataType = '';
  protected $insertInlineImageType = 'Google_Service_Docs_InsertInlineImageResponse';
  protected $insertInlineImageDataType = '';
  protected $insertInlineSheetsChartType = 'Google_Service_Docs_InsertInlineSheetsChartResponse';
  protected $insertInlineSheetsChartDataType = '';
  protected $replaceAllTextType = 'Google_Service_Docs_ReplaceAllTextResponse';
  protected $replaceAllTextDataType = '';

  /**
   * @param Google_Service_Docs_CreateNamedRangeResponse
   */
  public function setCreateNamedRange(Google_Service_Docs_CreateNamedRangeResponse $createNamedRange)
  {
    $this->createNamedRange = $createNamedRange;
  }
  /**
   * @return Google_Service_Docs_CreateNamedRangeResponse
   */
  public function getCreateNamedRange()
  {
    return $this->createNamedRange;
  }
  /**
   * @param Google_Service_Docs_InsertInlineImageResponse
   */
  public function setInsertInlineImage(Google_Service_Docs_InsertInlineImageResponse $insertInlineImage)
  {
    $this->insertInlineImage = $insertInlineImage;
  }
  /**
   * @return Google_Service_Docs_InsertInlineImageResponse
   */
  public function getInsertInlineImage()
  {
    return $this->insertInlineImage;
  }
  /**
   * @param Google_Service_Docs_InsertInlineSheetsChartResponse
   */
  public function setInsertInlineSheetsChart(Google_Service_Docs_InsertInlineSheetsChartResponse $insertInlineSheetsChart)
  {
    $this->insertInlineSheetsChart = $insertInlineSheetsChart;
  }
  /**
   * @return Google_Service_Docs_InsertInlineSheetsChartResponse
   */
  public function getInsertInlineSheetsChart()
  {
    return $this->insertInlineSheetsChart;
  }
  /**
   * @param Google_Service_Docs_ReplaceAllTextResponse
   */
  public function setReplaceAllText(Google_Service_Docs_ReplaceAllTextResponse $replaceAllText)
  {
    $this->replaceAllText = $replaceAllText;
  }
  /**
   * @return Google_Service_Docs_ReplaceAllTextResponse
   */
  public function getReplaceAllText()
  {
    return $this->replaceAllText;
  }
}
