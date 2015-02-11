<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
     * layout information, glyphicon
     * @var string
     */
    private $CssClass = "";

    /**
     * link to url location
     * @var string
     */
    private $Url = "";

    /**
     * parent node, used to mark active nodes
     * @var null|MenuItem
     */
    private $parent = null ;

    /**
     * is this node or any of the child nodes selected
     * @var bool
     */
    private $selected = false;

    /**
     * Find setter for property, ignore case
     * @param string $name property name
     * @return null|string method name
     */
    private function getXmlPropertySetterName($name)
    {
        $class_methods = get_class_methods($this);
        foreach ($class_methods as $method_name) {
            if ("set".strtolower($name) == strtolower($method_name)) {
                return $method_name;
            }
        }

        return null;
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
    }

    /**
     * getter for id field
     * @return item|string
     */
    public function getId()
    {
        return $this->id ;
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
        $this->CssClass = $value ;
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
     * @return bool is this item selected
     */
    public function getSelected()
    {
        return $this->selected;
    }

    /**
     * append node, reuses existing node if it's already there.
     * @param $id item id
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
                $newMenuItem = $node ;
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
            if ($methodName != null) {
                $newMenuItem->$methodName((string)$propvalue);
            }
        }

        if ($isNew) {
            // new item, add to child list
            $orderNum = sprintf("%05d", $newMenuItem->getOrder());
            $this->children[$orderNum."_".$newMenuItem->id] = $newMenuItem;
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
        if ($xmlNode->count() >0) {
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
        $this->selected = true ;
        if ($this->parent != null) {
            $this->parent->select();
        }
    }

    /**
     * set url and all it's parents selected
     * @param string $url target url
     */
    public function toggleSelected($url)
    {
        $this->selected = false ;
        foreach ($this->children as $nodeId => $node) {
            $node->toggleSelected($url);
            if ($node->getUrl() != "") {
                if (strlen($url) >= strlen($node->getUrl()) && $node->getUrl() == substr($url, strlen($url)-strlen($node->getUrl()))) {
                    $node->select();
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
        $properties = array();
        // probe this object for available setters, so we know what to publish to the outside world.
        $prop_exclude_list = array("getXmlPropertySetterName");
        $class_methods = get_class_methods($this);
        foreach ($class_methods as $method_name) {
            if (substr($method_name, 0, 3) == "get" && in_array($method_name, $prop_exclude_list) == false) {
                $properties[$method_name] = substr($method_name, 3);
            }
        }

        // sort by order/id and map getters to array items
        ksort($this->children);
        foreach ($this->children as $key => $node) {
            $result[$node->id] = new \stdClass();
            foreach ($properties as $methodName => $propName) {
                $result[$node->id]->{$propName} = $node->$methodName();
            }
        }

        return $result;
    }
}
