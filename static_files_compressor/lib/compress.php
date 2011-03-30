<?php

// Initialize part of engine
	define('DOCROOT', rtrim(realpath(dirname(__FILE__) . ''), '/'));
	//define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '/') . str_replace('/extensions/jit_image_manipulation/lib', NULL, dirname($_SERVER['PHP_SELF'])), '/'));
	
	##Include some parts of the engine
	require_once(DOCROOT . '/symphony/lib/boot/bundle.php');
	require_once(TOOLKIT . '/class.lang.php');
	require_once(CORE . '/class.log.php');
		
	
	if (method_exists('Lang','load')) {
		Lang::load(LANG . '/lang.%s.php', ($settings['symphony']['lang'] ? $settings['symphony']['lang'] : 'en'));
	}
	else {
		Lang::init(LANG . '/lang.%s.php', ($settings['symphony']['lang'] ? $settings['symphony']['lang'] : 'en'));
	}

// Parameters
	$mode = isset($_GET['mode']) ? $_GET['mode'] : 'plain';

	// All file paths are assumed to be relative to workspace/, also supports http://cssfile.tld
	$files = isset($_GET['files']) ? explode(',', $_GET['files']) : array();
	for($i = 0, $l = count($files); $i < $l; $i++) {
		if(strpos($files[$i], ':') === false) {
			$files[$i] = DOCROOT . $files[$i];
		}
	}
	
	print_r($files);