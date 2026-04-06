<?php

/*
 * Copyright (C) 2015-2025 Franco Fichtner <franco@opnsense.org>
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

use ReflectionClass;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

/**
 * Class MenuSystem
 * @package OPNsense\Base\Menu
 */
class MenuSystem
{
    /**
     * @var null|MenuItem root node
     */
    private $root = null;

    /**
     * @var string location to store merged menu xml
     */
    private $menuCacheFilename = null;

    /**
     * @var array model directories
     */
    private $modelDirs = [];

    /**
     * @var int time to live for merged menu xml
     */
    private $menuCacheTTL = 3600;

    /**
     * add menu structure to root
     * @param string $filename menu xml filename
     * @return \SimpleXMLElement
     * @throws MenuInitException unloadable menu xml
     */
    private function addXML($filename)
    {
        // load and validate menu xml
        if (!file_exists($filename)) {
            throw new MenuInitException('Menu xml ' . $filename . ' missing');
        }
        $menuXml = simplexml_load_file($filename);
        if ($menuXml === false) {
            throw new MenuInitException('Menu xml ' . $filename . ' not valid');
        }
        if ($menuXml->getName() != "menu") {
            throw new MenuInitException('Menu xml ' . $filename . ' seems to be of wrong type');
        }

        return $menuXml;
    }

    /**
     * get menu item from existing root
     * @param string $root xpath expression
     * @return null|MenuItem
     */
    public function getItem($root)
    {
        return $this->root->findNodeByPath($root);
    }

    /**
     * append menu item to existing root
     * @param string $root xpath expression
     * @param string $id item if (tag name)
     * @param array $properties properties
     * @return null|MenuItem
     */
    public function appendItem($root, $id, $properties)
    {
        return $this->getItem($root)?->append($id, $properties);
    }

    /**
     * invalidate cache, removes cache file from disk if available, which forces the next request to persist() again
     */
    public function invalidateCache()
    {
        @unlink($this->menuCacheFilename);
    }

    private function iterateMenuPaths()
    {
        foreach ($this->modelDirs as $modelDir) {
            foreach (glob(preg_replace('#/+#', '/', "{$modelDir}/*")) as $vendor) {
                foreach (glob($vendor . '/*') as $module) {
                    if (is_dir($module . '/Menu')) {
                        $path = $module . '/Menu/';
                        yield ['path' => $path, 'base' => substr($path, strlen($modelDir))];
                    }
                }
            }
        }
    }

    /**
     * Load and persist Menu configuration to disk.
     * @param bool $nowait when the cache is locked, skip waiting for it to become available.
     * @return SimpleXMLElement
     */
    public function persist($nowait = true)
    {
        // collect all XML menu definitions into a single file
        $menuXml = new \DOMDocument('1.0');
        $root = $menuXml->createElement('menu');
        $menuXml->appendChild($root);
        // crawl all vendors and modules and add menu definitions
        foreach ($this->iterateMenuPaths() as $menu_dir) {
            if (file_exists($menu_dir['path'] . 'Menu.xml')) {
                try {
                    $domNode = dom_import_simplexml($this->addXML($menu_dir['path'] . 'Menu.xml'));
                    $domNode = $root->ownerDocument->importNode($domNode, true);
                    $root->appendChild($domNode);
                } catch (MenuInitException $e) {
                    error_log($e);
                }
            }
        }
        // flush to disk
        $fp = fopen($this->menuCacheFilename, file_exists($this->menuCacheFilename) ? "r+" : "w+");
        $lockMode = $nowait ? LOCK_EX | LOCK_NB : LOCK_EX;
        if (flock($fp, $lockMode)) {
            ftruncate($fp, 0);
            fwrite($fp, $menuXml->saveXML());
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            chmod($this->menuCacheFilename, 0660);
            @chown($this->menuCacheFilename, 'wwwonly'); /* XXX frontend owns file */
            @chgrp($this->menuCacheFilename, 'wheel'); /* XXX backend can work with it */
        }
        // return generated xml
        return simplexml_import_dom($root);
    }

    /**
     * check if stored menu's are expired
     * @return bool is expired
     */
    public function isExpired()
    {
        if (file_exists($this->menuCacheFilename)) {
            $fstat = stat($this->menuCacheFilename);
            return $this->menuCacheTTL < (time() - $fstat['mtime']);
        }
        return true;
    }

    /**
     * construct a new menu
     * @throws MenuInitException
     */
    public function __construct()
    {
        $appconfig = new AppConfig();
        if (!empty($appconfig->application->modelsDir)) {
            $this->modelDirs = $appconfig->application->modelsDir;
            if (!is_array($this->modelDirs) && !is_object($this->modelDirs)) {
                $this->modelDirs = [$this->modelDirs];
            }
        }

        // set cache location
        $this->menuCacheFilename = $appconfig->application->tempDir . '/opnsense_menu_cache.xml';

        // load menu xml's
        $menuxml = null;
        if (!$this->isExpired()) {
            $menuxml = @simplexml_load_file($this->menuCacheFilename);
        }
        if ($menuxml == null) {
            $menuxml = $this->persist();
        }

        // load menu xml's
        $this->root = new MenuItem("root");
        foreach ($menuxml as $menu) {
            foreach ($menu as $node) {
                $this->root->addXmlNode($node);
            }
        }

        // collect and insert dynamic entries
        foreach ($this->iterateMenuPaths() as $menu_dir) {
            if (file_exists($menu_dir['path'] . 'Menu.php')) {
                $classname = str_replace('/', '\\', $menu_dir['base']) . 'Menu';
                try {
                    $cls = new ReflectionClass($classname);
                    if (!$cls->isInstantiable() || !$cls->isSubclassOf('OPNsense\\Base\\Menu\\MenuContainer')) {
                        continue; /* ignore, not ours */
                    }
                } catch (\ReflectionException) {
                    continue; /* ignore, can't construct */
                }
                $cls->newInstance($this)->collect();
            }
        }
    }

    /**
     * return full menu system including selected items
     * @param string $url current location
     * @return array
     */
    public function getItems($url)
    {
        $this->root->toggleSelected($url);
        $menu = $this->root->getChildren();

        return $menu;
    }

    /**
     * return the currently selected page's breadcrumbs
     * @return array
     */
    public function getBreadcrumbs()
    {
        $nodes = $this->root->getChildren();
        $breadcrumbs = [];

        while ($nodes != null) {
            $next = null;
            foreach ($nodes as $node) {
                if ($node->Selected) {
                    /* ignore client-side anchor in breadcrumb */
                    if (!empty($node->Url) && strpos($node->Url, '#') !== false) {
                        $next = null;
                        break;
                    }
                    $breadcrumbs[] = ['name' => $node->VisibleName];
                    /* only go as far as the first reachable URL */
                    $next = empty($node->Url) ? $node->Children : null;
                    break;
                }
            }
            $nodes = $next;
        }

        return $breadcrumbs;
    }
}
