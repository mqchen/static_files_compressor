<?php

class SFCompress {
	
	/// Config
	public static $debug = false;
	protected $httpReqestConfig = array(
		'follow_redirects' => true,
		'connect_timeout' => 3,
		'timeout' => 4,
		'adapter' => 'curl'
	);
	
	/// URL configurable props
	protected $mode = 'plain'; // Either css or js
	protected $compress = false; // minifies css and js
	protected $cache = 'normal'; // Other options: refresh (force refresh this), flush (delete all cache files)
	protected $cacheTimeout = 86400; // 24h. Only for remote cache (recommended to be very large unless they are often updated)
	protected $path = ''; // All local files must be relative to this directory. This is assumed to be within workspace.
	protected $outputCompress = true; // attempt gzip compress is supported by browser
	protected $files = array();
	
	/// Internal props
	protected $hash = null;
	protected $contentsHash = '';
	protected $localFiles = 0;
	protected $remoteFiles = 0;
	protected $responseStatus = 200;
	protected $headers = array();
	protected $cachePath = '/tmp'; // Reset later to CACHE.
	protected $cachePrefix = 'sfc-';
	protected $compressors = array(
		// Javascript minifiers
		'js' => array('JSMin', 'minify'),
		//'js' => array('JSMinPlus', 'minify'), // Note: JSMin is much faster, but compresses ca 2% less.
		
		// CSS minifiers
		//'css' => array('Minify_CSS_Compressor', 'process'),
		'css' => array('CssMin', 'minify'),
		//'css' => array(__CLASS__, 'pseudoCompress'),
	);
	protected $compression = array();
	protected $timers = array();
	
	protected $firephp = null;
	
	public static function init(array $params) {
		if(self::$debug) {
			error_reporting(E_ALL);
		}
		$that = new self();
		
		// timer
		$that->beginTimer('main');
		
		$that->initSymphony();
		$that->processParams($params);
		
		return $that;
	}
	
	public function process() {
		
		$this->debug('Cache file: ' . $this->getCacheFilePath());
		$this->debug('Cache mode: ' . $this->cache);
		
		// maybe flush cache (call $this->flushCache())
		$refreshCache = false;
		if($this->cache === 'flush') {
			$this->debug('Flush all cache.');
			$this->flushCache(true);
			$refreshCache = true;
		}
		else if($this->cache === 'refresh') {
			$this->debug('Refresh cache for this request.');
			$this->flushCache();
			$refreshCache = true;
		}
		else if($this->cache === 'normal' && !$this->isCacheHealthy()) {
			$this->debug('Cache for this request is unhealthy.');
			$this->flushCache();
			$refreshCache = true;
		}
		else {
			$this->debug('Cache still healthy.');
		}
		
		// maybe refresh cache (call $this->refreshCache()) - create new cache file with refreshed contents
		if($refreshCache) {
			$this->debug('Fetching and processing content.');
			$contents = $this->processContents($this->getContents(), $this->mode, $this->compress);
			
			// Calculate total compression
			$cmp = $this->calcTotalCompression();
			$this->debug('Compression: ' . $this->calcCompression($cmp[1], $cmp[2]));
			
			if(self::$debug) { // Some debugging stuff needs to be outputed to FirePHP
				$this->refreshCache($contents);
			}
		}
		else {
			// Cache is still healthy and fine
			$contents = $this->getCacheContents();
			$this->debug('Loaded ' . self::formatBytes(strlen($contents)) . ' bytes from cache.');
		}
		
		// ETag and If-Modified-Since
		if($this->isClientsCacheValid($contents)) {
			$this->debug('Client\'s cache copy is still valid. Not modified.');
			// Add a not modified header
			$this->setResponseStatus(304);
		}
		else {
			$this->debug('Client\'s cache copy is invalid.');
		}
		
		// gzip compress output
		$output = $this->compressOutput($contents, $cmp[1]);
		
		// Timer
		$this->debug('Total time: ' . number_format($this->elapsedTime('main'), 3) . ' sec.');
		
		// Send output
		$this->sendHeaders();
		
		echo $output;
		
		// Refresh cache
		if(!self::$debug) { // Refresh cache after content has been sent, unless debug
			$this->refreshCache($contents);
		}
		
		// Free memory
		unset($contents);
		unset($output);
	}
	
	protected function isClientsCacheValid($contents) {
		// If modified since
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			$date = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			if(!$date) {
				return false; // Date parsing failed
			}
			// If server«s cache has been modified after this date, client must refresh
			if($this->getCacheLastModified() > $date) {
				return false; // Server«s cache is newer
			}
			// If cache is scheduled to expire soon (due to remote files), expire it
			if($this->remoteFiles > 0 && $this->getCacheScheduledExpire() <= $date) {
				return false; // There were remote files requested, and servers cache was scheduled to expire before this request.
			}
			// Cache has not been modified since
			return true;
		}
		
		// Etag
		$this->contentsHash = md5($contents);
		$clientEtag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;
		$clientEtag = str_replace(array('"',"'"), '', $clientEtag);
		return $clientEtag === $this->contentsHash;
	}
	
	protected static function formatBytes($bytes, $pad = false) {
		if(!self::$debug) {
			return '' . $bytes;
		}
		$b = number_format($bytes);
		if($pad) {
			return str_pad($b, 7, chr(160), STR_PAD_LEFT);
		}
		return $b;
	}
	
	protected static function formatDate($time) {
		if(!self::$debug) {
			return '' . $time;
		}
		//return date('Y-m-d H:i:s', $time);
		return date('r', $time);
	}
	
	protected function calcTotalCompression() {
		$totalOrg = 0;
		$totalCmp = 0;
		foreach($this->compression as $c) {
			$totalOrg += $c[0];
			$totalCmp += $c[1];
		}
		return array(1 - ($totalOrg === 0 ? 1 : $totalCmp / $totalOrg), $totalOrg, $totalCmp);
	}
	
	protected function calcCompression($originalSize, $newSize) {
		$p = 1 - ($originalSize === 0 ? 1 : $newSize / $originalSize);
		return number_format($p * 100, 2) . '%. '.
				'From ' . self::formatBytes($originalSize) . ' bytes to ' . self::formatBytes($newSize) . ' bytes.';
	}
	
	protected function beginTimer($id) {
		$this->timers[$id] = microtime(true);
	}
	
	protected function elapsedTime($id) {
		return microtime(true) - $this->timers[$id];
	}
	
	public function debug($str) {
		if(self::$debug) {
			if($this->firephp === null) {
				require_once dirname(__FILE__) . '/FirePHP.php';
				$this->firephp = FirePHP::init();
			}
			$tmp = debug_backtrace();
			$method = $tmp[1]['function'];
			
			try {
				if(is_array($str) || is_object($str)) {
					$this->firephp->info(str_pad($method, 20, chr(160), STR_PAD_RIGHT). ': &darr;');
					$this->firephp->info($str);
				}
				else {
					// Always hide as much as possible of the path
					$str = str_replace(DOCROOT, 'DOCROOT', $str);
					$this->firephp->info(str_pad($method, 20, chr(160), STR_PAD_RIGHT). ': ' . $str);
				}
			}
			catch(Exception $e) {
				echo "<pre>";
				echo $e->getMessage() . "\n";
				debug_print_backtrace();
				echo "</pre>";
			}
		}
	}

	protected function comment($str, $mode = null) {
		if($mode === null) {
			$mode = $this->mode;
		}
		
		$lines = explode("\n", $str);
		
		if($mode === 'js') {
			return "\n/// " . implode("\n/// ", $lines) . "\n";
		}
		else if($mode === 'css') {
			return "\n/** " . $str . " */\n";
		}
		else {
			return "\n" . $str . "\n";
		}
	}
	
	protected function initSymphony() {
		define('DOCROOT', rtrim(realpath(dirname(__FILE__) . '/../../../'), '/'));
		define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '/') . str_replace('/extensions/static_files_compressor/lib', NULL, dirname($_SERVER['PHP_SELF'])), '/'));
		
		##Include some parts of the engine
		require_once(DOCROOT . '/symphony/lib/boot/bundle.php');
		/*require_once(TOOLKIT . '/class.lang.php');
		require_once(CORE . '/class.log.php');
			
		
		if (method_exists('Lang','load')) {
			Lang::load(LANG . '/lang.%s.php', ($settings['symphony']['lang'] ? $settings['symphony']['lang'] : 'en'));
		}
		else {
			Lang::init(LANG . '/lang.%s.php', ($settings['symphony']['lang'] ? $settings['symphony']['lang'] : 'en'));
		}*/
		
		$this->cachePath = CACHE;
	}
	
	protected function processParams(array $params) {
		// Parameters
		// Mode: css or js
		$this->mode = isset($params['mode']) && ($params['mode'] === 'css' || $params['mode'] === 'js') ? $params['mode'] : 'txt';
		
		// Compress
		$this->compress = isset($params['compress']);
		
		// Output compress
		$this->outputCompress = isset($params['outputcompress']) ? !!$params['outputcompress'] : $this->outputCompress;
		
		// Cache timeout for remote files
		if(isset($params['cachetimeout'])) {
			$timeout = intval($params['cachetimeout']);
			if($timeout >= 0) {
				$this->cacheTimeout = $timeout;
			}
		}
		
		// Cache mode
		if(isset($params['cache'])) {
			$cache = $params['cache'];
			if($cache === 'refresh' || $cache === 'flush' || $cache === 'normal') {
				$this->cache = $cache;
			}
		}
	
		// All file paths are assumed to be relative to workspace/, also supports http://cssfile.tld
		$this->path = isset($params['path']) ? $params['path'] : $this->path;
		$files = isset($params['files']) ? explode(',', $params['files']) : array();
		for($i = 0, $l = count($files); $i < $l; $i++) {
			$this->addFile(WORKSPACE . '/' . $this->path . '/'. trim($files[$i]));
		}
	}
	
	protected function addFile($file) {
		
		if(strpos($file, ':') === false) {
			//$file = WORKSPACE . '/' . $this->path . '/'. trim($file);
			$tmpFile = realpath($file);
			if($tmpFile !== false) {
				// exists, validate that it is within workspace
				if(strpos($tmpFile, WORKSPACE . '/') !== 0 // need to make sure there is no '../' in $path.
				|| strpos($tmpFile, WORKSPACE . '/' . $this->path . '/') !== 0) {
					// it is not inside specified path
					$this->debug('Local file must be within: workspace/' . $this->path . '. Ignoring: ' . $file);
					continue;
				}
				$file = $tmpFile;
				$this->appendFile('local', $file);
			}
			else { // File does not exist, try glob()
				$fileGlob = glob($file);
				$this->debug('Attempting glob: ' . $file);
				if(is_array($fileGlob)) {
					foreach($fileGlob as $file) {
						//$this->appendFile('local', $file);
						$this->addFile($file);
					}
				}
				else { // Glob failed, add file to list so that it will be refreshed
					$this->debug('Glob failed.');
					$this->appendFile('local', $file);
				}
			}
		}
		else {
			$file = $files[$i];
			$url = parse_url($file);
			if(!in_array(strtolower($url['scheme']), array('http', 'https', 'ftp', 'sftp', 'ftps'))) {
				$this->debug('Unsupported scheme: ' . $file);
				continue;
			}
			$this->appendFile('remote', $file);
		}
		
	}
	
	protected function appendFile($location, $file) {
		$this->files[] = array($location, $file);
		if($location === 'local') {
			$this->localFiles++;
		}
		else {
			$this->remoteFiles++;
		}
	}
	
	public function setResponseStatus($code) {
		$this->responseStatus = $code;
	}
	
	public function addHeader($header, $data, $overwrite = true) {
		//$header = ucwords($header);
		if(!$overwrite && array_key_exists($header, $this->headers)) {
			return;
		}
		$this->headers[$header] = $data;
	}
	
	protected function makeHeaders() {
		
		/// Content type
		$contentType = 'text/plain';
		if($this->mode === 'css') {
			$contentType = 'text/css';
		}
		else if($this->mode === 'js') {
			$contentType = 'application/javascript';
		}
		$this->addHeader('Content-type', $contentType, false);
		
		/// Last modified
		//var_dump(($this->getCacheFilePath()));
		$filemtime = $this->getCacheLastModified();
		
		$this->addHeader('Last-modified', date('r', $filemtime), false);
			
		/// Add expires
		$this->addHeader('Expires', date('r', $this->getCacheScheduledExpire()), false);
			
		/// Add etype
		$this->addHeader('ETag', '"' . $this->contentsHash . '"', false);
	}
	
	public function sendHeaders() {
		if(headers_sent()) { return; }
		
		/// Response status
		$desc = array(
			200 => 'OK',
			304 => 'Not Modified'
		);
		header('HTTP/1.1 ' . $this->responseStatus . ' ' . $desc[$this->responseStatus]);
		
		$this->makeHeaders();
		
		foreach($this->headers as $header => $data) {
			header($header . ': '. $data);
		}
	}
	
	public function makeHash() {
		if($this->hash === null) {
			$f = '';
			foreach($this->files as $v) {
				$f .= implode(',', $v);
			}
			$this->hash = md5($this->mode . $this->compress . $f);
		}
		return $this->hash;
	}
	
	protected function compressOutput($contents, $originalSize = 0) {
		$this->beginTimer('gzip');
		
		if(!$this->outputCompress) {
			$encoding = false;
		}
		else if(headers_sent()) {
			$encoding = false;
		}
		else if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) {
			$encoding = 'x-gzip';
		}
		else if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
			$encoding = 'gzip';
		}
		else {
			$encoding = false;
		}
		
		if($encoding !== false) {
			$this->debug('Gzip encoding: ' . $encoding);
			//$contents = ob_get_contents();
			ob_end_clean();
			$size = strlen($contents);
			$contents = gzcompress($contents, 9);
			$contents = substr($contents, 0, $size);
			$compressedSize = strlen($contents);
			$this->addHeader('Content-Encoding', $encoding, true);
			$this->debug('Gzip compression: ' . $this->calcCompression($size, $compressedSize));
			if($originalSize > 0) {
				$this->debug('Total compression: ' . $this->calcCompression($originalSize, $compressedSize));
			}
			$this->debug('Gzip time: ' . number_format($this->elapsedTime('gzip'), 3) . ' sec');
			//$this->sendHeaders();
			/*echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
			echo $contents;*/
			return "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $contents;
			//exit();
		}
		else {
			$this->debug('No output compression.');
			//$this->sendHeaders();
			return $contents;
			//exit();
		}
	}
	
	public function getCacheScheduledExpire() {
		return $this->getCacheLastModified() + $this->cacheTimeout;
	}
	
	public function getCacheLastModified() {
		$filemtime = @filemtime($this->getCacheFilePath());
		if(!$filemtime) {
			$filemtime = time(); // Could not get, assume cache was just created
		}
		return $filemtime;
	}
	
	public function getCacheContents() {
		return file_get_contents($this->getCacheFilePath());
	}
	
	public function getCacheFilePath() {
		return $this->cachePath . '/' . $this->getCacheFilename(true);
	}
	
	public function getCacheFilename($withPrefix = false) {
		$m = $this->mode === 'plain' ? 'txt' : $this->mode;
		return ($withPrefix ? $this->cachePrefix : '') . $this->makeHash() . '.' . $m;
	}
	
	protected function isCacheHealthy() {
		/*
		 * Conditions:
		 * 1. File exists, is file and is readable
		 * 2. Cache is younger than the timeout (only consider if link to remote files)
		 * 3. Cache is younger than the lastmod time for all local files
		 */
		
		$path = $this->getCacheFilePath();
		
		// Condition 1
		if(!(is_readable($path) && is_file($path))) {
			$this->debug('Cache file does not exist.');
			return false;
		}
		
		$filemtime = $this->getCacheLastModified();
		
		// Condition 2
		if($this->remoteFiles > 0) {
			$scheduledTimeout = $this->getCacheScheduledExpire();
			if(time() > $scheduledTimeout) {
				$this->debug('Cache file has expired - need to refresh remote files.');
				$this->debug('Cache file last updated: ' . self::formatDate($filemtime) .
					' scheduled timeout: ' . self::formatDate($scheduledTimeout));
				return false;
			}
			else {
				// Debug about timeout
				$this->debug('Scheduled timeout: ' . self::formatDate($scheduledTimeout));
			}
		}
		
		// Condition 3
		foreach($this->files as $file) {
			if($file[0] === 'remote') continue;
			$file = $file[1];
			if(!is_readable($file) || !is_file($file) || filemtime($file) > $filemtime) {
				$this->debug('Cache not updated. Local file has been modified: ' . $file);
				return false; // This local file was modified after the cache
			}
		}
		
		// It is still healthy
		return true;
	}

	protected function refreshCache($contents) {
		// Open and lock the cache file
		$handle = fopen($this->getCacheFilePath(), 'w');
		$lock = flock($handle, LOCK_EX);
		if(!$handle || !$lock) {
			if(!$lock) {
				$this->debug('Could not lock cache file: ' . $this->getCacheFilePath());
			}
			else {
				$this->debug('Could not open or create file: ' . $this->getCacheFilePath());
			}
			return false; // Could not open, or lock
		}
		
		//$contents = $this->processContents($contents, $this->mode, $this->compress);
		if(($written = fwrite($handle, $contents)) < strlen($contents)) {
			$this->debug('Failed to write to cache. Only ' . self::formatBytes($written) . ' bytes of ' .
				self::formatBytes(strlen($contents)) . ' bytes were written.');
		}
		
		// Release lock and close
		flock($handle, LOCK_UN);
		fclose($handle);
		
		$this->debug('Cache refreshed and '.self::formatBytes($written).' bytes written to: ' . $this->getCacheFilename(true));
		
		return true;
	}
	
	protected function readLocalFile($file) {
		if(preg_match('/(\.(php([0-9]{1})?)|phtml)$/i', $file)) {
			// Cannot open php files
			$this->debug('Reading PHP file not allowed: ' . $file);
			return false;
		}
		return @file_get_contents($file);
	}
	
	protected function getHTTPRequester() {
		// Load HTTP_Request2 from PEAR
		if(!class_exists('HTTP_Request2')) {
			require_once 'HTTP/Request2.php';
		}
		
		$req = new HTTP_Request2();
		$req->setConfig($this->httpReqestConfig);
		return $req;
	}
	
	protected function readRemoteFile($url) {
		$req = $this->getHTTPRequester();
		$req->setUrl($url);
		$req->setMethod(HTTP_Request2::METHOD_GET);
		
		try {
			$this->beginTimer('http request');
			$data = $req->send()->getBody();
			$this->debug('Loaded remote file in ' . number_format($this->elapsedTime('http request'), 3) .
				' sec using ' . $req->getConfig('adapter'));
			return $data;
		}
		catch(Exception $e) {
			$this->debug('Failed to load remote file ('.$e->getMessage().'): ' . $url);
			return false;
		}
	}
	
	protected function sanitizeContents($str) {
		/*if($this->mode === 'js') {
			return '+function(){'.$str.'}();';
		}*/
		if($this->mode === 'css') {
			// Remove @CHARSET
			$str = preg_replace('/^@CHARSET ".+";/', '', $str);
		}
		return $str;
	}
	
	protected function getContents() {
		// Open each file and get the contents
		$contents = '';
		foreach($this->files as $file) {
			$location = $file[0];
			$url = $file[1];
			if($location === 'remote') {
				$str = $this->readRemoteFile($url);
			}
			else {
				$str = $this->readLocalFile($url);
			}
			
			if($str === false) {
				// Failed
				$this->debug('Failed to process file: ' . $url);
			}
			else {
				
				// Sanitize string
				$str = $this->sanitizeContents($str);
				
				$contents .= $str;
				$this->debug('Loaded ' . self::formatBytes(strlen($str), true) . ' bytes from '.$location.' file: ' . $url);
				unset($str);
			}
		}
		/*
		foreach($this->localFiles as $file) {
			$str = $this->readLocalFile($file);
			if($str === false) {
				// Failed
				$this->debug('Failed to process file: ' . $file);
			}
			else {
				$contents .= $str;
				$this->debug('Loaded ' . self::formatBytes(strlen($str), true) . ' bytes from local file: ' . $file);
				unset($str);
			}
		}
		
		foreach($this->remoteFiles as $file) {
			$str = $this->readRemoteFile($file);
			if($str === false) {
				// Failed
				$this->debug('Failed to process file: ' . $file);
			}
			else {
				$contents .= $str;
				$this->debug('Loaded ' . self::formatBytes(strlen($str), true) . ' bytes from remote file: ' . $file);
				unset($str);
			}
		}*/
		
		return $contents;
	}
	
	protected function processContents($contents, $mode = 'plain', $compress = false) {
		
		$this->beginTimer('compression');
		
		$orgSize = $compressedSize = strlen($contents);
		
		if($compress) {
			$contents = $this->compress($contents, $mode);
			$compressedSize = strlen($contents);
		}
		
		// Set compression stats
		$this->compression[$file] = array($orgSize, $compressedSize);
		
		$this->debug('Compression time: ' . number_format($this->elapsedTime('compression'), 3) . ' sec.');
		
		return $contents;
	}
	
	protected function compress($str, $mode = 'plain') {
		$classAndMethod = $this->loadCompressor($mode);
		$this->debug('Compressor: ' . $classAndMethod[0] . '::' . $classAndMethod[1]);
		$str = call_user_func($classAndMethod, &$str);
		return $str;
	}
	
	public static function pseudoCompress($str) {
		return $str;
	}
	
	protected function loadCompressor($mode) {
		// Load compressor
		$defaultCompressor = array(__CLASS__, 'pseudoCompress');
		$compressor = $this->compressors[$mode];
		if(!is_array($compressor) || !count($compressor) == 2) {
			$this->debug($mode . ' compressor not specified.');
			return $defaultCompressor; // Compressor didn«t load
		}
		
		$class = $compressor[0];
		$method = $compressor[1];
		
		if(!class_exists($class)) {
			// Attempt to load it
			$file = dirname(__FILE__) . '/Compressors/' . $class . '.php';
			if(is_file($file) && is_readable($file)) {
				include_once $file;
			}
			
			// If class still doesnt exist, there«s something wrong
			if(!class_exists($class)) {
				$this->debug($mode . ' compressor ('.$class.') doesn«t exist: ' . $file);
				return $defaultCompressor;
			}
		}
		
		// Check if method exists
		if(!method_exists($class, $method)) {
			$this->debug('Static method (' . $method . ') in class (' . $class . ') doesn«t exist.');
			return $defaultCompressor;
		}
		
		return array($class, $method);
	}
	
	protected function flushCache($everything = false) {
		if($everything) {
			$files = glob($this->cachePath . '/' . $this->cachePrefix . '*');
			$someFails = false;
			foreach($files as $file) {
				if(!@unlink($file)) {
					$someFails = true;
					$this->debug('Failed to delete file: ' . $file);
				}
				else {
					$this->debug('Deleted file: ' . $file);
				}
			}
			return !$someFails;
		}
		else {
			// Delete only one cache, only empty it
			if(!file_exists($this->getCacheFilePath())) {
				$this->debug('Cache file did not exist: ' . $this->getCacheFilePath());
				return true;
			}
			else if(($handle = fopen($this->getCacheFilePath(), 'w')) !== false) {
				flock($handle, LOCK_EX);
				fwrite($handle, '');
				flock($handle, LOCK_UN);
				fclose($handle);
				$this->debug('Emptied file: ' . $this->getCacheFilename(true));
				return true;
			}
			else {
				// Could not open it, attempt to delete it
				$res = @unlink($this->getCacheFilePath());
				if($res) {
					$this->debug('Deleted file: ' . $this->getCacheFilePath());
				}
				else {
					$this->debug('Failed to delete file: ' . $this->getCacheFilePath());
				}
				return $res;
			}
		}
	}
}
/*
$s = SFCompress::init($_GET);
$s->process();
//readfile($s->getCacheFilePath());*/