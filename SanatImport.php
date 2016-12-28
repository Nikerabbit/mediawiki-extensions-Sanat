<?php
/**
 * @author Niklas Laxström
 * @license MIT
 * @file
 */

$IP = getenv( 'MW_INSTALL_PATH' ) ?: '.';
require_once "$IP/maintenance/Maintenance.php";

class SanatImport extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Imports infra pages from files';
		$this->addArg( 'source', 'Source directory' );
	}

	public function execute() {
		$source = $this->getArg( 0 );
		$user = User::newFromId( 1 );

		$iter = new DirectoryIterator( $source );
		foreach ( $iter as $entry ) {
			if ( !$entry->isFile() ) {
				continue;
			}

			$filename = $entry->getFilename();

			$text = file_get_contents( "$source/$filename" );
			$text = UtfNormal\Validator::cleanUp( $text );

			$titletext = strtr( $filename, '_', '/' );
			$titletext = UtfNormal\Validator::cleanUp( $titletext );
			$title = Title::newFromText( $titletext );
			if ( !$title ) {
				die( "Invalid title from '$filename'" );
			}

			$content = ContentHandler::makeContent( $text, $title );

			$page = new WikiPage( $title );
			$page->doEditContent( $content, '', false, $user );

			$this->output( ".", 'progress' );
		}
	}
}

$maintClass = 'SanatImport';
require_once RUN_MAINTENANCE_IF_MAIN;
