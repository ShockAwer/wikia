<?php

/**
 * @author Inez Korczyński <korczynski@gmail.com>
 * @author Piotr Bablok <piotr.bablok@gmail.com>
 */

class AssetsManagerSassBuilder extends AssetsManagerBaseBuilder {

	public function __construct(WebRequest $request) {
		global $wgDevelEnvironment;
		$wgDevelEnvironment ? $timeStart = microtime( true ) : null;

		parent::__construct($request);

		if (strpos($this->mOid, '..') !== false) {
			throw new Exception('File path must not contain \'..\'.');
		}

		if (!endsWith($this->mOid, '.scss', false)) {
			throw new Exception('Requested file must be .scss.');
		}

		//remove slashes at the beginning of the string, we need a pure relative path to open the file
		$this->mOid = preg_replace( '/^[\/]+/', '', $this->mOid );

		$hash = wfAssetManagerGetSASShash( $this->mOid );
		$inputHash = md5(urldecode(http_build_query($this->mParams, '', ' ')));

		$cacheId = "/Sass-$inputHash-$hash";

		$memc = F::App()->wg->Memc;

		if ( $obj = $memc->get( $cacheId ) ) {
			$this->mContent = $obj;
		} else {
			$this->processContent();
			// Prevent cache poisoning if we are serving sass from preview server
			if (getHostPrefix() == null) {
				$memc->set( $cacheId, $this->mContent );
			}
		}

		$this->mContentType = AssetsManager::TYPE_CSS;

		if( $wgDevelEnvironment ) {
			$timeEnd = microtime( true );
			$time = intval( ($timeEnd - $timeStart) * 1000 );
			$contentLen = strlen( $this->mContent);
			error_log( "{$this->mOid}\t{$time}ms {$contentLen}b" );
		}
	}

	private function processContent() {
		wfProfileIn(__METHOD__);

		$this->sassProcessing();
		$this->importsProcessing();
		$this->stylePathProcessing();
		$this->base64Processing();
		$this->janusProcessing();

		wfProfileOut(__METHOD__);
	}

	private function sassProcessing() {
		global $IP, $wgSassExecutable, $wgDevelEnvironment;
		wfProfileIn(__METHOD__);

		$tempDir = sys_get_temp_dir();
		//replace \ to / is needed because escapeshellcmd() replace \ into spaces (?!!)
		$tempOutFile = str_replace('\\', '/', tempnam($tempDir, 'Sass'));
		$tempDir = str_replace('\\', '/', $tempDir);
		$params = urldecode(http_build_query($this->mParams, '', ' '));

		$cmd = "{$wgSassExecutable} {$IP}/{$this->mOid} {$tempOutFile} --cache-location {$tempDir}/sass --style compressed -r {$IP}/extensions/wikia/SASS/wikia_sass.rb {$params}";
		$escapedCmd = escapeshellcmd($cmd) . " 2>&1";

		$sassResult = shell_exec($escapedCmd);
		if ($sassResult != '') {
			Wikia::log(__METHOD__, false, "commandline error: " . $sassResult. " -- Full commandline was: $escapedCmd", true /* $always */);
			Wikia::log(__METHOD__, false, "Full commandline was: {$escapedCmd}", true /* $always */);
			Wikia::log(__METHOD__, false, AssetsManager::getRequestDetails(), true /* $always */);
			if ( file_exists( $tempOutFile ) ) {
				unlink($tempOutFile);
			}

			if (!empty($wgDevelEnvironment)) {
				$exceptionMsg = "Problem with SASS processing: {$sassResult}";
			}
			else {
				$exceptionMsg = 'Problem with SASS processing. Check the PHP error log for more info.';
			}

			throw new Exception("/* {$exceptionMsg} */");
		}

		$this->mContent = file_get_contents($tempOutFile);

		unlink($tempOutFile);

		wfProfileOut(__METHOD__);
	}

	private function importsProcessing() {
		global $IP;
		wfProfileIn(__METHOD__);

		$matches = array();
		$importRegexOne = "/@import ['\\\"]([^\\n]*?\\.css)['\\\"]([^\\n]*?)(\\n|;|$)/is"; // since this stored is in a string, remember to escape quotes, slashes, etc.
		$importRegexTwo = "/@import url[\\( ]['\\\"]?([^\\n]*?\\.css)['\\\"]?[ \\)]([^\\n]*?)(\\n|;|$)/is";
		if ((0 < preg_match_all($importRegexOne, $this->mContent, $matches, PREG_SET_ORDER)) || (0 < preg_match_all($importRegexTwo, $this->mContent, $matches, PREG_SET_ORDER))) {
			foreach($matches as $match) {
				$lineMatched = $match[0];
				$fileName = trim($match[1]);
				$fileContents = file_get_contents($IP . $fileName);
				$this->mContent = str_replace($lineMatched, $fileContents, $this->mContent);
			}
		}
	
		wfProfileOut(__METHOD__);
	}

	private function stylePathProcessing() {
		global $wgCdnStylePath;
		wfProfileIn(__METHOD__);

		if(strpos($this->mContent, "wgCdnStylePath") !== false){ // faster to skip the regex in most cases
			// Because of fonts in CSS, we have to allow for lines with multiple url()s in them.
			// This will rewrite all but the last URL on the line (the last regex will fix the final URL and remove the special comment).
			$wasChanged = true;
			
			// As long as a URL was just replaced, check for a new match in the resulting code.
			while($wasChanged) {
				$changedCss = preg_replace("/([\(][\"']?)(\/[^\n;]*?)([, ]url[^\n;]*?)(\s*\/\*\s*[\\\$]?wgCdnStylePath\s*\*\/)/is", '\\1'.$wgCdnStylePath.'\\2\\3\\4', $this->$mContent);
				if($changedCss != $this->mContent) {
					$wasChanged = true;
					$this->mContent = $changedCss;
				} else {
					$wasChanged = false;
				}
			}

			$this->mContent = preg_replace("/([\(][\"']?)(\/[^\n;]*?)\s*\/\*\s*[\\\$]?wgCdnStylePath\s*\*\//is", '\\1'.$wgCdnStylePath.'\\2', $this->mContent);
		}

		wfProfileOut(__METHOD__);
	}

	/**
	 * Tries to base64 encode images marked with "base64" comment
	 */
	private function base64Processing() {
		wfProfileIn(__METHOD__);

		$this->mContent = preg_replace_callback("/([, ]url[^\n]*?)(\s*\/\*\s*base64\s*\*\/)/is", function($matches) {
			global $IP;
			$fileName = $IP . trim(substr($matches[1], 4, -1), '\'"() ');

			$encoded = AssetsManagerSassBuilder::base64EncodeFile($fileName);
			if ($encoded !== false) {
				return "url({$encoded});";
			}
			else {
				throw new Exception("/* Base64 encoding failed: {$fileName} not found! */");
			}
		}, $this->mContent);

		wfProfileOut(__METHOD__);
	}

	/**
	 * Base64 encodes given file
	 *
	 * @param string $fileName file absolute path
	 * @return mixed encoded file content or false (if file doesn't exist)
	 */
	public static function base64EncodeFile($fileName) {
		wfProfileIn(__METHOD__);

		if (!file_exists($fileName)) {
			return false;
		}

		$ext = end(explode('.', $fileName));

		switch($ext) {
			case 'gif':
			case 'png':
		        $type = $ext;
		        break;

			case 'jpg':
				$type = 'jpeg';
				break;

			// not supported image type provided
			default:
				return false;
		}

		$content = file_get_contents($fileName);
		$encoded = base64_encode($content);

		wfProfileOut(__METHOD__);
		return "data:image/{$type};base64,{$encoded}";
	}

	private function janusProcessing() {
		global $IP;
		wfProfileIn(__METHOD__);

		if (isset($this->mParams['rtl']) && $this->mParams['rtl'] == true) {
			$descriptorspec = array(
				0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
				1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
				2 => array("pipe", "a")
			);

			$env = array();
			$process = proc_open("{$IP}/extensions/wikia/SASS/cssjanus.py", $descriptorspec, $pipes, NULL, $env);

			if (is_resource($process)) {
				fwrite($pipes[0], $this->mContent);
				fclose($pipes[0]);

				$this->mContent = stream_get_contents($pipes[1]);
				fclose($pipes[1]);
				fclose($pipes[2]);
				proc_close($process);
			}
		}

		wfProfileOut(__METHOD__);
	}
}
