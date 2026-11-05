<?php

/*
 * Copyright (C) 2024-2026 Deciso B.V.
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

class ApiKeyField extends BaseField
{
    protected $internalIsContainer = false;

    /**
     * Always return blank
     * @return string
     */
    public function __toString()
    {
        return "";
    }

    /**
     * unpack api keys from internal string storage
     * @return array
     */
    private function deserialize(): array
    {
        $result = [];
        foreach (array_filter(explode("\n", (string)$this->internalValue)) as $line) {
            $parts = explode("|", $line, 4);
            if (count($parts) >= 2) {
                $result[$parts[0]] = [
                    'key' => $parts[0],
                    'secret' => $parts[1],
                    'created' => $parts[2] ?? '',
                    'descr' => $parts[3] ?? ''
                ];
            }
        }
        return $result;
    }

    /**
     * pack api keys into internal string storage
     * @param array $data
     */
    private function serialize(array $data): void
    {
        $lines = [];
        foreach ($data as $key => $item) {
            $lines[] = sprintf(
                "%s|%s|%s|%s",
                $key,
                $item['secret'] ?? '',
                $item['created'] ?? '',
                $item['descr'] ?? ''
            );
        }
        $this->internalValue = implode("\n", $lines);
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
     * @return array|null with key and crypted secret
     */
    public function get(string $key)
    {
        return $this->deserialize()[$key] ?? null;
    }

    /**
     * @return array list of keys (without secrets)
     */
    public function all()
    {
        $result = [];
        foreach ($this->deserialize() as $key => $item) {
            $result[] = [
                'key' => $key,
                'id' => base64_encode($key),
                'created' => $item['created'],
                'descr' => $item['descr']
            ];
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
        $data = $this->deserialize();
        if (isset($data[$key])) {
            unset($data[$key]);
            $this->serialize($data);
            return true;
        }
        return false;
    }

    /**
     * update api key description
     * @param string $key
     * @param string $value
     * @return bool key found and updated
     */
    public function setDescription(string $key, string $value)
    {
        $data = $this->deserialize();
        if (isset($data[$key])) {
            $data[$key]['descr'] = $value;
            $this->serialize($data);
            return true;
        }
        return false;
    }

    /**
     * generate a new key and return it
     * @return array generated key+secret
     */
    public function add()
    {
        $data = $this->deserialize();
        $result = [
            'key' => base64_encode(random_bytes(60)),
            'secret' => base64_encode(random_bytes(60)),
            'created' => (string)time(),
            'descr' => ''
        ];
        $data[$result['key']] = array_merge($result, ['secret' => crypt($result['secret'], '$6$')]);
        $this->serialize($data);
        return $result;
    }
}
