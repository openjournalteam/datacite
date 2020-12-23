<?php
/**
 * @defgroup plugins_importexport_crossref Crossref export plugin
 */

/**
 * @file plugins/importexport/crossref/CrossrefExportDeployment.inc.php*
 * @class CrossrefExportDeployment
 * @ingroup plugins_importexport_crossref* @brief Base class configuring the crossref export process to an
 * application's specifics.
 */

define('CROSSREF_XMLNS', 'http://crossref.org/schema/kernel-4');
define('XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('CROSSREF_XSI_SCHEMA_LOCATION', 'http://schema.crossref.org/meta/kernel-4.3/metadata.xsd');
import('lib.pkp.classes.plugins.importexport.PKPImportExportDeployment');

class CrossrefExportDeployment extends PKPImportExportDeployment {

	var $_context;

	var $_plugin;

	function __construct($request, $plugin) {
		$context = $request->getContext();
		parent::__construct($context, $plugin);
		$this->_context = $context;
		$this->_plugin = $plugin;
	}

	function getNamespace() {
		return CROSSREF_XMLNS;
	}

	function getPlugin() {
		return $this->_plugin;
	}

	function setPlugin($plugin) {
		$this->_plugin = $plugin;
	}

	function getRootElementName() {
		return 'resource';
	}

	function getXmlSchemaInstance() {
		return XMLNS_XSI;
	}

	function getSchemaLocation() {
		return CROSSREF_XSI_SCHEMA_LOCATION;
	}

	function createNodes($documentNode, $object) {
		$documentNode = $this->createRootNode($documentNode);
		$documentNode = $this->createResourceType($documentNode);
		$documentNode = $this->createResourceIdentifier($documentNode, $object);
		$documentNode = $this->createTitles($documentNode, $object);
		$documentNode = $this->createAuthors($documentNode, $object);
		$documentNode = $this->createPublicationYear($documentNode, $object);
		$documentNode = $this->createPublisher($documentNode);
		return $documentNode;
	}

	function createRootNode($documentNode) {
		$rootNode = $documentNode->createElementNS($this->getNamespace(), $this->getRootElementName());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $this->getXmlSchemaInstance());
		$rootNode->setAttribute('xsi:schemaLocation', $this->getNamespace() . ' ' . $this->getSchemaLocation());
		$documentNode->appendChild($rootNode);
		return $documentNode;
	}

	function createResourceType($documentNode) {
		$type = 'Monograph';
		$e = $documentNode->createElement("resourceType", $type);
		$e->setAttribute('resourceTypeGeneral', 'Text');
		$documentNode->documentElement->appendChild($e);
		return $documentNode;
	}

	function createResourceIdentifier($documentNode, $object) {

		$request = Application::getRequest();
		$press = $request->getPress();
		$pubId = $object->getData('pub-id::doi');

		if (isset($pubId)) {
			if ($this->getPlugin()->isTestMode($press)) {
				$pubId = preg_replace('/^[\d]+(.)[\d]+/', $this->getPlugin()->getSetting($press->getId(), "testPrefix"), $pubId);
			}
		}

		$identifier = $documentNode->createElement("identifier", $pubId);
		$identifier->setAttribute("identifierType", "DOI");
		$documentNode->documentElement->appendChild($identifier);

		return $documentNode;
	}

	function createTitles($documentNode, $object) {

		$locale = $object->getData('locale');
		$localizedTitle = $object->getLocalizedTitle($locale);
		$language = $documentNode->createElement("language", substr($locale, 0, 2));
		$titles = $documentNode->createElement("titles");
		$titleValue = $this->xmlEscape($localizedTitle);

		$title = $documentNode->createElement("title", $titleValue);
		$title->setAttribute("xml:lang", str_replace_first("_", "-", $locale));
		$titles->appendChild($title);

		$documentNode->documentElement->appendChild($titles);

		return $documentNode;
	}

	function xmlEscape($value) {
		return XMLNode::xmlentities($value, ENT_NOQUOTES);
	}

	function createAuthors($documentNode, $object) {

		$locale = $object->getData('locale');
		$creators = $documentNode->createElement("creators");
		$authors = $object->getAuthors();
		foreach ($authors as $author) {
			$creator = $this->createAuthor($documentNode, $author, $locale);
			if ($creator) {
				$creators->appendChild($creator);
				$documentNode->documentElement->appendChild($creators);
			}
		}
		return $documentNode;
	}

	function createAuthor($documentNode, $author, $locale) {

		$creator = $documentNode->createElement("creator");
		$person = $documentNode->createElement("person");
		$familyName = $author->getFamilyName($locale);
		$givenName = $author->getGivenName($locale);
		if ($familyName == '' && $givenName == '') {
			return null;
		}

		$creatorName = $documentNode->createElement("creatorName", $familyName . ', ' . $givenName);
		$cratorName->setAttribute("nameType", "Personal");
		$creator->appendChild($creatorName);

		return $creator;
	}

	function createPublicationYear($documentNode, $object) {

		$date = $object->getDatePublished();
		if ($date == null) {
			$date = $object->getDateSubmitted();
		}

		$publicationYear = $documentNode->createElement("publicationYear", substr($date, 0, 4));
		$documentNode->documentElement->appendChild($publicationYear);

		return $documentNode;
	}

	function createPublisher($documentNode) {

		$request = Application::getRequest();
		$press = $request->getPress();
		$publisher = $documentNode->createElement("publisher", $press->getData('publisher'));
		$documentNode->documentElement->appendChild($publisher);

		return $documentNode;

	}

	function getContext()
	{

		return $this->_context;
	}

	function setContext($context)
	{

		$this->_context = $context;
	}


}
