<?php

class YUICompressor {
	
	protected static $javaExec = 'java';
	protected static $yuiJar = 'YUICompressor/yuicompressor-2.4.2.jar';
	
	
	
	public static function compressJS($js) {
		$file = self::writeTmpFile($js);
		if(!$file) {
			return $js; // failed
		}
		
		$js = self::compress('js', $file);
		
		@unlink($file);
		
		return $js;
	}
	
	public static function compressCSS($css) {
		$file = self::writeTmpFile($css);
		if(!$file) {
			return $css;
		}
		
		$css = self::compress('css', $file);
		
		@unlink($file);
		
		return $css;
	}
	
	protected static function writeTmpFile($str) {
		$file = tempnam(sys_get_temp_dir(), 'sfc');
		chmod($file, 0644);
		$r = @file_put_contents($file, $str);
		if($r === false) {
			return false;
		}
		return $file;
	}
	
	protected static function compress($type, $file) {
		$output = array();
		$cmd = self::$javaExec . ' -jar ' . escapeshellarg(dirname(__FILE__) . '/' . self::$yuiJar) .
			' --type ' . $type .
			' ' . escapeshellarg($file);
		exec($cmd, $output);
		return implode("\n", $output);
	}
}