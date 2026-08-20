<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Base\FieldTypes;

class JsonAuditField extends JsonField implements IStructuredInput
{
    private const SCHEMA = [
        'created' => [
            'username' => '',
            'time' => '',
            'description' => '',
        ],
        'updated' => [
            'username' => '',
            'time' => '',
            'description' => '',
        ],
        'userdata' => [
            'note' => '',
        ],
    ];

    public function setValue($value)
    {
        if (is_a($value, 'SimpleXMLElement')) {
            return parent::setValue($value); /* only during loading */
        }

        /* Only userdata is user supplied, rest is ignored intentionally */
        if (is_array($value) && array_key_exists('note', $value['userdata'] ?? [])) {
            $metadata = array_replace_recursive(
                self::SCHEMA,
                $this->deserialize()
            );

            $metadata['userdata']['note'] = (string)$value['userdata']['note'];
            $this->serialize($metadata);
        }
    }

    /**
     * Store audit data without using the protected setValue() path.
     */
    public function serialize(array $value): bool
    {
        // normalize a bit for the user supplied note data
        $tmp = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($tmp) > $this->maxsize) {
            return false;
        }

        parent::setValue(base64_encode($tmp));
        return true;
    }

    public function update($username, $description)
    {
        $metadata = array_replace_recursive(
            self::SCHEMA,
            $this->deserialize()
        );

        $entry = [
            'username' => $username,
            'time' => sprintf('%0.2f', microtime(true)),
            'description' => $description,
        ];

        if (empty($metadata['created']['time'])) {
            $metadata['created'] = $entry;
        }

        $metadata['updated'] = $entry;

        $this->serialize($metadata);
    }

    public function getNodeData()
    {
        // Always return the full structure to prevent stale values in reused forms.
        return array_replace_recursive(
            self::SCHEMA,
            $this->deserialize()
        );
    }

    /**
     * Migrate legacy created/updated fields into the audit JSON structure.
     * Compatibility code. Remove together with the legacy created/updated model fields (e.g. in DNat.xml).
     */
    protected function actionPostLoadingEvent()
    {
        if (!$this->isEmpty()) {
            return;
        }

        $parent = $this->getParentNode();
        if ($parent === null) {
            return;
        }

        $metadata = self::SCHEMA;

        foreach (['created', 'updated'] as $key) {
            if (isset($parent->$key)) {
                $metadata[$key] = [
                    'username' => $parent->$key->username->getValue(),
                    'time' => $parent->$key->time->getValue(),
                    'description' => $parent->$key->description->getValue(),
                ];
            }
        }

        if (!empty($metadata['created']['time']) || !empty($metadata['updated']['time'])) {
            $this->serialize($metadata);
        }

        // We do not remove old data, it will naturally disappear once the old fields are removed
    }
}
