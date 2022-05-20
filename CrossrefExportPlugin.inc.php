<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.plugins.ImportExportPlugin');
import('plugins.importexport.crossref.CrossrefExportDeployment');
define('CROSSREF_API_DEPOSIT_OK', 200);
define('CROSSREF_STATUS_FAILED', 'failed');
define('CROSSREF_API_URL', 'https://api.crossref.org/v2/deposits');
define('CROSSREF_API_URL_DEV', 'https://test.crossref.org/v2/deposits');
define('EXPORT_STATUS_REGISTERED', 'registered');


class CrossrefExportPlugin extends ImportExportPlugin {

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
				$result = $this->exportSubmissions((array)$request->getUserVar('submission'));
				$this->createNotifications($request, $result);
				$request->redirect(null, 'management', 'importexport', array('plugin', 'CrossrefExportPlugin'));
				break;
			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	function getName() {
		return 'CrossrefExportPlugin';
	}

	function getSettings(TemplateManager $templateMgr) {
		$request = Application::getRequest();
		$press = $request->getPress();
		$username = $this->getSetting($press->getId(), 'username');
		$templateMgr->assign('username', $username);
		$password = $this->getSetting($press->getId(), 'password');
		$templateMgr->assign('password', $password);
		$testMode = $this->getSetting($press->getId(), 'testMode');
		$templateMgr->assign('testMode', $testMode);
		return array($press, $username, $password, $testMode);
	}

	function updateSettings($request) {
		$contextId = $request->getContext()->getId();
		$userVars = $request->getUserVars();
		if (count($userVars) > 0) {
			$this->updateSetting($contextId, "username", $userVars["username"]);
			$this->updateSetting($contextId, "password", $userVars["password"]);
			$this->updateSetting($contextId, "testMode", $userVars["testMode"]);
		}
	}

	private function depositHandler($request, TemplateManager $templateMgr) {
		$context = $request->getContext();
		$username = $this->getSetting($context->getId(), 'username');
		$password = $this->getSetting($context->getId(), 'password');

		$submissionService = Services::get('submission');
		$submissions = $submissionService->getMany([
			'contextId' => $context->getId(),
			'status' => STATUS_PUBLISHED,
		]);
		$itemsQueue = [];
		$itemsDeposited = [];
		$locale = AppLocale::getLocale();

		foreach ($submissions as $submission) {
			$submissionId = $submission->getId();
			$publication = $submission->getCurrentPublication();
			$doi = $submission->getCurrentPublication()->getData('pub-id::doi');

			$registeredDoi = Capsule::table('submission_settings')
						->where('submission_id', '=', $submissionId)
						->where('setting_name', '=', 'crossref::registeredDoi')
						->value('setting_value');

			$failedMsg = Capsule::table('submission_settings')
						->where('submission_id', '=', $submissionId)
						->where('setting_name', '=', 'crossref::failedMsg')
						->value('setting_value');

			$notices = [];
			$errors = [];

			$chapters = $publication->getData('chapters');
			$chapterDois = [];
			foreach ($chapters as $chapter) {
				if ($chapter->getData('pub-id::doi')){
					$chapterDois[] = $chapter->getData('pub-id::doi');
				}
			}

			// Check for notices and errors:
			// Notice: No ISBN! Element 'noisbn' will be used in export.
			// Notice: Crossref failed messages
			// Notice: No ISSN for series!
			// Error: No publisher name in Press settings

			$publicationFormats = $publication->getData('publicationFormats');
			$noIsbn = true;
			foreach ($publicationFormats as $publicationFormat) {
				$identificationCodes = $publicationFormat->getIdentificationCodes();
				while ($identificationCode = $identificationCodes->next()) {
					if ($identificationCode->getCode() == "02" || $identificationCode->getCode() == "15") {
						// 02 and 15: ONIX codes for ISBN-10 or ISBN-13
						$noIsbn = false;
					}
				}
			}
			if ($noIsbn){
				$notices[] = __('plugins.importexport.crossref.notice.noIsbn');
			}

			if ($failedMsg) {
				 $notices[] = $failedMsg;
			}

			if (!$username && !$password) {
				 $errors[] = __('plugins.importexport.crossref.error.noUserCrecentials');
			}

			if (!$context->getData('publisher')) {
				 $errors[] = __('plugins.importexport.crossref.error.noPublisher');
			}

			$seriesDao = DAORegistry::getDAO('SeriesDAO'); /* @var $seriesDao SeriesDAO */
			if ($series = $seriesDao->getById($publication->getData('seriesId'))){
				if (!$series->getOnlineISSN() && !$series->getPrintISSN()) {
					$notices[] = __('plugins.importexport.crossref.error.noIssn');
				}
			}

			if ($doi and $registeredDoi) {
				$itemsDeposited[] = array(
					'id' => $submissionId,
					'title' => $submission->getLocalizedTitle($locale),
					'authors' => $submission->getAuthorString($locale),
					'pubId' => $doi,
					'chapterPubIds' => $chapterDois,
					'notices' => $notices,
					'errors' => $errors,
				);
			}
			if ($doi and !$registeredDoi) {
				$itemsQueue[] = array(
					'id' => $submissionId,
					'title' => $submission->getLocalizedTitle($locale),
					'authors' => $submission->getAuthorString($locale),
					'pubId' => $doi,
					'chapterPubIds' => $chapterDois,
					'notices' => $notices,
					'errors' => $errors,
				);
			}
		}

		$templateMgr->assign('itemsQueue', $itemsQueue);
		$templateMgr->assign('itemsSizeQueue', sizeof($itemsQueue));
		$templateMgr->assign('itemsDeposited', $itemsDeposited);
		$templateMgr->assign('itemsSizeDeposited', sizeof($itemsDeposited));
	}

	function isTestMode($context) {
		return ($this->getSetting($context->getId(), 'testMode') == 1);
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
			$publication = $submission->getCurrentPublication();
			$doi = $publication->getStoredPubId('doi');
			if ($doi) {
				$DOMDocument = new DOMDocument('1.0', 'utf-8');
				$DOMDocument->formatOutput = true;
				$DOMDocument = $deployment->createNodes($DOMDocument, $submission, $publication);
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'crossref-' . $submissionId, $press, '.xml');
				$exportXml = $DOMDocument->saveXML();
				error_log(print_r($exportXml, true));
				$fileManager->writeFile($exportFileName, $exportXml);
				$result = $this->depositXML($submission, $press, $exportFileName);
				$fileManager->deleteByPath($exportFileName);
			}
		}

		return $result;
	}

	function depositXML($submission, $context, $filename) {
		$status = null;
		$msg = null;

		import('lib.pkp.classes.helpers.PKPCurlHelper');
		$curlCh = PKPCurlHelper::getCurlObject();

		curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlCh, CURLOPT_POST, true);
		curl_setopt($curlCh, CURLOPT_HEADER, 0);

		// Use a different endpoint for testing and production.
		$endpoint = ($this->isTestMode($context) ? CROSSREF_API_URL_DEV : CROSSREF_API_URL);
		curl_setopt($curlCh, CURLOPT_URL, $endpoint);
		// Set the form post fields
		$username = $this->getSetting($context->getId(), 'username');
		$password = $this->getSetting($context->getId(), 'password');
		assert(is_readable($filename));
		if (function_exists('curl_file_create')) {
			curl_setopt($curlCh, CURLOPT_SAFE_UPLOAD, true);
			$cfile = new CURLFile($filename);
		} else {
			$cfile = "@$filename";
		}
		$data = array('operation' => 'doMDUpload', 'usr' => $username, 'pwd' => $password, 'mdFile' => $cfile);
		curl_setopt($curlCh, CURLOPT_POSTFIELDS, $data);
		$response = curl_exec($curlCh);

		if ($response === false) {
			$result = array(array('plugins.importexport.common.register.error.mdsError', 'No response from server.'));
		} elseif (curl_getinfo($curlCh, CURLINFO_HTTP_CODE) != CROSSREF_API_DEPOSIT_OK) {
			// These are the failures that occur immediatelly on request
			// and can not be accessed later, so we save the falure message in the DB
			$xmlDoc = new DOMDocument();
			$xmlDoc->loadXML($response);
			// Get batch ID
			$batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
			// Get re message
			$msg = $xmlDoc->getElementsByTagName('msg')->item(0)->nodeValue;
			$status = CROSSREF_STATUS_FAILED;
			$result = false;
		} else {
			// Get DOMDocument from the response XML string
			$xmlDoc = new DOMDocument();
			$xmlDoc->loadXML($response);
			$batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);

			// Get the DOI deposit status
			// If the deposit failed
			$failureCountNode = $xmlDoc->getElementsByTagName('failure_count')->item(0);
			$failureCount = (int) $failureCountNode->nodeValue;

			if ($failureCount > 0) {
				$status = CROSSREF_STATUS_FAILED;
				$result = false;
			} else {
				// Deposit was received
				$status = EXPORT_STATUS_REGISTERED;
				$result = true;

				// If there were some warnings, display them
				$warningCountNode = $xmlDoc->getElementsByTagName('warning_count')->item(0);
				$warningCount = (int) $warningCountNode->nodeValue;
				if ($warningCount > 0) {
					$result = array(array('plugins.importexport.crossref.register.success.warning', htmlspecialchars($response)));
				}
			}
		}

		// Update the status
		if ($status) {
			$this->updateDepositStatus($context, $submission, $status, $batchIdNode->nodeValue, $msg);
		}

		curl_close($curlCh);
		return $result;
	}

	function updateDepositStatus($context, $submission, $status, $batchId, $failedMsg = null) {
		assert(is_a($submission, 'Submission'));

		Capsule::table('submission_settings')
			->where('submission_id', '=', $submission->getId())
			->where('setting_name', '=', 'crossref::failedMsg')
			->delete();

		if ($failedMsg) {
			Capsule::table('submission_settings')->insert([
				'submission_id' => $submission->getId(),
				'locale' => '',
				'setting_name' => 'crossref::failedMsg',
				'setting_value' => $failedMsg
			]);
		}

		if ($status == EXPORT_STATUS_REGISTERED) {
			// Save the DOI -- the submission will be updated
			$this->saveRegisteredDoi($context, $submission);
		}
	}

	function saveRegisteredDoi($context, $submission) {
		$registeredDoi = $submission->getStoredPubId('doi');
		assert(!empty($registeredDoi));

		Capsule::table('submission_settings')
			->where('submission_id', '=', $submission->getId())
			->where('setting_name', '=', 'crossref::registeredDoi')
			->delete();

		Capsule::table('submission_settings')->insert([
			'submission_id' => $submission->getId(),
			'locale' => '',
			'setting_name' => 'crossref::registeredDoi',
			'setting_value' => $registeredDoi
		]);
	}

	private function createNotifications($request, $result) {
		if (!$result) {
			$this->_sendNotification(
				$request->getUser(),
				'plugins.importexport.crossref.register.error.mdsError',
				NOTIFICATION_TYPE_ERROR
			);
		} else {
			if (!is_array($result)) {
				$this->_sendNotification(
					$request->getUser(),
					'plugins.importexport.crossref.register.success',
					NOTIFICATION_TYPE_SUCCESS
				);
			} else {
				foreach ($result as $submission => $error) {
					assert(is_array($error) && count($error) >= 1);
					self::writeLog($submission . " ::  " . $error, 'ERROR');
					$this->_sendNotification(
						$request->getUser(),
						$error[0],
						NOTIFICATION_TYPE_ERROR,
						(isset($error[1]) ? $error[1] : null)
					);
				}
			}
		}

	}

	private static function writeLog($message, $level) {
		$fineStamp = date('Y-m-d H:i:s') . substr(microtime(), 1, 4);
		error_log("$fineStamp $level $message\n", 3, self::logFilePath());
	}

	function _sendNotification($user, $message, $notificationType, $param = null) {
		static $notificationManager = null;
		if (is_null($notificationManager)) {
			import('classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
		}
		if (!is_null($param)) {
			$params = array('param' => $param);
		} else {
			$params = null;
		}
		$notificationManager->createTrivialNotification(
				$user->getId(),
				$notificationType,
				array('contents' => __($message, $params))
				);
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
