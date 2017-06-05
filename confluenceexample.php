#!/usr/bin/php
<?php

	// This script is setup to run in a CLI (bash session / command line interface)
	// and is really the worker in this set of files; it can be run as a scheduled task.
	// Just set the execute bit to be able to run it directly:
	// chmod +x confluenceexample.php

	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once('settings.php');
	require_once('atlassianapi.php');
	require_once('utils.php');
	$targetUrl = CONFLUENCE_BASE_URL . SAMPLE_PAGE_ID;
	$japi = new AtlassianAPI(ATLASSIAN_SERVER, 'Confluence', CONSUMER_KEY, PRIVATE_KEY_PATH, OAUTH_TOKENS_PATH_CONFLUENCE, ERROR_LOG_FILE, ERROR_LOG_SENDER, ERROR_LOG_RECIPIENT, 'AtlassianAPI Log Message', TEST_MODE);

	// First Get the current page version number since we need to increment that to update it
	if ($japi->LastErrorCode == 0) {
		$results = $japi->SendRequest($targetUrl);
		if ($results !== FALSE) {
			$versionNumber = $results->version->number;
			$title = $results->title;
			$nextVersion = $versionNumber + 1;
			// Build the page
			$statusPage = buildPage($title, $nextVersion, $japi->AccessTokenExpirationDate());
			// Send as an e-mail
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-type: text/html; charset=iso-8859-1';
			$headers[] = 'From: ' . ERROR_LOG_SENDER;
			$fullBody = fixForEmail($statusPage->body);
			if (!empty(ERROR_LOG_RECIPIENT))
				mail(ERROR_LOG_RECIPIENT, ERROR_LOG_SUBJECT, $fullBody, implode("\r\n", $headers));
			$json = $statusPage->toJSON();
			// Update the Confluence page
			$results = $japi->SendRequest($targetUrl, $json, 'PUT');
			if ($results !== FALSE) {
				echo("Woot!\n");
			} else {
				$japi->HandleError();
			}
		} else {
			$japi->HandleError();
		}
	} else { //if ($japi->LastErrorCode != 0)
		$japi->HandleError("Error instantiating AtlassianAPI");
	}
	
	function fixForEmail($txt) {
		$rootIconUrl = CONFLUENCE_WIKI . '/_/images/icons/emoticons/';
		$txt = str_replace('<ac:emoticon ac:name="light-off" />', "<img src=\"{$rootIconUrl}lightbulb.png\">", $txt);
		$txt = str_replace('<ac:emoticon ac:name="light-on" />', "<img src=\"{$rootIconUrl}lightbulb_on.png\">", $txt);
		$txt = str_replace('<ac:emoticon ac:name="tick" />', "<img src=\"{$rootIconUrl}check.png\">", $txt);
		$txt = str_replace('<ac:emoticon ac:name="cross" />', "<img src=\"{$rootIconUrl}error.png\">", $txt);
		$txt = str_replace('<ac:emoticon ac:name="yellow-star" />', "<img src=\"{$rootIconUrl}star_yellow.png\">", $txt);
		$txt = str_replace('<ac:emoticon ac:name="information" />', "<img src=\"{$rootIconUrl}information.png\">", $txt);
		$txt = str_replace('<table>', '<table border="1">', $txt);
		$txt = str_replace('class="highlight-red" data-highlight-colour="red"', 'style="background-color:#ffe7e7"', $txt);
		$txt = str_replace('class="highlight-blue" data-highlight-colour="blue"', 'style="background-color:#e0f0ff"', $txt);
		$txt = '<!DOCTYPE html><html><head><style>' .
			'body {font-family:sans-serif;} table {border-collapse:collapse;} td,th {padding:5px;} th {background-color:silver;text-align:left;}' .
			'</style></head><body>' . $txt . '</body></html>';
		return $txt;
	}
	
	function buildPage($pageTitle, $nextVersionNumber, $accessExpDate) {
		$sp = new ConfluencePage(SAMPLE_PAGE_ID, $nextVersionNumber);
		$sp->title = $pageTitle;
		$body = "<h1>A Snazzy Page Topic</h1>\n" . "<p>Some content goes here.</p>";
		// Warn if Access Token is about to expire (in 30 days or less)
		$niceAccessDate = $accessExpDate->format('m/d/Y');
		$accessInterval = $accessExpDate->diff(new DateTime());
		$daysLeft = $accessInterval->days;
		if ($daysLeft < 30)
			$accessWarning = '<ac:structured-macro ac:name="warning" ac:schema-version="1" ac:macro-id="a4c8ac44-9e4e-464f-b39c-1bdc676bce88"><ac:rich-text-body><p style="color:red;">' .
				"Warning: Confluence OAuth Access Token is about to expire ($daysLeft days)." .
				'</p></ac:rich-text-body></ac:structured-macro>';
		else
			$accessWarning = '';
		$accessValidFor = Utils::IntervalFriendly($accessInterval);
		$body .= "<p>Access Expires: $niceAccessDate ($accessValidFor)</p>$accessWarning\n";
	
		$sp->body = $body;
	
		return $sp;
	}
?>