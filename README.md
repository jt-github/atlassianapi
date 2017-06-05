# atlassianapi
## A PHP example of how to access Confluence with the REST API using OAuth authentication.

Ready to move away from Basic authentication?  Because Atlassian needs some more details in their documentation, I had to battle my way through the OAuth dance.  Now you can do it with ease!  Note that this example only provides sample code to access Confluence pages.  JIRA issues (etc.) can also be accessed, but you'll need to build that code yourself That being said, there is code to show that OAuth for JIRA also works.

## Requirements
As noted in the `atlassianapi.php` file, the OAuth PECL module must be installed.  It's the closest thing to OAuth actually being a built-in PHP module.  PECL/oauth requires PHP 5.1 or newer.  Note that, with some minor modifications, you can use just about any OAuth provider you want.  I even wrote my own initially until I realized that reinventing the wheel was both frustrating and crazy!

### PECL/oauth Installation
If you have CLI access, here are the commands needed to quickly install the module:
```
yum install php-pear php-devel
pecl channel-update pecl.php.net
pecl install oauth
```
or, for those running PHP < 7, that last command should be:
```
pecl install oauth-1.2.3
```

Then add an Apache config file `/etc/php.d/oauth.ini` with contents:
```
extension=oauth.so
```

Alternately, just add the line above to the `Extensions` section in the `php.ini` file.

Finally, restart Apache:
```
service httpd restart
```

## How to use it
### Set Constants
After copying the files to your local web server, edit the `settings.php` file and modify the constants as needed.  The four at the bottom do not generally need to be modified for those running the Cloud versions of JIRA and Confluence.

### Create RSA Encryption Keys
There are a couple of easy commands to create RSA keys (again, assuming you have CLI access - if not, Google it!)

Private (mykey.pem is the one your app will use to encrypt messages):
```
openssl genrsa -out mykey.pem 2048
```

Public (the output from this command is what you'll need to supply during the Application Link setup - see the next section):
```
openssl rsa -in mykey.pem -pubout
```

### Setup Application Links
Confluence (and JIRA) require that an Application Link (specifically Incoming Authentication) be setup in order to use OAuth authentication.  Check out their documentation for instructions:  
[Confluence](https://confluence.atlassian.com/display/APPLINKS061/OAuth+security+for+application+links)  
[JIRA](https://developer.atlassian.com/jiradev/jira-apis/jira-rest-apis/jira-rest-api-tutorials/jira-rest-api-example-oauth-authentication)

Just be sure that:
- the Consumer Key matches the `CONSUMER_KEY` in the `settings.php` file
- The Public Key is set to the one generated from the Private Key referred to by `PRIVATE_KEY_PATH` in the `settings.php` file
- The Consumer Callback URL matches the URL for the `index.php` file on the web server where these files are stored (i.e. `http://yourserver.yourcompany.com/atlassianapi/index.php`)

Note that the webserver where these files live does **not** need to be publically accessible!  As long as *you* can browse to the `index.php` file, it will work as expected.

### Do the OAuth Dance
First, login to JIRA/Confluence as the user you wish to have associated with the Access Token (usually this is some kind of service account)
Then, browse to the `index.php` file in your favorite web browser to "do the OAuth Dance".  This requires human interaction.
1. Click the "Get Token" button
2. Authorize the Request Token by clicking the "Allow" button
3. Click the Test Access button to find out if it worked

There are some behind-the-scenes steps going on between steps 1 and 2, and you can show those by setting `TEST_MODE` to 1 or 2 in the `settings.php` file.

### Access a Confluence Page
Now that an Access Token has been saved to `OAUTH_TOKENS_PATH_CONFLUENCE`, that token can be used until it expires (as of 2017, that's 5 years from the date of issue) without needing any human interaction.

Try this out by executing the `getpage.php` file in a command line interface (CLI) like bash.
1. Ensure that the `SAMPLE_PAGE_ID` is set to a valid test Confluence page in `settings.php`
2. Enable execute with `chmod +x getpage.php`
3. Execute `./getpage.php`

The JSON should spill out, providing information about the page.

### Change a Confluence Page
That same Access Token can be used to modify Confluence pages, too.  **Make sure that the page specified above is not important before trying this next step!**
1. Enable execute with `chmod +x confluenceexample.php`
2. Execute `.\confluenceexample.php`

Exciting content has now been added to your Confluence page!
