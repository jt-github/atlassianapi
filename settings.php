<?php
	const TEST_MODE = 0; 													// 0 = Off, 1 = On, 2 = Verbose (note that enabling this can add sensitive data to log files!)

	const ATLASSIAN_SERVER = 'https://yourcompany.jira.com';				// Fully qualified URL to Atlassian server
	const CONSUMER_KEY = 'com.yourcompany.yourapp';							// Used during Confluence & JIRA  Application Link setup
	const OAUTH_TOKENS_PATH_CONFLUENCE = '/secret/tokensconfluence.json';	// Path to OAuth tokens used for Confluence access (API will create/update file)
	const OAUTH_TOKENS_PATH_JIRA = '/secret/tokensjira.json';				// Path to OAuth tokens used for JIRA access (API will create/update file)
	const PRIVATE_KEY_PATH = 'mykey.pem'; 									// Path to private RSA key, used by both Confluence and JIRA (see atlassianapi.php for instructions)
	const ERROR_LOG_FILE = '/var/log/atlassianapi/errors.log';				// Path to error log
	const ERROR_LOG_RECIPIENT = 'Your Team <team@yourcompany.com>';			// To address for error reports - leave unset to never e-mail errors
	const ERROR_LOG_SENDER = 'Your App <app@yourcompany.com>'; 				// From address for error reports, etc.
	const ERROR_LOG_SUBJECT = 'Your App Atlassian API Error';				// The default subject of error reports
	define('CONFLUENCE_WIKI', ATLASSIAN_SERVER . '/wiki/s/1234/1234/0a1b2');// Path to Wiki, for use with icons (check out a Confluence page's source to determine)
	const SAMPLE_PAGE_ID = 1234567890;										// The ID of a Confluence page to be accessed for test purposes

	define('CONFLUENCE_API', ATLASSIAN_SERVER . '/wiki/rest/api');			
	define('CONFLUENCE_BASE_URL', CONFLUENCE_API . '/content/');
	define('JIRA_API', ATLASSIAN_SERVER . '/rest/api/2');
	define('JIRA_TEST_URL', JIRA_API . '/myself');
	
	date_default_timezone_set('America/Chicago');
?>