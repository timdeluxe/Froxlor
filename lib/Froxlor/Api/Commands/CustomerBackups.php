<?php
namespace Froxlor\Api\Commands;

use Froxlor\Database\Database;
use Froxlor\Settings;

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright (c) the authors
 * @author Froxlor team <team@froxlor.org> (2010-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package API
 * @since 0.10.0
 *       
 */
class CustomerBackups extends \Froxlor\Api\ApiCommand implements \Froxlor\Api\ResourceEntity
{

	/**
	 * check whether backup is enabled systemwide and if accessible for customer (hide_options)
	 *
	 * @throws \Exception
	 */
	private function validateAccess()
	{
		if (Settings::Get('system.backupenabled') != 1) {
			throw new \Exception("You cannot access this resource", 405);
		}
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'extras')) {
			throw new \Exception("You cannot access this resource", 405);
		}
		if ($this->isAdmin() == false && Settings::IsInList('panel.customer_hide_options', 'extras.backup')) {
			throw new \Exception("You cannot access this resource", 405);
		}
	}

	/**
	 * add a new customer backup job
	 *
	 * @param string $path
	 *        	path to store the backup to
	 * @param bool $backup_dbs
	 *        	optional whether to backup databases, default is 0 (false)
	 * @param bool $backup_mail
	 *        	optional whether to backup mail-data, default is 0 (false)
	 * @param bool $backup_web
	 *        	optional whether to backup web-data, default is 0 (false)
	 * @param int $customerid
	 *        	optional, required when called as admin (if $loginname is not specified)
	 * @param string $loginname
	 *        	optional, required when called as admin (if $customerid is not specified)
	 *        	
	 * @access admin, customer
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function add()
	{
		$this->validateAccess();

		// required parameter
		$path = $this->getParam('path');

		// parameter
		$backup_dbs = $this->getBoolParam('backup_dbs', true, 0);
		$backup_mail = $this->getBoolParam('backup_mail', true, 0);
		$backup_web = $this->getBoolParam('backup_web', true, 0);

		// get customer data
		$customer = $this->getCustomerData();

		// validation
		$path = \Froxlor\FileDir::makeCorrectDir(\Froxlor\Validate\Validate::validate($path, 'path', '', '', array(), true));
		$userpath = $path;
		$path = \Froxlor\FileDir::makeCorrectDir($customer['documentroot'] . '/' . $path);

		// path cannot be the customers docroot
		if ($path == \Froxlor\FileDir::makeCorrectDir($customer['documentroot'])) {
			\Froxlor\UI\Response::standard_error('backupfoldercannotbedocroot', '', true);
		}

		if ($backup_dbs != '1') {
			$backup_dbs = '0';
		}

		if ($backup_mail != '1') {
			$backup_mail = '0';
		}

		if ($backup_web != '1') {
			$backup_web = '0';
		}

		$task_data = array(
			'customerid' => $customer['customerid'],
			'uid' => $customer['guid'],
			'gid' => $customer['guid'],
			'loginname' => $customer['loginname'],
			'destdir' => $path,
			'backup_dbs' => $backup_dbs,
			'backup_mail' => $backup_mail,
			'backup_web' => $backup_web
		);
		// schedule backup job
		\Froxlor\System\Cronjob::inserttask('20', $task_data);

		$this->logger()->logAction($this->isAdmin() ? \Froxlor\FroxlorLogger::ADM_ACTION : \Froxlor\FroxlorLogger::USR_ACTION, LOG_NOTICE, "[API] added customer-backup job for '" . $customer['loginname'] . "'. Target directory: " . $userpath);
		return $this->response(200, "successful", $task_data);
	}

	/**
	 * You cannot get a planned backup.
	 * Try CustomerBackups.listing()
	 */
	public function get()
	{
		throw new \Exception('You cannot get a planned backup. Try CustomerBackups.listing()', 303);
	}

	/**
	 * You cannot update a planned backup.
	 * You need to delete it and re-add it.
	 */
	public function update()
	{
		throw new \Exception('You cannot update a planned backup. You need to delete it and re-add it.', 303);
	}

	/**
	 * list all planned backup-jobs, if called from an admin, list all planned backup-jobs of all customers you are allowed to view, or specify id or loginname for one specific customer
	 *
	 * @param int $customerid
	 *        	optional, admin-only, select backup-jobs of a specific customer by id
	 * @param string $loginname
	 *        	optional, admin-only, select backup-jobs of a specific customer by loginname
	 * @param array $sql_search
	 *        	optional array with index = fieldname, and value = array with 'op' => operator (one of <, > or =), LIKE is used if left empty and 'value' => searchvalue
	 * @param int $sql_limit
	 *        	optional specify number of results to be returned
	 * @param int $sql_offset
	 *        	optional specify offset for resultset
	 * @param array $sql_orderby
	 *        	optional array with index = fieldname and value = ASC|DESC to order the resultset by one or more fields
	 *
	 * @access admin, customer
	 * @throws \Exception
	 * @return string json-encoded array count|list
	 */
	public function listing()
	{
		$this->validateAccess();

		$customer_ids = $this->getAllowedCustomerIds('extras.backup');

		// check whether there is a backup-job for this customer
		$query_fields = array();
		$sel_stmt = Database::prepare("SELECT * FROM `" . TABLE_PANEL_TASKS . "` WHERE `type` = '20'" . $this->getSearchWhere($query_fields, true) . $this->getOrderBy() . $this->getLimit());
		Database::pexecute($sel_stmt, $query_fields, true, true);
		$result = array();
		while ($entry = $sel_stmt->fetch(\PDO::FETCH_ASSOC)) {
			$entry['data'] = json_decode($entry['data'], true);
			if (in_array($entry['data']['customerid'], $customer_ids)) {
				$result[] = $entry;
			}
		}
		$this->logger()->logAction($this->isAdmin() ? \Froxlor\FroxlorLogger::ADM_ACTION : \Froxlor\FroxlorLogger::USR_ACTION, LOG_NOTICE, "[API] list customer-backups");
		return $this->response(200, "successful", array(
			'count' => count($result),
			'list' => $result
		));
	}

	/**
	 * returns the total number of planned backups
	 *
	 * @param int $customerid
	 *        	optional, admin-only, select backup-jobs of a specific customer by id
	 * @param string $loginname
	 *        	optional, admin-only, select backup-jobs of a specific customer by loginname
	 *        	
	 * @access admin, customer
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function listingCount()
	{
		$this->validateAccess();

		$customer_ids = $this->getAllowedCustomerIds('extras.backup');

		// check whether there is a backup-job for this customer
		$result_count = 0;
		$sel_stmt = Database::prepare("SELECT * FROM `" . TABLE_PANEL_TASKS . "` WHERE `type` = '20'");
		Database::pexecute($sel_stmt, null, true, true);
		while ($entry = $sel_stmt->fetch(\PDO::FETCH_ASSOC)) {
			$entry['data'] = json_decode($entry['data'], true);
			if (in_array($entry['data']['customerid'], $customer_ids)) {
				$result_count ++;
			}
		}
		return $this->response(200, "successful", $result_count);
	}

	/**
	 * delete a planned backup-jobs by id, if called from an admin you need to specify the customerid/loginname
	 *
	 * @param int $backup_job_entry
	 *        	id of backup job
	 * @param int $customerid
	 *        	optional, required when called as admin (if $loginname is not specified)
	 * @param string $loginname
	 *        	optional, required when called as admin (if $customerid is not specified)
	 *        	
	 * @access admin, customer
	 * @throws \Exception
	 * @return bool
	 */
	public function delete()
	{
		// get planned backups
		$result = $this->apiCall('CustomerBackups.listing', $this->getParamList());

		$entry = $this->getParam('backup_job_entry');
		$customer_ids = $this->getAllowedCustomerIds('extras.backup');

		if ($result['count'] > 0 && $entry > 0) {
			// prepare statement
			$del_stmt = Database::prepare("DELETE FROM `" . TABLE_PANEL_TASKS . "` WHERE `id` = :tid");
			// check for the correct job
			foreach ($result['list'] as $backupjob) {
				if ($backupjob['id'] == $entry && in_array($backupjob['data']['customerid'], $customer_ids)) {
					Database::pexecute($del_stmt, array(
						'tid' => $entry
					), true, true);
					$this->logger()->logAction($this->isAdmin() ? \Froxlor\FroxlorLogger::ADM_ACTION : \Froxlor\FroxlorLogger::USR_ACTION, LOG_NOTICE, "[API] deleted planned customer-backup #" . $entry);
					return $this->response(200, "successful", true);
				}
			}
		}
		throw new \Exception('Backup job with id #' . $entry . ' could not be found', 404);
	}
}
