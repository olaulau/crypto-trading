<?php
if (! file_exists ("./vendor/")) {
	die("no '/vendor/' directory : please run 'composer install' before using this web app");
}
require 'vendor/autoload.php';

$f3 = \Base::instance();

$f3->config('conf/index.ini');

$f3->run();
