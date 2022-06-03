<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Firewall\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Firewall
 */
class AliasController extends ApiMutableModelControllerBase
{

    protected static $internalModelName = 'alias';
    protected static $internalModelClass = 'OPNsense\Firewall\Alias';

    /**
     * search aliases
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchItemAction()
    {
        $type = $this->request->get('type');
        $filter_funct = null;
        if (!empty($type)) {
            $filter_funct = function ($record) use ($type) {
                return in_array($record->type, $type);
            };
        }
        return $this->searchBase(
            "aliases.alias",
            array('enabled', 'name', 'description', 'type', 'content', 'current_items', 'last_updated'),
            "name",
            $filter_funct
        );
    }

    /**
     * Update alias with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('aliases.alias.' . $uuid);
        $old_name = $node != null ? (string)$node->name : null;
        if ($old_name !== null && $this->request->isPost() && $this->request->hasPost("alias")) {
            $new_name = $this->request->getPost("alias")['name'];
            if ($new_name != $old_name) {
                // replace aliases, setBase() will synchronise the changes to disk
                $this->getModel()->refactor($old_name, $new_name);
            }
        }
        return $this->setBase("alias", "aliases.alias", $uuid);
    }

    /**
     * Add new alias and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addItemAction()
    {
        return $this->addBase("alias", "aliases.alias");
    }

    /**
     * Retrieve alias settings or return defaults for new one
     * @param $uuid item unique id
     * @return array alias content
     * @throws \ReflectionException when not bound to model
     */
    public function getItemAction($uuid = null)
    {
        $response = $this->getBase("alias", "aliases.alias", $uuid);
        $selected_aliases = array_keys($response['alias']['content']);
        foreach ($this->getModel()->aliasIterator() as $alias) {
            if (!in_array($alias['name'], $selected_aliases)) {
                $response['alias']['content'][$alias['name']] = array(
                  "selected" => 0, "value" => $alias['name']
                );
            }
        }
        return $response;
    }

    /**
     * find the alias uuid by name
     * @param $name alias name
     * @return array uuid
     * @throws \ReflectionException
     */
    public function getAliasUUIDAction($name)
    {
        $node = $this->getModel();
        foreach ($node->aliases->alias->iterateItems() as $key => $alias) {
            if ((string)$alias->name == $name) {
                return array('uuid' => $key);
            }
        }
        return array();
    }

    /**
     * Delete alias by uuid, save contents to tmp for removal on apply
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\UserException when unable to delete
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('aliases.alias.' . $uuid);
        if ($node != null) {
            $uses = $this->getModel()->whereUsed((string)$node->name);
            if (!empty($uses)) {
                $message = "";
                foreach ($uses as $key => $value) {
                    $message .= sprintf("\n[%s] %s", $key, $value);
                }
                $message = sprintf(gettext("Cannot delete alias. Currently in use by %s"), $message);
                throw new \OPNsense\Base\UserException($message, gettext("Alias in use"));
            }
        }
        return $this->delBase("aliases.alias", $uuid);
    }

    /**
     * toggle status
     * @param string $uuid id to toggled
     * @param string|null $enabled set enabled by default
     * @return array status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase("aliases.alias", $uuid, $enabled);
    }

    /**
     * list countries and regions
     * @return array indexed by country code
     */
    public function listCountriesAction()
    {
        $result = array();

        foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/iso3166.tab')) as $line) {
            $line = trim($line);
            if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
                $result[substr($line, 0, 2)] = array(
                    "name" => trim(substr($line, 2, 9999)),
                    "region" => null
                );
            }
        }
        foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/zone.tab')) as $line) {
            if (strlen($line) > 0 && substr($line, 0, 1) == '#') {
                continue;
            }
            $line = explode("\t", $line);
            if (empty($line[0]) || strlen($line[0]) != 2) {
                continue;
            }
            if (empty($line[2]) || strpos($line[2], '/') === false) {
                continue;
            }
            if (!empty($result[$line[0]]) && empty($result[$line[0]]['region'])) {
                $result[$line[0]]['region'] = explode('/', $line[2])[0];
            }
        }
        return $result;
    }

    /**
     * list network alias types
     * @return array indexed by country alias name
     */
    public function listNetworkAliasesAction()
    {
        $result = array();
        foreach ($this->getModel()->aliases->alias->iterateItems() as $alias) {
            if (!in_array((string)$alias->type, ['external', 'port'])) {
                $result[(string)$alias->name] = (string)$alias->name;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * reconfigure aliases
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend->configdRun('template reload OPNsense/Filter');
            $backend->configdRun("filter reload skip_alias");
            $bckresult = json_decode($backend->configdRun("filter refresh_aliases"), true);
            if (!empty($bckresult['messages'])) {
                throw new UserException(implode("\n", $bckresult['messages']), gettext("Alias"));
            }
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * get aliases load stats and table-entries limit
     */
    public function getTableSizeAction()
    {
        return json_decode((new Backend())->configdRun('filter diag table_size'), true);
    }

    /**
     * variant on model.getNodes() returning actual field values
     * @param $parent_node BaseField node to reverse
     */
    private function getRawNodes($parent_node)
    {
        $result = array();
        foreach ($parent_node->iterateItems() as $key => $node) {
            if ($node->isContainer()) {
                $result[$key] = $this->getRawNodes($node);
            } else {
                $result[$key] = (string)$node;
            }
        }
        return $result;
    }

    /**
     * export configured aliases
     */
    public function exportAction()
    {
        if ($this->request->isGet()) {
            // return raw, unescaped since this content is intended for direct download
            $this->response->setContentType('application/json', 'UTF-8');
            $this->response->setContent(json_encode($this->getRawNodes($this->getModel())));
        } else {
            throw new UserException("Unsupported request type");
        }
    }

    /**
     * import delivered aliases in post variable "data", validate all only commit when fully valid.
     */
    public function importAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $result = array("existing" => 0, "new" => 0, "status" => "failed");
            $data = $this->request->getPost("data");
            if (
                is_array($data) && !empty($data['aliases'])
                    && !empty($data['aliases']['alias']) && is_array($data['aliases']['alias'])
            ) {
                Config::getInstance()->lock();

                // save into model
                $uuid_mapping = array();
                foreach ($data['aliases']['alias'] as $uuid => $content) {
                    if (is_array($content) && !empty($content['name'])) {
                        $node = $this->getModel()->getByName($content['name']);
                        if ($node == null) {
                            $node = $this->getModel()->aliases->alias->Add();
                            $result['new'] += 1;
                        } else {
                            $result['existing'] += 1;
                        }
                        foreach ($content as $prop => $value) {
                            $node->$prop = $value;
                        }
                        $uuid_mapping[$node->getAttribute('uuid')] = $uuid;
                    }
                }
                // attach this alias to util class, to avoid recursion issues (aliases used in aliases).
                \OPNsense\Firewall\Util::attachAliasObject($this->getModel());

                // perform validation, record details.
                foreach ($this->getModel()->performValidation() as $msg) {
                    if (empty($result['validations'])) {
                        $result['validations'] = array();
                    }
                    $parts = explode('.', $msg->getField());
                    $uuid = $parts[count($parts) - 2];
                    $fieldname = $parts[count($parts) - 1];
                    $result['validations'][$uuid_mapping[$uuid] . "." . $fieldname] = $msg->getMessage();
                }


                // only persist when valid import
                if (empty($result['validations'])) {
                    $result['status'] = "ok";
                    $this->save();
                } else {
                    $result['status'] = "failed";
                    Config::getInstance()->unlock();
                }
            }
        } else {
            throw new UserException("Unsupported request type");
        }
        return $result;
    }

    /**
     * get geoip settings (and stats)
     */
    public function getGeoIPAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $cnf = Config::getInstance()->object();
            $result[static::$internalModelName] = ['geoip' => array()];
            $node = $this->getModel()->getNodeByReference('geoip');
            if ($node != null) {
                $result[static::$internalModelName]['geoip'] = $node->getNodes();
            }
            // count aliases that depend on GeoIP data
            $result[static::$internalModelName]['geoip']['usages'] = 0;
            foreach ($this->getModel()->aliasIterator() as $alias) {
                if ($alias['type'] == "geoip") {
                    $result[static::$internalModelName]['geoip']['usages']++;
                }
            }
            if (isset($cnf->system->firmware) && !empty($cnf->system->firmware->mirror)) {
                // XXX: we might add some attribute in firmware to store subscription status, since we now only store uri
                $result[static::$internalModelName]['geoip']['subscription'] =
                    strpos($cnf->system->firmware->mirror, "opnsense-update.deciso.com") !== false;
            }

            $result[static::$internalModelName]['geoip']['address_count'] = 0;
            if (file_exists('/usr/local/share/GeoIP/alias.stats')) {
                $stats = json_decode(file_get_contents('/usr/local/share/GeoIP/alias.stats'), true);
                $result[static::$internalModelName]['geoip'] = array_merge(
                    $result[static::$internalModelName]['geoip'],
                    $stats
                );
            }
        }
        return $result;
    }
}
