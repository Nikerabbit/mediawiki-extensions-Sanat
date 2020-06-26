<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['null_casts_as_any_type'] = true;
$cfg['scalar_implicit_cast'] = true;

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'SanatExport.php',
		'SanatImport.php',
	]
);

return $cfg;
