<?php

/**
 * Upholster
 *
 * Automated poll spammer
 *
 * @package     kbanman/upholster
 * @author      Kelly Banman
 * @version     0.1
 * @license     http://philsturgeon.co.uk/code/dbad-license
 *
 * Depends on php-curl and php-xml extensions.
 * @todo: remove php-xml dependency. That functionality could
 * be done in the implementation.
 *
 * Takes advantage of php-pcntl if available.
 * @todo: support forking the process to increase volume
 *
 */
class Upholster {

	/**
	 * The currently loaded page
	 *
	 * @var DOMDocument
	 */
	protected $dom;

	/**
	 * cURL handle
	 *
	 * @var pointer
	 */
	protected $conn;

	/**
	 * URL to get the session data from
	 *
	 * @var string
	 */
	protected $formUrl;

	/**
	 * HTTP method with which to retrieve the form
	 *
	 * @var string
	 */
	protected $formMethod;

	/**
	 * URL to poll endpoint
	 *
	 * @var string
	 */
	protected $voteUrl;

	/**
	 * HTTP method used to submit the poll data
	 *
	 * @var string
	 */
	protected $voteMethod;

	/**
	 * Seconds to wait between requests.
	 *
	 * @var integer|array
	 */
	protected $delay;

	/**
	 * Callback to apply vote response
	 *
	 * @var callable
	 */
	protected $responseCallback;

	/**
	 * Fields to extract from the form page
	 *
	 * @var array
	 */
	protected $extractables = array();

	/**
	 * Fields to send with every request
	 *
	 * @var array
	 */
	protected $submittables = array();

	/**
	 * Keeps track of the number of iterations run
	 *
	 * @var array
	 */
	protected $iterations;

	/**
	 * User agents to use
	 *
	 * @var array
	 */
	public $userAgents = array(
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31', //	Chrome 26.0 Win7 64-bit
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.65 Safari/537.31', //	Chrome 26.0 MacOSX
		'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0', //	Firefox 20.0 Win7 64-bit
		'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31', //	Chrome 26.0 Win7 32-bit
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/536.29.13 (KHTML, like Gecko) Version/6.0.4 Safari/536.29.13', //	Safari 6.0 MacOSX
		'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31', //	Chrome 26.0 WinXP 32-bit
		'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31', //	Chrome 26.0 Win8 64-bit
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/536.28.10 (KHTML, like Gecko) Version/6.0.3 Safari/536.28.10', //	Safari 6.0 MacOSX
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0', //	Firefox 20.0 MacOSX
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.65 Safari/537.31', //	Chrome 26.0 MacOSX
		'Mozilla/5.0 (Windows NT 5.1; rv:20.0) Gecko/20100101 Firefox/20.0', //	Firefox 20.0 WinXP 32-bit
		'Mozilla/5.0 (Windows NT 6.1; rv:20.0) Gecko/20100101 Firefox/20.0', //	Firefox 20.0 Win7 32-bit
		'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)', //	IE 9.0 Win7 64-bit
		'Mozilla/5.0 (iPhone; CPU iPhone OS 6_1_3 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10B329 Safari/8536.25', //	Mobile Safari 6.1iOS
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31', //	Chrome 26.0 Win7 64-bit
		'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:20.0) Gecko/20100101 Firefox/20.0', //	Firefox 20.0 Linux
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.63', // Safari/537.31 
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Check for environment
		if ( ! class_exists('DOMDocument')) {
			die('Upholster depends on the PHP XML extension');
		}
		if ( ! function_exists('curl_init')) {
			die('Upholster depends on the PHP cURL extension');
		}

		$this->dom = new DOMDocument;
		$this->dom->preserveWhiteSpace = false;
		$this->dom->validateOnParse = true;
		// Do our own error handling
		libxml_use_internal_errors(true);
	}

	/**
	 * Set the url from which to grab the form
	 *
	 * @param    string     URL
	 * @param    string     Optional HTTP method (GET or POST)
	 */
	public function setFormUrl($url, $method = 'GET')
	{
		$this->formUrl = $url;
		$this->formMethod = $method;

		return $this;
	}

	/**
	 * Set the url to submit poll data
	 *
	 * @param    string     URL
	 * @param    string     Optional HTTP method (GET or POST)
	 */
	public function setVoteUrl($url, $method = 'GET')
	{
		$this->voteUrl = $url;
		$this->voteMethod = $method;

		return $this;
	}

	/**
	 * Add a field to extract from the form page
	 *
	 * @param    string     Field name
	 * @param    mixed      Xpath or closure ($doc) returning the value
	 */
	public function extractField($field, $value)
	{
		$this->extractables[$field] = $value;

		return $this;
	}

	/**
	 * Add a field to send to the poll
	 *
	 * @param    string     Field name
	 * @param    mixed      Value or closure ($doc) returning the value,
	 *                      which can be a string or an array from which
	 *						to randomly choose a value
	 */
	public function submitField($field, $value)
	{
		$this->submittables[$field] = $value;

		return $this;
	}

	/**
	 * Set the delay between form submissions
	 *
	 * @param    mixed      Integer seconds or an array with two elements,
	 *                      the minimum and max from which to randomly choose
	 */
	public function setDelay($delay)
	{
		$this->delay = $delay;

		return $this;
	}

	/**
	 * Try to prevent PHP from dying if we're running this for a long time
	 */
	public function setLongRunning($secs = 3600)
	{
		set_time_limit(0);
		ini_set('memory_limit', '64M');
		ini_set('max_execution_time', $secs);
		ini_set('output_buffering', false);
		ini_set('implicit_flush', true);
		@ob_end_clean();

		return $this;
	}

	/**
	 * Allow the calling script to do something with the response
	 */
	public function handleResponse($callable)
	{
		$this->responseCallback = $callable;

		return $this;
	}

	/**
	 * Run the script for a specified number of iterations
	 *
	 * @param    integer   Iterations
	 */
	public function run($n = 1)
	{
		echo "Starting upholstery\n-----------------";

		// Catch CTRL-C (if PCTNL is installed)
		$this->setupPCTNL();

		// DO IT
		for ($i = $this->iterations = 0; $i < $n; $i++) {
			// Start the cURL session
			$this->connect();

			// Choose a user agent
			$this->setUserAgent($this->userAgents[array_rand($this->userAgents)]);

			// Hit the form
			$response = $this->request($this->formUrl, $data = array(), $this->formMethod);
			$this->dom->loadHTML($response);

			// Get the data to send
			$response = $this->request($this->voteUrl, $this->compileData(), $this->voteMethod);

			if (is_callable($this->responseCallback)) {
				call_user_func($this->responseCallback, $response);
			}

			// End the session
			$this->disconnect();

			// Count it
			$this->iterations++;

			// Wait a while
			$this->delay();
		}

		// Wrap things up
		$this->finish();
	}

	/**
	 * Causes the delay between requests
	 */
	protected function delay()
	{
		$delay = 1;

		if (is_numeric($this->delay)) {
			$delay = $this->delay;
		} elseif (is_array($this->delay)) {
			$delay = rand($this->delay[0], $this->delay[1]);
		}

		// Sleep for a second at a time so the pctnl signal can get through
		for ($delay; $delay; $delay--) {
			sleep(1);
			echo '.';
		}
	}

	/**
	 * Compile the data to send to the poll endpoint
	 */
	protected function compileData()
	{
		$data = array();

		// Fields extracted from the form
		foreach ($this->extractables as $field => $value) {
			if (is_callable($value)) {
				// Closure
				$value = $value($this->dom);
			} else {
				if (preg_match('/^#[0-9a-zA-Z-_]*$/', $value)) {
					// Id attribute
					$value = $this->dom->getElementById(substr($value, 1));
				} elseif (preg_match('/^\.[0-9a-zA-Z-_]*$/', $value)) {
					// Class attribute
					$classname = substr($value, 1);
					$nodes = (new DomXPath($this->dom))
						->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]")
						->item(0);
				} else {
					// Xpath;
					$value = (new DomXPath($this->dom))
						->query($value)
						->item(0);
				}

				if ($value && $value->hasAttribute('value')) {
					$value = $value->getAttribute('value');
				} elseif ($value) {
					$value = trim($value->textContent);
				} else {
					var_dump($this->extractables[$field]);
				}
			}

			if (is_array($value)) {
				$value = $value[array_rand($value)];
			}

			$data[$field] = $value;
		}

		// Manually-specified fields
		foreach ($this->submittables as $field => $value) {
			if (is_array($value)) {
				$value = $value[array_rand($value)];
			}

			$data[$field] = $value;
		}

		return $data;
	}

	/**
	 * Clean up after we're done
	 *
	 * @param    string    User agent
	 */
	protected function finish()
	{
		$this->disconnect();

		echo "\n-----------------\nFinished {$this->iterations} iterations.\n";

		exit;
	}

	/**
	 * Listen for CTRL-C
	 */
	protected function setupPCTNL()
	{
		if ( ! function_exists('pcntl_signal')) return;

		declare(ticks = 1);

		$that = $this;

		pcntl_signal(SIGINT, function($signo) use ($that) {
			$that->finish();
		});
	}

	/**
	 * Show how many iterations we've completed
	 */
	protected function status()
	{
		echo "\nWe've done {$this->iterations} iterations.\n";
	}

	/**
	 * Set the user agent to use on the next request
	 *
	 * @param    string    User agent
	 */
	protected function setUserAgent($ua)
	{
		curl_setopt($this->conn, CURLOPT_USERAGENT, $ua);
	}

	/**
	 * Initialize a cURL instance
	 *
	 * @param    string    User agent
	 */
	protected function connect()
	{
		// If there is no connection
		if ( ! is_resource($this->conn)) {
			// Try to create one
			if ( ! $this->conn = curl_init()) {
				trigger_error('Could not start new CURL instance');
				$this->error = true;
				return false;
			}
		}

		curl_setopt_array($this->conn, array(
			CURLOPT_HEADER         => false,
			CURLOPT_POST           => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FRESH_CONNECT  => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_AUTOREFERER    => true,
			CURLOPT_USERAGENT      => reset($this->userAgents),
			CURLOPT_COOKIEJAR      => tempnam('/tmp', 'CURLCOOKIE'),
			CURLOPT_COOKIEFILE     => tempnam('/tmp', 'CURLCOOKIEFILE'),
		));

		return true;
	}

	/**
	* Close the current cURL session
	*
	* @access protected
	* @return boolean
	*/
	protected function disconnect()
	{
		if (is_resource($this->conn)) {
			curl_close($this->conn);
		}
	}

	/**
	* Send a request through the current cURL session
	*
	* @param     string    The URL to send it to
	* @param     array     The data to be POSTed or attached as GET query parameters or false on error
	*
	* @return    string    Response body
	*/    
	protected function request($url, $data = array(), $method = 'POST')
	{
		// Set the url to send data to
		curl_setopt($this->conn, CURLOPT_URL, $url);

		if ($method != 'POST') {
			curl_setopt($this->conn, CURLOPT_HTTPGET, true);
			curl_setopt($this->conn, CURLOPT_URL, $url.'?'.http_build_query($data));
		} else {
			curl_setopt($this->conn, CURLOPT_POST, true);
			curl_setopt($this->conn, CURLOPT_POSTFIELDS, http_build_query($data));
		}

		// Send data and grab the result
		$response = curl_exec($this->conn);
		if ($response === false) {
			trigger_error(curl_error($this->conn));
			$this->error = true;
			return false;
		}

		return $response;
	}
}