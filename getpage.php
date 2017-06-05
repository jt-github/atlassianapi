#!/usr/bin/php
<?php

	// This is just a cheap script to snag the contents of a Confluence page.
	
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once('settings.php');
	require_once('atlassianapi.php');
	require_once('utils.php');
	//$targetUrl = CONFLUENCE_BASE_URL . SAMPLE_PAGE_ID;
	$japi = new AtlassianAPI(ATLASSIAN_SERVER, 'Confluence', CONSUMER_KEY, PRIVATE_KEY_PATH, OAUTH_TOKENS_PATH_CONFLUENCE);

	$results = $japi->GetPage(SAMPLE_PAGE_ID); //$japi->SendRequest($targetUrl . '?expand=body.storage');
	if ($results !== FALSE) {
		echo ('<pre>');
		print_r($results);
		echo ('</pre>');
	} else {
		$japi->HandleError();
	}

?>