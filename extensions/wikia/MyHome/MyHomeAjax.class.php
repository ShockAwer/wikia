<?php

class MyHomeAjax {

	/*
	 * Get raw HTML of given feed
	 */
	public static function getFeed() {
		// get request params
		global $wgRequest;
		$type  = $wgRequest->getVal('type', false);
		$since = $wgRequest->getVal('since', wfTimestamp(TS_MW, time()));
		$limit = $wgRequest->getInt('limit', 30);


		$feedProxy = new ActivityFeedAPIProxy();
		$feedRenderer = new ActivityFeedRenderer();

		$feedProvider = new DataFeedProvider($feedProxy);
		$feedData = $feedProvider->get($limit, $since);
		$feedHTML = $feedRenderer->render($feedData, false);


		// get feed
		return array(
			'fetchSince' => isset($feedData['query-continue']) ? $feedData['query-continue'] : false,
			'html' => $feedHTML,
		);
	}

	/*
	 * Get HTML of video player for given video file
	 *
	 * Used for on-click video play
	 */
	public static function getVideoPlayer() {
		global $wgTitle;

		$video = new VideoPage($wgTitle);
		$video->load();

		// get default video dimensions
		$dimensions = explode('x', $video->getTextRatio());
		$width = intval($dimensions[0]);
		$height = intval($dimensions[1]);

		return array(
			'width' => $width,
			'height' => $height,
			'html' => $video->getEmbedCode($width, true),
		);
	}

	/*
	 * Get HTML for full-size image
	 *
	 * Used for on-click image preview
	 */
	public static function getImagePreview() {
		global $wgTitle;

		// limit dimensions of returned image
		global $wgRequest;
		$maxWidth = $wgRequest->getInt('maxwidth', 500) - 20;
		$maxHeight = $wgRequest->getInt('maxheight', 300) - 75;

		// get the correct revision of file
		$timestamp = $wgRequest->getInt('timestamp', 0);

		if ($timestamp == 0) {
			$image = wfFindFile($wgTitle);
		}
		else {
			$image = FeedProvider::getFile($wgTitle, $timestamp);
		}

		// get original dimensions of an image
		$width = $image->getWidth();
		$height = $image->getHeight();

		// don't try to make image larger
		if ($width > $maxWidth or $height > $maxHeight) {
			$width = $maxWidth;
			$height = $maxHeight;
		}

		// generate thumbnail
		$thumb = $image->getThumbnail($width, $height);

		return array(
			'width' => $thumb->getWidth(),
			'height' => $thumb->getHeight(),
			'html' => $thumb->toHtml(),
		);
	}

	/*
	 * Save default view in user preferences
	 *
	 * @author Maciej Brencz <macbre@wikia-inc.com>
         */
	public static function setDefaultView() {
		global $wgRequest;
		$defaultView = $wgRequest->getVal('defaultView');

		// this method will perfrom extra check
		if (MyHome::setDefaultView($defaultView)) {
			return array('msg' => wfMsg('myhome-default-view-success'));
		}
		else {
			return array();
		}
	}
}
