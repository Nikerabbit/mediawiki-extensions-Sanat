<?php

declare( strict_types=1 );

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths( [
		__DIR__
	] )
	->withSkipPath( 'vendor' )
	->withPhpSets()
	->withComposerBased()
	->withSkip( [
		// Keep consecutive class member declarations together.
		NewlineBetweenClassLikeStmtsRector::class,
	] )
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		codingStyle: true,
		typeDeclarations: true,
		privatization: true,
	);
