<?php

/*
   AtlassianAPI 1.0
   Created by John Tolle, PhySoft Operations Manager
   5/17/2017
   
   JIRA Application Link setup instructions:
   https://developer.atlassian.com/jiradev/jira-apis/jira-rest-apis/jira-rest-api-tutorials/jira-rest-api-example-oauth-authentication

   Confluence Application Link setup instructions:
   https://confluence.atlassian.com/display/APPLINKS061/OAuth+security+for+application+links
   
   JIRA REST API
   https://docs.atlassian.com/jira/REST/cloud/
   
   CONFLUENCE REST API
   https://docs.atlassian.com/atlassian-confluence/REST/latest/
   
   Note that the index.php page (or something like it) must be configured as the Callback URL when setting up Application Links.
   
   Requirements:
   PEAR + OAuth PECL
   (See http://php.net/manual/en/book.oauth.php)
   Installation:
	yum install php-pear php-devel
	pecl channel-update pecl.php.net
	pecl install oauth
	(or, for PHP < 7)
	pecl install oauth-1.2.3
   Add Apache config file /etc/php.d/oauth.ini with contents:
    extension=oauth.so
   Then restart Apache:
    service httpd restart
   
   How to generate RSA encrypted keys:
    Private:
    openssl genrsa -out mykey.pem 2048
    Public:
    openssl rsa -in mykey.pem -pubout
	
*/
require_once('utils.php');

class AtlassianAPI {

	const OAUTH_SIGNING_TYPE = 'RSA-SHA1'; // Required by Atlassian although the standard is HMAC-SHA1
	const DEFAULT_TEST_MODE = 0; // 0 = Off, 1 = On, 2 = Verbose

	public $AccessToken;
	public $ErrorLogPath;
	public $ErrorLogMailRecipient;
	public $ErrorLogMailSender;
	public $ErrorLogMailSubject;
	public $LastErrorCode = 0;
	public $LastErrorMessage;
	public $LastErrorCaller;
	public $RequestToken;
	public $SessionHandle;
	public $TokenSecret; // Not used by RSA-SHA1 signature encryption method - only by HMAC-SHA1
						 // Therefore, it's not used by Atlassian's OAuth (at least for Confluence and JIRA)

	private $AccessTokenAuthExpiresInSeconds;
	private $AccessTokenExpiresInSeconds;
	private $AuthUrl;
	private $ConfluenceBaseUrl;
	private $ConfluenceContentUrl;
	private $ConfluenceOAuthUrl;
	private $ConsumerKey;
	private $JiraOAuthUrl;
	private $JiraBaseUrl;
	private $GetAccessTokenUrl;
	private $GetRequestTokenUrl;
	private $OAuthTokensPath;
	private $PrivateKey;
	private $TestMode;
	private $TargetApp;
	
	private $oauth;

	public function __construct($serverRootUrl, $targetApp, $conKey, $priKeyPath, $oauthTokensPath, $errLogPath = null, $errLogMailSender = null, $errLogMailRecipient = null, $errLogMailSubject = null, $testMode = self::DEFAULT_TEST_MODE) {
		$this->ConfluenceBaseUrl = $serverRootUrl . '/wiki/rest/api';
		$this->ConfluenceContentUrl = $this->ConfluenceBaseUrl . '/content/';
		$this->ConfluenceOAuthUrl = $serverRootUrl . '/wiki/plugins/servlet/oauth/';
		$this->JiraOAuthUrl = $serverRootUrl . '/plugins/servlet/oauth/';
		$this->ConsumerKey = $conKey;
		$this->ErrorLogPath = $errLogPath;
		$this->ErrorLogMailRecipient = $errLogMailRecipient;
		$this->ErrorLogMailSender = $errLogMailSender;
		$this->ErrorLogMailSubject = $errLogMailSubject;
		$this->TestMode = $testMode;
		if ($this->TestMode == 1)
			$this->LogIt('__construct', 'Instantiating class.');
		if ($this->TestMode == 2)
			$this->LogIt('__construct', "Instantiating class ($targetApp, $conKey, $priKeyPath, $oauthTokensPath, $errLogPath, $errLogMailRecipient, $testMode)");
		switch ($targetApp) {
			case 'Confluence':
				$this->GetAccessTokenUrl = $this->ConfluenceOAuthUrl . 'access-token';
				$this->AuthUrl = $this->ConfluenceOAuthUrl . 'authorize';
				$this->GetRequestTokenUrl = $this->ConfluenceOAuthUrl . 'request-token';
				break;
			case 'JIRA':
				$this->GetAccessTokenUrl = $this->JiraOAuthUrl . 'access-token';
				$this->AuthUrl = $this->JiraOAuthUrl . 'authorize';
				$this->GetRequestTokenUrl = $this->JiraOAuthUrl . 'request-token';
				break;
			default:
				$this->LastErrorCode = 1;
				$this->LastErrorCaller = '__construct';
				$this->LastErrorMessage = "Failed to initialize.  \$targetApp ($targetApp) must be 'Confluence' or 'JIRA'.";
				return;
				break;
		}
		if (file_exists($priKeyPath)) {
			try {
				$this->PrivateKey = openssl_pkey_get_private('file://' . $priKeyPath); //var_dump(file_get_contents($priKeyPath));
				if ($this->PrivateKey === FALSE) {
					$this->LastErrorCode = 2;
					$this->LastErrorCaller = '__construct';
					$this->LastErrorMessage = "Private Key file does not contain a valid key: $priKeyPath";
					return;
				}
			} catch (Exception $e) {
				$this->LastErrorCode = 3;
				$this->LastErrorCaller = '__construct';
				$this->LastErrorMessage = "Private Key file not accessible: $priKeyPath " .
					"(Is it owned by apache and is selinux allowing access?)" .
					"Actual Error: " .	$e->getMessage();
				return;
			}
		} else {
			$this->LastErrorCode = 4;
			$this->LastErrorCaller = '__construct';
			$this->LastErrorMessage = "Private Key file not found: $priKeyPath (Did you create a private key using openssl?)";
			return;
		}
		$this->OAuthTokensPath = $oauthTokensPath;
		$this->LoadTokens();
		$this->oauth = new OAuth($conKey, null, OAUTH_SIG_METHOD_RSASHA1, OAUTH_AUTH_TYPE_FORM); // Note that JIRA will only accept auth as part of the body, not the header
		if ($this->TestMode > 0)
			$this->oauth->enableDebug();
		$this->oauth->SetRSACertificate(file_get_contents($priKeyPath));
		return;
	}

	public function AccessTokenAuthExpirationDate() {
		$theDate = new DateTime();
		$theDate->add(new DateInterval('PT' . $this->AccessTokenAuthExpiresInSeconds . 'S'));
		return $theDate;
	}
	
	public function AccessTokenExpirationDate() {
		$theDate = new DateTime();
		$theDate->add(new DateInterval('PT' . $this->AccessTokenExpiresInSeconds . 'S'));
		return $theDate;
	}

	public function AccessTokenAuthValidFor() {
		$i = $this->AccessTokenAuthExpirationDate()->diff(new DateTime());
		return Utils::IntervalFriendly($i);
	}
	
	public function AccessTokenValidFor() {
		$i = $this->AccessTokenExpirationDate()->diff(new DateTime());
		return Utils::IntervalFriendly($i);
	}
	
	public function AuthorizeRequest($autoRedirect = TRUE) {
		if ($this->TestMode > 0)
			$this->LogIt('AuthorizeRequest', "Executing AuthorizeRequest($autoRedirect).");
		$url = $this->AuthUrl . "?oauth_token={$this->RequestToken}";
		if ($autoRedirect) {
			if ($this->TestMode > 0)
				$this->LogIt('AuthorizeRequest', "Redirecting to $url.");
			header('Location: ' . $url, true);
			exit;
		} else {
			return ("Go to the following URL to authorize the OAuth Request Token:<br><a href=\"$url\">$url</a><br>" .
				"(Be sure to login as an appropriate service user account first!)");
		}
	}
	
	public function DeleteAccessToken() {
		if ($this->TestMode > 0)
			$this->LogIt('DeleteAccessToken', "Executing DeleteAccessToken().");
		$this->AccessToken = '';
		$this->SaveTokens();
	}

	public function GetPage($pageId, $expansions = null) {
		if ($this->TestMode > 0)
			$this->LogIt('GetPage', "Executing GetPage($pageId, $expansions).");
		$url = $this->ConfluenceContentUrl . $pageId;
		if ($expansions != null)
			$data = array('expand' => $expansions);
		else
			$data = array();
		return $this->SendRequest($url, $data);
	}
	
	public function GetAccessToken() {
		if ($this->TestMode > 0)
			$this->LogIt('GetAccessToken', "Executing GetAccessToken().");
		try {
			$this->oauth->setToken($this->RequestToken, $this->TokenSecret);
			$results = $this->oauth->getAccessToken($this->GetAccessTokenUrl);
		} catch(OAuthException $e) {
			$this->LastErrorCode = $e->getCode();
			$this->LastErrorMessage = $e->lastResponse;
			if ($this->TestMode == 2)
				print_r($e);
			return FALSE;
		}
		if ($this->TestMode == 2)
			$this->LogIt('GetAccessToken', print_r($results, TRUE));
		if (!empty($results)) {
			$this->AccessToken = $results['oauth_token'];
			$this->TokenSecret = $results['oauth_token_secret'];
			$this->AccessTokenAuthExpiresInSeconds = $results['oauth_authorization_expires_in'];
			$this->AccessTokenExpiresInSeconds = $results['oauth_expires_in'];
			$this->SessionHandle = $results['oauth_session_handle'];
			$this->RequestToken = '';
			return $this->SaveTokens();
		} else {
			return FALSE;
		}		
	}
	
	public function GetRequestToken() {
		if ($this->TestMode > 0)
			$this->LogIt('GetRequestToken', "Executing GetRequestToken().");
		try {
			$results = $this->oauth->getRequestToken($this->GetRequestTokenUrl);		
		} catch(OAuthException $e) {
			$this->LastErrorCode = $e->getCode();
			$this->LastErrorMessage = $e->lastResponse;
			if ($this->TestMode == 2)
				$this->LogIt('GetRequestToken', print_r($e, TRUE));
			return FALSE;
		}
		if ($this->TestMode == 2)
			$this->LogIt('GetRequestToken', print_r($results, TRUE));
		if ($results !== FALSE) {
			$this->RequestToken = $results['oauth_token'];
			$this->TokenSecret = $results['oauth_token_secret'];
			return $this->SaveTokens();
		} else {
			return FALSE;
		}
	}
	
	public function HandleError($preMsg = '') {
		$errMsg = $this->LastErrorMessage;
		$errJson = json_decode($errMsg);
		$smartError = $errMsg;
		if ($errJson != null && $errJson->statusCode == 403 && $errJson->message == 'Parent page view is restricted')
			$error = "Error: ({$this->LastErrorCode}) AtlassianAPI cannot access resource ($errMsg).  Are you certain that the Application Link is setup in $targetApp?";
		else
			$error = $errMsg;
		if ($preMsg != '')
			$preMsg .= ': ';
		$error = $preMsg . $error;
		$this->LogIt($this->LastErrorCaller, $error);
	}

	public function HasAccessToken() {
		return ($this->AccessToken != null && $this->AccessToken != '');
	}

	public function HasRequestToken() {
		return ($this->RequestToken != null && $this->RequestToken != '');
	}
	
	private function LoadTokens() {
		if ($this->TestMode > 0)
			$this->LogIt('LoadTokens', "Executing LoadTokens().");
		if (file_exists($this->OAuthTokensPath)) {
			$keysRaw = file_get_contents($this->OAuthTokensPath);
			$keys = json_decode($keysRaw);
			$this->AccessToken = $keys->AccessToken;
			$this->AccessTokenAuthExpiresInSeconds = $keys->AccessTokenAuthExpiresInSeconds;
			$this->AccessTokenExpiresInSeconds = $keys->AccessTokenExpiresInSeconds;
			$this->RequestToken = $keys->RequestToken;
			$this->SessionHandle = $keys->SessionHandle;
			$this->TokenSecret = $keys->TokenSecret;
		}
	}
	
	private function LogIt($caller, $msg) {
		$timestamp = (new DateTime())->format('Y-m-d h:i:s') . ' CT';
		$logLine = "$timestamp AtlassianAPI::$caller $msg\n";
		if ($this->ErrorLogPath != null) {
			$bytes = 0;
			if (touch($this->ErrorLogPath))
				$bytes = file_put_contents($this->ErrorLogPath, $logLine, FILE_APPEND);
			if ($bytes == 0) {
				error_log($timestamp . "AtlassianAPI::LogIt is unable to access the ErrorLogPath {$this->ErrorLogPath}.  Logs will be written to the standard log instead.\n");
				error_log($logLine);
			}
		} else { // Log to stdout
			echo ($logLine);
		}
		if ($this->ErrorLogMailRecipient != null && $this->TestMode == 0) {
			mail($this->ErrorLogMailRecipient, $this->ErrorLogMailSubject, $logLine, 'From: ' . $this->ErrorLogMailSender);
		}
	}

	private function SaveTokens() {
		if ($this->TestMode > 0)
			$this->LogIt('SaveTokens', "Executing SaveTokens().");
		$keysArray = array(
			'AccessToken' => $this->AccessToken,
			'AccessTokenAuthExpiresInSeconds' => $this->AccessTokenAuthExpiresInSeconds,
			'AccessTokenExpiresInSeconds' => $this->AccessTokenExpiresInSeconds,
			'RequestToken' => $this->RequestToken,
			'SessionHandle' => $this->SessionHandle,
			'TokenSecret' => $this->TokenSecret
			);
		$keys = json_encode($keysArray, JSON_PRETTY_PRINT);
		Utils::WarningHandlerEnable();
		try {
			file_put_contents($this->OAuthTokensPath, $keys);
			Utils::WarningHandlerDisable();
			return TRUE;
		} catch (ErrorException $e) {
			$this->LastErrorCode = 1;
			$this->LastErrorCaller = 'SaveTokens';
			$this->LastErrorMessage = 'AtlassianAPI->SaveTokens() Exception: ' . $e->getMessage();
			Utils::WarningHandlerDisable();
			return FALSE;
		}
	}
	
	public function SendRequest($url, $dataArray = array(), $oauthHttpMethod = OAUTH_HTTP_METHOD_GET, array $headerArray = array('Content-Type' => 'application/json;charset=utf8')) {
		if ($this->TestMode == 1)
			$this->LogIt('SendRequest', "Executing SendRequest(...).");
		if ($this->TestMode == 2)
			$this->LogIt('SendRequest', "Executing SendRequest($url, { " . print_r($dataArray, TRUE) . " }, $oauthHttpMethod, { " . print_r($headerArray, TRUE) . " } ).");
		if (empty($this->AccessToken))
			$this->LogIt('SendRequest', 'NOTICE: Missing Access Token (is the $oauthTokensPath correct?)');
		try {
			$this->oauth->setAuthType(OAUTH_AUTH_TYPE_URI); // Tested and works
			$this->oauth->setToken($this->AccessToken, $this->TokenSecret);
			$this->oauth->fetch($url, $dataArray, $oauthHttpMethod, $headerArray);
			$results = $this->oauth->getLastResponse();
		} catch(OAuthException $e) {
			$this->LastErrorCaller = 'SendRequest';
			$this->LastErrorCode = $e->getCode();
			$this->LastErrorMessage = $e->lastResponse . ' ' . $e->getMessage();
			if ($this->TestMode == 2)
				$this->LogIt('SendRequest', print_r($e, TRUE));
			return FALSE;
		}
		if ($this->TestMode == 2)
			$this->LogIt('SendRequest', print_r($results, TRUE));
		if ($this->TestMode == 1)
			$this->LogIt('SendRequest', 'Woot!');
		return json_decode($results);
	}
	
}


// Note that this class does NOT contain all possible elements for a Confluence page
// It is just a simple example containing the basics.  Build as needed.
class ConfluencePage {
	public $body;
	public $id;
	public $title;
	public $version;
	
	public function __construct($pageId, $nextVersionNumber) {
		$this->id = $pageId;
		$this->version = $nextVersionNumber;
	}
	
	public function content() {
		return array(
			'id' => $this->id,
			'title' => $this->title,
			'type' => 'page',
			'body' => array(
					'storage' => array(
							'value' => $this->body,
							'representation' => 'storage'
					)
			),
			'version' => array('number' => $this->version));
	}
	
	public function toJSON() {
		return json_encode($this->content(), JSON_PRETTY_PRINT);
	}
}