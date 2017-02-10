<?php
$wgAutoloadClasses['ArticleVideoMVPHooks'] =  __DIR__ . '/ArticleVideoMVP.hooks.php';
$wgAutoloadClasses[ 'ArticleVideoMVPController' ] = __DIR__ . '/ArticleVideoMVPController.class.php';

$wgHooks['BeforePageDisplay'][] = 'ArticleVideoMVPHooks::onBeforePageDisplay';
$wgHooks['MakeGlobalVariablesScript'][] = 'ArticleVideoMVPHooks::onMakeGlobalVariablesScript';

$wgVideoMVPArticles = [
	'509' => [
		3581 => [
			'time' => '2:39',
		    'title' => 'Top 5 Best Spells in the Wizarding World',
		    'videoId' => 'hwM2FkOTE6R_fZR9uu5jvOy9FHm3NS1O'
		]
	],
	'3035' => [
		496531 => [
			'time' => '2:39',
			'title' => 'Top 5 Best Spells in the Wizarding World',
			'videoId' => 'hwM2FkOTE6R_fZR9uu5jvOy9FHm3NS1O'
		]
	]
];
