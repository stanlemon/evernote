<?php

require_once('../vendor/autoload.php');

set_exception_handler(function($e){
	$className = get_class($e);
	print <<<EOHTML
<h1>{$className} Exception</h1>
<h2>{$e->getMessage()}</h2>
<pre>{$e->getTraceAsString()}</pre>
EOHTML;
});

session_start();

define('OAUTH_CONSUMER_KEY', '');
define('OAUTH_CONSUMER_SECRET', '');

if (OAUTH_CONSUMER_KEY == '' || OAUTH_CONSUMER_SECRET == '') {
	throw new \InvalidArgumentException('You need an Evernote API key to begin, grab one at <a href="http://dev.evernote.com/support/api_key.php">http://dev.evernote.com/support/api_key.php</a>.');
}

$evernoteAuth = new Lemon\EvernoteOAuth(true);
$evernoteAuth->setConsumerKey(OAUTH_CONSUMER_KEY)
			 ->setConsumerSecret(OAUTH_CONSUMER_SECRET)
			 ->setCallbackUrl($evernoteAuth->buildUrl('?action=callback'))
;

// Step One: Request temporary credentials
if (isset($_GET['action']) && $_GET['action'] == 'connect') {
	$requestTokenInfo = $evernoteAuth->requestTempCredentials();

	$_SESSION['requestToken'] = $requestTokenInfo['oauth_token'];
	$_SESSION['requestTokenSecret'] = $requestTokenInfo['oauth_token_secret'];

	$evernoteAuth->redirectAuth();
// Step Two: Request permanent credentials, these are the ones you want to save with you app
} elseif (isset($_GET['action']) && $_GET['action'] == 'callback') {
	if (!isset($_GET['oauth_verifier'])) {
		throw new Exception("Did not auth!");
	}

	$accessTokenInfo = $evernoteAuth->requestTokenCredentials($_SESSION['requestToken'], $_SESSION['requestTokenSecret']);

	$_SESSION['accessTokenInfo'] = $accessTokenInfo; // <-- Save these!

	$evernoteAuth->redirect();
// Clear out all credentials to this point
} elseif (isset($_GET['action']) && $_GET['action'] == 'reset') {
	session_destroy();
	unset($_SESSION);
	$evernoteAuth->redirect();
}

// IF we have credentials load our library wrapper
if (isset($_SESSION['accessTokenInfo']) && !empty($_SESSION['accessTokenInfo'])) {
	$evernote = new Lemon\Evernote(
		$_SESSION['accessTokenInfo']['oauth_token'],
		$_SESSION['accessTokenInfo']['edam_noteStoreUrl']
	);
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>Evernote PHP OAuth Example</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width">
		<style>body { padding-top: 60px; }</style>
		<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.1/css/bootstrap-combined.min.css" rel="stylesheet">
    </head>
    <body>
		<div class="navbar navbar-inverse navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</a>

					<a class="brand" href="#">Evernote</a>
					<div class="nav-collapse collapse">
						<ul class="nav">
							<li class="active"><a href="#">Home</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="container">

		<h1>Evernote PHP OAuth Example</h1>

		<p><a href="index.php?action=reset">Click here to reset the session.</a></p>

		<p><a href="index.php?action=connect">Click here to authenticate with Evernote</a></p>

		<?php if (isset($evernote)): ?>
			<h3>Notebooks:</h2>
			<ul>
			<?php foreach ($evernote->listNotebooks() as $notebook): ?>
			  <li>
				<?=$notebook->name?>
				<ol>
				<?php foreach ($evernote->listNotes($notebook->guid) as $note): ?>
					<li>
						<?=$note->title?>
						<?=$note->created?>
					</li>
				<?php endforeach; ?>
				</ol>
			</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<pre><?php var_dump($_SESSION); ?></pre>
	</body>
</html>
