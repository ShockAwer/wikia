<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die( 1 );

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'CorporatePage',
	'author' => 'Tomasz Odrobny',
	'description' => 'global page for wikia.com',
	'descriptionmsg' => 'myextension-desc',
	'version' => '1.0.0',
);

$dir = dirname(__FILE__) . '/';

// this should be set in CommonSettings.php / WikiFactory
if ( !isset( $wgCorporatePageRedirectWiki ) ) {
	$wgCorporatePageRedirectWiki = "http://community.wikia.com/wiki/";
}

$wgAutoloadClasses['CorporatePageHelper']  = $dir . 'CorporatePageHelper.class.php';
$wgAutoloadClasses['CorporateSiteModule'] = $dir . 'modules/CorporateSiteModule.class.php';

$wgExtensionMessagesFiles['CorporatePage'] = $dir . 'CorporatePage.i18n.php'; 
$wgHooks['MakeGlobalVariablesScript'][] = 'CorporatePageHelper::jsVars';
$wgHooks['ArticleFromTitle'][] = 'CorporatePageHelper::ArticleFromTitle';
$wgHooks['ArticleSaveComplete'][] = 'CorporatePageHelper::clearMessageCache';
$wgHooks['OutputPageCheckLastModified'][] = 'CorporatePageHelper::forcePageReload';
$wgAjaxExportList[] = 'CorporatePageHelper::blockArticle';
