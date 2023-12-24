<?php
declare( strict_types=1 );

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

$env = getenv( 'MW_INSTALL_PATH' );
$IP = $env !== false ? $env : __DIR__ . '/../..';
require_once "$IP/maintenance/Maintenance.php";

/**
 * @author Niklas Laxström
 * @license MIT
 */
class SanatImport extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Imports pages from files in a directory' );
		$this->addOption( 'threads', 'Import in parallel' );
		$this->addArg( 'source', 'Source directory' );
	}

	public function execute(): void {
		$threads = $this->getOption( 'threads', 1 );
		if ( $threads < 1 || $threads != intval( $threads ) ) {
			$this->output( "Invalid thread count specified; running single-threaded.\n" );
			$threads = 1;
		}
		if ( $threads > 1 && !function_exists( 'pcntl_fork' ) ) {
			$this->output( "PHP pcntl extension is not present; running single-threaded.\n" );
			$threads = 1;
		}

		$filenames = iterator_to_array( $this->getFilenames() );
		$chunks = array_chunk( $filenames, (int)ceil( count( $filenames ) / $threads ) );

		$pids = [];
		foreach ( $chunks as $chunk ) {
			// Do not fork for only one thread
			$pid = ( $threads > 1 ) ? pcntl_fork() : -1;

			if ( $pid === 0 ) {
				// Child, reseed because there is no bug in PHP:
				// https://bugs.php.net/bug.php?id=42465
				mt_srand( getmypid() );

				foreach ( $chunk as $filename ) {
					$this->doWork( $filename );
				}

				exit( 0 );
			} elseif ( $pid === -1 ) {
				foreach ( $chunk as $filename ) {
					$this->doWork( $filename );
				}
			} else {
				// Main thread
				$pids[] = $pid;
			}
		}
		// Wait for all children
		foreach ( $pids as $pid ) {
			$status = 0;
			pcntl_waitpid( $pid, $status );
			if ( pcntl_wexitstatus( $status ) ) {
				// Pass a fatal error code through to the caller
				exit( pcntl_wexitstatus( $status ) );
			}
		}
	}

	private function getFilenames(): Traversable {
		$root = $this->getArg( 0 );
		$iter = new DirectoryIterator( $root );
		foreach ( $iter as $entry ) {
			if ( $entry->isFile() ) {
				yield $root . '/' . $entry->getFilename();
			}
		}
	}

	private function doWork( string $filename ): void {
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		$user = $userFactory->newFromId( 1 );
		$text = file_get_contents( $filename );
		$text = UtfNormal\Validator::cleanUp( $text );

		$titleText = strtr( basename( $filename ), '_', '/' );
		$titleText = UtfNormal\Validator::cleanUp( $titleText );
		$title = Title::newFromText( $titleText );
		if ( !$title ) {
			die( "Invalid title from '$filename'" );
		}

		$page = $wikiPageFactory->newFromTitle( $title );
		$content = ContentHandler::makeContent( $text, $title );

		$page->newPageUpdater( $user )
			->setContent( SlotRecord::MAIN, $content )
			->saveRevision( CommentStoreComment::newUnsavedComment( '' ) );

		$this->output( '.', 'progress' );
	}
}

$maintClass = SanatImport::class;
require_once RUN_MAINTENANCE_IF_MAIN;
