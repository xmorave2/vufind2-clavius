<?php
/**
 * VF Configuration Upgrade Tool
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Class to upgrade previous VuFind configurations to the current version
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Config_Upgrade
{
    protected $from;
    protected $to;
    protected $oldDir;
    protected $rawDir;
    protected $newDir;
    protected $oldConfigs = array();
    protected $newConfigs = array();
    protected $comments = array();
    protected $warnings = array();

    /**
     * Constructor
     *
     * @param string $from   Version we're upgrading from.
     * @param string $to     Version we're upgrading to.
     * @param string $oldDir Directory containing old configurations.
     * @param string $rawDir Directory containing raw new configurations.
     * @param string $newDir Directory to write updated new configurations into.
     */
    public function __construct($from, $to, $oldDir, $rawDir, $newDir)
    {
        $this->from = $from;
        $this->to = $to;
        $this->oldDir = $oldDir;
        $this->rawDir = $rawDir;
        $this->newDir = $newDir;
    }

    /**
     * Run through all of the necessary upgrading.
     *
     * @return void
     */
    public function run()
    {
        // Load all old configurations:
        $this->loadConfigs();

        // Upgrade them one by one and write the results to disk; order is
        // important since in some cases, settings may migrate out of config.ini
        // and into other files.
        $this->upgradeConfig();
        $this->upgradeAuthority();
        $this->upgradeFacets();
        $this->upgradeFulltext();
        $this->upgradeReserves();
        $this->upgradeSearches();
        $this->upgradeSitemap();
        $this->upgradeSms();
        $this->upgradeSummon();
        $this->upgradeWorldCat();

        // The following routines load special configurations that were not
        // explicitly loaded by loadConfigs:
        $this->upgradeSolrMarc();
        $this->upgradeSearchSpecs();
        $this->upgradeILS();
    }

    /**
     * Get warning strings generated during upgrade process.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Support function -- merge the contents of two arrays parsed from ini files.
     *
     * @param string $config_ini The base config array.
     * @param string $custom_ini Overrides to apply on top of the base array.
     *
     * @return array             The merged results.
     */
    public static function iniMerge($config_ini, $custom_ini)
    {
        foreach ($custom_ini as $k => $v) {
            // Make a recursive call if we need to merge array values into an
            // existing key...  otherwise just drop the value in place.
            if (is_array($v) && isset($config_ini[$k])) {
                $config_ini[$k] = self::iniMerge($config_ini[$k], $custom_ini[$k]);
            } else {
                $config_ini[$k] = $v;
            }
        }
        return $config_ini;
    }

    /**
     * Load the old config.ini settings.
     *
     * @return void
     */
    protected function loadOldBaseConfig()
    {
        // Load the base settings:
        $mainArray = parse_ini_file($this->oldDir . '/config.ini', true);

        // Merge in local overrides as needed.  VuFind 2 structures configurations
        // differently, so people who used this mechanism will need to refactor
        // their configurations to take advantage of the new "local directory"
        // feature.  For now, we'll just merge everything to avoid losing settings.
        if (isset($mainArray['Extra_Config'])
            && isset($mainArray['Extra_Config']['local_overrides'])
        ) {
            $file = trim(
                $this->oldDir . '/' . $mainArray['Extra_Config']['local_overrides']
            );
            $localOverride = @parse_ini_file($file, true);
            if ($localOverride) {
                $mainArray = self::iniMerge($mainArray, $localOverride);
            }
        }

        // Save the configuration to the appropriate place:
        $this->oldConfigs['config.ini'] = $mainArray;
    }

    /**
     * Find the path to the old configuration file.
     *
     * @param string $filename Filename of configuration file.
     *
     * @return string
     */
    protected function getOldConfigPath($filename)
    {
        // Check if the user has overridden the filename in the [Extra_Config]
        // section:
        $index = str_replace('.ini', '', $filename);
        if (isset($this->oldConfigs['config.ini']['Extra_Config'][$index])) {
            $path = $this->oldDir . '/'
                . $this->oldConfigs['config.ini']['Extra_Config'][$index];
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }
        return $this->oldDir . '/' . $filename;
    }

    /**
     * Load all of the user's existing configurations.
     *
     * @return void
     */
    protected function loadConfigs()
    {
        // Configuration files to load.  Note that config.ini must always be loaded
        // first so that getOldConfigPath can work properly!
        $configs = array(
            'config.ini', 'authority.ini', 'facets.ini', 'reserves.ini',
            'searches.ini', 'Summon.ini', 'WorldCat.ini'
        );
        foreach ($configs as $config) {
            // Special case for config.ini, since we may need to overlay extra
            // settings:
            if ($config == 'config.ini') {
                $this->loadOldBaseConfig();
            } else {
                $this->oldConfigs[$config]
                    = parse_ini_file($this->getOldConfigPath($config), true);
            }
            $this->newConfigs[$config]
                = parse_ini_file($this->rawDir . '/' . $config, true);
            $this->comments[$config]
                = VF_Config_Reader::extractComments($this->rawDir . '/' . $config);
        }
    }

    /**
     * Apply settings from an old configuration to a new configuration.
     *
     * @param string $filename     Name of the configuration being updated.
     * @param array  $fullSections Array of section names that need to be fully
     * overridden (as opposed to overridden on a setting-by-setting basis).
     *
     * @return void
     */
    protected function applyOldSettings($filename, $fullSections = array())
    {
        // First override all individual settings:
        foreach ($this->oldConfigs[$filename] as $section => $subsection) {
            foreach ($subsection as $key => $value) {
                $this->newConfigs[$filename][$section][$key] = $value;
            }
        }

        // Now override on a section-by-section basis where necessary:
        foreach ($fullSections as $section) {
            $this->newConfigs[$filename][$section]
                = isset($this->oldConfigs[$filename][$section])
                ? $this->oldConfigs[$filename][$section] : array();
        }
    }

    /**
     * Save a modified configuration file.
     *
     * @param string $filename Name of config file to write (contents will be
     * pulled from current state of object properties).
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function saveModifiedConfig($filename)
    {
        $outfile = $this->newDir . '/' . $filename;
        $result = VF_Config_Writer::writeFile(
            $this->newConfigs[$filename], $this->comments[$filename], $outfile
        );
        if (!$result) {
            throw new VF_Exception_FileAccess(
                "Error: Problem writing to {$outfile}."
            );
        }
    }

    /**
     * Save an unmodified configuration file -- copy the old version, unless it is
     * the same as the new version!
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function saveUnmodifiedConfig($filename)
    {
        // Figure out directories for all versions of this config file:
        $src = $this->getOldConfigPath($filename);
        $raw = $this->rawDir . '/' . $filename;
        $dest = $this->newDir . '/' . $filename;

        // Compare the source file against the raw file; if they happen to be the
        // same, we don't need to copy anything!
        if (md5(file_get_contents($src)) == md5(file_get_contents($raw))) {
            return;
        }

        // If we got this far, we need to copy the user's file into place:
        if (!copy($src, $dest)) {
            throw new VF_Exception_FileAccess(
                "Error: Could not copy {$src} to {$dest}."
            );
        }
    }

    /**
     * Check for invalid theme setting.
     *
     * @param string $setting Name of setting in [Site] section to check.
     * @param string $default Default value to use if invalid option was found.
     *
     * @return void
     */
    protected function checkTheme($setting, $default)
    {
        // If a setting is not set, there is nothing to check:
        $theme = isset($this->newConfigs['config.ini']['Site'][$setting])
            ? $this->newConfigs['config.ini']['Site'][$setting] : null;
        if (empty($theme)) {
            return;
        }

        $parts = explode(',', $theme);
        $theme = trim($parts[0]);

        if (!file_exists(APPLICATION_PATH . '/themes/' . $theme) ||
            !is_dir(APPLICATION_PATH . '/themes/' . $theme)
        ) {
            $this->warnings[] = "WARNING: This version of VuFind does not support "
                . "the {$theme} theme.  Your config.ini [Site] {$setting} setting "
                . "has been reset to the default: {$default}.  You may need to "
                . "reimplement your custom theme.";
            $this->newConfigs['config.ini']['Site'][$setting] = $default;
        }
    }

    /**
     * Upgrade config.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeConfig()
    {
        // override new version's defaults with matching settings from old version:
        $this->applyOldSettings('config.ini');

        // Set up reference for convenience (and shorter lines):
        $newConfig = & $this->newConfigs['config.ini'];

        // Brazilian Portuguese language file is now disabled by default (since
        // it is very incomplete, and regular Portuguese file is now available):
        if (isset($newConfig['Languages']['pt-br'])) {
            unset($newConfig['Languages']['pt-br']);
        }

        // If the [BulkExport] options setting is the old v1.2 default, update it to
        // reflect the fact that we now support RefWorks.
        if ($this->from == '1.2'
            && $newConfig['BulkExport']['options'] == 'MARC:EndNote:BibTeX'
        ) {
            $newConfig['BulkExport']['options'] = 'MARC:EndNote:RefWorks:BibTeX';
        }

        // Warn the user if they have Amazon enabled but do not have the appropriate
        // credentials set up.
        $hasAmazonReview = isset($newConfig['Content']['reviews'])
            && stristr($newConfig['Content']['reviews'], 'amazon');
        $hasAmazonCover = isset($newConfig['Content']['coverimages'])
            && stristr($newConfig['Content']['coverimages'], 'amazon');
        if ($hasAmazonReview || $hasAmazonCover) {
            if (!isset($newConfig['Content']['amazonsecret'])) {
                $this->warnings[]
                    = 'WARNING: You have Amazon content enabled but are missing '
                    . 'the required amazonsecret setting in the [Content] section '
                    . 'of config.ini';
            }
            if (!isset($newConfig['Content']['amazonassociate'])) {
                $this->warnings[]
                    = 'WARNING: You have Amazon content enabled but are missing '
                    . 'the required amazonassociate setting in the [Content] section'
                    . ' of config.ini';
            }
        }

        // Warn the user if they are using an unsupported theme:
        $this->checkTheme('theme', 'blueprint');
        $this->checkTheme('mobile_theme', 'jquerymobile');

        // Translate legacy session settings:
        $newConfig['Session']['type'] = ucwords(
            str_replace('session', '', strtolower($newConfig['Session']['type']))
        );
        if ($newConfig['Session']['type'] == 'Mysql') {
            $newConfig['Session']['type'] = 'Database';
        }

        // Eliminate obsolete database settings:
        $newConfig['Database']
            = array('database' => $newConfig['Database']['database']);

        // Eliminate obsolete config override settings:
        unset($newConfig['Extra_Config']);

        // Deal with shard settings (which may have to be moved to another file):
        $this->upgradeShardSettings();

        // save the file
        $this->saveModifiedConfig('config.ini');
    }

    /**
     * Upgrade facets.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeFacets()
    {
        // we want to retain the old installation's various facet groups
        // exactly as-is
        $facetGroups = array(
            'Results', 'ResultsTop', 'Advanced', 'Author', 'CheckboxFacets'
        );
        $this->applyOldSettings('facets.ini', $facetGroups);

        // save the file
        $this->saveModifiedConfig('facets.ini');
    }

    /**
     * Update an old VuFind 1.x-style autocomplete handler name to the new style.
     *
     * @param string $name Name of module.
     *
     * @return string
     */
    protected function upgradeAutocompleteName($name)
    {
        if ($name == 'NoAutocomplete') {
            return 'None';
        }
        return str_replace('Autocomplete', '', $name);
    }

    /**
     * Upgrade searches.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSearches()
    {
        // we want to retain the old installation's Basic/Advanced search settings
        // and sort settings exactly as-is
        $groups = array(
            'Basic_Searches', 'Advanced_Searches', 'Sorting', 'DefaultSortingByType'
        );
        $this->applyOldSettings('searches.ini', $groups);

        // Fix autocomplete settings in case they use the old style:
        $newConfig = & $this->newConfigs['searches.ini'];
        if (isset($newConfig['Autocomplete']['default_handler'])) {
            $newConfig['Autocomplete']['default_handler']
                = $this->upgradeAutocompleteName(
                    $newConfig['Autocomplete']['default_handler']
                );
        }
        if (isset($newConfig['Autocomplete_Types'])) {
            foreach ($newConfig['Autocomplete_Types'] as $k => $v) {
                $parts = explode(':', $v);
                $parts[0] = $this->upgradeAutocompleteName($parts[0]);
                $newConfig['Autocomplete_Types'][$k] = implode(':', $parts);
            }
        }

        // save the file
        $this->saveModifiedConfig('searches.ini');
    }

    /**
     * Upgrade fulltext.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeFulltext()
    {
        $this->saveUnmodifiedConfig('fulltext.ini');
    }

    /**
     * Upgrade sitemap.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSitemap()
    {
        $this->saveUnmodifiedConfig('sitemap.ini');
    }

    /**
     * Upgrade sms.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSms()
    {
        $this->saveUnmodifiedConfig('sms.ini');
    }

    /**
     * Upgrade authority.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeAuthority()
    {
        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = array(
            'Facets', 'Basic_Searches', 'Advanced_Searches', 'Sorting'
        );
        $this->applyOldSettings('authority.ini', $groups);

        // save the file
        $this->saveModifiedConfig('authority.ini');
    }

    /**
     * Upgrade reserves.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeReserves()
    {
        // If Reserves module is disabled, don't bother updating config:
        if (!isset($this->newConfigs['config.ini']['Reserves']['search_enabled'])
            || !$this->newConfigs['config.ini']['Reserves']['search_enabled']
        ) {
            return;
        }

        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = array(
            'Facets', 'Basic_Searches', 'Advanced_Searches', 'Sorting'
        );
        $this->applyOldSettings('reserves.ini', $groups);

        // save the file
        $this->saveModifiedConfig('reserves.ini');
    }

    /**
     * Upgrade Summon.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSummon()
    {
        // If Summon is disabled in our current configuration, we don't need to
        // load any Summon-specific settings:
        if (!isset($this->newConfigs['config.ini']['Summon']['apiKey'])) {
            return;
        }

        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = array(
            'Facets', 'FacetsTop', 'Basic_Searches', 'Advanced_Searches', 'Sorting'
        );
        $this->applyOldSettings('Summon.ini', $groups);

        // save the file
        $this->saveModifiedConfig('Summon.ini');
    }

    /**
     * Upgrade WorldCat.ini.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeWorldCat()
    {
        // If WorldCat is disabled in our current configuration, we don't need to
        // load any WorldCat-specific settings:
        if (!isset($this->newConfigs['config.ini']['WorldCat']['apiKey'])) {
            return;
        }

        // we want to retain the old installation's search settings exactly as-is
        $groups = array(
            'Basic_Searches', 'Advanced_Searches', 'Sorting'
        );
        $this->applyOldSettings('WorldCat.ini', $groups);

        // save the file
        $this->saveModifiedConfig('WorldCat.ini');
    }

    /**
     * Upgrade SolrMarc configurations.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSolrMarc()
    {
        // Is there a marc_local.properties file?
        $src = realpath($this->oldDir . '/../../import/marc_local.properties');
        if (empty($src) || !file_exists($src)) {
            return;
        }

        // Does the file contain any meaningful lines?
        $lines = file($src);
        $empty = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && substr($line, 0, 1) != '#') {
                $empty = false;
                break;
            }
        }

        // Copy the file if it contains customizations:
        if (!$empty) {
            $dest = realpath($this->newDir . '/../../import')
                . '/marc_local.properties';
            if (!copy($src, $dest) || !file_exists($dest)) {
                throw new VF_Exception_FileAccess(
                    "Cannot copy {$src} to {$dest}."
                );
            }
        }
    }

    /**
     * Upgrade .yaml configurations.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeSearchSpecs()
    {
        // VuFind 1.x uses *_local.yaml files as overrides; VuFind 2.x uses files
        // with the same filename in the local directory.  Copy any old override
        // files into the new expected location:
        $files = array('searchspecs', 'authsearchspecs', 'reservessearchspecs');
        foreach ($files as $file) {
            $old = $this->oldDir . '/' . $file . '_local.yaml';
            $new = $this->newDir . '/' . $file . '.yaml';
            if (file_exists($old)) {
                if (!copy($old, $new)) {
                    throw new VF_Exception_FileAccess(
                        "Cannot copy {$old} to {$new}."
                    );
                }
            }
        }
    }

    /**
     * Upgrade ILS driver configuration.
     *
     * @throws VF_Exception_FileAccess
     * @return void
     */
    protected function upgradeILS()
    {
        $driver = isset($this->newConfigs['config.ini']['Catalog']['driver'])
            ? $this->newConfigs['config.ini']['Catalog']['driver'] : '';
        if (empty($driver)) {
            $this->warnings[] = "WARNING: Could not find ILS driver setting.";
        } else if (!file_exists($this->oldDir . '/' . $driver . '.ini')) {
            $this->warnings[] = "WARNING: Could not find {$driver}.ini file; "
                . "check your ILS driver configuration.";
        } else {
            $this->saveUnmodifiedConfig($driver . '.ini');
        }

        // If we're set to load NoILS.ini on failure, copy that over as well:
        if (isset($this->newConfigs['config.ini']['Catalog']['loadNoILSOnFailure'])
            && $this->newConfigs['config.ini']['Catalog']['loadNoILSOnFailure']
        ) {
            // If NoILS is also the main driver, we don't need to copy it twice:
            if ($driver != 'NoILS') {
                $this->saveUnmodifiedConfig('NoILS.ini');
            }
        }
    }

    /**
     * Upgrade shard settings (they have moved to a different config file, so
     * this is handled as a separate method so that all affected settings are
     * addressed in one place.
     *
     * This gets called from updateConfig(), which gets called before other
     * configuration upgrade routines.  This means that we need to modify the
     * config.ini settings in the newConfigs property (since it is currently
     * being worked on and will be written to disk shortly), but we need to
     * modify the searches.ini/facets.ini settings in the oldConfigs property
     * (because they have not been processed yet).
     *
     * @return void
     */
    protected function upgradeShardSettings()
    {
        // move settings from config.ini to searches.ini:
        if (isset($this->newConfigs['config.ini']['IndexShards'])) {
            $this->oldConfigs['searches.ini']['IndexShards']
                = $this->newConfigs['config.ini']['IndexShards'];
            unset($this->newConfigs['config.ini']['IndexShards']);
        }
        if (isset($this->newConfigs['config.ini']['ShardPreferences'])) {
            $this->oldConfigs['searches.ini']['ShardPreferences']
                = $this->newConfigs['config.ini']['ShardPreferences'];
            unset($this->newConfigs['config.ini']['ShardPreferences']);
        }

        // move settings from facets.ini to searches.ini (merging StripFacets
        // setting with StripFields setting):
        if (isset($this->oldConfigs['facets.ini']['StripFacets'])) {
            if (!isset($this->oldConfigs['searches.ini']['StripFields'])) {
                $this->oldConfigs['searches.ini']['StripFields'] = array();
            }
            foreach ($this->oldConfigs['facets.ini']['StripFacets'] as $k => $v) {
                // If we already have values for the current key, merge and dedupe:
                if (isset($this->oldConfigs['searches.ini']['StripFields'][$k])) {
                    $v .= ',' . $this->oldConfigs['searches.ini']['StripFields'][$k];
                    $parts = explode(',', $v);
                    foreach ($parts as $i => $part) {
                        $parts[$i] = trim($part);
                    }
                    $v = implode(',', array_unique($parts));
                }
                $this->oldConfigs['searches.ini']['StripFields'][$k] = $v;
            }
            unset($this->oldConfigs['facets.ini']['StripFacets']);
        }
    }
}