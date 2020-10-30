<?php
/**
 * @author Niklas Laxström
 * @license MIT
 * @file
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

$IP = getenv( 'MW_INSTALL_PATH' ) ?: '.';
require_once "$IP/maintenance/Maintenance.php";

class SanatExport extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports infra pages to files';
		$this->addOption( 'category', 'Export pages from this category', true, true );
		$this->addOption( 'target', 'Target directory', false, true );
	}

	public function execute() {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

		$target = $this->getOption( 'target', '.' );

		$category = Category::newFromName( $this->getOption( 'category' ) );
		$titles = $category->getMembers();

		foreach ( $titles as $title ) {
			$revisionRecord = $revisionStore->getRevisionByTitle( $title );
			$content = $revisionRecord->getContent( SlotRecord::MAIN );
			$text = ContentHandler::getContentText( $content );
			$filename = $title->getPrefixedText();
			$filename = strtr( $filename, '/', '_' );
			file_put_contents( "$target/$filename", $text );
		}
	}
}

$maintClass = 'SanatExport';
require_once RUN_MAINTENANCE_IF_MAIN;
