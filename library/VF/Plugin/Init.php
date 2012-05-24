<?php
/**
 * Zend plugin to initialize key VuFind settings.
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
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Zend plugin to initialize key VuFind settings.
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Plugin_Init extends Zend_Controller_Plugin_Abstract
{
    /**
     * Called after Zend_Controller_Router exits.
     *
     * Called after Zend_Controller_Front exits from the router.
     *
     * @param Zend_Controller_Request_Abstract $request
     *
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        // Get convenient access to the main configuration:
        $this->config = VF_Config_Reader::getConfig();

        // If the system is unavailable, forward to a different place:
        if (isset($this->config->System->available)
            && !$this->config->System->available
        ) {
            $request->setControllerName('Error');
            $request->setActionName('Unavailable');
        }
    }

    /**
     * Dispatch Loop Startup
     *
     * @param Zend_Controller_Request_Abstract $request - required by abstract?
     *
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        // Get convenient access to the view:
        $front = Zend_Controller_Front::getInstance();
        $bootstrap = $front->getParam('bootstrap');
        $this->view = $bootstrap->getResource('view');

        // Do all the necessary initialization tasks:
        $this->initViewRendererInflector();
        $this->initThemeSettings($request);
        $this->initAccount();
        $this->initLanguages($request);
    }

    /**
     * Initialize account
     *
     * @return void
     */
    protected function initAccount()
    {
        $account = VF_Account_Manager::getInstance();
        $this->view->account = $account;
    }

    /**
     * Set inflector for view scripts to match inflector for controller methods;
     * otherwise, controller methods are case-insensitive but script loading is
     * case-sensitive, which leads to weird inconsistencies.
     *
     * @return void
     */
    protected function initViewRendererInflector()
    {
        $viewRenderer
            = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $inflector = $viewRenderer->getInflector();
        $rules = $inflector->getRules();
        // Strip CamelCase-to-dash conversions from controller and action rules:
        foreach (array('controller', 'action') as $ruleSet) {
            $newRules = array();
            foreach ($rules[$ruleSet] as $current) {
                if (get_class($current) != 'Zend_Filter_Word_CamelCaseToDash') {
                    $newRules[] = $current;
                }
            }
            $inflector->setFilterRule($ruleSet, $newRules);
        }
    }

    /**
     * Initialize languages
     *
     * @param Zend_Controller_Request_Abstract $request Request object (for obtaining
     * user parameters).
     *
     * @return void
     */
    protected function initLanguages(Zend_Controller_Request_Abstract $request)
    {
        // Setup Translator
        if (($language = $request->getPost('mylang', false))
            || ($language = $request->getParam('lng', false))
        ) {
            setcookie('language', $language, null, '/');
        } else {
            $language = $request->getCookie('language')
                ? $request->getCookie('language')
                : $this->config->Site->language;
        }
        // Make sure language code is valid, reset to default if bad:
        $validLanguages = array();
        foreach ($this->config->Languages as $key => $value) {
            $validLanguages[] = $key;
        }
        if (!in_array($language, $validLanguages)) {
            $language = $this->config->Site->language;
        }

        // Set up language caching for better performance:
        $manager = new VF_Cache_Manager();
        Zend_Translate::setCache($manager->getCache('language'));

        // Set up the actual translator object:
        $translator = VF_Translate_Factory::getTranslator($language);
        Zend_Registry::getInstance()->set('Zend_Translate', $translator);

        // Send key values to view:
        $this->view->userLang = $language;
        $this->view->allLangs = $this->config->Languages;
    }

    /**
     * Support method for initThemeSettings() -- figure out which theme option
     * is currently active.
     *
     * @param Zend_Controller_Request_Abstract $request Request object (for obtaining
     * user parameters).
     *
     * @return string
     */
    protected function pickTheme($request)
    {
        // Load standard configuration options:
        $standardTheme = $this->config->Site->theme;
        $mobileTheme = isset($this->config->Site->mobile_theme)
            ? $this->config->Site->mobile_theme : false;

        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedUI = $request->getPost(
            'ui', $request->getParam('ui', $request->getCookie('ui'))
        );
        if (empty($selectedUI)) {
            $selectedUI = ($mobileTheme && VF_Mobile::detect())
                ? 'mobile' : 'standard';
        }

        // Save the current setting to a cookie so it persists:
        $_COOKIE['ui'] = $selectedUI;
        setcookie('ui', $selectedUI, null, '/');

        // Do we have a valid mobile selection?
        if ($mobileTheme && $selectedUI == 'mobile') {
            return $mobileTheme;
        }

        // Do we have a non-standard selection?
        if ($selectedUI != 'standard'
            && isset($this->config->Site->alternate_themes)
        ) {
            // Check the alternate theme settings for a match:
            $parts = explode(',', $this->config->Site->alternate_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                if ((trim($subparts[0]) == trim($selectedUI))
                    && isset($subparts[1]) && !empty($subparts[1])
                ) {
                    return $subparts[1];
                }
            }
        }

        // If we got this far, we either have a standard option or the user chose
        // an invalid non-standard option; either way, we need to default to the
        // standard theme:
        return $standardTheme;
    }

    /**
     * Return an array of information about user-selectable themes.  Each entry in
     * the array is an associative array with 'name', 'desc' and 'selected' keys.
     *
     * @return array
     */
    protected function getThemeOptions()
    {
        $options = array();
        if (isset($this->config->Site->selectable_themes)) {
            $parts = explode(',', $this->config->Site->selectable_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                $name = trim($subparts[0]);
                $desc = isset($subparts[1]) ? trim($subparts[1]) : '';
                $desc = empty($desc) ? $name : $desc;
                if (!empty($name)) {
                    $options[] = array(
                        'name' => $name, 'desc' => $desc,
                        'selected' => ($_COOKIE['ui'] == $name)
                    );
                }
            }
        }
        return $options;
    }

    /**
     * Support method for setUpThemes -- set up CSS for the current theme.
     *
     * @param array $css CSS files to load.
     *
     * @return void
     */
    protected function setUpThemeCss($css)
    {
        foreach ($css as $current) {
            $parts = explode(':', $current);
            $this->view->headLink()->appendStylesheet(
                trim($parts[0]),
                isset($parts[1]) ? trim($parts[1]) : 'all',
                isset($parts[2]) ? trim($parts[2]) : false
            );
        }
    }

    /**
     * Support method for setUpThemes -- set up Javascript for the current theme.
     *
     * @param array $js Javascript files to load.
     *
     * @return void
     */
    protected function setUpThemeJs($js)
    {
        foreach ($js as $current) {
            $parts =  explode(':', $current);
            $this->view->headScript()->appendFile(
                trim($parts[0]),
                'text/javascript',
                isset($parts[1])
                ? array('conditional' => trim($parts[1])) : array()
            );
        }
    }

    /**
     * Support method for initThemeSettings() -- set up theme once current settings
     * are known.
     *
     * @param array $themes Theme configuration information.
     *
     * @return void
     */
    protected function setUpThemes($themes)
    {
        // Apply the loaded theme settings in reverse for proper inheritance:
        foreach ($themes as $key=>$currentThemeInfo) {
            // Add view helper paths:
            $this->view->addHelperPath(
                APPLICATION_PATH . "/themes/$key/helpers",
                "VuFind_Theme_" . ucwords($key) . "_Helper"
            );

            // Add template and layout paths:
            $this->view->addScriptPath(APPLICATION_PATH . "/themes/$key/templates");
            $this->view->addScriptPath(APPLICATION_PATH . "/themes/$key/layouts");

            // Add CSS and JS dependencies:
            if ($css = $currentThemeInfo->get('css')) {
                $this->setUpThemeCss($css);
            }
            if ($js = $currentThemeInfo->get('js')) {
                $this->setUpThemeJs($js);
            }

            // Select favicon (we only want one, so we'll pick the best available
            // one inside this loop and actually load it later outside the loop):
            if ($favicon = $currentThemeInfo->get('favicon')) {
                $bestFavicon = $favicon;
            }
        }

        // If we found a favicon above, load it now:
        if (isset($bestFavicon)) {
            $this->view->headLink(
                array(
                    'href' => $this->view->imageLink($bestFavicon),
                    'type' => 'image/x-icon',
                    'rel' => 'shortcut icon'
                )
            );
        }
    }

    /**
     * Initialize Theme Settings
     *
     * @param Zend_Controller_Request_Abstract $request Request object (for obtaining
     * user parameters).
     *
     * @return void
     */
    protected function initThemeSettings($request)
    {
        // Determine the current theme:
        $currentTheme = $this->pickTheme($request);

        // Determine theme options:
        $this->view->themeOptions = $this->getThemeOptions();

        // Set up a session namespace for storing theme settings, and fill it
        // in if it is not already populated:
        $session = new Zend_Session_Namespace('Theme');
        if (!isset($session->currentTheme)
            || $session->currentTheme !== $currentTheme
        ) {
            // If the configured theme setting is illegal, switch it to "blueprint"
            // and set a flag so we can throw an Exception once everything is set
            // up:
            if (!file_exists(APPLICATION_PATH . "/themes/$currentTheme/theme.ini")) {
                $themeLoadError = 'Cannot load theme: ' . $currentTheme;
                $currentTheme = 'blueprint';
            }

            // Remember the top-level theme setting:
            $session->currentTheme = $currentTheme;

            // Build an array of theme information by inheriting up the theme tree:
            $allThemeInfo = array();
            do {
                $currentThemeInfo = new Zend_Config_Ini(
                    APPLICATION_PATH . "/themes/$currentTheme/theme.ini"
                );

                $allThemeInfo[$currentTheme] = $currentThemeInfo;

                $currentTheme = $currentThemeInfo->extends;
            } while ($currentTheme);

            $session->allThemeInfo = $allThemeInfo;
        }

        // Using the settings we initialized above, actually configure the themes:
        $this->setUpThemes(array_reverse($session->allThemeInfo));

        // If we encountered an error loading theme settings, fail now (we can't fail
        // earlier, since we need a theme configured in order to show the error!)
        if (isset($themeLoadError)) {
            throw new Exception($themeLoadError);
        }
    }
}