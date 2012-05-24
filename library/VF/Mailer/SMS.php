<?php
/**
 * VuFind Mailer Class for SMS messages
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

/**
 * VuFind Mailer Class for SMS messages
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class VF_Mailer_SMS extends VF_Mailer
{
    // Defaults, usually overridden by contents of sms.ini:
    protected $carriers = array(
        'virgin' => array('name' => 'Virgin Mobile', 'domain' => 'vmobl.com'),
        'att' => array('name' => 'AT&T', 'domain' => 'txt.att.net'),
        'verizon' => array('name' => 'Verizon', 'domain' => 'vtext.com'),
        'nextel' => array('name' => 'Nextel', 'domain' => 'messaging.nextel.com'),
        'sprint' => array('name' => 'Sprint', 'domain' => 'messaging.sprintpcs.com'),
        'tmobile' => array('name' => 'T Mobile', 'domain' => 'tmomail.net'),
        'alltel' => array('name' => 'Alltel', 'domain' => 'message.alltel.com'),
        'Cricket' => array('name' => 'Cricket', 'domain' => 'mms.mycricket.com')
    );

    // Default "from" address:
    protected $defaultFrom;

    /**
     * Constructor
     *
     * Sets up SMS carriers and other settings from sms.ini.
     */
    public function __construct()
    {
        // if using sms.ini, then load the carriers from it
        // otherwise, fall back to the default list of US carriers
        $smsConfig = VF_Config_Reader::getConfig('sms');
        if (isset($smsConfig->Carriers) && count($smsConfig->Carriers) > 0) {
            $this->carriers = array();
            foreach ($smsConfig->Carriers as $id=>$settings) {
                list($domain, $name) = explode(':', $settings, 2);
                $this->carriers[$id] = array('name'=>$name, 'domain'=>$domain);
            }
        }

        // Load default "from" address:
        $config = VF_Config_Reader::getConfig();
        $this->defaultFrom = isset($config->Site->email) ? $config->Site->email : '';

        parent::__construct();
    }

    /**
     * Get a list of carriers supported by the module.  Returned as an array of
     * associative arrays indexed by carrier ID and containing "name" and "domain"
     * keys.
     *
     * @return array
     */
    public function getCarriers()
    {
        return $this->carriers;
    }

    /**
     * Send a text message to the specified provider.
     *
     * @param string $provider The provider ID to send to
     * @param string $to       The phone number at the provider
     * @param string $from     The email address to use as sender
     * @param string $message  The message to send
     *
     * @throws VF_Exception_Mail
     * @return mixed           PEAR error on error, boolean true otherwise
     */
    public function text($provider, $to, $from, $message)
    {
        $knownCarriers = array_keys($this->carriers);
        if (empty($provider) || !in_array($provider, $knownCarriers)) {
            throw new VF_Exception_Mail('Unknown Carrier');
        }

        $badChars = array('-', '.', '(', ')', ' ');
        $to = str_replace($badChars, '', $to);
        $to = $to . '@' . $this->carriers[$provider]['domain'];
        $from = empty($from) ? $this->defaultFrom : $from;
        $subject = '';
        return $this->send($to, $from, $subject, $message);
    }

    /**
     * Send a text message representing a record.
     *
     * @param string               $provider The provider ID to send to
     * @param string               $to       Recipient phone number
     * @param VF_RecordDriver_Base $record   Record being emailed
     * @param Zend_View            $view     View object (used to render email
     * templates)
     *
     * @throws VF_Exception_Mail
     * @return void
     */
    public function textRecord($provider, $to, $record, $view)
    {
        $body = $view->partial(
            'Email/record-sms.phtml', array('driver' => $record, 'to' => $to)
        );
        return $this->text($provider, $to, null, $body);
    }
}