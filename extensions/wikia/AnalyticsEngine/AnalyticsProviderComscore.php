<?php

class AnalyticsProviderComscore implements iAnalyticsProvider {

	private $account = 'p-8bG6eLqkH6Avk';

	function getSetupHtml(){
		return null;
	}

	function trackEvent($event, $eventDetails=array()){
		switch ($event){
		  case AnalyticsEngine::EVENT_PAGEVIEW : return '
<!-- Begin comScore Tag -->
<script>
document.write(unescape("%3Cscript src=\'" + (document.location.protocol == "https:" ? "https://sb" : "http://b") + ".scorecardresearch.com/beacon.js\' %3E%3C/script%3E"));
</script>
<script>
COMSCORE.beacon({
  c1:2,
  c2:6177433,
  c3:"",
  c4:"",
  c5:"",
  c6:"",
  c15:""
});
</script>
<noscript>
<img src="http://b.scorecardresearch.com/p?c1=2&c2=6177433&c3=&c4=&c5=&c6=&c15=&cj=1" />
</noscript>
<!-- End comScore Tag -->';
			break;
                  default: return '<!-- Unsupported event for ' . __CLASS__ . ' -->';
		}
	}


}
