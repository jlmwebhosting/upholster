<?php

require_once('Upholster.php');

$upholster = new Upholster;
$upholster
	// Try to prevent php from timing out
	->setLongRunning($secs = 1000)

	// Random delay of 10-30 seconds between requests (can also be a constant)
	->setDelay(array(10, 30))

	// The URL to get the form/session from (optional second argument for http method)
	->setFormUrl('http://example.com/poll')

	// URL to form action
	->setVoteUrl('http://example.com/poll/vote', 'POST')

	// Send data extracted from the document by #elementID, .classname, xpath string
	->extractField('nonce', '#poll_201_nonce')

	// Or do your own wacky stuff to find the value to submit
	->extractField('nonce_complicated', function(DOMDocument $dom) {
		// use xpath and/or regex to find and return the string value
		// or DOMNode which contains the value either in the value attribute
		// or contentText.
	})

	// Send your own constant data
	->submitField('pollValue', '4')

	// Use an array to randomize the data
	->submitField('pollValueRand', array('2', '4'))

	// Callback so you can parse and display results or whatever
	->handleResponse(function($response) {
		// $response here is the response body returned from submitting the 
	})

	// Run it as many times as you like
	->run(1000);
