<?php
/**
 * Error Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Class controls the displaying and logging of errors
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class ErrorController extends Zend_Controller_Action
{
    /**
     * Determine how to display error and log it
     *
     * @return void
     */
    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');

        // Special case -- in command line mode, just dump the error to the console:
        if (PHP_SAPI == 'cli') {
            $this->_helper->viewRenderer->setNoRender();
            $this->_helper->layout->disableLayout();
            echo isset($errors->exception->xdebug_message)
                ? $errors->exception->xdebug_message
                : $errors->exception->getMessage();
            echo "\n";
            return;
        }

        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = 'You have reached the error page';
            return;
        }

        // Treat "record missing" exceptions as missing actions:
        if (isset($errors->exception)
            && is_a($errors->exception, 'VF_Exception_RecordMissing')
        ) {
            $errors->type = Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION;
        }

        switch ($errors->type) {
        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
        case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
            // 404 error -- controller or action not found
            $this->getResponse()->setHttpResponseCode(404);
            $priority = Zend_Log::NOTICE;
            $this->view->message = 'Page not found';
            break;
        default:
            if (isset($errors->exception)
                && is_a($errors->exception, 'VF_Exception_Forbidden')
            ) {
                // access forbidden
                $this->getResponse()->setHttpResponseCode(403);
            } else {
                // application error
                $this->getResponse()->setHttpResponseCode(500);
            }
            $priority = Zend_Log::CRIT;
            $this->view->message = 'Application error';
            break;
        }

        // Log exception, if logger available
        if ($log = $this->getLog()) {
            // Log the error for administrative purposes
            // We need to build a variety of pieces so we can supply
            // information at five different verbosity levels:
            $error = $errors->exception;
            $baseError = $error->getMessage();
            $referer = $this->_request->getServer('HTTP_REFERER', 'none');
            $basicServer
                = '(Server: IP = ' . $this->_request->getServer('REMOTE_ADDR') . ', '
                . 'Referer = ' . $referer . ', '
                . 'User Agent = '
                . $this->_request->getServer('HTTP_USER_AGENT') . ', '
                . 'Request URI = '
                . $this->_request->getServer('REQUEST_URI') . ')';
            $detailedServer = "\nServer Context:\n"
                . print_r($this->_request->getServer(), true);
            $basicBacktrace = $detailedBacktrace = "\nBacktrace:\n";
            if (is_array($error->getTrace())) {
                foreach ($error->getTrace() as $line) {
                    if (!isset($line['file'])) {
                        $line['file'] = 'unlisted file';
                    }
                    if (!isset($line['line'])) {
                        $line['line'] = 'unlisted';
                    }
                    $basicBacktraceLine = $detailedBacktraceLine = $line['file'] .
                        ' line ' . $line['line'] . ' - ' .
                        (isset($line['class'])? 'class = '.$line['class'].', ' : '')
                        . 'function = ' . $line['function'];
                    $basicBacktrace .= "{$basicBacktraceLine}\n";
                    if (!empty($line['args'])) {
                        $args = array();
                        foreach ($line['args'] as $i => $arg) {
                            $val = is_object($arg)
                                ? get_class($arg) . ' Object'
                                : is_array($arg)
                                    ? count($arg) . '-element Array'
                                    : $arg;
                            $args[] = $i . ' = ' . $val;
                        }
                        $detailedBacktraceLine .= ', args: ' . implode(', ', $args);
                    } else {
                        $detailedBacktraceLine .= ', args: none.';
                    }
                    $detailedBacktrace .= "{$detailedBacktraceLine}\n";
                }
            }

            $errorDetails = array(
                1 => $baseError,
                2 => $baseError . $basicServer,
                3 => $baseError . $basicServer . $basicBacktrace,
                4 => $baseError . $detailedServer . $basicBacktrace,
                5 => $baseError . $detailedServer . $detailedBacktrace
            );

            $log->log($errorDetails, $priority);
        }

        // should we show a link to the installer?
        $config = VF_Config_Reader::getConfig();
        $this->view->showInstallLink = isset($config->System->autoConfigure)
            ? $config->System->autoConfigure : false;

        // conditionally display exceptions
        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }

        $this->view->request   = $errors->request;
    }

    /**
     * Display a "system unavailable" message.
     *
     * @return void
     */
    public function unavailableAction()
    {
        // No action -- just display template.
    }

    /**
     * Gets the current log
     *
     * @return object $log
     */
    public function getLog()
    {
        return Zend_Registry::get('Log');
    }
}

