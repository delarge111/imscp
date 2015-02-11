<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * The Original Code is "VHCS - Virtual Hosting Control System".
 *
 * The Initial Developer of the Original Code is moleSoftware GmbH.
 * Portions created by Initial Developer are Copyright (C) 2001-2006
 * by moleSoftware GmbH. All Rights Reserved.
 *
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 *
 * Portions created by the i-MSCP Team are Copyright (C) 2010-2015 by
 * i-MSCP - internet Multi Server Control Panel. All Rights Reserved.
 *
 * @category    i-MSCP
 * @package     iMSCP_Core
 * @subpackage  Admin
 * @copyright   2001-2006 by moleSoftware GmbH
 * @copyright   2006-2010 by ispCP | http://isp-control.net
 * @copyright   2010-2015 by i-MSCP | http://i-mscp.net
 * @author      ispCP Team
 * @author      i-MSCP Team
 * @link        http://i-mscp.net
 */

/***********************************************************************************************************************
 * Functions
 */

/**
 * Generate page
 *
 * @param  iMSCP_pTemplate $tpl
 * @return void
 */
function admin_generatePage($tpl)
{
	/** @var $cfg iMSCP_Config_Handler_File */
	$cfg = iMSCP_Registry::get('config');

	if (!isset($cfg['CHECK_FOR_UPDATES']) || !$cfg['CHECK_FOR_UPDATES']) {
		set_page_message(tr('i-MSCP version update checking is disabled'), 'static_warning');
	} else {
		/** @var iMSCP_Update_Version $updateVersion */
		$updateVersion = iMSCP_Update_Version::getInstance();

		if ($updateVersion->isAvailableUpdate()) {
			if (($updateInfo = $updateVersion->getUpdateInfo())) {
				$date = new DateTime($updateInfo['created_at']);

				$tpl->assign(
					array(
						'TR_UPDATE_INFO' => tr('Update info'),
						'TR_RELEASE_VERSION' => tr('Release version'),
						'RELEASE_VERSION' => tohtml($updateInfo['tag_name']),
						'TR_RELEASE_DATE' => tr('Release date'),
						'RELEASE_DATE' => tohtml($date->format($cfg['DATE_FORMAT'])),
						'TR_RELEASE_DESCRIPTION' => tr('Release description'),
						'RELEASE_DESCRIPTION' => tohtml($updateInfo['body']),
						'TR_DOWNLOAD_LINKS' => tr('Download links'),
						'TR_DOWNLOAD_ZIP' => tr('Download ZIP'),
						'TR_DOWNLOAD_TAR' => tr('Download TAR'),
						'TARBALL_URL' => tohtml($updateInfo['tarball_url']),
						'ZIPBALL_URL' => tohtml($updateInfo['zipball_url'])
					)
				);
				return;
			} else {
				set_page_message($updateVersion->getError(), 'error');
			}
		} elseif ($updateVersion->getError()) {
			set_page_message($updateVersion, 'error');
		} else {
			set_page_message(tr('No update available'), 'static_info');
		}
	}

	$tpl->assign('UPDATE_INFO', '');
}

/***********************************************************************************************************************
 * Main
 */

// Include core library
require 'imscp-lib.php';

iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onAdminScriptStart);

check_login('admin');

/** @var $cfg iMSCP_Config_Handler_File */
$cfg = iMSCP_Registry::get('config');

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic(
	array(
		'layout' => 'shared/layouts/ui.tpl',
		'page' => 'admin/imscp_updates.tpl',
		'page_message' => 'layout',
		'update_info' => 'page'
	)
);

$tpl->assign(
	array(
		'TR_PAGE_TITLE' => tr('Admin / System Tools / i-MSCP Updates'),
		'ISP_LOGO' => layout_getUserLogo()
	)
);

generateNavigation($tpl);
admin_generatePage($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');

iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onAdminScriptEnd, array('templateengine' => $tpl));

$tpl->prnt();

unsetMessages();
