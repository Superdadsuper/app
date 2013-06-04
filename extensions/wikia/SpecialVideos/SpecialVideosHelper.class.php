<?php

/**
 * SpecialVideos Helper
 * @author Liz
 * @author Saipetch
 */
class SpecialVideosHelper extends WikiaModel {

	const VIDEOS_PER_PAGE = 24;
	const THUMBNAIL_WIDTH = 330;
	const THUMBNAIL_HEIGHT = 211;
	const POSTED_IN_ARTICLES = 5;

	/**
	 * get list of sorting options
	 * @return array $options
	 */
	public function getSortingOptions() {
		$options = array(
			'recent' => $this->wf->Msg( 'specialvideos-sort-latest' ),
			'popular' => $this->wf->Msg( 'specialvideos-sort-most-popular' ),
			'trend' => $this->wf->Msg( 'specialvideos-sort-trending' ),
		);

		return $options;
	}

	/**
	 * get list of filter options
	 * @return array $options
	 */
	public function getFilterOptions() {
		$options = array();

		$premiumVideos = $this->premiumVideosExist();
		if ( !empty($premiumVideos) ) {
			$options['premium'] = $this->wf->Msg( 'specialvideos-sort-featured' );
		}

		return $options;
	}

	/**
	 * get list of videos
	 * @param string $sort [recent/popular/trend]
	 * @param integer $page
	 * @return array $videos
	 */
	public function getVideos( $sort, $page ) {
		wfProfileIn( __METHOD__ );

		if ( $sort == 'premium' ) {
			$sort = 'recent';
			$filter = 'premium';
		} else {
			$filter = 'all';
		}

		$mediaService = new MediaQueryService();
		$videoList = $mediaService->getVideoList( $sort, $filter, self::VIDEOS_PER_PAGE, $page );

		$thumbParams = array(
			'width' => self::THUMBNAIL_WIDTH,
			'height' => self::THUMBNAIL_HEIGHT,
			'constHeight' => self::THUMBNAIL_HEIGHT,
		);

		$videos = array();
		$helper = new VideoHandlerHelper();
		foreach ( $videoList as $videoInfo ) {
			$videoDetail = $helper->getVideoDetail( $videoInfo, $thumbParams, self::POSTED_IN_ARTICLES );
			if ( !empty($videoDetail) ) {
				$videos[] = $videoDetail;
			}
		}

		wfProfileOut( __METHOD__ );

		return $videos;
	}

	/**
	 * get message for by user section
	 * @param string $userName
	 * @param string $userUrl
	 * @return string $byUserMsg
	 */
	public function getByUserMsg( $userName, $userUrl ) {
		$byUserMsg = '';
		if ( !empty($userName) ) {
			$attribs = array(
				'href' => $userUrl,
				'class' => 'wikia-gallery-item-user',
			);

			$userLink = Xml::element( 'a', $attribs, $userName, false );
			$byUserMsg = $this->wf->Msg( 'specialvideos-uploadby', $userLink );
		}

		return $byUserMsg;
	}

	/**
	 * get html tag for article
	 * @param array $article
	 * @return string $articleLink
	 */
	protected function getArticleLink( $article ) {
		$attribs = array(
			'href' => $article['url'],
		);

		$articleLink = Xml::element( 'a', $attribs, $article['titleText'], false );

		return $articleLink;
	}

	/**
	 * get message for by posted in section
	 * @param array $truncatedList
	 * @param integer $isTruncated [0/1]
	 * @return string $postedInMsg
	 */
	public function getPostedInMsg( $truncatedList, $isTruncated ) {
		$postedInMsg = '';
		$articleLinks = array();
		foreach( $truncatedList as $article ) {
			$articleLinks[] = $this->getArticleLink( $article );
		}

		if ( !empty($articleLinks) ) {
			$postedInMsg = $this->wf->Msg( 'specialvideos-posted-in', implode($articleLinks, ', ') );
		}

		return $postedInMsg;
	}

	/**
	 * check if premium video exists
	 * @return integer $videoExist [0/1]
	 */
	public function premiumVideosExist() {
		$mediaService = new MediaQueryService();
		$videoExist = (bool) $mediaService->getTotalPremiumVideos();

		return $videoExist;
	}

}