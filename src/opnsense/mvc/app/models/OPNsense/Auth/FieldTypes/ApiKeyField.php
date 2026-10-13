<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Auth\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;

class ApiKeyField extends BaseField
{
    protected $internalIsContainer = false;
    private $pendingDescr = null;

    /**
     * Always return blank
     * @return string
     */
    public function __toString()
    {
        return "";
    }

    private function isValidDescr(string $descr): bool
    {
        return preg_match('/\A[^|\p{Cc}\x{FFFE}\x{FFFF}]{0,255}\z/u', $descr) === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        $validators = [];
        if ($this->pendingDescr !== null) {
            $validators[] = new CallbackValidator(['callback' => function () {
                if (!$this->isValidDescr($this->pendingDescr)) {
                    return [gettext('Description can be up to 255 characters and cannot contain "|" or control characters such as line breaks.')];
                }
                return [];
            }]);
        }
        return $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (is_a($value, 'SimpleXMLElement') && isset($value->item)) {
            /* auto convert to simple text blob */
            $tmp = [];
            foreach ($value->children() as $child) {
                $tmp[] = sprintf("%s|%s", $child->key, $child->secret);
            }
            return parent::setValue(implode("\n", $tmp));
        } elseif (!empty($value)) {
            /* update only */
            return parent::setValue($value);
        }
    }

    /**
     * get api key + secret
     * @param string $key
     * @return array with key and crypted secret
     */
    public function get(string $key)
    {
        foreach (array_filter(explode("\n", $this->internalValue)) as $line) {
            $parts = explode("|", $line, 4);
            if (count($parts) >= 2 && $parts[0] == $key) {
                return [
                    "key" => $parts[0],
                    "secret" => $parts[1],
                    "created" => $parts[2] ?? '',
                    "descr" => $parts[3] ?? ''
                ];
            }
        }
        return null;
    }

    /**
     * @return array list of keys (without secrets)
     */
    public function all()
    {
        $result = [];
        foreach (array_filter(explode("\n", $this->internalValue)) as $line) {
            $parts = explode("|", $line, 4);
            if (count($parts) >= 2) {
                $result[] = [
                    'key' => $parts[0],
                    'id' => base64_encode($parts[0]),
                    'created' => $parts[2] ?? '',
                    'descr' => $parts[3] ?? ''
                ];
            }
        }
        return $result;
    }

    /**
     * remove api key
     * @param string $key
     * @return bool key found
     */
    public function del(string $key)
    {
        $found = false;
        $tmp = '';
        $searchkey = sprintf("%s|", $key);
        foreach (array_filter(explode("\n", $this->internalValue)) as $line) {
            if (strpos($line, $searchkey) === 0) {
                $found = true;
            } else {
                $tmp .= sprintf("%s\n", $line);
            }
        }
        $this->internalValue = $tmp;
        return $found;
    }

    /**
     * update api key description
     * @param string $key
     * @param string $descr
     * @return bool key found and updated
     */
    public function setDescr(string $key, string $descr = '')
    {
        $this->pendingDescr = $descr;
        $this->setChanged();
        if (!$this->isValidDescr($descr)) {
            return false;
        }
        $found = false;
        $lines = [];
        foreach (array_filter(explode("\n", $this->internalValue)) as $line) {
            $parts = explode("|", $line, 4);
            if (count($parts) >= 2 && $parts[0] === $key) {
                $found = true;
                $lines[] = sprintf("%s|%s|%s|%s", $parts[0], $parts[1], $parts[2] ?? '', $descr);
            } else {
                $lines[] = $line;
            }
        }
        if ($found) {
            $this->internalValue = implode("\n", $lines);
        }
        return $found;
    }

    /**
     * generate a new key and return it
     * @param string $descr optional description
     * @return array|null generated key+secret
     */
    public function add(string $descr = '')
    {
        $this->pendingDescr = $descr;
        $this->setChanged();
        if (!$this->isValidDescr($descr)) {
            return null;
        }
        $result = [
            'key' => base64_encode(random_bytes(60)),
            'secret' => base64_encode(random_bytes(60)),
            'created' => (string)time(),
            'descr' => $descr
        ];
        $new_items = array_merge(
            array_filter(explode("\n", $this->internalValue)),
            [sprintf("%s|%s|%s|%s", $result['key'], crypt($result['secret'], '$6$'), $result['created'], $result['descr'])]
        );

        $this->internalValue = implode("\n", $new_items);
        return $result;
    }
}
