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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\ACL;
use OPNsense\Core\Config;
use SimpleXMLElement;

class DashboardController extends ApiControllerBase
{
    private $metadataFileLocation = "/usr/local/opnsense/www/js/widgets/Metadata";
    private $acl = null;

    public function __construct()
    {
        $this->acl = new ACL();
    }

    private function canAccessEndpoints($endpoints)
    {
        foreach ($endpoints as $endpoint) {
            if (!$this->acl->isPageAccessible($this->getUserName(), $endpoint)) {
                return false;
            }
        }

        return true;
    }

    private function getMetadata()
    {
        $combinedXml = new \DOMDocument('1.0');
        $root = $combinedXml->createElement('metadata');
        $combinedXml->appendChild($root);
        foreach (glob($this->metadataFileLocation . '/*.xml') as $file) {
            $metadataXml = simplexml_load_file($file);
            if ($metadataXml === false) {
                // not a valid xml file
                continue;
            }

            if ($metadataXml->getName() !== "metadata") {
                // wrong type
                continue;
            }

            $node = dom_import_simplexml($metadataXml);
            $node = $root->ownerDocument->importNode($node, true);
            $root->appendChild($node);
        }

        return simplexml_import_dom($combinedXml);
    }

    private function getDefaultDashboard()
    {
        return [
            'options' => [],
            'widgets' => [
                ['id' => 'systeminformation', 'x' => 0, 'y' => 0, 'w' => 2],
                ['id' => 'memory', 'x' => 2, 'y' => 0],
                ['id' => 'disk', 'x' => 3, 'y' => 0],
                ['id' => 'interfacestatistics', 'x' => 4, 'y' => 0, 'w' => 4],
                ['id' => 'firewall', 'x' => 8, 'y' => 0, 'w' => 4],
                ['id' => 'gateways', 'x' => 2, 'y' => 1, 'w' => 2],
                ['id' => 'services', 'x' => 4, 'y' => 1, 'w' => '4'],
                ['id' => 'traffic', 'x' => 8, 'y' => 1, 'w' => 4],
                ['id' => 'cpu', 'x' => 0, 'y' => 1, 'w' => 2],
                ['id' => 'announcements', 'x' => 2, 'y' => 2, 'w' => 2],
            ]
        ];
    }

    public function getDashboardAction()
    {
        $result = [];
        $dashboard = null;

        $config = Config::getInstance()->object();
        foreach ($config->system->user as $node) {
            if ($this->getUserName() === (string)$node->name) {
                // json_decode returns null if json is invalid
                $dashboard = json_decode(base64_decode((string)$node->dashboard), true);
                break;
            }
        }

        if (empty($dashboard)) {
            $dashboard = $this->getDefaultDashboard();
        }

        $result['modules'] = [];
        $metadata = $this->getMetadata();
        foreach ($metadata as $md) {
            foreach ($md as $widgetId => $metadataAttributes) {
                $widgetId = (string)$widgetId;
                $fname = (string)$metadataAttributes->filename;
                $link = (string)$metadataAttributes->link;
                $endpoints = (array)($metadataAttributes->endpoints->endpoint ?? []);
                $translations = (array)($metadataAttributes->translations ?? []);

                if (!empty($link) && !$this->canAccessEndpoints([$link])) {
                    $link = '';
                }

                if (!$this->canAccessEndpoints($endpoints)) {
                    continue;
                }

                if (!file_exists('/usr/local/opnsense/www/js/widgets/' . $fname)) {
                    continue;
                }

                foreach ($translations as $key => $value) {
                    $translations[$key] = gettext($value);
                }

                $result['modules'][] = [
                    'id' => $widgetId,
                    'module' => $fname,
                    'link' => $link,
                    'translations' => $translations
                ];
            }
        }

        // filter widgets according to metadata
        $moduleIds = array_column($result['modules'], 'id');
        $filteredWidgets = array_filter($dashboard['widgets'], function ($widget) use ($moduleIds) {
            return in_array($widget['id'], $moduleIds);
        });
        $dashboard['widgets'] = array_values($filteredWidgets);
        $result['dashboard'] = $dashboard;

        return $result;
    }

    public function saveWidgetsAction()
    {
        $result = ['result' => 'failed'];

        if ($this->request->isPost() && $this->request->hasPost('widgets')) {
            $dashboard = json_encode($this->request->getPost());
            if (strlen($dashboard) > (1024 * 1024)) {
                // prevent saving large blobs of data
                $result['message'] = 'dashboard size limit reached';
                return $result;
            }

            $encoded = base64_encode($dashboard);
            $config = Config::getInstance()->object();
            $name = $this->getUserName();
            foreach ($config->system->user as $node) {
                if ($name === (string)$node->name) {
                    $node->dashboard = $encoded;
                    Config::getInstance()->save();
                    $result = ['result' => 'saved'];
                    break;
                }
            }
        }

        return $result;
    }

    public function restoreDefaultsAction()
    {
        $result = ['result' => 'failed'];

        if ($this->request->isPost()) {
            $config = Config::getInstance()->object();
            $name = $this->getUserName();

            foreach ($config->system->user as $node) {
                if ($name === (string)$node->name) {
                    $node->dashboard = null;
                    Config::getInstance()->save();
                    $result = ['result' => 'saved'];
                    break;
                }
            }
        }

        return $result;
    }

    public function productInfoFeedAction()
    {
        $result = ['items' => []];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://forum.opnsense.org/index.php?board=11.0&action=.xml;limit=5;type=rss2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $payload = @simplexml_load_string($output);
        if (empty($payload)) {
            return $result;
        }
        foreach ($payload->channel->children() as $key => $node) {
            if ($key == 'item') {
                $result['items'][] = [
                    'title' => (string)$node->title,
                    'description' => (string)$node->description,
                    'link' => (string)$node->link,
                    'pubDate' => (string)$node->pubDate,
                    'guid' => (string)$node->guid
                ];
            }
        }
        return $result;
    }

    public function pictureAction()
    {
        $result = ['result' => 'failed'];
        $config = Config::getInstance()->object();
        if (!empty($config->system->picture) && !empty($config->system->picture_filename)) {
            $ext = pathinfo((string)$config->system->picture_filename, PATHINFO_EXTENSION);
            if (empty($ext)) {
                return $result;
            }
            return [
                'result' => 'ok',
                'mime' => 'image/' . $ext,
                'picture' => (string)$config->system->picture,
            ];
        }

        return $result;
    }
}
