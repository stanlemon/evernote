<?php
namespace Lemon;

use EDAM\NoteStore\NoteStoreClient;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NotesMetadataResultSpec;
use TBinaryProtocol;
use THttpClient;

class Evernote {

	protected $authToken;
	protected $noteStoreUrl;

	protected $protocol;
	protected $client;

	public function __construct($authToken, $noteStoreUrl) {
		$this->authToken = $authToken;
		$this->noteStoreUrl = $noteStoreUrl;
	}

	public function loadProtocol() {
		if (is_null($this->protocol)) {
			$parts = parse_url($this->noteStoreUrl);
			$parts['port'] = ($parts['scheme'] === 'https') ? 443 : 80;

			$client = new THttpClient($parts['host'], $parts['port'], $parts['path'], $parts['scheme']);

			$this->protocol = new TBinaryProtocol($client);
		}
		return $this->protocol;
	}

	public function loadClient() {
		if (is_null($this->client)) {
			$protocol = $this->loadProtocol();
			$this->client = new NoteStoreClient($protocol, $protocol);
		}
		return $this->client;
	}

	public function syncState() {
		$client = $this->loadClient();
		return $client->getSyncState($this->authToken);
	}

	public function listNotebooks() {
		$client = $this->loadClient();
		return $client->listNotebooks($this->authToken);
	}

	public function listNotes($notebookGuid) {
		$client = $this->loadClient();

		$counts = $client->findNoteCounts($this->authToken, new NoteFilter(), false);
		$total = $counts->notebookCounts[$notebookGuid];

		$spec = new NotesMetadataResultSpec();
		$spec->includeTitle = true;
		$spec->includeContentLenght = true;
		$spec->includeCreated = true;
		$spec->includeUpdated = true;
		$spec->includeUpdateSequenceNum = true;
		$spec->includeNotebookGuid = true;

		$notes = $client->findNotesMetadata($this->authToken, new NoteFilter(), 0, $total+1, $spec);

		return $notes->notes;
	}

	public function getNote($noteGuid) {
		$client = $this->loadClient();
		$note = $client->getNote($this->authToken, $noteGuid, true, true, true, true);
		return $note;
	}
	
	public function getNoteAsHtml($noteGuid) {
		$note = (object) $this->getNote($noteGuid);

		$doc = new \DOMDocument();
		$doc->loadXML($note->content);

		// Loop through every resource and embed it
		foreach ($doc->getElementsByTagName('en-media') as $media) {
			$type = $media->getAttribute('type');
			$hash = $media->getAttribute('hash');

			$imgSrc = false;
			foreach ($note->resources as $resource) {
				// Is there a better way to match this hash?
				if (false !== strpos($resource->recognition->body, $hash)) {
					$imgSrc = $resource->data->body;
				}
			}

			if ($imgSrc) {
				$img = $doc->createElement('img');
				$img->setAttribute('src', 'data:'  .$type . ';base64,' . base64_encode($imgSrc));
			
				$media->parentNode->replaceChild($img, $media);
			}
		}

		$dom = new \DOMDocument();
		$dom->loadXml("<div></div>");

		foreach ($doc->getElementsByTagName('en-note')->item(0)->childNodes as $child) {
			$node = $dom->importNode($child, true);
			
			$dom->documentElement->appendChild($node);
		}

		$note->content = $dom->saveHTML($dom->documentElement);

		return $note;
	}
}
