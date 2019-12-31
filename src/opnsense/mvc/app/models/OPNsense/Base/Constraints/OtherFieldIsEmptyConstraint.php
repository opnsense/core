<?php


namespace OPNsense\Base\Constraints;


class OtherFieldIsEmptyConstraint extends BaseConstraint
{

    /**
     * Executes validation, where the value must be set if another field is
     * set to a specific value and this one is filtered. Configuration example
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $node = $this->getOption('node');
        $field_name = $this->getOption('field');
        $blacklist = $this->getOption('blacklist');
        if (!empty($blacklist)) {
            $blacklist = explode(',', $blacklist);
        }
        if ($node) {
            $parentNode = $node->getParentNode();
            if (!$this->isEmpty($node)) {
                $other_value = (string)$parentNode->$field_name;
                if (!empty($other_value)) {
                    if (!empty($blacklist)) {
                        // check if we have it in our list - if yes, ignore others
                        if (in_array((string)$node, $blacklist)) {
                            $this->appendMessage($validator, $attribute);
                        }
                    } else {
                        // all values are blacklisted
                        $this->appendMessage($validator, $attribute);
                    }
                }
            }
        }
        return true;
    }
}
