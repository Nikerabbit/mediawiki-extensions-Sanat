<?php
declare( strict_types=1 );

use MediaWiki\Category\Category;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

$env = getenv( 'MW_INSTALL_PATH' );
$IP = $env !== false ? $env : __DIR__ . '/../..';
require_once "$IP/maintenance/Maintenance.php";

/**
 * @author Niklas Laxström
 * @license MIT
 */
class SanatExport extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Exports infra pages to files' );
		$this->addOption( 'category', 'Export pages from this category', true, true );
		$this->addOption( 'target', 'Target directory', false, true );
	}

	public function execute(): void {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

		$target = $this->getOption( 'target', '.' );

		$category = Category::newFromName( $this->getOption( 'category' ) );
		$titles = $category->getMembers();

		foreach ( $titles as $title ) {
			$revisionRecord = $revisionStore->getRevisionByTitle( $title );
			$content = $revisionRecord->getContent( SlotRecord::MAIN );
			if ( $content instanceof TextContent ) {
				$text = $content->getText();
				$filename = $title->getPrefixedText();
				$filename = strtr( $filename, '/', '_' );
				file_put_contents( "$target/$filename", $text );
			}
		}
	}
}

$maintClass = SanatExport::class;
require_once RUN_MAINTENANCE_IF_MAIN;
