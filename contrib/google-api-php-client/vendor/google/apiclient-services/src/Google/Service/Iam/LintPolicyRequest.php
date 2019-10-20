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

class Google_Service_Iam_LintPolicyRequest extends Google_Model
{
  protected $bindingType = 'Google_Service_Iam_Binding';
  protected $bindingDataType = '';
  protected $conditionType = 'Google_Service_Iam_Expr';
  protected $conditionDataType = '';
  public $context;
  public $fullResourceName;
  protected $policyType = 'Google_Service_Iam_Policy';
  protected $policyDataType = '';

  /**
   * @param Google_Service_Iam_Binding
   */
  public function setBinding(Google_Service_Iam_Binding $binding)
  {
    $this->binding = $binding;
  }
  /**
   * @return Google_Service_Iam_Binding
   */
  public function getBinding()
  {
    return $this->binding;
  }
  /**
   * @param Google_Service_Iam_Expr
   */
  public function setCondition(Google_Service_Iam_Expr $condition)
  {
    $this->condition = $condition;
  }
  /**
   * @return Google_Service_Iam_Expr
   */
  public function getCondition()
  {
    return $this->condition;
  }
  public function setContext($context)
  {
    $this->context = $context;
  }
  public function getContext()
  {
    return $this->context;
  }
  public function setFullResourceName($fullResourceName)
  {
    $this->fullResourceName = $fullResourceName;
  }
  public function getFullResourceName()
  {
    return $this->fullResourceName;
  }
  /**
   * @param Google_Service_Iam_Policy
   */
  public function setPolicy(Google_Service_Iam_Policy $policy)
  {
    $this->policy = $policy;
  }
  /**
   * @return Google_Service_Iam_Policy
   */
  public function getPolicy()
  {
    return $this->policy;
  }
}
