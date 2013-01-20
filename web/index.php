<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['oauth'] = $app->share(function() use($app){
	// Base url, during development use the sanbdo
	$baseUrl = 'https://sandbox.evernote.com/';
	//$baseUrl = 'https://evernote.com/'; 
	
	$oauth = new Lemon\OAuthWrapper($baseUrl);
	$oauth->setConsumerKey('')
	      ->setConsumerSecret('')
	      ->setCallbackUrl(
              $app['url_generator']->generate('callback', array(), true)
          );
	;
	return $oauth;
});

$app['evernote'] = $app->share(function() use($app){
	$oauth = $app['session']->get('oauth');

	$evernote = new Lemon\Evernote(
		$oauth['oauth_token'],
		$oauth['edam_noteStoreUrl']
	);

	return $evernote;
});

$app->get('/', function() use($app){
	$oauth = $app['session']->get('oauth');

	if (empty($oauth)) {
		$notebooks = null;
	} else {
		$notebooks = $app['evernote']->listNotebooks();
		
		foreach ($notebooks as $key => $notebook) {
			$notebooks[$key] = (array) $notebook;
			$notebooks[$key]['notes'] = $evernote->listNotes($notebook->guid);
		}
	}

	return $app['twig']->render('layout.twig', array(
		'oauth' => $oauth,
		'notebooks' => $notebooks
	));
})->bind('home');

$app->get('/connect', function() use($app){
	$token = $app['oauth']->requestTempCredentials();
	
	$app['session']->set('oauth', $token);

	return $app->redirect(
		$app['oauth']->makeAuthUrl()
	);
})->bind('connect');

$app->get('/callback', function() use($app){
	$verifier = $app['request']->get('oauth_verifier');

	if (empty($verifier)) {
		throw new \InvalidArgumentException("There was no oauth verifier in the request");
	}
	
	$tempToken = $app['session']->get('oauth');

	$token = $app['oauth']->requestAuthCredentials(
		$tempToken['oauth_token'],
		$tempToken['oauth_token_secret'],
		$verifier
	);

	$app['session']->set('oauth', $token);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('callback');

$app->get('/reset', function() use($app){
	$app['session']->set('oauth', null);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('reset');

$app->get('/note/{id}', function($id) use($app){
	$note = (array) $app['evernote']->getNoteAsHtml($id);

	return $app['twig']->render('layout.twig', array(
		'note' => $note
	));
})->bind('note');

$app->run();
