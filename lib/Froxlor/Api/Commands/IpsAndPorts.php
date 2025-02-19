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
class IpsAndPorts extends \Froxlor\Api\ApiCommand implements \Froxlor\Api\ResourceEntity
{

	/**
	 * lists all ip/port entries
	 *
	 * @param array $sql_search
	 *        	optional array with index = fieldname, and value = array with 'op' => operator (one of <, > or =), LIKE is used if left empty and 'value' => searchvalue
	 * @param int $sql_limit
	 *        	optional specify number of results to be returned
	 * @param int $sql_offset
	 *        	optional specify offset for resultset
	 * @param array $sql_orderby
	 *        	optional array with index = fieldname and value = ASC|DESC to order the resultset by one or more fields
	 *
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array count|list
	 */
	public function listing()
	{
		if ($this->isAdmin() && ($this->getUserDetail('change_serversettings') || ! empty($this->getUserDetail('ip')))) {
			$this->logger()->logAction(\Froxlor\FroxlorLogger::ADM_ACTION, LOG_NOTICE, "[API] list ips and ports");
			$ip_where = "";
			$append_where = false;
			if (! empty($this->getUserDetail('ip')) && $this->getUserDetail('ip') != - 1) {
				$ip_where = "WHERE `id` IN (" . implode(", ", json_decode($this->getUserDetail('ip'), true)) . ")";
				$append_where = true;
			}
			$query_fields = array();
			$result_stmt = Database::prepare("
				SELECT * FROM `" . TABLE_PANEL_IPSANDPORTS . "` " . $ip_where . $this->getSearchWhere($query_fields, $append_where) . $this->getOrderBy() . $this->getLimit());
			Database::pexecute($result_stmt, $query_fields, true, true);
			$result = array();
			while ($row = $result_stmt->fetch(\PDO::FETCH_ASSOC)) {
				$result[] = $row;
			}
			return $this->response(200, "successful", array(
				'count' => count($result),
				'list' => $result
			));
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * returns the total number of accessible ip/port entries
	 *
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function listingCount()
	{
		if ($this->isAdmin() && ($this->getUserDetail('change_serversettings') || ! empty($this->getUserDetail('ip')))) {
			$ip_where = "";
			if (! empty($this->getUserDetail('ip')) && $this->getUserDetail('ip') != - 1) {
				$ip_where = "WHERE `id` IN (" . implode(", ", json_decode($this->getUserDetail('ip'), true)) . ")";
			}
			$result_stmt = Database::prepare("
				SELECT COUNT(*) as num_ips FROM `" . TABLE_PANEL_IPSANDPORTS . "` " . $ip_where);
			$result = Database::pexecute_first($result_stmt, null, true, true);
			if ($result) {
				return $this->response(200, "successful", $result['num_ips']);
			}
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * return an ip/port entry by id
	 *
	 * @param int $id
	 *        	ip-port-id
	 *        	
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function get()
	{
		if ($this->isAdmin() && ($this->getUserDetail('change_serversettings') || ! empty($this->getUserDetail('ip')))) {
			$id = $this->getParam('id');
			if (! empty($this->getUserDetail('ip')) && $this->getUserDetail('ip') != - 1) {
				$allowed_ips = json_decode($this->getUserDetail('ip'), true);
				if (! in_array($id, $allowed_ips)) {
					throw new \Exception("You cannot access this resource", 405);
				}
			}
			$result_stmt = Database::prepare("
				SELECT * FROM `" . TABLE_PANEL_IPSANDPORTS . "` WHERE `id` = :id
			");
			$result = Database::pexecute_first($result_stmt, array(
				'id' => $id
			), true, true);
			if ($result) {
				$this->logger()->logAction(\Froxlor\FroxlorLogger::ADM_ACTION, LOG_NOTICE, "[API] get ip " . $result['ip'] . " " . $result['port']);
				return $this->response(200, "successful", $result);
			}
			throw new \Exception("IP/port with id #" . $id . " could not be found", 404);
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * create a new ip/port entry
	 *
	 * @param string $ip
	 * @param int $port
	 *        	optional, default 80
	 * @param bool $listen_statement
	 *        	optional, default 0 (false)
	 * @param bool $namevirtualhost_statement
	 *        	optional, default 0 (false)
	 * @param bool $vhostcontainer
	 *        	optional, default 0 (false)
	 * @param string $specialsettings
	 *        	optional, default empty
	 * @param bool $vhostcontainer_servername_statement
	 *        	optional, default 0 (false)
	 * @param string $default_vhostconf_domain
	 *        	optional, defatul empty
	 * @param string $docroot
	 *        	optional, default empty (point to froxlor)
	 * @param bool $ssl
	 *        	optional, default 0 (false)
	 * @param string $ssl_cert_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_key_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_ca_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_cert_chainfile
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_specialsettings
	 *        	optional, requires $ssl = 1, default empty
	 * @param bool $include_specialsettings
	 *        	optional, requires $ssl = 1, whether or not to include non-ssl specialsettings, default false
	 * @param string $ssl_default_vhostconf_domain
	 *        	optional, requires $ssl = 1, defatul empty
	 * @param bool $include_default_vhostconf_domain
	 *        	optional, requires $ssl = 1, whether or not to include non-ssl default_vhostconf_domain, default false
	 *        	
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function add()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {

			$ip = \Froxlor\Validate\Validate::validate_ip2($this->getParam('ip'), false, 'invalidip', false, true, false, false, true);
			$port = \Froxlor\Validate\Validate::validate($this->getParam('port', true, 80), 'port', \Froxlor\Validate\Validate::REGEX_PORT, array(
				'stringisempty',
				'myport'
			), array(), true);
			$listen_statement = ! empty($this->getBoolParam('listen_statement', true, 0)) ? 1 : 0;
			$namevirtualhost_statement = ! empty($this->getBoolParam('namevirtualhost_statement', true, 0)) ? 1 : 0;
			$vhostcontainer = ! empty($this->getBoolParam('vhostcontainer', true, 0)) ? 1 : 0;
			$specialsettings = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('specialsettings', true, '')), 'specialsettings', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
			$vhostcontainer_servername_statement = ! empty($this->getBoolParam('vhostcontainer_servername_statement', true, 1)) ? 1 : 0;
			$default_vhostconf_domain = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('default_vhostconf_domain', true, '')), 'default_vhostconf_domain', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
			$docroot = \Froxlor\Validate\Validate::validate($this->getParam('docroot', true, ''), 'docroot', \Froxlor\Validate\Validate::REGEX_DIR, '', array(), true);

			if ((int) Settings::Get('system.use_ssl') == 1) {
				$ssl = ! empty($this->getBoolParam('ssl', true, 0)) ? intval($this->getBoolParam('ssl', true, 0)) : 0;
				$ssl_cert_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_cert_file', $ssl, ''), 'ssl_cert_file', '', '', array(), true);
				$ssl_key_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_key_file', $ssl, ''), 'ssl_key_file', '', '', array(), true);
				$ssl_ca_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_ca_file', true, ''), 'ssl_ca_file', '', '', array(), true);
				$ssl_cert_chainfile = \Froxlor\Validate\Validate::validate($this->getParam('ssl_cert_chainfile', true, ''), 'ssl_cert_chainfile', '', '', array(), true);
				$ssl_specialsettings = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('ssl_specialsettings', true, '')), 'ssl_specialsettings', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
				$include_specialsettings = ! empty($this->getBoolParam('include_specialsettings', true, 0)) ? 1 : 0;
				$ssl_default_vhostconf_domain = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('ssl_default_vhostconf_domain', true, '')), 'ssl_default_vhostconf_domain', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
				$include_default_vhostconf_domain = ! empty($this->getBoolParam('include_default_vhostconf_domain', true, 0)) ? 1 : 0;
			} else {
				$ssl = 0;
				$ssl_cert_file = '';
				$ssl_key_file = '';
				$ssl_ca_file = '';
				$ssl_cert_chainfile = '';
				$ssl_specialsettings = '';
				$include_specialsettings = 0;
				$ssl_default_vhostconf_domain = '';
				$include_default_vhostconf_domain = 0;
			}

			if ($listen_statement != '1') {
				$listen_statement = '0';
			}

			if ($namevirtualhost_statement != '1') {
				$namevirtualhost_statement = '0';
			}

			if ($vhostcontainer != '1') {
				$vhostcontainer = '0';
			}

			if ($vhostcontainer_servername_statement != '1') {
				$vhostcontainer_servername_statement = '0';
			}

			if ($ssl != '1') {
				$ssl = '0';
			}

			if ($ssl_cert_file != '') {
				$ssl_cert_file = \Froxlor\FileDir::makeCorrectFile($ssl_cert_file);
			}

			if ($ssl_key_file != '') {
				$ssl_key_file = \Froxlor\FileDir::makeCorrectFile($ssl_key_file);
			}

			if ($ssl_ca_file != '') {
				$ssl_ca_file = \Froxlor\FileDir::makeCorrectFile($ssl_ca_file);
			}

			if ($ssl_cert_chainfile != '') {
				$ssl_cert_chainfile = \Froxlor\FileDir::makeCorrectFile($ssl_cert_chainfile);
			}

			if (strlen(trim($docroot)) > 0) {
				$docroot = \Froxlor\FileDir::makeCorrectDir($docroot);
			} else {
				$docroot = '';
			}

			// always use compressed ipv6 format
			$ip = inet_ntop(inet_pton($ip));

			$result_checkfordouble_stmt = Database::prepare("
			SELECT `id` FROM `" . TABLE_PANEL_IPSANDPORTS . "`
			WHERE `ip` = :ip AND `port` = :port");
			$result_checkfordouble = Database::pexecute_first($result_checkfordouble_stmt, array(
				'ip' => $ip,
				'port' => $port
			));

			if ($result_checkfordouble && $result_checkfordouble['id'] != '') {
				\Froxlor\UI\Response::standard_error('myipnotdouble', '', true);
			}

			$ins_stmt = Database::prepare("
				INSERT INTO `" . TABLE_PANEL_IPSANDPORTS . "`
				SET
				`ip` = :ip, `port` = :port, `listen_statement` = :ls,
				`namevirtualhost_statement` = :nvhs, `vhostcontainer` = :vhc,
				`vhostcontainer_servername_statement` = :vhcss,
				`specialsettings` = :ss, `ssl` = :ssl,
				`ssl_cert_file` = :ssl_cert, `ssl_key_file` = :ssl_key,
				`ssl_ca_file` = :ssl_ca, `ssl_cert_chainfile` = :ssl_chain,
				`default_vhostconf_domain` = :dvhd, `docroot` = :docroot,
				`ssl_specialsettings` = :ssl_ss, `include_specialsettings` = :incss,
				`ssl_default_vhostconf_domain` = :ssl_dvhd, `include_default_vhostconf_domain` = :incdvhd;
			");
			$ins_data = array(
				'ip' => $ip,
				'port' => $port,
				'ls' => $listen_statement,
				'nvhs' => $namevirtualhost_statement,
				'vhc' => $vhostcontainer,
				'vhcss' => $vhostcontainer_servername_statement,
				'ss' => $specialsettings,
				'ssl' => $ssl,
				'ssl_cert' => $ssl_cert_file,
				'ssl_key' => $ssl_key_file,
				'ssl_ca' => $ssl_ca_file,
				'ssl_chain' => $ssl_cert_chainfile,
				'dvhd' => $default_vhostconf_domain,
				'docroot' => $docroot,
				'ssl_ss' => $ssl_specialsettings,
				'incss' => $include_specialsettings,
				'ssl_dvhd' => $ssl_default_vhostconf_domain,
				'incdvhd' => $include_default_vhostconf_domain
			);
			Database::pexecute($ins_stmt, $ins_data);
			$ins_data['id'] = Database::lastInsertId();

			\Froxlor\System\Cronjob::inserttask('1');
			// Using nameserver, insert a task which rebuilds the server config
			\Froxlor\System\Cronjob::inserttask('4');

			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$ip = '[' . $ip . ']';
			}
			$this->logger()->logAction(\Froxlor\FroxlorLogger::ADM_ACTION, LOG_WARNING, "[API] added IP/port '" . $ip . ":" . $port . "'");
			// get ip for return-array
			$result = $this->apiCall('IpsAndPorts.get', array(
				'id' => $ins_data['id']
			));
			return $this->response(200, "successful", $result);
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * update ip/port entry by given id
	 *
	 * @param int $id
	 * @param string $ip
	 *        	optional
	 * @param int $port
	 *        	optional, default 80
	 * @param bool $listen_statement
	 *        	optional, default 0 (false)
	 * @param bool $namevirtualhost_statement
	 *        	optional, default 0 (false)
	 * @param bool $vhostcontainer
	 *        	optional, default 0 (false)
	 * @param string $specialsettings
	 *        	optional, default empty
	 * @param bool $vhostcontainer_servername_statement
	 *        	optional, default 0 (false)
	 * @param string $default_vhostconf_domain
	 *        	optional, defatul empty
	 * @param string $docroot
	 *        	optional, default empty (point to froxlor)
	 * @param bool $ssl
	 *        	optional, default 0 (false)
	 * @param string $ssl_cert_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_key_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_ca_file
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_cert_chainfile
	 *        	optional, requires $ssl = 1, default empty
	 * @param string $ssl_specialsettings
	 *        	optional, requires $ssl = 1, default empty
	 * @param bool $include_specialsettings
	 *        	optional, requires $ssl = 1, whether or not to include non-ssl specialsettings, default false
	 * @param string $ssl_default_vhostconf_domain
	 *        	optional, requires $ssl = 1, defatul empty
	 * @param bool $include_default_vhostconf_domain
	 *        	optional, requires $ssl = 1, whether or not to include non-ssl default_vhostconf_domain, default false
	 *        	
	 *        	
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function update()
	{
		if ($this->isAdmin() && ($this->getUserDetail('change_serversettings') || ! empty($this->getUserDetail('ip')))) {
			$id = $this->getParam('id');

			$result = $this->apiCall('IpsAndPorts.get', array(
				'id' => $id
			));

			$ip = \Froxlor\Validate\Validate::validate_ip2($this->getParam('ip', true, $result['ip']), false, 'invalidip', false, true, false, false, true);
			$port = \Froxlor\Validate\Validate::validate($this->getParam('port', true, $result['port']), 'port', \Froxlor\Validate\Validate::REGEX_PORT, array(
				'stringisempty',
				'myport'
			), array(), true);
			$listen_statement = $this->getBoolParam('listen_statement', true, $result['listen_statement']);
			$namevirtualhost_statement = $this->getBoolParam('namevirtualhost_statement', true, $result['namevirtualhost_statement']);
			$vhostcontainer = $this->getBoolParam('vhostcontainer', true, $result['vhostcontainer']);
			$specialsettings = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('specialsettings', true, $result['specialsettings'])), 'specialsettings', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
			$vhostcontainer_servername_statement = $this->getParam('vhostcontainer_servername_statement', true, $result['vhostcontainer_servername_statement']);
			$default_vhostconf_domain = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('default_vhostconf_domain', true, $result['default_vhostconf_domain'])), 'default_vhostconf_domain', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
			$docroot = \Froxlor\Validate\Validate::validate($this->getParam('docroot', true, $result['docroot']), 'docroot', \Froxlor\Validate\Validate::REGEX_DIR, '', array(), true);

			if ((int) Settings::Get('system.use_ssl') == 1) {
				$ssl = $this->getBoolParam('ssl', true, $result['ssl']);
				$ssl_cert_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_cert_file', $ssl, $result['ssl_cert_file']), 'ssl_cert_file', '', '', array(), true);
				$ssl_key_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_key_file', $ssl, $result['ssl_key_file']), 'ssl_key_file', '', '', array(), true);
				$ssl_ca_file = \Froxlor\Validate\Validate::validate($this->getParam('ssl_ca_file', true, $result['ssl_ca_file']), 'ssl_ca_file', '', '', array(), true);
				$ssl_cert_chainfile = \Froxlor\Validate\Validate::validate($this->getParam('ssl_cert_chainfile', true, $result['ssl_cert_chainfile']), 'ssl_cert_chainfile', '', '', array(), true);
				$ssl_specialsettings = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('ssl_specialsettings', true, $result['ssl_specialsettings'])), 'ssl_specialsettings', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
				$include_specialsettings = $this->getBoolParam('include_specialsettings', true, $result['include_specialsettings']);
				$ssl_default_vhostconf_domain = \Froxlor\Validate\Validate::validate(str_replace("\r\n", "\n", $this->getParam('ssl_default_vhostconf_domain', true, $result['ssl_default_vhostconf_domain'])), 'ssl_default_vhostconf_domain', \Froxlor\Validate\Validate::REGEX_CONF_TEXT, '', array(), true);
				$include_default_vhostconf_domain = $this->getBoolParam('include_default_vhostconf_domain', true, $result['include_default_vhostconf_domain']);
			} else {
				$ssl = 0;
				$ssl_cert_file = '';
				$ssl_key_file = '';
				$ssl_ca_file = '';
				$ssl_cert_chainfile = '';
				$ssl_specialsettings = '';
				$include_specialsettings = 0;
				$ssl_default_vhostconf_domain = '';
				$include_default_vhostconf_domain = 0;
			}

			$result_checkfordouble_stmt = Database::prepare("
				SELECT `id` FROM `" . TABLE_PANEL_IPSANDPORTS . "`
				WHERE `ip` = :ip AND `port` = :port
			");
			$result_checkfordouble = Database::pexecute_first($result_checkfordouble_stmt, array(
				'ip' => $ip,
				'port' => $port
			));

			$result_sameipotherport_stmt = Database::prepare("
				SELECT `id` FROM `" . TABLE_PANEL_IPSANDPORTS . "`
				WHERE `ip` = :ip AND `id` <> :id
			");
			$result_sameipotherport = Database::pexecute_first($result_sameipotherport_stmt, array(
				'ip' => $ip,
				'id' => $id
			), true, true);

			if ($listen_statement != '1') {
				$listen_statement = '0';
			}

			if ($namevirtualhost_statement != '1') {
				$namevirtualhost_statement = '0';
			}

			if ($vhostcontainer != '1') {
				$vhostcontainer = '0';
			}

			if ($vhostcontainer_servername_statement != '1') {
				$vhostcontainer_servername_statement = '0';
			}

			if ($ssl != '1') {
				$ssl = '0';
			}

			if ($ssl_cert_file != '') {
				$ssl_cert_file = \Froxlor\FileDir::makeCorrectFile($ssl_cert_file);
			}

			if ($ssl_key_file != '') {
				$ssl_key_file = \Froxlor\FileDir::makeCorrectFile($ssl_key_file);
			}

			if ($ssl_ca_file != '') {
				$ssl_ca_file = \Froxlor\FileDir::makeCorrectFile($ssl_ca_file);
			}

			if ($ssl_cert_chainfile != '') {
				$ssl_cert_chainfile = \Froxlor\FileDir::makeCorrectFile($ssl_cert_chainfile);
			}

			if (strlen(trim($docroot)) > 0) {
				$docroot = \Froxlor\FileDir::makeCorrectDir($docroot);
			} else {
				$docroot = '';
			}

			// always use compressed ipv6 format
			$ip = inet_ntop(inet_pton($ip));

			if ($result['ip'] != $ip && $result['ip'] == Settings::Get('system.ipaddress') && $result_sameipotherport == false) {
				\Froxlor\UI\Response::standard_error('cantchangesystemip', '', true);
			} elseif ($result_checkfordouble && $result_checkfordouble['id'] != '' && $result_checkfordouble['id'] != $id) {
				\Froxlor\UI\Response::standard_error('myipnotdouble', '', true);
			} else {

				$upd_stmt = Database::prepare("
					UPDATE `" . TABLE_PANEL_IPSANDPORTS . "`
					SET
					`ip` = :ip, `port` = :port, `listen_statement` = :ls,
					`namevirtualhost_statement` = :nvhs, `vhostcontainer` = :vhc,
					`vhostcontainer_servername_statement` = :vhcss,
					`specialsettings` = :ss, `ssl` = :ssl,
					`ssl_cert_file` = :ssl_cert, `ssl_key_file` = :ssl_key,
					`ssl_ca_file` = :ssl_ca, `ssl_cert_chainfile` = :ssl_chain,
					`default_vhostconf_domain` = :dvhd, `docroot` = :docroot,
					`ssl_specialsettings` = :ssl_ss, `include_specialsettings` = :incss,
					`ssl_default_vhostconf_domain` = :ssl_dvhd, `include_default_vhostconf_domain` = :incdvhd
					WHERE `id` = :id;
				");
				$upd_data = array(
					'ip' => $ip,
					'port' => $port,
					'ls' => $listen_statement,
					'nvhs' => $namevirtualhost_statement,
					'vhc' => $vhostcontainer,
					'vhcss' => $vhostcontainer_servername_statement,
					'ss' => $specialsettings,
					'ssl' => $ssl,
					'ssl_cert' => $ssl_cert_file,
					'ssl_key' => $ssl_key_file,
					'ssl_ca' => $ssl_ca_file,
					'ssl_chain' => $ssl_cert_chainfile,
					'dvhd' => $default_vhostconf_domain,
					'docroot' => $docroot,
					'ssl_ss' => $ssl_specialsettings,
					'incss' => $include_specialsettings,
					'ssl_dvhd' => $ssl_default_vhostconf_domain,
					'incdvhd' => $include_default_vhostconf_domain,
					'id' => $id
				);
				Database::pexecute($upd_stmt, $upd_data);

				\Froxlor\System\Cronjob::inserttask('1');
				// Using nameserver, insert a task which rebuilds the server config
				\Froxlor\System\Cronjob::inserttask('4');

				$this->logger()->logAction(\Froxlor\FroxlorLogger::ADM_ACTION, LOG_WARNING, "[API] changed IP/port from '" . $result['ip'] . ":" . $result['port'] . "' to '" . $ip . ":" . $port . "'");

				$result = $this->apiCall('IpsAndPorts.get', array(
					'id' => $result['id']
				));
				return $this->response(200, "successful", $result);
			}
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * delete an ip/port entry by id
	 *
	 * @param int $id
	 *        	ip-port-id
	 *        	
	 * @access admin
	 * @throws \Exception
	 * @return string json-encoded array
	 */
	public function delete()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$id = $this->getParam('id');

			$result = $this->apiCall('IpsAndPorts.get', array(
				'id' => $id
			));

			$result_checkdomain_stmt = Database::prepare("
				SELECT `id_domain` FROM `" . TABLE_DOMAINTOIP . "` WHERE `id_ipandports` = :id
			");
			$result_checkdomain = Database::pexecute_first($result_checkdomain_stmt, array(
				'id' => $id
			), true, true);

			if (empty($result_checkdomain)) {
				if (! in_array($result['id'], explode(',', Settings::Get('system.defaultip'))) && ! in_array($result['id'], explode(',', Settings::Get('system.defaultsslip')))) {

					// check whether there is the same IP with a different port
					// in case this ip-address is the system.ipaddress and therefore
					// when there is one - we have an alternative
					$result_sameipotherport_stmt = Database::prepare("
						SELECT `id` FROM `" . TABLE_PANEL_IPSANDPORTS . "`
						WHERE `ip` = :ip AND `id` <> :id");
					$result_sameipotherport = Database::pexecute_first($result_sameipotherport_stmt, array(
						'id' => $id,
						'ip' => $result['ip']
					));

					if (($result['ip'] != Settings::Get('system.ipaddress')) || ($result['ip'] == Settings::Get('system.ipaddress') && $result_sameipotherport != false)) {

						$del_stmt = Database::prepare("
							DELETE FROM `" . TABLE_PANEL_IPSANDPORTS . "`
							WHERE `id` = :id
						");
						Database::pexecute($del_stmt, array(
							'id' => $id
						), true, true);

						// also, remove connections to domains (multi-stack)
						$del_stmt = Database::prepare("
							DELETE FROM `" . TABLE_DOMAINTOIP . "` WHERE `id_ipandports` = :id
						");
						Database::pexecute($del_stmt, array(
							'id' => $id
						), true, true);

						\Froxlor\System\Cronjob::inserttask('1');
						// Using nameserver, insert a task which rebuilds the server config
						\Froxlor\System\Cronjob::inserttask('4');

						$this->logger()->logAction(\Froxlor\FroxlorLogger::ADM_ACTION, LOG_WARNING, "[API] deleted IP/port '" . $result['ip'] . ":" . $result['port'] . "'");
						return $this->response(200, "successful", $result);
					} else {
						\Froxlor\UI\Response::standard_error('cantdeletesystemip', '', true);
					}
				} else {
					\Froxlor\UI\Response::standard_error('cantdeletedefaultip', '', true);
				}
			} else {
				\Froxlor\UI\Response::standard_error('ipstillhasdomains', '', true);
			}
		}
		throw new \Exception("Not allowed to execute given command.", 403);
	}
}
