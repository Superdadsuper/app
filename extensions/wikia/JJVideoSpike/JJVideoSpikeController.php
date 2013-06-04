<?php
/**
 * @author: Jacek Jursza <jacek@wikia-inc.com>
 * Date: 23.04.13 15:36
 *
 */

class JJVideoSpikeController extends WikiaSpecialPageController {

	const FREEBASE_URL = 'https://www.googleapis.com/freebase/v1/search';
	const CACHE_DURATION = 86400; //1 day

	private $videoMetadataProvider;
	private $relevancyEstimator;
	private $relevancyService;

	private $fbClient;

	public function __construct() {

		// parent SpecialPage constructor call MUST be done
		parent::__construct( 'JJVideoSpike', '', false );
		$this->videoMetadataProvider = new VideoInformationProvider();
		$estimatorFactory = new CompositeRelevancyEstimatorFactory();
		$this->relevancyEstimator = $estimatorFactory->get();
		$this->relevancyService = new RelevancyEstimatorService();
		$this->fbClient = new FreebaseClient();
	}


	public function index() {
		die("AAAA");

	}

	private function getArticleId( $param = 'art' ) {

		$title = $this->request->getVal( $param, '' );
		$artId = false;

		if ( !empty( $title ) ) {

			$titleObj = Title::newFromText( $title );
			if ( !empty( $titleObj ) && $titleObj->exists() ) {
				$artId = $titleObj->getArticleID();
				if( $titleObj->isRedirect() ) {
					$art = new Article( $titleObj );
					$titleObj = Title::newFromRedirectRecurse( $art->getContent() );
					$artId = $titleObj->getArticleID();
				}
			}
		}

		return $artId;
	}

	public function test() {

		$articleId = $this->getArticleId();
		if ( !$articleId ) {
			die("ARTICLE NOT FOUND");
		}

		$art = new ArticleSubject( $articleId );


		$subjectsObject = new WikiSubjects();
		$art->setAllSubjectList( $subjectsObject->get() );

		//var_dump( $subjectsObject->get() );
		$subjects = $art->getSubjects();
		var_dump( $subjects );

		die("<hr>!");
	}


	public function elastic() {

		$elastic = new ElasticSearchQuery('testing', 'test');
		$data = $elastic->getData('1');

		$dataToIndex = json_encode( array(
			'name' => 'test'
		));

		$resp = $elastic->indexData('3', $dataToIndex);

		var_dump( $resp );

		die("<hr>");
	}

	public function moarTopics() {
		print_r( '<pre>' );
		$id = $this->getVal( 'id', null );
		$n = $this->getVal( 'name', null );
		$l = $this->getVal( 'lang', 'en' );

		if ( $id !== null ) {
			$result = $this->fbClient->getTopicById( $id );
		} elseif ( $n !== null ) {
			$result = $this->fbClient->getTopicByName( $n, $l );
		}
		print_r( $result );
		die;
	}

	public function moar() {

		print_r( '<pre>' );
		$q = $this->getVal( 'q' );
		$d = $this->getVal( 'd' );
		$score = $this->getVal( 'score', 0 );

		$types = array(
//			'actor',
			'/fictional_universe/fictional_character',
//			'/film/film',
//			'/film/film_series',
//			'/tv/tv_program',
//			'/tv/tv_series_season',
//			'/cvg/game_series',
//			'/cvg/computer_videogame',
//			'/book/literary_series',
//			'/book/book_edition',
//			'/book/book',
		);

		$typesMapping = array(
			'actor' => 'actor',
			'/m/02hrh1q' => 'actor', //actor id in freebase
			'/fictional_universe/fictional_character' => 'character',
			'/film/film' => 'movie',
			'/film/film_series' => 'movie',
			'/tv/tv_program' => 'series',
			'/tv/tv_series_season' => 'season',
//			'/cvg/game_series' => ,
			'/cvg/computer_videogame' => 'game',
//			'/book/literary_series',
			'/book/book_edition' => 'book',
			'/book/book' => 'book'
		);

		$json = $this->fbClient->queryWithTypeDomainFilter( $q, 'getPersonTypes', $d );
		print_r( $json );
		$resultTypes = array();
//		$maxScore = $json->result[0]->score;
		foreach ( $json->result as $res ) {
//			print_r( $res );
			if ( $res->score > $score && ( isset( $res->notable ) && isset( $typesMapping[ strtolower( $res->notable->id ) ] ) ) ) {
				if ( isset( $resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ] ) && $res->score < $resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ] ) {
					continue;
				}
				$resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ] = $res->score;
			}
		}
		$sortedResult = array_unique( $resultTypes );
		arsort( $sortedResult );
		$result = array();
		foreach( $sortedResult as $key => $score ) {
			$result[] = array( $key => $score );
		}
		print_r( $result );
		die();

	}

	public function getTypes( $json, $score ) {
		$typesMapping = array(
			'actor' => 'actor',
			'/m/02hrh1q' => 'actor', //actor id in freebase
			'/fictional_universe/fictional_character' => 'character',
			'/film/film' => 'movie',
			'/film/film_series' => 'movie',
			'/tv/tv_program' => 'series',
			'/tv/tv_series_season' => 'season',
//			'/cvg/game_series' => ,
			'/cvg/computer_videogame' => 'game',
//			'/book/literary_series',
			'/book/book_edition' => 'book',
			'/book/book' => 'book'
		);

		$resultTypes = array();
//		$maxScore = $json->result[0]->score;
		foreach ( $json->result as $res ) {
//			print_r( $res );
			if ( $res->score > $score && ( isset( $res->notable ) && isset( $typesMapping[ strtolower( $res->notable->id ) ] ) ) ) {
				if ( isset( $resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ] ) && $res->score < $resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ][ 's' ] ) {
					continue;
				}
				$resultTypes[ $typesMapping[ strtolower( $res->notable->id ) ] ] = array( 's' => $res->score, 'n' => $res->name );
			}
		}
		$sortedResult = array_unique( $resultTypes );
		arsort( $sortedResult );
		$result = array();
		foreach( $sortedResult as $key => $score ) {
			$result[] = array( $key => $score );
		}
		print_r( $result );
		die();
	}

	public function filterTypes( $toFilter ) {
		$typesMapping = array(
			'actor' => 'actor',
			'/m/02hrh1q' => 'actor', //actor id in freebase
			'/fictional_universe/fictional_character' => 'character',
			'/film/film' => 'movie',
			'/film/film_series' => 'movie',
			'/tv/tv_program' => 'series',
			'/tv/tv_series_season' => 'season',
			'/tv/tv_series_episode' => 'episode',
//			'/cvg/game_series' => ,
			'/cvg/computer_videogame' => 'game',
//			'/book/literary_series',
			'/book/book_edition' => 'book',
			'/book/book' => 'book'
		);

		$result = array();
		foreach( $toFilter as $key => $res ) {
			if ( isset( $res->notable ) ) {
				if ( isset( $typesMapping[ $res->notable->id ] ) ) {
					$result[ $key ] = $res;
				}
			}
		}
		return $result;
	}

	public function whyICantHandleAllThisCokes() {
		$cokeProvider = new VideoInformationProvider();

//		$cokeProvider->getExpanded( 'A Muppets Christmas Letters to Santa (2,008) - Featurette Miss Piggy' );
//		$cokeProvider->getExpanded( 'Ace_Attorney_5_-_Japanese_TGS_2012_Trailer' );
//		$cokeProvider->getExpanded( 'Age_of_Empires_Online_Video' );
//		$cokeProvider->getExpanded( 'Assassin\'s_Creed_3_The_Tyranny_of_King_Washington_The_Redemption_Walkthrough_(Part_1)' );
//		$cokeProvider->getExpanded( 'Aliens_Colonial_Marines_PC_Commentary' );
//		$md = $cokeProvider->getExpanded( 'Astro_Boy_The_Video_Game_Nintendo_Wii_Trailer_-_GC_2009_VO_Talent_Kristen_Bell_and_Freddie_Highmore' );
//		$cokeProvider->getExpanded( 'Afghan_Luke_(2011)_-_Home_Video_Trailer_for_Afghan_Luke' );
//		$cokeProvider->getExpanded( 'Africa_Unite_(2007)_-_Clip_UNICEF_participants,_Rita_Marley_and_Danny_Glover_discuss_the_mission_of_Africa_Unite' );
//		$cokeProvider->getExpanded( 'African_Cats_(2011)_-_Clip_Sita_Has_a_Secret' );
//		$md = $cokeProvider->getExpanded( 'Harry_Potter_and_the_Chamber_of_Secrets_(2002)_-_Home_Video_Trailer_(e17229)' );
//		$md = $cokeProvider->getExpanded( 'After.Life_(2009)_-_Clip_Please_let_me_go' );
//		$md = $cokeProvider->getExpanded( '007_Legends_(_(2012)_-_Opening_Credit_Cinematic_trailer' );
		$md = $cokeProvider->getExpanded( '10_Things_I_Hate_About_You_10th_Anniversary_Edition_(1999)_-_Clip_Tell_me_something_true' );
		print_r( '<pre>' );
		print_r( $md );
		die;
	}

	public function getMoarDataForThoseVideosHere() {
		$typesMapping = array(
			'actor' => 'actor',
			'/m/02hrh1q' => 'actor', //actor id in freebase
			'/fictional_universe/fictional_character' => 'character',
			'/film/film' => 'movie',
			'/film/film_series' => 'movie',
			'/tv/tv_program' => 'series',
			'/tv/tv_series_season' => 'season',
			'/tv/tv_series_episode' => 'episode',
//			'/cvg/game_series' => ,
			'/cvg/computer_videogame' => 'game',
//			'/book/literary_series',
			'/book/book_edition' => 'book',
			'/book/book' => 'book'
		);

		$videos = array(
//			'A Muppets Christmas Letters to Santa (2,008) - Featurette Miss Piggy',
//			'3D Dot Game Heroes (VG) (2,010) - Vignette 3 trailer',
//			'A_Clockwork_Orange_(1,971)_-_Theatrical_Trailer_(e11,729)',
//			'Around the World in 80 Days (2,004) - Clip The wager',
//			'Arena_(2,011)_-_Open-ended_Trailer_for_Arena',
//			'Annie_Hall_(1,977)_-_Open-ended_Trailer_(e10,940)',
//			'Anaconda_(1,997)_-_Trailer',
//			'America\'s Heart and Soul (2,004) - CT 3 Post',
//			'All Purpose Cat Girl Nuku Nuku (1,992) - Home Video Trailer',
//			'Alex And Emma (2,003) - Trailer',
//			'Affliction_(1,997)_-_Open-ended_Trailer_(e10,448)',
//			'A Walk Into The Sea: Danny Williams And The Warhol Factory (2,007) - Open-ended Trailer ',
//			'Ace Combat Joint Assault (VG) (2010) - Gameplay trailer',
//			'Ace Ventura Jr. (2008) - Home Video Trailer',
//			'Adam (2009) - Interview Hugh Dancy "On how Adam is revealed throughout the film"',
			'A_Night_in_Heaven_(1,983)_-_Open-ended_Trailer_(e25,362)',
			'A Nightmare On Elm Street (1,984) - HD',
			'A Witch\'s Tale (VG) (2,009) - Main trailer for A Witch\'s Tale',
			'Abba_You_Can_Dance_(VG)_(2,011)_-_Launch_trailer',
			'ABC TV On DVD 2,011 (2,010) - ABC TV on DVD Trailer 1',
			'Abduction_(2,011)_-_Clip:_Diner_Shoot_Out',
			'Abel\'s Field (2,012) - Home Video Trailer 2 for Abel\'s Field',
			'Adventure Time The Complete Second Season (2,012) - Clip Princess Rescue Party',
			'The muppet show (1980) - kermit the frog trailer'
		);

		$score = 100;
		$preDomainScore = 30;
		$domainScore = 100;

		foreach( $videos as $video ) {

			//change underscores for spaces, removes comas and dots
			$tmp = str_replace(
				array( '_' ),
				array( ' ' ),
				$video
			);
			print_r( '<pre>' );
			$domain = null;
			//get the data before ( character
			if ( ($parenthisStart = strpos( $tmp, '(' ) ) !== false ) {
				$title = substr( $tmp, 0, $parenthisStart );
				$typesFbresult = $this->fbClient->queryWithTypeFilter( $title, array_keys( $typesMapping ) );
				foreach( $typesFbresult->result as $res ) {
					if ( isset( $res->notable ) ) {
//						print_r( $res->name." ".$res->notable->id."\n" );
						if ( $res->score > $score ) {
							$keywords[ $title ][] = array( 'n' => $res->name, 't' => $typesMapping[ $res->notable->id ] , 's' => $res->score );
							$domain = $res->name;
						} else {
							//check for type and exact match if yes take as keyword, else drop
							if ( isset( $typesMapping[ $res->notable->id ] ) ) {
								if ( trim( $res->name ) === trim( $title ) ) {
									$keywords[ $title ][] = array( 'n' => $res->name, 't' => $typesMapping[ $res->notable->id ] , 's' => $res->score );
								}
							}
						}
					}
				}
			}

//			$domain = ( $freebaseResult->result && $freebaseResult->result[0]->notable ) ? $freebaseResult->result[0]->name : null;

			$words = explode( '-', $tmp );

			//remove date
			foreach ( $words as $key => $word ) {
				//drop the first sentence
				if ( $key == 0 ) continue;
				$wordSplitted = explode( ' ', trim( $word ) );

				$ok = false;
				//cut from back
				$count = count( $wordSplitted );

				//get result for every word with domain of object title
				if ( $domain !== null ) {
					foreach( $wordSplitted as $word ) {
						$fb = $this->fbClient->queryWithDomain( $word, $domain, 5 );
						print_r( $fb );
					}
				}
			}
		}
		print_r( $keywords );
		die;
	}

	protected function checkIfInTitle( $text, $title ) {
		$textCount = count( $text );
		$words = explode( ' ', $text );
		$tWords = explode( ' ', $title );
		$res = 0;
		foreach( $words as $w ) {
			if ( in_array( $w, $tWords ) ) {
				$res++;
			}
		}
		if ( $res == $textCount ) return true;
		return false;
	}

	public function topics() {
		$serviceFactory = new WikiPageCategoryServiceFactory();
		$service = $serviceFactory->get();

		$games = $service->getArticleTitlesByCategory("game");
		$this->setVal("games", $games);

		$movie = $service->getArticleTitlesByCategory("movie");
		$this->setVal("movie", $movie);

		$book = $service->getArticleTitlesByCategory("book");
		$this->setVal("book", $book);

		$this->getResponse()->setFormat("json");
	}

	public function rel() {
		$videoTitle = $this->getVal( "video" );
		if ( $videoTitle == null ) {
			$videoTitle = "IGN Live Presents WWE '13";
		}
		$videMetadata = $this->videoMetadataProvider->get( $videoTitle );
		$title = $this->getVal( "articleTitle" );
		if( $title ) {
			$titleObject = Title::newFromText( $title );
		} else {
			$id = $this->getVal( "articleId" );
			if( !$id ) {
				$id = 383882;
			} else {
				$id = intval( $id );
			}
			$titleObject = Title::newFromID( $id );
		}
		$article = false;
		if ( !empty( $titleObject ) && $titleObject->exists() ) {
			$article = new Article( $titleObject );
		}
		$estimate = $this->relevancyEstimator->compositeEstimate(
			new ArticleInformation( $article ),
			$videMetadata );
		//var_dump($estimate);
		$this->setVal("estimates", $estimate);
		$this->getResponse()->setFormat("json");
		//die();
	}

	public function rel2() {
		$video   = $this->getVal( "video" );
		$article = $this->getVal( "article" );
		$relevancyService = new RelevancyEstimatorService();
		$this->setVal("estimates", $relevancyService->getRelevancy( $video, $article ));
		$this->getResponse()->setFormat("json");
	}

	public function testSuggestions() {

		$articleId = $this->getArticleId();

		$mode = $this->getVal('mode', 'default');

		if ( !$articleId ) {
			die("ARTICLE NOT FOUND");
		}

		$suggestions = new ArticleVideoSuggestion( $articleId );
		$subjects = $suggestions->getSubject();

		if ( $mode == 'default') {

			$result = $suggestions->getDefaultSuggestions();

		} elseif ( $mode == 'subject' ) {

			if ( isset($subjects[0][0] ) ) {
				$result = $suggestions->getBySubject();
			}
		} elseif ( $mode == 'elastic' ) {

			$result = $suggestions->getFromElasticSearch();

		} elseif ( $mode == 'merge' ) {
			$resultSets[] = $suggestions->getDefaultSuggestions();
			if ( isset($subjects[0][0] ) ) {
				$resultSets[] = $suggestions->getBySubject();
			}
			if ( isset($subjects[1][0] ) ) {
				$resultSets[] = $suggestions->getBySubject(1);
			}
			if ( isset($subjects[2][0] ) ) {
				$resultSets[] = $suggestions->getBySubject(2);
			}

			$result = $this->relevancyService->mergeResults(
				Title::newFromID( $articleId )->getBaseText()
				, $resultSets );
		} elseif ( $mode == 'mergeElastic' ) {
			$result = $suggestions->getMergedElastic();
		}

		if ( isset( $subjects[0][0] ) ) {
			$this->setVal( 'subject', $suggestions->getLastQuery() );
		} else {
			$this->setVal( 'subject', 'unknown' );
		}

		$this->inflateWithVideoData( $result );
		$this->setVal( 'results' , $result );
	}

	private function inflateWithVideoData( &$result ) {

		$config = array(
			'contextWidth' => 460,
			'maxHeight' => 250
		);

		foreach ( $result['items'] as $i => $r ) {


			$title = Title::newFromText( $r['title'], NS_FILE );
			$file = wfFindFile( $title );

			$htmlParams = array(
				'custom-title-link' => $title,
				'linkAttribs' => array( 'class' => 'video-thumbnail' )
			);

			if ( !empty( $file ) ) {
				$thumb = $file->transform( array('width'=>460, 'height'=>250), 0 );
				$result['items'][$i]['thumb'] = $thumb->toHtml( $htmlParams );
			}
		}

	}

}
