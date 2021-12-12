<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Base\Menu;

/**
 * Class MenuItem
 * @package OPNsense\Core\Menu
 */
class MenuItem
{
    /**
     * named array of child items
     * @var array
     */
    private $children  = array();

    /**
     * this items id (xml tag name)
     * @var item|string
     */
    private $id = "";

    /**
     * visible name, default same as id
     * @var null|item
     */
    private $visibleName = null;

    /**
     * sort order in the menu list
     * @var int
     */
    private $sortOrder = 0;

    /**
     * layout information, icon
     * @var string
     */
    private $CssClass = "";

    /**
     * link to url location
     * @var string
     */
    private $Url = "";

    /**
     * link to external page
     * @var string
     */
    private $isExternal = "N";

    /**
     * visibility level, all, hidden, ...
     * @var string
     */
    private $visibility = 'all';

    /**
     * parent node, used to mark active nodes
     * @var null|MenuItem
     */
    private $parent = null;

    /**
     * is this node or any of the child nodes selected
     * @var bool
     */
    private $selected = false;

    /**
     * class method getters
     * @var array
     */
    private static $internalClassGetterNames = null;

    /**
     * class method setters
     * @var array
     */
    private static $internalClassSetterNames = null;

    /**
     * map internal methods to support faster case-insensitive matching
     * @var array|null
     */
    private static $internalClassMethodAliases = null;

    /**
     * Find setter for property, ignore case
     * @param string $name property name
     * @return null|string method name
     */
    private function getXmlPropertySetterName($name)
    {
        if (!isset(self::$internalClassMethodAliases[$name])) {
            self::$internalClassMethodAliases[$name] = null;
            $propKey = strtolower($name);
            foreach (self::$internalClassSetterNames as $methodName => $propValue) {
                if ($propKey == strtolower($propValue)) {
                    self::$internalClassMethodAliases[$name] = $methodName;
                }
            }
        }
        return self::$internalClassMethodAliases[$name];
    }

    /**
     * construct new menu item
     * @param string $id item id / tag name from xml
     * @param null|MenuItem $parent parent node
     */
    public function __construct($id, $parent = null)
    {
        $this->id = $id;
        $this->visibleName = $id;
        $this->parent = $parent;
        $prop_exclude_list = array("getXmlPropertySetterName" => true);
        if (self::$internalClassMethodAliases === null) {
            self::$internalClassMethodAliases = array();
            self::$internalClassSetterNames = array();
            self::$internalClassGetterNames = array();
            // cache method names, get_class_methods() should always return the initial methods.
            // Caching the methods delivers quite some performance at minimal memory cost.
            foreach (get_class_methods($this) as $methodName) {
                if (!isset($prop_exclude_list[$methodName])) {
                    if (substr($methodName, 0, 3) == "get") {
                        self::$internalClassGetterNames[$methodName] = substr($methodName, 3);
                    } elseif (substr($methodName, 0, 3) == "set") {
                        self::$internalClassSetterNames[$methodName] = substr($methodName, 3);
                    }
                }
            }
        }
    }

    /**
     * getter for id field
     * @return item|string
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * set sort order
     * @param $value order number
     */
    public function setOrder($value)
    {
        $this->sortOrder = $value;
    }

    /**
     * set visibility
     * @param $value visibility level
     */
    public function setVisibility($value)
    {
        $this->visibility = $value;
    }

    /**
     * get sort order
     * @return int
     */
    public function getOrder()
    {
        return $this->sortOrder;
    }

    /**
     * setter for visiblename field
     * @param $value
     */
    public function setVisibleName($value)
    {
        $this->visibleName = $value;
    }

    /**
     * getter for visiblename field
     * @return null|item
     */
    public function getVisibleName()
    {
        return $this->visibleName;
    }

    /**
     * setter for cssclass field
     * @param $value
     */
    public function setCssClass($value)
    {
        $this->CssClass = $value;
    }

    /**
     * getter for cssclass
     * @return string
     */
    public function getCssClass()
    {
        return $this->CssClass;
    }

    /**
     * setter for url field
     * @param $value
     */
    public function setUrl($value)
    {
        $this->Url = $value;
    }

    /**
     * getter for url field
     * @return string
     */
    public function getUrl()
    {
        return $this->Url;
    }

    /**
     * setter for isExternal
     * @param $value
     */
    public function setIsExternal($value)
    {
        $this->isExternal = $value;
    }

    /**
     * getter for isExternal
     * @return string
     */
    public function getIsExternal()
    {
        return $this->isExternal;
    }

    /**
     * getter for visibility level
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * is node visible
     * @return bool
     */
    public function isVisible()
    {
        return $this->visibility  != 'delete';
    }

    /**
     * check if this item is selected
     * @return bool is this item selected
     */
    public function getSelected()
    {
        return $this->selected;
    }

    /**
     * append node, reuses existing node if it's already there.
     * @param string $id item id
     * @param array $properties named array property list, there should be setters for every option
     * @return MenuItem
     */
    public function append($id, $properties = array())
    {
        // items should be unique by id, search children for given id first
        $newMenuItem = null;
        $isNew = false;
        foreach ($this->children as $nodeKey => $node) {
            if ($node->getId() == $id) {
                $newMenuItem = $node;
            }
        }
        if ($newMenuItem == null) {
            // create new menu item
            $newMenuItem = new MenuItem($id, $this);
            $isNew = true;
        }

        // set attributes
        foreach ($properties as $propname => $propvalue) {
            $methodName = $newMenuItem->getXmlPropertySetterName($propname);
            if ($methodName !== null) {
                $newMenuItem->$methodName((string)$propvalue);
            }
        }

        $orderNum = sprintf("%05d", $newMenuItem->getOrder());
        $idx = $orderNum . "_" . $newMenuItem->id;
        if ($isNew) {
            // new item, add to child list
            $this->children[$idx] = $newMenuItem;
        } else {
            // update existing, if the sort order changed, move this child into the new position
            foreach ($this->children as $key => $node) {
                if ($node == $newMenuItem && $key != $idx) {
                    unset($this->children[$key]);
                    $this->children[$idx] = $newMenuItem;
                    break;
                }
            }
        }

        return $newMenuItem;
    }

    /**
     * add simple xml node
     * @param $xmlNode
     */
    public function addXmlNode($xmlNode)
    {
        // copy properties from xml node attributes
        $properties = array();
        foreach ($xmlNode->attributes() as $attrKey => $attrValue) {
            $properties[$attrKey] = (string)$attrValue;
        }

        // add to this node
        $newMenuItem = $this->append($xmlNode->getName(), $properties);

        // when there are child nodes, add them to the new menu item
        if ($xmlNode->count() > 0) {
            foreach ($xmlNode as $key => $node) {
                $newMenuItem->addXmlNode($node);
            }
        }
    }

    /**
     * set node and all subnodes selected
     */
    public function select()
    {
        $this->selected = true;
        if ($this->parent != null) {
            $this->parent->select();
        }
    }

    /**
     * set url and all its parents selected
     * @param string $url target url
     */
    public function toggleSelected($url)
    {
        $this->selected = false;
        foreach ($this->children as $nodeId => &$node) {
            if ($node->isVisible()) {
                $node->toggleSelected($url);
                if ($node->getUrl() != "") {
                    // hash part isn't available on server end
                    $menuItemUrl = explode("#", $node->getUrl())[0];
                    $match = str_replace(array(".", "*","?", "@"), array("\.", ".*","\?", "\@"), $menuItemUrl);
                    if (preg_match("@^{$match}$@", "{$url}")) {
                        $node->select();
                    }
                }
            }
        }
    }

    /**
     * Recursive method to retrieve a simple ordered structure of all menu items
     * @return array named array containing menu items as simple objects to keep the api cleaner for our templates
     */
    public function getChildren()
    {
        $result = array();
        // sort by order/id and map getters to array items
        foreach ($this->children as $key => &$node) {
            if ($node->isVisible()) {
                $result[$key] = new \stdClass();
                foreach (self::$internalClassGetterNames as $methodName => $propName) {
                    $result[$key]->{$propName} = $node->$methodName();
                }
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * find node by id/tag name, ignore case.
     * @param $id id / tagname
     * @return null|MenuItem
     */
    public function findNodeById($id)
    {
        foreach ($this->children as $key => &$node) {
            if ($node->isVisible() && strtolower($node->getId()) == strtolower($id)) {
                return $node;
            }
        }
        return null;
    }
}
