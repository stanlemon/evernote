<?php
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;

class EvernoteOAuth {

	protected $baseUrl;
	protected $sandbox;
	protected $consumerKey;
	protected $consumerSecret;
	protected $callbackUrl;
	protected $requestTokenUrl = 'oauth';
	protected $accessTokenUrl = 'oauth';
	protected $authorizationUrl = 'OAuth.action?oauth_token=%s';

	protected $tempCreds;
	protected $tokenCreds;

	public function __construct($sandbox = false) {
		if ($sandbox) {
			$this->baseUrl = 'https://sandbox.evernote.com/';
		} else {
			$this->baseUrl = 'https://evernote.com/';
		}
		$this->sandbox = $sandbox;
	}

	public function setConsumerKey($consumerKey) {
		$this->consumerKey = $consumerKey;
		return $this;
	}

	public function setConsumerSecret($consumerSecret) {
		$this->consumerSecret = $consumerSecret;
		return $this;
	}

	public function setCallbackUrl($callbackUrl) {
		$this->callbackUrl = $callbackUrl;
		return $this;
	}

	public function setRequestTokenUrl($requestTokenUrl) {
		$this->requestTokenUrl = $requestTokenUrl;
		return $this;
	}

	public function setAccessTokenUrl($accessTokenUrl) {
		$this->accessTokenUrl = $accessTokenUrl;
		return $this;
	}

	public function setAuthorizationUrl($authorizationUrl) {
		$this->authorizationUrl = $authorizationUrl;
		return $this;
	}

	public function requestTempCredentials() {
		$client = new Client($this->baseUrl);

		$oauth = new OauthPlugin(array(
			'consumer_key' 		=> $this->consumerKey,
			'consumer_secret' 	=> $this->consumerSecret,
			'token' 			=> false,
			'token_secret' 		=> false,
		));
		$client->addSubscriber($oauth);

		$timestamp = time();
		$nonce = $oauth->generateNonce($client->post($this->requestTokenUrl));
		$params = $oauth->getParamsToSign($client->post($this->requestTokenUrl), $timestamp, $nonce);

		$params['oauth_signature'] = $oauth->getSignature($client->post($this->requestTokenUrl), $timestamp, $nonce);
		$response = $client->post($this->requestTokenUrl . '?oauth_callback=' . $this->callbackUrl)->send();

		$body = $response->getBody();

		$tokens = array();
		parse_str((string) $body, $tokens);

		if (empty($tokens)) {
			throw new Exception("An error occurred while requesting oauth temporary credentials");
		}

		$this->tempCreds = $tokens;
		return $this->tempCreds;
	}

	public function requestTokenCredentials($token, $tokenSecret) {
		$client = new Client($this->baseUrl);

		$oauth = new OauthPlugin(array(
			'consumer_key' 		=> $this->consumerKey,
			'consumer_secret' 	=> $this->consumerSecret,
			'token'	 			=> $token,
			'token_secret' 		=> $tokenSecret, 
		));
		$client->addSubscriber($oauth);

		$timestamp = time();
		$nonce = $oauth->generateNonce($client->post($this->accessTokenUrl));

		$params = $oauth->getParamsToSign(
			$client->post($this->accessTokenUrl), $timestamp, $nonce
		);
		$params['oauth_signature'] = $oauth->getSignature(
			$client->post($this->accessTokenUrl), $timestamp, $nonce
		);

		$response = $client->post(
			$this->accessTokenUrl . '?oauth_callback=' . $this->callback . '&oauth_verifier=' . $_GET['oauth_verifier']
		)->send();

		$body = $response->getBody();

		$tokens = array();
		parse_str((string) $body, $tokens);

		if (empty($tokens)) {
			throw new Exception("An error occurred while requesting oauth token credentials");
		}

		$this->tokenCreds = $tokens;
		return $this->tokenCreds;
	}

	public function redirectAuth() {
		$this->redirect($this->baseUrl . sprintf($this->authorizationUrl, urlencode($this->tempCreds['oauth_token'])));
	}

	public function redirect($url = null) {
		if (is_null($url)) {
			$url = $_SERVER['PHP_SELF'];
		}
		header("Location: {$url}");
		exit;
	}

	public function buildUrl($append = null) {
		$url = (empty($_SERVER['HTTPS'])) ? "http://" : "https://";
		$url .= $_SERVER['SERVER_NAME'];
		$url .= ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? "" : (":".$_SERVER['SERVER_PORT']);
		$url .= $_SERVER['SCRIPT_NAME'];
		if (!is_null($append)) {
			$url .= $append;
		}
		return $url;
	}
}

