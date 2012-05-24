<?php
/**
 * VuFind Cache Manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * VuFind Cache Manager
 *
 * Creates file and APC caches
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Cache_Manager extends Zend_Cache_Manager
{
    protected $directoryCreationError = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        // If we have a parent constructor, call it (none exists at the time of
        // this writing, but this is just in case Zend Framework changes later).
        if (is_callable($this, 'parent::__construct')) {
            parent::__construct();
        }

        // Get base cache directory.
        $cacheBase = $this->getCacheDir();

        // Standard front-end settings for object caching:
        $objectFront = array(
            'name' => 'Core',
            'options' => array(
                'automatic_serialization' => true
            )
        );

        // Set up basic object cache:
        $this->createFileCache('object', $objectFront, $cacheBase . 'objects');

        // Set up language cache:
        $this->createFileCache('language', $objectFront, $cacheBase . 'languages');

        // Set up search specs cache based on config settings:
        $config = VF_Config_Reader::getConfig('searches');
        $cacheSetting = isset($config->Cache->type) ? $config->Cache->type : false;
        switch ($cacheSetting) {
        case 'APC':
            $this->createAPCCache('searchspecs', $objectFront);
            break;
        case 'File':
            $this->createFileCache(
                'searchspecs', $objectFront, $cacheBase . 'searchspecs'
            );
            break;
        }
    }

    /**
     * Get the path to the directory containing VuFind's cache data.
     *
     * @return string
     */
    public function getCacheDir()
    {
        if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            return LOCAL_OVERRIDE_DIR . '/cache/';
        }
        return realpath(APPLICATION_PATH . '/../cache') . '/';
    }

    /**
     * Check if there have been problems creating directories.
     *
     * @return bool
     */
    public function hasDirectoryCreationError()
    {
        return $this->directoryCreationError;
    }

    /**
     * Add a file cache to the manager and ensure that necessary directory exists.
     *
     * @param string $cacheName    Name of new cache to create
     * @param array  $frontOptions Front end options to use for cache
     * @param string $dirName      Directory to use for storage
     *
     * @return void
     */
    protected function createFileCache($cacheName, $frontOptions, $dirName)
    {
        if (!is_dir($dirName)) {
            if (!@mkdir($dirName)) {
                $this->directoryCreationError = true;
            }
        }
        $this->setCacheTemplate(
            $cacheName,
            array(
                'frontend' => $frontOptions,
                'backend' => array(
                    'name' => 'File',
                    'options' => array(
                        'cache_dir' => $dirName
                    )
                )
            )
        );
    }

    /**
     * Add an APC cache to the manager.
     *
     * @param string $cacheName    Name of new cache to create
     * @param array  $frontOptions Front end options to use for cache
     *
     * @return void
     */
    protected function createAPCCache($cacheName, $frontOptions)
    {
        $this->setCacheTemplate(
            $cacheName,
            array(
                'frontend' => $frontOptions,
                'backend' => array(
                    'name' => 'APC'
                )
            )
        );
    }
}