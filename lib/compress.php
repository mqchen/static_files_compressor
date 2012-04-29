<?php

ignore_user_abort(true); // Dont want it to abort in the middle of writing, etc.

require_once dirname(__FILE__) . '/SFCompress.php';

if(isset($_GET['debug'])) {
	SFCompress::$debug = true;
}
SFCompress::init($_GET)->process();