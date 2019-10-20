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

class Google_Service_Docs_SectionStyle extends Google_Collection
{
  protected $collection_key = 'columnProperties';
  protected $columnPropertiesType = 'Google_Service_Docs_SectionColumnProperties';
  protected $columnPropertiesDataType = 'array';
  public $columnSeparatorStyle;
  public $contentDirection;

  /**
   * @param Google_Service_Docs_SectionColumnProperties
   */
  public function setColumnProperties($columnProperties)
  {
    $this->columnProperties = $columnProperties;
  }
  /**
   * @return Google_Service_Docs_SectionColumnProperties
   */
  public function getColumnProperties()
  {
    return $this->columnProperties;
  }
  public function setColumnSeparatorStyle($columnSeparatorStyle)
  {
    $this->columnSeparatorStyle = $columnSeparatorStyle;
  }
  public function getColumnSeparatorStyle()
  {
    return $this->columnSeparatorStyle;
  }
  public function setContentDirection($contentDirection)
  {
    $this->contentDirection = $contentDirection;
  }
  public function getContentDirection()
  {
    return $this->contentDirection;
  }
}
