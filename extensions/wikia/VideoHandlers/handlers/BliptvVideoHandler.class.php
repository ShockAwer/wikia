<?php

class BliptvVideoHandler extends VideoHandler {
	
	protected $apiName = 'BliptvApiWrapper';
	protected static $aspectRatio = 1.7777778;
	
	public function getEmbed( $articleId, $width, $autoplay = false, $isAjax = false, $postOnload = false ) {
		$height =  $this->getHeight( $width );
		$embedVideoId = $this->getEmbedVideoId();
        $autoStartParam = $autoplay ? 'true' :  'false';
		$code = <<<EOT
<iframe src="http://blip.tv/play/$embedVideoId.html?p=1&autoStart={$autoStartParam}" width="{$width}" height="{$height}" frameborder="0" allowfullscreen></iframe><embed type="application/x-shockwave-flash" src="http://a.blip.tv/api.swf#{$embedVideoId}" style="display:none"></embed>                
EOT;
		return $code;
	}
}
