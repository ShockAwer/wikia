<?php
namespace Wikia\UI;

use Wikia\Template\MustacheEngine;

class Component {

	/**
	 * @desc Mustache file extension ;)
	 */
	const MUSTACHE_FILE_EXTENSION = 'mustache';

	/**
	 * @desc default type of a component i.e. buttons have three types: input, button and link
	 */
	const COMPONENT_DEFAULT_TYPE = 'default';

	/**
	 * @var $templateEngine \Wikia\Template\Engine
	 */
	private $templateEngine;

	/**
	 * @desc Array with required and optional attributes for component
	 * @var Array $templateVarsConfig
	 */
	private $templateVarsConfig;

	/**
	 * @var String base path to templates (a prefix for full $templatePath)
	 */
	private $templateBasePath;

	/**
	 * @var String path to the template file
	 */
	private $templatePath;

	/**
	 * @var Array data passed to the template
	 */
	private $templateData;

	/**
	 * @var String a type of the component i.e. buttons have three types: input, button and link
	 */
	private $type;

	/**
	 * @var String component name
	 */
	private $name;

	/**
	 * @var Array assets needed to render this components. This dictionary contains arrays under the
	 * Factory::ASSET_TYPE_CSS and Factory::ASSET_TYPE_JS keys
	 */
	private $assets = null;

	/**
	 * @var Array names of the components that this component depends on
	 */
	private $componentDependencies = [];

	/**
	 * @var String name of the JS AMD module that wraps the component functionality
	 */
	private $jsWrapperModule = null;

	/**
	 * @desc Sets template JS and CSS assets
	 *
	 * @param $assets Dictionary containing Factory::ASSET_TYPE_CSS and Factory::ASSET_TYPE_JS keys
	 */
	public function setAssets( $assets ) {
		$this->assets = $assets;
	}

	/**
	 * @desc Returns a dictionary of JS and CSS assets
	 *
	 * @return Array
	 */
	public function getAssets() {
		return $this->assets;
	}

	/**
	 * @desc Set the dependency on other components
	 *
	 * @param $componentDependencies array containing names of the components
	 */
	public function setComponentDependencies( $componentDependencies ) {
		$this->componentDependencies = $componentDependencies;
	}

	/**
	 * @desc Returns names of the components this component depends on
	 *
	 * @return array containing names of the components
	 */
	public function getComponentDependencies() {
		return $this->componentDependencies;
	}

	/**
	 * @desc Sets the JS wrapper module name
	 *
	 * @param $jsWrapperModule String name of the AMD module
	 */
	public function setJSWrapperModule( $jsWrapperModule ) {
		$this->jsWrapperModule = $jsWrapperModule;
	}

	/**
	 * @desc Returns JS wrapper module name for the component
	 *
	 * @return String
	 */
	public function getJSWrapperModule() {
		return $this->jsWrapperModule;
	}

	/**
	 * @desc Set component name
	 *
	 * @param $name component name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * @desc Get component name
	 *
	 * @return String component name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @desc Sets template variables from configuration
	 *
	 * @param $templateVarsConfig
	 */
	public function setTemplateVarsConfig( $templateVarsConfig ) {
		$this->templateVarsConfig = $templateVarsConfig;
	}

	/**
	 * @desc Gets template variables set from configuration
	 *
	 * @return array
	 */
	public function getTemplateVarsConfig() {
		return $this->templateVarsConfig;
	}

	/**
	 * @desc Validates if required template variables' values are set and renders component
	 *
	 * @param $params
	 * @return string
	 * @throws DataException
	 */
	public function render( $params ) {
		$this->setType( $params['type'] );
		$this->setVarsValues( $params['vars'] ); // set mustache variables
		$this->validateTemplateVars(); // check if required vars are set

		return $this->getTemplateEngine()
			->setData( $this->getVarsValues() )
			->render( $this->getTemplatePath() );
	}

	/**
	 * @desc Returns base path for template
	 *
	 * @return String
	 */
	public function getBaseTemplatePath() {
		return $this->templateBasePath;
	}

	/**
	 * @desc Sets base path for template
	 *
	 * @param $path
	 * @return mixed
	 */
	public function setBaseTemplatePath( $path ) {
		return $this->templateBasePath = $path;
	}

	/**
	 * @param String $template "sub template" name of the component (i.e. a button component can have three
	 * "sub templates": button_input.mustache, button_link.mustache and button_button.mustache the part between _ and .
	 * is a "sub template" name
	 *
	 * @throws \Wikia\UI\TemplateException
	 */
	private function setTemplatePath( $template ) {
		$template = \Sanitizer::escapeId( strtolower( $template ), 'noninitial' );
		$mustacheTplPath = $this->getBaseTemplatePath() . '_' . $template . '.' . self::MUSTACHE_FILE_EXTENSION;
		if ( $this->fileExists( $mustacheTplPath ) ) {
			$this->templatePath = $mustacheTplPath;
		} else {
			throw new TemplateException( TemplateException::EXCEPTION_MSG_INVALID_TEMPLATE . ' (' . $mustacheTplPath . ')' );
		}
	}

	/**
	 * @desc Returns template path. This should be called after setting the type of the component
	 * @return String
	 */
	public function getTemplatePath() {
		return $this->templatePath;
	}

	/**
	 * @desc Sets type for a component
	 *
	 * @param $type
	 */
	public function setType( $type ) {
		$this->type = $type;
		$this->setTemplatePath( $this->type );
	}

	/**
	 * @desc Returns type of component
	 *
	 * @return String
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @desc Sets template variables and their values
	 *
	 * @param array $varsArray an array with key=>value structure;
	 * key is the template variable name and the second is its value
	 */
	public function setVarsValues( Array $varsArray ) {
		$this->templateData = $varsArray;
	}

	/**
	 * @desc Gets template variables and their values
	 *
	 * @return Array
	 */
	public function getVarsValues() {
		return $this->templateData;
	}

	/**
	 * @desc Checks if all required variables are set; throws an exception if not
	 *
	 * @throws DataException
	 */
	private function validateTemplateVars() {
		$config = $this->getTemplateVarsConfig();
		$data = $this->getVarsValues();

		foreach ( $config[ $this->getType() ][ 'required' ] as $templateRequiredVarName ) {
			if ( ! isset( $data[ $templateRequiredVarName ] ) ) {
				$exceptionMessage = DataException::getInvalidParamDataMsg($templateRequiredVarName);
				throw new DataException( $exceptionMessage );
			}
		}
	}

	/**
	 * @return \Wikia\Template\Engine
	 */
	private function getTemplateEngine() {
		if ( empty( $this->templateEngine ) ) {
			$this->templateEngine = new MustacheEngine;
		}

		return $this->templateEngine;
	}

	/**
	 * @desc Method using built-in PHP function; created because of unit tests
	 *
	 * @param string $file path to a file
	 * @return bool
	 */
	public function fileExists($file) {
		return file_exists($file);
	}

}
