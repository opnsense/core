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

class Google_Service_CivicInfo_LivegraphBacktraceRecordInfo extends Google_Model
{
  public $dataSourcePublishMsec;
  public $expId;
  protected $expInfoType = 'Google_Service_CivicInfo_LivegraphBacktraceRecordInfoExpInfo';
  protected $expInfoDataType = '';
  public $isRecon;
  public $isWlmThrottled;
  public $numberOfTriples;
  public $priority;
  public $process;
  public $proxyReceiveMsec;
  public $proxySentMsec;
  public $recordId;
  public $shouldMonitorLatency;
  public $subscriberReceiveMsec;
  public $topicBuildFinishMsec;
  public $topicBuildStartMsec;
  public $version;

  public function setDataSourcePublishMsec($dataSourcePublishMsec)
  {
    $this->dataSourcePublishMsec = $dataSourcePublishMsec;
  }
  public function getDataSourcePublishMsec()
  {
    return $this->dataSourcePublishMsec;
  }
  public function setExpId($expId)
  {
    $this->expId = $expId;
  }
  public function getExpId()
  {
    return $this->expId;
  }
  /**
   * @param Google_Service_CivicInfo_LivegraphBacktraceRecordInfoExpInfo
   */
  public function setExpInfo(Google_Service_CivicInfo_LivegraphBacktraceRecordInfoExpInfo $expInfo)
  {
    $this->expInfo = $expInfo;
  }
  /**
   * @return Google_Service_CivicInfo_LivegraphBacktraceRecordInfoExpInfo
   */
  public function getExpInfo()
  {
    return $this->expInfo;
  }
  public function setIsRecon($isRecon)
  {
    $this->isRecon = $isRecon;
  }
  public function getIsRecon()
  {
    return $this->isRecon;
  }
  public function setIsWlmThrottled($isWlmThrottled)
  {
    $this->isWlmThrottled = $isWlmThrottled;
  }
  public function getIsWlmThrottled()
  {
    return $this->isWlmThrottled;
  }
  public function setNumberOfTriples($numberOfTriples)
  {
    $this->numberOfTriples = $numberOfTriples;
  }
  public function getNumberOfTriples()
  {
    return $this->numberOfTriples;
  }
  public function setPriority($priority)
  {
    $this->priority = $priority;
  }
  public function getPriority()
  {
    return $this->priority;
  }
  public function setProcess($process)
  {
    $this->process = $process;
  }
  public function getProcess()
  {
    return $this->process;
  }
  public function setProxyReceiveMsec($proxyReceiveMsec)
  {
    $this->proxyReceiveMsec = $proxyReceiveMsec;
  }
  public function getProxyReceiveMsec()
  {
    return $this->proxyReceiveMsec;
  }
  public function setProxySentMsec($proxySentMsec)
  {
    $this->proxySentMsec = $proxySentMsec;
  }
  public function getProxySentMsec()
  {
    return $this->proxySentMsec;
  }
  public function setRecordId($recordId)
  {
    $this->recordId = $recordId;
  }
  public function getRecordId()
  {
    return $this->recordId;
  }
  public function setShouldMonitorLatency($shouldMonitorLatency)
  {
    $this->shouldMonitorLatency = $shouldMonitorLatency;
  }
  public function getShouldMonitorLatency()
  {
    return $this->shouldMonitorLatency;
  }
  public function setSubscriberReceiveMsec($subscriberReceiveMsec)
  {
    $this->subscriberReceiveMsec = $subscriberReceiveMsec;
  }
  public function getSubscriberReceiveMsec()
  {
    return $this->subscriberReceiveMsec;
  }
  public function setTopicBuildFinishMsec($topicBuildFinishMsec)
  {
    $this->topicBuildFinishMsec = $topicBuildFinishMsec;
  }
  public function getTopicBuildFinishMsec()
  {
    return $this->topicBuildFinishMsec;
  }
  public function setTopicBuildStartMsec($topicBuildStartMsec)
  {
    $this->topicBuildStartMsec = $topicBuildStartMsec;
  }
  public function getTopicBuildStartMsec()
  {
    return $this->topicBuildStartMsec;
  }
  public function setVersion($version)
  {
    $this->version = $version;
  }
  public function getVersion()
  {
    return $this->version;
  }
}
