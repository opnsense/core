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

class Google_Service_CloudSearch_RestrictItem extends Google_Model
{
  protected $driveFollowUpRestrictType = 'Google_Service_CloudSearch_DriveFollowUpRestrict';
  protected $driveFollowUpRestrictDataType = '';
  protected $driveLocationRestrictType = 'Google_Service_CloudSearch_DriveLocationRestrict';
  protected $driveLocationRestrictDataType = '';
  protected $driveMimeTypeRestrictType = 'Google_Service_CloudSearch_DriveMimeTypeRestrict';
  protected $driveMimeTypeRestrictDataType = '';
  protected $driveTimeSpanRestrictType = 'Google_Service_CloudSearch_DriveTimeSpanRestrict';
  protected $driveTimeSpanRestrictDataType = '';
  protected $gmailActionRestrictType = 'Google_Service_CloudSearch_GmailActionRestrict';
  protected $gmailActionRestrictDataType = '';
  protected $gmailAttachmentRestrictType = 'Google_Service_CloudSearch_GmailAttachmentRestrict';
  protected $gmailAttachmentRestrictDataType = '';
  protected $gmailFolderRestrictType = 'Google_Service_CloudSearch_GmailFolderRestrict';
  protected $gmailFolderRestrictDataType = '';
  protected $gmailIntelligentRestrictType = 'Google_Service_CloudSearch_GmailIntelligentRestrict';
  protected $gmailIntelligentRestrictDataType = '';
  protected $gmailTimeRestrictType = 'Google_Service_CloudSearch_GmailTimeRestrict';
  protected $gmailTimeRestrictDataType = '';
  public $searchOperator;

  /**
   * @param Google_Service_CloudSearch_DriveFollowUpRestrict
   */
  public function setDriveFollowUpRestrict(Google_Service_CloudSearch_DriveFollowUpRestrict $driveFollowUpRestrict)
  {
    $this->driveFollowUpRestrict = $driveFollowUpRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_DriveFollowUpRestrict
   */
  public function getDriveFollowUpRestrict()
  {
    return $this->driveFollowUpRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_DriveLocationRestrict
   */
  public function setDriveLocationRestrict(Google_Service_CloudSearch_DriveLocationRestrict $driveLocationRestrict)
  {
    $this->driveLocationRestrict = $driveLocationRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_DriveLocationRestrict
   */
  public function getDriveLocationRestrict()
  {
    return $this->driveLocationRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_DriveMimeTypeRestrict
   */
  public function setDriveMimeTypeRestrict(Google_Service_CloudSearch_DriveMimeTypeRestrict $driveMimeTypeRestrict)
  {
    $this->driveMimeTypeRestrict = $driveMimeTypeRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_DriveMimeTypeRestrict
   */
  public function getDriveMimeTypeRestrict()
  {
    return $this->driveMimeTypeRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_DriveTimeSpanRestrict
   */
  public function setDriveTimeSpanRestrict(Google_Service_CloudSearch_DriveTimeSpanRestrict $driveTimeSpanRestrict)
  {
    $this->driveTimeSpanRestrict = $driveTimeSpanRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_DriveTimeSpanRestrict
   */
  public function getDriveTimeSpanRestrict()
  {
    return $this->driveTimeSpanRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_GmailActionRestrict
   */
  public function setGmailActionRestrict(Google_Service_CloudSearch_GmailActionRestrict $gmailActionRestrict)
  {
    $this->gmailActionRestrict = $gmailActionRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_GmailActionRestrict
   */
  public function getGmailActionRestrict()
  {
    return $this->gmailActionRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_GmailAttachmentRestrict
   */
  public function setGmailAttachmentRestrict(Google_Service_CloudSearch_GmailAttachmentRestrict $gmailAttachmentRestrict)
  {
    $this->gmailAttachmentRestrict = $gmailAttachmentRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_GmailAttachmentRestrict
   */
  public function getGmailAttachmentRestrict()
  {
    return $this->gmailAttachmentRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_GmailFolderRestrict
   */
  public function setGmailFolderRestrict(Google_Service_CloudSearch_GmailFolderRestrict $gmailFolderRestrict)
  {
    $this->gmailFolderRestrict = $gmailFolderRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_GmailFolderRestrict
   */
  public function getGmailFolderRestrict()
  {
    return $this->gmailFolderRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_GmailIntelligentRestrict
   */
  public function setGmailIntelligentRestrict(Google_Service_CloudSearch_GmailIntelligentRestrict $gmailIntelligentRestrict)
  {
    $this->gmailIntelligentRestrict = $gmailIntelligentRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_GmailIntelligentRestrict
   */
  public function getGmailIntelligentRestrict()
  {
    return $this->gmailIntelligentRestrict;
  }
  /**
   * @param Google_Service_CloudSearch_GmailTimeRestrict
   */
  public function setGmailTimeRestrict(Google_Service_CloudSearch_GmailTimeRestrict $gmailTimeRestrict)
  {
    $this->gmailTimeRestrict = $gmailTimeRestrict;
  }
  /**
   * @return Google_Service_CloudSearch_GmailTimeRestrict
   */
  public function getGmailTimeRestrict()
  {
    return $this->gmailTimeRestrict;
  }
  public function setSearchOperator($searchOperator)
  {
    $this->searchOperator = $searchOperator;
  }
  public function getSearchOperator()
  {
    return $this->searchOperator;
  }
}
