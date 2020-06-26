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
		$this->addOption( 'threads', 'Import in parallel' );
		$this->addArg( 'source', 'Source directory' );
	}

	public function execute() {
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
		$chunks = array_chunk( $filenames, ceil( count( $filenames ) / $threads ) );

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
		$user = User::newFromId( 1 );
		$text = file_get_contents( $filename );
		$text = UtfNormal\Validator::cleanUp( $text );

		$titletext = strtr( basename( $filename ), '_', '/' );
		$titletext = UtfNormal\Validator::cleanUp( $titletext );
		$title = Title::newFromText( $titletext );
		if ( !$title ) {
			die( "Invalid title from '$filename'" );
		}

		$content = ContentHandler::makeContent( $text, $title );

		$page = new WikiPage( $title );
		$page->doEditContent( $content, '', false, false, $user );

		$this->output( ".", 'progress' );
	}
}

$maintClass = 'SanatImport';
require_once RUN_MAINTENANCE_IF_MAIN;
