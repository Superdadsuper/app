<?php
/**
 * Rebuild interwiki table using the file on meta and the language list
 * Wikimedia specific!
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @todo document
 * @ingroup Maintenance
 * @ingroup Wikimedia
 */

require_once( dirname( __FILE__ ) . '/dumpInterwiki.php' );

class RebuildInterwiki extends DumpInterwiki {

	/**
	 * @var array
	 */
	protected $specials, $languageAliases, $prefixRewrites,
		$langlist, $dblist;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Rebuild the interwiki table using the file on meta and the language list.";
		$this->addOption( 'langlist', 'File with one language code per line', false, true );
		$this->addOption( 'dblist', 'File with one db per line', false, true );
		$this->addOption( 'd', 'Output folder', false, true );
		$this->addOption( 'protocolrelative', 'Output wikimedia interwiki urls as protocol relative', false, false );
	}

	function execute() {
		# List of language prefixes likely to be found in multi-language sites
		$this->langlist = array_map( "trim", file( $this->getOption( 'langlist', "/home/wikipedia/common/langlist" ) ) );

		# List of all database names
		$this->dblist = array_map( "trim", file( $this->getOption( 'dblist', "/home/wikipedia/common/all.dblist" ) ) );

		# Special-case databases
		//$this->specials = array_flip(	array_map( "trim", file( $this->getOption( 'specialdbs', "/home/wikipedia/common/special.dblist" ) ) ) );

		$this->makeInterwikiSQL( $this->getOption( 'd', '/home/wikipedia/conf/interwiki/sql' ) );

		if ( $this->hasOption( 'protocolrelative' ) ) {
			$this->urlprotocol = '';
		} else {
			$this->urlprotocol = 'http:';
		}
	}

	/**
	 * @param $destDir string
	 */
	function makeInterwikiSQL( $destDir ) {
		$this->output( "Making new interwiki SQL files in $destDir\n" );

		# Multi-language sites
		# db suffix => db suffix, iw prefix, hostname
		$sites = array(
			'wiki' => new WMFSite( 'wiki', 'w', 'wikipedia.org' ),
			'wiktionary' => new WMFSite( 'wiktionary', 'wikt', 'wiktionary.org' ),
			'wikiquote' => new WMFSite( 'wikiquote', 'q', 'wikiquote.org' ),
			'wikibooks' => new WMFSite( 'wikibooks', 'b', 'wikibooks.org' ),
			'wikinews' => new WMFSite( 'wikinews', 'n', 'wikinews.org' ),
			'wikisource' => new WMFSite( 'wikisource', 's', 'wikisource.org' ),
			'wikimedia' => new WMFSite( 'wikimedia', 'chapter', 'wikimedia.org' ),
			'wikiversity' => new WMFSite( 'wikiversity', 'v', 'wikiversity.org' ),
		);

		# Special-case hostnames
		$this->specials = array(
			'sourceswiki' => 'sources.wikipedia.org',
			'quotewiki' => 'wikiquote.org',
			'textbookwiki' => 'wikibooks.org',
			'sep11wiki' => 'sep11.wikipedia.org',
			'metawiki' => 'meta.wikimedia.org',
			'commonswiki' => 'commons.wikimedia.org',
			'specieswiki' => 'species.wikimedia.org',
		);

		# Extra interwiki links that can't be in the intermap for some reason
		$extraLinks = array(
			array( 'm', $this->urlprotocol . '//meta.wikimedia.org/wiki/$1', 1 ),
			array( 'meta', $this->urlprotocol . '//meta.wikimedia.org/wiki/$1', 1 ),
			array( 'sep11', $this->urlprotocol . '//sep11.wikipedia.org/wiki/$1', 1 ),
		);

		# Language aliases, usually configured as redirects to the real wiki in apache
		# Interlanguage links are made directly to the real wiki
		# Something horrible happens if you forget to list an alias here, I can't
		#   remember what
		$this->languageAliases = array(
			'zh-cn' => 'zh',
			'zh-tw' => 'zh',
			'dk' => 'da',
			'nb' => 'no',
		);

		# Special case prefix rewrites, for the benefit of Swedish which uses s:t
		# as an abbreviation for saint
		$this->prefixRewrites = array(
			'svwiki' => array( 's' => 'src' ),
		);

		# Construct a list of reserved prefixes
		$reserved = array();
		foreach ( $this->langlist as $lang ) {
			$reserved[$lang] = 1;
		}
		foreach ( $this->languageAliases as $alias => $lang ) {
			$reserved[$alias] = 1;
		}

		/**
		 * @var $site WMFSite
		 */
		foreach ( $sites as $site ) {
			$reserved[$site->lateral] = 1;
		}

		# Extract the intermap from meta
		$intermap = Http::get( 'http://meta.wikimedia.org/w/index.php?title=Interwiki_map&action=raw', 30 );
		$lines = array_map( 'trim', explode( "\n", trim( $intermap ) ) );

		if ( !$lines || count( $lines ) < 2 ) {
			$this->error( "m:Interwiki_map not found", true );
		}

		$iwArray = array();

		foreach ( $lines as $line ) {
			$matches = array();
			if ( preg_match( '/^\|\s*(.*?)\s*\|\|\s*(https?:\/\/.*?)\s*$/', $line, $matches ) ) {
				$prefix = strtolower( $matches[1] );
				$url = $matches[2];
				if ( preg_match( '/(wikipedia|wiktionary|wikisource|wikiquote|wikibooks|wikimedia|wikinews|wikiversity|wikimediafoundation|mediawiki)\.org/', $url ) ) {
					$local = 1;
				} else {
					$local = 0;
				}

				if ( empty( $reserved[$prefix] ) ) {
					$iwArray[$prefix] = array( "iw_prefix" => $prefix, "iw_url" => $url, "iw_local" => $local );
				}
			}
		}

		foreach ( $this->dblist as $db ) {
			$sql = "-- Generated by rebuildInterwiki.php";
			if ( isset( $this->specials[$db] ) ) {
				# Special wiki
				# Has interwiki links and interlanguage links to wikipedia

				$host = $this->specials[$db];
				$sql .= "\n--$host\n\n";
				$sql .= "USE $db;\n" .
						"TRUNCATE TABLE interwiki;\n" .
						"INSERT IGNORE INTO interwiki (iw_prefix, iw_url, iw_local) VALUES \n";
				$first = true;

				# Intermap links
				foreach ( $iwArray as $iwEntry ) {
					$sql .= $this->makeLink( $iwEntry, $first, $db );
				}

				# Links to multilanguage sites
				/**
				 * @var $targetSite WMFSite
				 */
				foreach ( $sites as $targetSite ) {
					$sql .= $this->makeLink( array( $targetSite->lateral, $targetSite->getURL( 'en', $this->urlprotocol ), 1 ), $first, $db );
				}

				# Interlanguage links to wikipedia
				$sql .= $this->makeLanguageLinks( $sites['wiki'], $first, $db );

				# Extra links
				foreach ( $extraLinks as $link ) {
					$sql .= $this->makeLink( $link, $first, $db );
				}

				$sql .= ";\n";
			} else {
				# Find out which site this DB belongs to
				$site = false;
				$matches = array();
				foreach ( $sites as $candidateSite ) {
					$suffix = $candidateSite->suffix;
					if ( preg_match( "/(.*)$suffix$/", $db, $matches ) ) {
						$site = $candidateSite;
						break;
					}
				}
				if ( !$site ) {
					print "Invalid database $db\n";
					continue;
				}
				$lang = $matches[1];
				$host = "$lang." . $site->url;
				$sql .= "\n--$host\n\n";

				$sql .= "USE $db;\n" .
						"TRUNCATE TABLE interwiki;\n" .
						"INSERT IGNORE INTO interwiki (iw_prefix,iw_url,iw_local) VALUES\n";
				$first = true;

				# Intermap links
				foreach ( $iwArray as $iwEntry ) {
					# Suppress links with the same name as the site
					if ( ( $site->suffix == 'wiki' && $iwEntry['iw_prefix'] != 'wikipedia' ) ||
						( $site->suffix != 'wiki' && $site->suffix != $iwEntry['iw_prefix'] ) )
					{
						$sql .= $this->makeLink( $iwEntry, $first, $db );
					}
				}

				# Lateral links
				foreach ( $sites as $targetSite ) {
					# Suppress link to self
					if ( $targetSite->suffix != $site->suffix ) {
						$sql .= $this->makeLink( array( $targetSite->lateral, $targetSite->getURL( $lang, $this->urlprotocol ), 1 ), $first, $db );
					}
				}

				# Interlanguage links
				$sql .= $this->makeLanguageLinks( $site, $first, $db );

				# w link within wikipedias
				# Other sites already have it as a lateral link
				if ( $site->suffix == "wiki" ) {
					$sql .= $this->makeLink( array( "w", "{$this->urlprotocol}//en.wikipedia.org/wiki/$1", 1 ), $first, $db );
				}

				# Extra links
				foreach ( $extraLinks as $link ) {
						$sql .= $this->makeLink( $link, $first, $db );
				}
				$sql .= ";\n";
			}
			file_put_contents( "$destDir/$db.sql", $sql );
		}
	}

	/**
	 * Returns part of an INSERT statement, corresponding to all interlanguage links to a particular site
	 *
	 * @param $site WMFSite
	 * @param $first
	 * @param $source
	 * @return string
	 */
	function makeLanguageLinks( &$site, &$first, $source ) {
		$sql = "";

		# Actual languages with their own databases
		foreach ( $this->langlist as $targetLang ) {
			$sql .= $this->makeLink( array( $targetLang, $site->getURL( $targetLang, $this->urlprotocol ), 1 ), $first, $source );
		}

		# Language aliases
		foreach ( $this->languageAliases as $alias => $lang ) {
			$sql .= $this->makeLink( array( $alias, $site->getURL( $lang, $this->urlprotocol ), 1 ), $first, $source );
		}
		return $sql;
	}

	/**
	 * Make SQL for a single link from an array
	 *
	 * @param $entry
	 * @param $first
	 * @param $source
	 * @return string
	 */
	function makeLink( $entry, &$first, $source ) {

		if ( isset( $this->prefixRewrites[$source] ) && isset($entry[0]) && isset( $this->prefixRewrites[$source][$entry[0]] ) ) {
			$entry[0] = $this->prefixRewrites[$source][$entry[0]];
		}

		$sql = "";
		# Add comma
		if ( $first ) {
			$first = false;
		} else {
			$sql .= ",\n";
		}
		$dbr = wfGetDB( DB_SLAVE );
		$sql .= "(" . $dbr->makeList( $entry ) . ")";
		return $sql;
	}
}

$maintClass = "RebuildInterwiki";
require_once( RUN_MAINTENANCE_IF_MAIN );

