<?php


import('lib.pkp.classes.plugins.ImportExportPlugin');
import('plugins.importexport.crossref.CrossrefExportDeployment');
define('CROSSREF_API_RESPONSE_OK', array(200));

define('CROSSREF_API_REGISTRY', 'https://crossref.org');
define('CROSSREF_MDS_REGISTRY', 'https://mds.crossref.org/metadata/');
define('CROSSREF_MDS_TEST__REGISTRY', 'https://mds.test.crossref.org/metadata/');


class CrossrefExportPlugin extends ImportExportPlugin
{

	function __construct() {
		parent::__construct();
	}

	function display($args, $request) {

		$templateMgr = TemplateManager::getManager($request);
		parent::display($args, $request);

		$templateMgr->assign('plugin', $this->getName());

		switch (array_shift($args)) {
			case 'settings':
				$this->getSettings($templateMgr);
				$this->updateSettings($request);
				$request->redirect(null, 'management', 'importexport', array('plugin', 'CrossrefExportPlugin'));
			case '':
				$this->getSettings($templateMgr);
				$this->depositHandler($request, $templateMgr);
				$templateMgr->display($this->getTemplateResource('index.tpl'));

				break;
			case 'export':
				import('classes.notification.NotificationManager');

				$responses = $this->exportSubmissions((array)$request->getUserVar('submission'));
				$this->createNotifications($request, $responses);
				$request->redirect(null, 'management', 'importexport', array('plugin', 'CrossrefExportPlugin'));
				break;
			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	function getName()
	{

		return 'CrossrefExportPlugin';
	}

	function getSettings(TemplateManager $templateMgr) {
		$request = Application::getRequest();
		$press = $request->getPress();


		$api = $this->getSetting($press->getId(), 'api');
		$templateMgr->assign('api', $api);
		$username = $this->getSetting($press->getId(), 'username');
		$templateMgr->assign('username', $username);
		$password = $this->getSetting($press->getId(), 'password');
		$templateMgr->assign('password', $password);
		$testMode = $this->getSetting($press->getId(), 'testMode');
		$templateMgr->assign('testMode', $testMode);
		$testPrefix = $this->getSetting($press->getId(), 'testPrefix');
		$templateMgr->assign('testPrefix', $testPrefix);
		$testRegistry = $this->getSetting($press->getId(), 'testRegistry');
		$templateMgr->assign('testRegistry', $testRegistry);
		$testUrl = $this->getSetting($press->getId(), 'testUrl');
		$templateMgr->assign('testUrl', $testUrl);

		return array($press, $api, $username, $password, $testMode, $testPrefix, $testRegistry, $testUrl);
	}

	function updateSettings($request) {

		$contextId = $request->getContext()->getId();
		$userVars = $request->getUserVars();
		if (count($userVars) > 0) {
			$this->updateSetting($contextId, "api", $userVars["api"]);
			$this->updateSetting($contextId, "username", $userVars["username"]);
			$this->updateSetting($contextId, "password", $userVars["password"]);
			$this->updateSetting($contextId, "testMode", $userVars["testMode"]);
			$this->updateSetting($contextId, "testPrefix", $userVars["testPrefix"]);
			$this->updateSetting($contextId, "testRegistry", $userVars["testRegistry"]);
			$this->updateSetting($contextId, "testUrl", $userVars["testUrl"]);
		}
	}

	private function depositHandler($request, TemplateManager $templateMgr) {

		$context = $request->getContext();
		$press = $request->getPress();
		$submissionService = Services::get('submission');
		$submissions = $submissionService->getMany(['contextId' => $press->getId()]);
		$itemsQueue = [];
		$itemsDeposited = [];
		$locale = AppLocale::getLocale();
		$registry = $this->getRegistry($press);
		foreach ($submissions as $submission) {
			$submissionId = $submission->getId();
			$doi = $submission->getCurrentPublication()->getData('pub-id::doi');
			$registeredDoi = $submission->getCurrentPublication()->getData('crossref::registeredDoi');

			if ($doi and $registeredDoi) {
				$itemsDeposited[] = array(
					'id' => $submissionId,
					'title' => $submission->getLocalizedTitle($locale),
					'authors' => $submission->getAuthorString($locale),
					'pubId' => $doi,
					'registry' => $registry,
				);
			}
			if ($doi and !$registeredDoi) {
				$itemsQueue[] = array(
					'id' => $submissionId,
					'title' => $submission->getLocalizedTitle($locale),
					'authors' => $submission->getAuthorString($locale),
					'pubId' => $doi,
					'registry' => $registry,
				);
			}
		}
		$templateMgr->assign('itemsQueue', $itemsQueue);
		$templateMgr->assign('itemsSizeQueue', sizeof($itemsQueue));
		$templateMgr->assign('itemsDeposited', $itemsDeposited);
		$templateMgr->assign('itemsSizeDeposited', sizeof($itemsDeposited));
	}

	function getRegistry($press) {

		$registry = CROSSREF_API_REGISTRY;
		if ($this->isTestMode($press)) {
			$registry = $this->getSetting($press->getId(), 'testRegistry');
		}

		return $registry;
	}

	function isTestMode($press) {

		$testMode = $this->getSetting($press->getId(), 'testMode');

		return ($testMode == "on");
	}

	function exportSubmissions($submissionIds) {

		import('lib.pkp.classes.file.FileManager');
		$submissionDao = Application::getSubmissionDAO();
		$request = Application::getRequest();
		$press = $request->getPress();
		$fileManager = new FileManager();
		$result = array();
		foreach ($submissionIds as $submissionId) {
			$deployment = new CrossrefExportDeployment($request, $this);
			$submission = $submissionDao->getById($submissionId, $request->getContext()->getId());
			if ($submission->getCurrentPublication()->getData('pub-id::doi')) {
				$DOMDocument = new DOMDocument('1.0', 'utf-8');
				$DOMDocument->formatOutput = true;
				$DOMDocument = $deployment->createNodes($DOMDocument, $submission, null, true);
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'crossref-' . $submissionId, $press, '.xml');
				$exportXml = $DOMDocument->saveXML();
				error_log(print_r($exportXml, true));
				$fileManager->writeFile($exportFileName, $exportXml);
				$response = $this->depositXML($submission, $exportFileName, true);
				$result[$submissionId] = ($response != "") ? $response : "";
				$fileManager->deleteByPath($exportFileName);
			}
		}

		return $result;
	}

	function depositXML($object, $filename) {


		$doi = $object->getData('pub-id::doi');
		$request = Application::getRequest();
		$press = $request->getPress();

		assert(!empty($doi));
		if ($this->isTestMode($press)) {
			$doi = $this->createTestDOI($request, $doi);
		}

		$url = Request::url($press->getPath(), 'catalog', 'book', array($object->getId()));
		assert(!empty($url));

		$curlCh = curl_init();


		$username = $this->getSetting($press->getId(), 'username');
		$api = $this->getSetting($press->getId(), 'api');
		$password = $this->getSetting($press->getId(), 'password');


		if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
			curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
			curl_setopt($curlCh, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
			if ($username = Config::getVar('proxy', 'username')) {
				curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
			}
		}

		if (array_key_exists('redeposit', $request->getUserVars())) {
			if ($request->getUserVar('redeposit') == 1) {
				$api = ($this->isTestMode($press)) ? CROSSREF_MDS_TEST__REGISTRY . $doi : CROSSREF_MDS_REGISTRY . $doi;
				curl_setopt($curlCh, CURLOPT_HTTPHEADER, array('Content-Type: text/plain;charset=UTF-8'));
			}
		} else {
			curl_setopt($curlCh, CURLOPT_HTTPHEADER, array('Content-Type: application/vnd.api+json'));
		}

		

		curl_setopt($curlCh, CURLOPT_VERBOSE, true);
		curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlCh, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curlCh, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($curlCh, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curlCh, CURLOPT_URL, $api);

		assert(is_readable($filename));
		$payload = file_get_contents($filename);

		assert($payload !== false && !empty($payload));
		$fp = fopen ($filename, "r");

		curl_setopt($curlCh, CURLOPT_VERBOSE, false);

		if (array_key_exists('redeposit', $request->getUserVars())) {
			if ($request->getUserVar('redeposit') == 1) {
				curl_setopt($curlCh, CURLOPT_PUT, true);
				curl_setopt($curlCh, CURLOPT_INFILE, $fp);

			}
		} else {
			$crossrefPayloadObject = $this->createCrossrefPayload($object, $url, $payload, true);
			curl_setopt($curlCh, CURLOPT_POSTFIELDS, $crossrefPayloadObject);
		}
		$response = curl_exec($curlCh);
		
		$status = curl_getinfo($curlCh, CURLINFO_HTTP_CODE);
		curl_close($curlCh);
		fclose($fp);


		$this->setDOI($object, $status, $response, $press, $request, $doi);

		return $response;
	}

	public function createTestDOI($request, $doi)
	{
		return PKPString::regexp_replace('#^[^/]+/#', $this->getCrossrefAPITestPrefix() . '/', $doi);
	}

	function getCrossrefAPITestPrefix()
	{
		$request = Application::getRequest();
		$press = $request->getPress();

		return $this->getSetting($press->getId(), 'testPrefix');
	}

	function createCrossrefPayload($obj, $url, $payload, $payLoadAvailable = false) {

		$doi = $obj->getStoredPubId("doi");
		$request = Application::getRequest();
		$press = $request->getPress();
		if ($this->isTestMode($press)) {
			$doi = $this->createTestDOI($request, $doi);
		}
		if ($payLoadAvailable) {
			$jsonPayload = array("data" => (array('id' => $doi, 'type' => "dois", 'attributes' => array("event" => "publish", "doi" => $doi, "url" => $url, "xml" => base64_encode($payload)))));
		} else {
			$jsonPayload = array("data" => (array('type' => "dois", 'attributes' => array("doi" => $doi))));
		}

		return json_encode($jsonPayload, JSON_UNESCAPED_SLASHES);

	}

	private function setDOI($object, $status, $response, $press, $request, $doi): void {
		$result = true;
		if (!in_array($status, CROSSREF_API_RESPONSE_OK)) {
			$result = array(array('plugins.importexport.common.register.error.mdsError', $response));
		}


		if ($result === true) {
			if ($this->isTestMode($press)) {
				$doi = $this->createTestDOI($request, $doi);
			}
			$object->setData('crossref::registeredDoi', $doi);
			$submissionDao = Application::getSubmissionDAO();
			$submissionDao->updateObject($object);
		}
	}

	private function createNotifications($request, array $responses) {

		$success = 1;
		$notification = "";
		$notificationManager = new NotificationManager();
		foreach ($responses as $submission => $error) {
			$result = json_decode(str_replace("\n", "", $error), true);

			if (isset($result["errors"])) {
				$detail = $result["errors"][0]["title"];
				$success = 0;
				self::writeLog($submission . " ::  " . $detail, 'ERROR');
				$notificationManager->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $detail));
			}
		}

		if ($success == 1) {
			$detail = (strpos(implode($responses), 'OK') !== false) ? implode($responses) : "Successfully deposited";
			$notificationManager->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $detail));
		}
	}

	private static function writeLog($message, $level) {

		$fineStamp = date('Y-m-d H:i:s') . substr(microtime(), 1, 4);
		error_log("$fineStamp $level $message\n", 3, self::logFilePath());
	}

	public static function logFilePath() {

		return Config::getVar('files', 'files_dir') . '/CROSSREF_ERROR.log';
	}

	function executeCLI($scriptName, &$args) {

		fatalError('Not implemented.');
	}

	function getDescription() {

		return __('plugins.importexport.crossref.description');
	}

	function getDisplayName() {

		return __('plugins.importexport.crossref.displayName');
	}

	function getPluginSettingsPrefix() {

		return 'crossref';
	}

	function register($category, $path, $mainContextId = null) {

		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled()) {
			$this->addLocaleData();
			$this->import('CrossrefExportDeployment');
		}

		return $success;
	}

	function usage($scriptName) {

		fatalError('Not implemented.');
	}

}
