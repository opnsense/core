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

class Google_Service_CloudHealthcare_Annotation extends Google_Model
{
  protected $annotationSourceType = 'Google_Service_CloudHealthcare_AnnotationSource';
  protected $annotationSourceDataType = '';
  protected $imageAnnotationType = 'Google_Service_CloudHealthcare_ImageAnnotation';
  protected $imageAnnotationDataType = '';
  public $name;
  protected $resourceAnnotationType = 'Google_Service_CloudHealthcare_ResourceAnnotation';
  protected $resourceAnnotationDataType = '';
  protected $textAnnotationType = 'Google_Service_CloudHealthcare_SensitiveTextAnnotation';
  protected $textAnnotationDataType = '';

  /**
   * @param Google_Service_CloudHealthcare_AnnotationSource
   */
  public function setAnnotationSource(Google_Service_CloudHealthcare_AnnotationSource $annotationSource)
  {
    $this->annotationSource = $annotationSource;
  }
  /**
   * @return Google_Service_CloudHealthcare_AnnotationSource
   */
  public function getAnnotationSource()
  {
    return $this->annotationSource;
  }
  /**
   * @param Google_Service_CloudHealthcare_ImageAnnotation
   */
  public function setImageAnnotation(Google_Service_CloudHealthcare_ImageAnnotation $imageAnnotation)
  {
    $this->imageAnnotation = $imageAnnotation;
  }
  /**
   * @return Google_Service_CloudHealthcare_ImageAnnotation
   */
  public function getImageAnnotation()
  {
    return $this->imageAnnotation;
  }
  public function setName($name)
  {
    $this->name = $name;
  }
  public function getName()
  {
    return $this->name;
  }
  /**
   * @param Google_Service_CloudHealthcare_ResourceAnnotation
   */
  public function setResourceAnnotation(Google_Service_CloudHealthcare_ResourceAnnotation $resourceAnnotation)
  {
    $this->resourceAnnotation = $resourceAnnotation;
  }
  /**
   * @return Google_Service_CloudHealthcare_ResourceAnnotation
   */
  public function getResourceAnnotation()
  {
    return $this->resourceAnnotation;
  }
  /**
   * @param Google_Service_CloudHealthcare_SensitiveTextAnnotation
   */
  public function setTextAnnotation(Google_Service_CloudHealthcare_SensitiveTextAnnotation $textAnnotation)
  {
    $this->textAnnotation = $textAnnotation;
  }
  /**
   * @return Google_Service_CloudHealthcare_SensitiveTextAnnotation
   */
  public function getTextAnnotation()
  {
    return $this->textAnnotation;
  }
}
