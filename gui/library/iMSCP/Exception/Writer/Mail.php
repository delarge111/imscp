<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2014 by i-MSCP Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @category    iMSCP
 * @package     iMSCP_Core
 * @copyright   2010-2014 by i-MSCP Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iMSCP_Exception_Writer_Abstract */
require_once 'iMSCP/Exception/Writer/Abstract.php';

/**
 * Class iMSCP_Exception_Writer_Mail
 *
 * This exception writer writes an exception messages to admin email.
 */
class iMSCP_Exception_Writer_Mail extends iMSCP_Exception_Writer_Abstract
{
	/**
	 * Exception writer name
	 *
	 * @var string
	 */
	const NAME = 'i-MSCP Exception Mail Writer';

	/**
	 * onUncaughtException event listener
	 *
	 * @param iMSCP_Exception_Event $event
	 * @return void
	 */
	public function onUncaughtException(iMSCP_Exception_Event $event)
	{
		$mail = $this->prepareMail($event->getException());

		if(!empty($mail)) {
			$footprints = array();
			$dbConfig = false;
			$now = time();

			// Load cache of mail body footprints and remove expired entries
			if(iMSCP_Registry::isRegistered('dbConfig')) {
				$dbConfig = iMSCP_Registry::get('dbConfig');

				if(isset($dbConfig['MAIL_BODY_FOOTPRINTS']) && isSerialized($dbConfig['MAIL_BODY_FOOTPRINTS'])) {
					$footprints = unserialize($dbConfig['MAIL_BODY_FOOTPRINTS']);

					foreach($footprints as $footprint => $expireTime) {
						if($expireTime <= $now) {
							unset($footprints[$footprint]);
						}
					}
				}
			}

			// Do not send mail for identical exception in next 24 hours
			if(!array_key_exists($mail['footprint'], $footprints) || $footprints[$mail['footprint']] < $now) {
				if(@mail($mail['rcptTo'], $mail['subject'], $mail['body'], $mail['header'])) {
					# Store cache into the database
					if($dbConfig) {
						# Add mail footprint into the cache
						$footprints[$mail['footprint']] = strtotime("+24 hours");
					}
				}
			}

			// Update footprints cache in
			if($dbConfig) {
				$dbConfig['MAIL_BODY_FOOTPRINTS'] = serialize($footprints);
			}
		}
	}

	/**
	 * Prepare the mail to be send
	 *
	 * @param Exception $exception An exception object
	 * @return array Array containing mail parts
	 */
	protected function prepareMail($exception)
	{
		$mail = array();

		if(iMSCP_Registry::isRegistered('config')) {
			/** @var iMSCP_Config_Handler_File $config */
			$config = iMSCP_Registry::get('config');

			if(isset($config['DEFAULT_ADMIN_ADDRESS'])) {
				$rcptTo = $config['DEFAULT_ADMIN_ADDRESS'];
				$sender = 'webmaster@' . $config['BASE_SERVER_VHOST'];

				if(filter_var($rcptTo, FILTER_VALIDATE_EMAIL) !== false) {
					$mail['rcptTo'] = $rcptTo;
					$message = preg_replace('#([\t\n]+|<br \/>)#', ' ', $exception->getMessage());

					/** @var $exception iMSCP_Exception_Database */
					if($exception instanceof iMSCP_Exception_Database) {
						$message .= "\n\nQuery was:\n\n" . $exception->getQuery();
					}

					// Header
					$mail['header'] = 'From: "' . self::NAME . "\" <$sender>\n";
					$mail['header'] .= "MIME-Version: 1.0\n";
					$mail['header'] .= "Content-Type: text/plain; charset=utf-8\n";
					$mail['header'] .= "Content-Transfer-Encoding: 8bit\n";
					$mail['header'] .= 'X-Mailer: ' . self::NAME;

					// Subject
					$mail['subject'] = self::NAME . ' - An exception has been thrown';

					// Body
					$mail['body'] = "Dear admin,\n\n";
					$mail['body'] .= sprintf(
						"An exception has been thrown in file %s at line %s:\n\n",
						$exception->getFile(),
						$exception->getLine()
					);
					$mail['body'] .= str_repeat('=', 65) . "\n\n";
					$mail['body'] .= "$message\n\n";
					$mail['body'] .= str_repeat('=', 65) . "\n\n";
					$mail['body'] .= "Debug backtrace:\n";
					$mail['body'] .= str_repeat('-', 15) . "\n\n";

					if(($traces = $exception->getTrace())) {
						foreach($traces as $trace) {
							if(isset($trace['file'])) {
								$mail['body'] .= sprintf("File: %s at line %s\n", $trace['file'], $trace['line']);
							}

							if(isset($trace['class'])) {
								$mail['body'] .= sprintf(
									"Method: %s\n", $trace['class'] . '::' . $trace['function'] . '()'
								);
							} elseif(isset($trace['function'])) {
								$mail['body'] .= sprintf("Function: %s\n", $trace['function'] . '()');
							}
						}
					} else {
						$mail['body'] .= sprintf(
							"File: %s at line %s\n", $exception->getFile(), $exception->getLine()
						);
						$mail['body'] .= "Function: main()\n";
					}

					// Generate mail footprint using static part of mail body
					$mail['footprint'] = md5($mail['body']);

					// Additional information
					$mail['body'] .= "\nAdditional information:\n";
					$mail['body'] .= str_repeat('-', 22) . "\n\n";

					foreach(array('HTTP_USER_AGENT', 'REQUEST_URI', 'HTTP_REFERER', 'REMOTE_ADDR', 'SERVER_ADDR') as $key) {
						if(isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
							$mail['body'] .= ucwords(strtolower(str_replace('_', ' ', $key))) . ": {$_SERVER["$key"]}\n";
						}
					}

					$mail['body'] .= "\n" . str_repeat('_', 60) . "\n";
					$mail['body'] .= self::NAME . "\n";
					$mail['body'] .= "\n\nNote: You will not receive further emails for such exception in the next 24 hours.\n";
					$mail['body'] = wordwrap($mail['body'], 70, "\n");
				}
			}
		}

		return $mail;
	}
}
