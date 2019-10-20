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

class Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1ConversationModel extends Google_Collection
{
  protected $collection_key = 'datasets';
  protected $articleSuggestionModelMetadataType = 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1ArticleSuggestionModelMetadata';
  protected $articleSuggestionModelMetadataDataType = '';
  public $createTime;
  protected $datasetsType = 'Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1InputDataset';
  protected $datasetsDataType = 'array';
  public $displayName;
  public $name;
  public $state;

  /**
   * @param Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1ArticleSuggestionModelMetadata
   */
  public function setArticleSuggestionModelMetadata(Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1ArticleSuggestionModelMetadata $articleSuggestionModelMetadata)
  {
    $this->articleSuggestionModelMetadata = $articleSuggestionModelMetadata;
  }
  /**
   * @return Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1ArticleSuggestionModelMetadata
   */
  public function getArticleSuggestionModelMetadata()
  {
    return $this->articleSuggestionModelMetadata;
  }
  public function setCreateTime($createTime)
  {
    $this->createTime = $createTime;
  }
  public function getCreateTime()
  {
    return $this->createTime;
  }
  /**
   * @param Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1InputDataset
   */
  public function setDatasets($datasets)
  {
    $this->datasets = $datasets;
  }
  /**
   * @return Google_Service_Dialogflow_GoogleCloudDialogflowV2beta1InputDataset
   */
  public function getDatasets()
  {
    return $this->datasets;
  }
  public function setDisplayName($displayName)
  {
    $this->displayName = $displayName;
  }
  public function getDisplayName()
  {
    return $this->displayName;
  }
  public function setName($name)
  {
    $this->name = $name;
  }
  public function getName()
  {
    return $this->name;
  }
  public function setState($state)
  {
    $this->state = $state;
  }
  public function getState()
  {
    return $this->state;
  }
}
