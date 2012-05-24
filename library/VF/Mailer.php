<?php
/**
 * VuFind Mailer Class
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
 * VuFind Mailer Class
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class VF_Mailer
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // This doesn't currently do anything, but we define it now so it can be
        // called by subclasses, just in case we need it here in the future.
    }

    /**
     * Send an email message.
     *
     * @param string $to      Recipient email address
     * @param string $from    Sender email address
     * @param string $subject Subject line for message
     * @param string $body    Message body
     *
     * @throws VF_Exception_Mail
     * @return void
     */
    public function send($to, $from, $subject, $body)
    {
        // Validate sender and recipient
        $validator = new Zend_Validate_EmailAddress();
        if (!$validator->isValid($to)) {
            throw new VF_Exception_Mail('Invalid Recipient Email Address');
        }
        if (!$validator->isValid($from)) {
            throw new VF_Exception_Mail('Invalid Sender Email Address');
        }

        // Convert all exceptions thrown by mailer into VF_Exception_Mail objects:
        try {
            // Get mail object
            $mail = new Zend_Mail();

            // Send message
            $mail->setBodyText($body, 'UTF-8')
                ->setFrom($from)
                ->addTo($to)
                ->setSubject($subject)
                ->send();
        } catch (Exception $e) {
            throw new VF_Exception_Mail($e->getMessage());
        }
    }

    /**
     * Send an email message representing a link.
     *
     * @param string    $to      Recipient email address
     * @param string    $from    Sender email address
     * @param string    $msg     User notes to include in message
     * @param string    $url     URL to share
     * @param Zend_View $view    View object (used to render email templates)
     * @param string    $subject Subject for email (optional)
     *
     * @throws VF_Exception_Mail
     * @return void
     */
    public function sendLink($to, $from, $msg, $url, $view, $subject = null)
    {
        if (is_null($subject)) {
            $subject = 'Library Catalog Search Result';
        }
        $subject = VF_Translator::translate($subject);
        $body = $view->partial(
            'Email/share-link.phtml',
            array(
                'msgUrl' => $url, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }

    /**
     * Send an email message representing a record.
     *
     * @param string               $to     Recipient email address
     * @param string               $from   Sender email address
     * @param string               $msg    User notes to include in message
     * @param VF_RecordDriver_Base $record Record being emailed
     * @param Zend_View            $view   View object (used to render email
     * templates)
     *
     * @throws VF_Exception_Mail
     * @return void
     */
    public function sendRecord($to, $from, $msg, $record, $view)
    {
        $subject = VF_Translator::translate('Library Catalog Record') . ': '
            . $record->getBreadcrumb();
        $body = $view->partial(
            'Email/record.phtml',
            array(
                'driver' => $record, 'to' => $to, 'from' => $from, 'message' => $msg
            )
        );
        return $this->send($to, $from, $subject, $body);
    }
}