<?php

class extension_staticfilescompressor extends Extension {
	
	public function about() {
		return array(
			'name' => 'Static files compressor (eg. CSS & Javascript)',
			'version' => '0.1',
			'release-date' => '2011-03-27',
			'author' => array('name' => '<a href="http://designoslo.com">Moquan Chen</a>'),
			'description' => 'Allows you to specify in a URL which files should be combined and compressed.'
		);
	}
	
	public function install() {
		// Inject rewrite rules into .htaccess
		
	}
	
}