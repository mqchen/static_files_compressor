<?php

class extension_Static_Files_Compressor extends Extension {
	
	public function about() {
		return array(
			'name' => 'Static files compressor',
			'version' => '0.1',
			'release-date' => '2011-03-27',
			'author' => array('name' => '<a href="http://designoslo.com">Moquan Chen</a>'),
			'description' => 'Allows you to specify in a URL which files should be combined and compressed.'
		);
	}
	
	public function enable() {
		// Inject rewrite rules into .htaccess
		$htaccess = self::readHtaccess();
		if(!$htaccess) { return false; }
		
		// Remove any old rules
		$htaccess = self::removeRewriteRules($htaccess);
		
		// Add new rules
		$htaccess = self::addRewriteRules($htaccess);
		
		// Write to file
		return self::writeHtaccess($htaccess);
	}
	
	public function disable() {
		// Clear cache
		$files = glob(CACHE . '/sfc-*');
		foreach($files as $file) {
			@unlink($file);
		}
		
		// Remove rules
		$htaccess = self::readHtaccess();
		$htaccess = self::removeRewriteRules($htaccess);
		return self::writeHtaccess($htaccess);
	}
	
	public function install() {
		// Move the sfc-urlbuilder.xsl to worksapce/utilities
		@copy(dirname(__FILE__) . '/utilities/sfc-urlbuilder.xsl', WORKSPACE . '/utilities/sfc-urlbuilder.xsl');
		
		return $this->enable();
	}
	
	public function uninstall() {
		// Delete sfc-urlbuilder.xsl
		@unlink(WORKSPACE . '/utilities/sfc-urlbuilder.xsl');
		
		return $this->disable();
	}
	
	protected static function readHtaccess() {
		$htaccess = @file_get_contents(DOCROOT . '/.htaccess');
		return $htaccess;
	}
	
	protected static function writeHtaccess($htaccess) {
		return file_put_contents(DOCROOT . '/.htaccess', $htaccess);
	}
	
	protected static $ruleBeginBlock = "\n\t### STATIC FILES COMPRESSOR BEGIN REWRITE\n\t";
	protected static $ruleEndBlock = "\n\t### STATIC FILES COMPRESSOR END\n\n\t";
	protected static $rule = 'RewriteRule ^.*\/SFC\.([a-z]+)$ extensions/static_files_compressor/lib/compress.php?mode=$1&%{QUERY_STRING} [L]';
	protected static $beforeRule = '### CHECK FOR TRAILING SLASH';
	
	protected function addRewriteRules($htaccess) {
		// Add before "### CHECK FOR TRAILING SLASH"
		$htaccess = str_replace(self::$beforeRule,
			self::$ruleBeginBlock . self::$rule . self::$ruleEndBlock . self::$beforeRule, $htaccess);
			
		return $htaccess;
	}
	
	protected function removeRewriteRules($htaccess) {
		$begin = addslashes(trim(self::$ruleBeginBlock));
		$end = addslashes(trim(self::$ruleEndBlock));
		return preg_replace('/' . $begin . "(.*)" . $end . '/', "", $htaccess);
	}
	
}