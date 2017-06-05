<?php

	// This page must be configured as the Callback URL when setting up Application Links

	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once('settings.php');
	require_once('atlassianapi.php');
	require_once('utils.php');
	
	$error = '';
	$notice = '';
	$testResults = '';
	
	$japiConfluence = 	new AtlassianAPI(ATLASSIAN_SERVER, 'Confluence', 	CONSUMER_KEY, PRIVATE_KEY_PATH, OAUTH_TOKENS_PATH_CONFLUENCE, ERROR_LOG_FILE, ERROR_LOG_SENDER, ERROR_LOG_RECIPIENT, ERROR_LOG_SUBJECT, TEST_MODE);
	$japiJIRA = 		new AtlassianAPI(ATLASSIAN_SERVER, 'JIRA', 		CONSUMER_KEY, PRIVATE_KEY_PATH, OAUTH_TOKENS_PATH_JIRA, ERROR_LOG_FILE, ERROR_LOG_SENDER, ERROR_LOG_RECIPIENT, ERROR_LOG_SUBJECT, TEST_MODE);
	
	if ($japiConfluence->LastErrorCode == 0 && $japiJIRA->LastErrorCode == 0) {
		$deleteAccessToken = array_key_exists('deleteAccessToken', $_POST);
		$getAccessToken = array_key_exists('oauth_token', $_GET);
		$getRequestToken = array_key_exists('getRequestToken', $_POST);
		$testAccessToken = array_key_exists('testAccessToken', $_POST);
		if (array_key_exists('targetApp', $_POST)) {
			$targetApp = $_POST['targetApp'];
			switch ($targetApp) {
				case 'Confluence':
					$japi = $japiConfluence;
					$testUrl = CONFLUENCE_BASE_URL . SAMPLE_PAGE_ID;
					break;
				case 'JIRA':
					$japi = $japiJIRA;
					$testUrl = JIRA_TEST_URL;
					break;
				default:
					$japi = null;
					break;
			}
		} else
			$targetApp = null;
		if ($getAccessToken) {
			$reqToken = $_GET['oauth_token'];	
			if ($reqToken == $japiConfluence->RequestToken) {
				$japi = $japiConfluence;
				$targetApp = 'Confluence';
			} elseif ($reqToken == $japiJIRA->RequestToken) {
				$japi = $japiJIRA;
				$targetApp = 'JIRA';
			} else
				$error = "The received Request Token doesn't match any we have!";
		}
			
		if (TEST_MODE)
			echo ('<pre style=text-align:left>');
			
		if ($deleteAccessToken) {
			$japi->DeleteAccessToken();
			$notice = $targetApp . ' Access Token deleted.';
		}
		if ($getRequestToken) {	
			if ($japi->GetRequestToken()) {
				$notice = "Got a Request Token for $targetApp, but if you're seeing this, we didn't automatically attempt to Authorize.<br>";
				$notice .= $japi->AuthorizeRequest(!TEST_MODE); // The Consumer Callback URL in JIRA must be set to this page!
			} else {
				$error = "Error ({$japi->LastErrorCode}) getting initial Request Token for $targetApp:<br>{$japi->LastErrorMessage}";
			}
		}
		if ($getAccessToken) {
			if ($japi->GetAccessToken())
				$notice = "Access Token obtained for $targetApp.";
			else
				$error = "Error ({$japi->LastErrorCode}) getting Access Token for $targetApp:<br>{$japi->LastErrorMessage}";
		}
		if ($testAccessToken) {
			//$args = array('status' => 'any');
			$results = $japi->SendRequest($testUrl);//, $args);
			if ($results !== FALSE) {
				$notice = "Test Passed for $targetApp!";
				$testResults = "<p>Test Results:</p><p><a href=\"$testUrl\">$testUrl</a></p>";
				$testResults .= "<pre style=text-align:left>" . print_r($results, TRUE) . "</pre>";
			} else {
				$errMsg = $japi->LastErrorMessage;
				$errJson = json_decode($errMsg);
				if ($errJson == null)
					$smartError = $errMsg;
				else {
					if ($errJson->statusCode == 403 && $errJson->message == 'Parent page view is restricted')
						$smartError = $errMsg . "<br><br>Are you certain that the Application Link is setup in $targetApp?";
				}
				$error = "Error ({$japi->LastErrorCode}) testing Access Token:<br>$smartError";
			}
		}
		if(TEST_MODE)
			echo ('</pre>');
	} else { // if ($japiConfluence->LastErrorCode != 0 || $japiJIRA->LastErrorCode != 0)
		$error = '';
		if ($japiConfluence->LastErrorCode != 0)
			$error = "Error instantiating \$japiConfluence ({$japiConfluence->LastErrorCode}):<br>" .
				"{$japiConfluence->LastErrorMessage}<br>\n";
		if ($japiJIRA->LastErrorCode != 0)
			$error .= "Error instantiating \$japiJIRA ({$japiJIRA->LastErrorCode}):<br>" .
				"{$japiJIRA->LastErrorMessage}\n";
	}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Atlassian OAuth Configuration</title>
		<style>
			html { box-sizing: border-box; } /* SO much better! */
			*, *:before, *:after { box-sizing: inherit; }
			a { text-decoration: none; }
			a:hover { text-decoration: underline; }
			body { font-family: sans-serif; text-align: center; }
			button { font-size: x-large; }
			div { padding: 10px 0; }
			fieldset { margin: 10px; }
			pre { white-space:pre-wrap; }
			.alert { color: red; }
			.centerbox {
				display: flex;
				flex-wrap: wrap; /* optional. only if you want the items to wrap */
				justify-content: center; /* for horizontal alignment */
				align-items: center; /* for vertical alignment */
			}
			.column {
				border: 1px solid silver;
				float: left;
				width: 50%;
			}
			#error {
				background-color: Red;
				color: White;
				text-align: left;
			}
			
			.good { color: green; }
			#notice {
				background-color: LightGreen;
				color: DarkGreen;
			}
		</style>
	</head>
	<body>
		<?php if ($notice != '') { ?>
		<div id="notice"><?php echo $notice; ?></div>
		<?php } ?>
		<?php if ($error != '') { ?>
		<div id="error"><?php echo $error; ?></div>
		<?php } ?>
		<h1>Atlassian OAuth Configuration</h1>
		<div>
			<div class="column">
				<h2>Confluence</h2>
				<?php
					if ($japiConfluence->HasAccessToken()) {
				?>
					<p><b class='good'>An Access Token is present.</b></p>
					<table style="margin: 0 auto;">
						<tr>
							<td style="text-align: right;">Expires:</td>
							<td style="text-align: left;"><?php echo $japiConfluence->AccessTokenExpirationDate()->format('Y-m-d') . ' (' . $japiConfluence->AccessTokenValidFor() . ')'; ?></td>
						</tr>
						<tr>
							<td>Auth Expires:</td>
							<td style="text-align: left;"><?php echo $japiConfluence->AccessTokenAuthExpirationDate()->format('Y-m-d') . ' (' . $japiConfluence->AccessTokenAuthValidFor() . ')'; ?></td>
						</tr>
					</table>
					<fieldset>
						<form action="./" method="post">
							<input type="hidden" name="targetApp" value="Confluence"/>
							<p><button type="submit" name="deleteAccessToken">Delete Token</button></p>
							<p>Deleting the Access Token will disable the job until a new Access Token is obtained.<br>
							   This may also be necessary if the current Token has become expired or invalidated.</p>
							<p>Note that deleting an Access Token will not revoke its authorization in JIRA.<br>
								<a href="<?php echo (ATLASSIAN_SERVER); ?>/wiki/users/revokeoauthtokens.action"
									target="_blank">View or Revoke Access Tokens</a>
							</p>
						</form>
					</fieldset>
					<fieldset>
						<form action="./" method="post">
							<input type="hidden" name="targetApp" value="Confluence"/>
							<p><button type="submit" name="testAccessToken">Test Access</button></p>
						</form>
					</fieldset>
				
				<?php
					} else {
				?>	
					<b><span class='alert'>Access Token not present.</span><br>
						The job will be unable to access app until an Access Token is available.</b>
					<p>Obtaining an Token will require an interactive user login:</p>
					<div class="centerbox">
						<ol style="text-align: left;">
							<li title="Opens in new window/tab">
								<a href="<?php echo (ATLASSIAN_SERVER); ?>/secure/Logout!default.jspa"
								   target="_blank">Logout of your normal JIRA user account</a> &boxbox;
							</li>
							<li>Login with a service JIRA user account<br>
							because the token shouldn't be linked with regular user accounts</li>
							<li>Come back to this page and click the button below:</li>
						</ol>
					</div>
					<form action="./" method="post">
						<input type="hidden" name="targetApp" value="Confluence"/>
						<p><button type="submit" name="getRequestToken">Get Token</button></p>
					</form>
				<?php	
					}
				?>
			</div>
			<div class="column">
				<h2>JIRA</h2>
				<?php
					if ($japiJIRA->HasAccessToken()) {
				?>
					<p><b class='good'>An Access Token is present.</b></p>
					<table style="margin: 0 auto;">
						<tr>
							<td style="text-align: right;">Expires:</td>
							<td style="text-align: left;"><?php echo $japiJIRA->AccessTokenExpirationDate()->format('Y-m-d') . ' (' . $japiJIRA->AccessTokenValidFor() . ')'; ?></td>
						</tr>
						<tr>
							<td>Auth Expires:</td>
							<td style="text-align: left;"><?php echo $japiJIRA->AccessTokenAuthExpirationDate()->format('Y-m-d') . ' (' . $japiJIRA->AccessTokenAuthValidFor() . ')'; ?></td>
						</tr>
					</table>
					<fieldset>
						<form action="./" method="post">
							<input type="hidden" name="targetApp" value="JIRA"/>
							<p><button type="submit" name="deleteAccessToken">Delete Token</button></p>
							<p>Deleting the Access Token will disable the job until a new Access Token is obtained.<br>
							   This may also be necessary if the current Token has become expired or invalidated.</p>
							<p>Note that deleting an Access Token will not revoke its authorization in JIRA.<br>
								<a href="<?php echo (ATLASSIAN_SERVER); ?>/plugins/servlet/oauth/users/access-tokens"
									target="_blank">View or Revoke Access Tokens</a>
							</p>
						</form>
					</fieldset>
					<fieldset>
						<form action="./" method="post">
							<input type="hidden" name="targetApp" value="JIRA"/>
							<p><button type="submit" name="testAccessToken">Test Access</button></p>
						</form>
					</fieldset>
				
				<?php
					} else {
				?>	
					<b><span class='alert'>Access Token not present.</span><br>
						The job will be unable to access app until an Access Token is available.</b>
					<p>Obtaining an Token will require an interactive user login:</p>
					<div class="centerbox">
						<ol style="text-align: left;">
							<li title="Opens in new window/tab">
								<a href="<?php echo (ATLASSIAN_SERVER); ?>/secure/Logout!default.jspa"
								   target="_blank">Logout of your normal JIRA user account</a> &boxbox;
							</li>
							<li>Login with a service JIRA user account<br>
							because the token shouldn't be linked with regular user accounts</li>
							<li>Come back to this page and click the button below:</li>
						</ol>
					</div>
					<form action="./" method="post">
						<input type="hidden" name="targetApp" value="JIRA"/>
						<p><button type="submit" name="getRequestToken">Get Token</button></p>
					</form>
				<?php	
					}
				?>
			</div>
			
		</div>
		<div>
			<p style="clear:both;"><?php echo $testResults; ?></p>
		</div>

	</body>
</html>
