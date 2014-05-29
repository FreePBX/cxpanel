<?php // -*- c-basic-offset: 2 -*-

/**
 * CXPest is a REST client for PHP.
 *
 * See http://github.com/educoder/pest for details.
 *
 * This code is licensed for use, modification, and distribution
 * under the terms of the MIT License (see http://en.wikipedia.org/wiki/MIT_License)
 */
class CXPest {
  public $curl_opts = array(
  	CURLOPT_RETURNTRANSFER => true,  // return result instead of echoing
  	CURLOPT_SSL_VERIFYPEER => false, // stop cURL from verifying the peer's certificate
  	CURLOPT_FOLLOWLOCATION => false,  // follow redirects, Location: headers
  	CURLOPT_MAXREDIRS      => 10,     // but don't redirect more than 10 times
    CURLOPT_HEADER => true, //I9 Technologies modification
  	CURLOPT_TIMEOUT => 7, 	//I9 Technologies modification
  	CURLOPT_SSL_VERIFYHOST => false //I9 Technologies modification
  );

  public $base_url;
  
  public $last_response;
  public $last_request;
  public $last_headers;
  
  public $throw_exceptions = true;
  
  public function __construct($base_url) {
    if (!function_exists('curl_init')) {
  	    throw new Exception('CURL module not available! CXPest requires CURL. See http://php.net/manual/en/book.curl.php');
  	}
  	
  	// only enable CURLOPT_FOLLOWLOCATION if safe_mode and open_base_dir are not in use
  	if(ini_get('open_basedir') == '' && strtolower(ini_get('safe_mode')) == 'off') {
  	  $this->curl_opts['CURLOPT_FOLLOWLOCATION'] = true;
  	}
    
    $this->base_url = $base_url;
    
    // The callback to handle return headers
    // Using PHP 5.2, it cannot be initialised in the static context
    $this->curl_opts[CURLOPT_HEADERFUNCTION] = array($this, 'handle_header');
  }
  
  // $auth can be 'basic' or 'digest'
  public function setupAuth($user, $pass, $auth = 'basic') {
    $this->curl_opts[CURLOPT_HTTPAUTH] = constant('CURLAUTH_'.strtoupper($auth));
    $this->curl_opts[CURLOPT_USERPWD] = $user . ":" . $pass;
  }
  
  // Enable a proxy
  public function setupProxy($host, $port, $user = NULL, $pass = NULL) {
    $this->curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
    $this->curl_opts[CURLOPT_PROXY] = $host;
    $this->curl_opts[CURLOPT_PROXYPORT] = $port;
    if ($user && $pass) {
      $this->curl_opts[CURLOPT_PROXYUSERPWD] = $user . ":" . $pass;
    }
  }
  
  public function get($url) {
    $curl = $this->prepRequest($this->curl_opts, $url);
    $body = $this->doRequest($curl);
    
    $body = $this->processBody($body);
    
    return $body;
  }
  
  public function prepData($data) {
    if (is_array($data)) {
        $multipart = false;
        
        foreach ($data as $item) {
            if (strncmp($item, "@", 1) == 0 && is_file(substr($item, 1))) {
                $multipart = true;
                break;
            }
        }
        
        return ($multipart) ? $data : http_build_query($data);
    } else {
        return $data;
    }
  }
  
  public function post($url, $data, $headers=array()) {
    $data = $this->prepData($data);
        
    $curl_opts = $this->curl_opts;
    $curl_opts[CURLOPT_CUSTOMREQUEST] = 'POST';
    if (!is_array($data)) $headers[] = 'Content-Length: '.strlen($data);
    $curl_opts[CURLOPT_HTTPHEADER] = $headers;
    $curl_opts[CURLOPT_POSTFIELDS] = $data;
    
    $curl = $this->prepRequest($curl_opts, $url);
    $body = $this->doRequest($curl);
    
    $body = $this->processBody($body);
    
    return $body;
  }
  
  public function put($url, $data, $headers=array()) {
    $data = $this->prepData($data);
    
    $curl_opts = $this->curl_opts;
    $curl_opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
    if (!is_array($data)) $headers[] = 'Content-Length: '.strlen($data);
    $curl_opts[CURLOPT_HTTPHEADER] = $headers;
    $curl_opts[CURLOPT_POSTFIELDS] = $data;
    
    $curl = $this->prepRequest($curl_opts, $url);
    $body = $this->doRequest($curl);
    
    $body = $this->processBody($body);
    
    return $body;
  }
  
    public function patch($url, $data, $headers=array()) {
    $data = (is_array($data)) ? http_build_query($data) : $data; 
    
    $curl_opts = $this->curl_opts;
    $curl_opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
    $headers[] = 'Content-Length: '.strlen($data);
    $curl_opts[CURLOPT_HTTPHEADER] = $headers;
    $curl_opts[CURLOPT_POSTFIELDS] = $data;
    
    $curl = $this->prepRequest($curl_opts, $url);
    $body = $this->doRequest($curl);
    
    $body = $this->processBody($body);
    
    return $body;
  }
  
  public function delete($url) {
    $curl_opts = $this->curl_opts;
    $curl_opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    
    $curl = $this->prepRequest($curl_opts, $url);
    $body = $this->doRequest($curl);
    
    $body = $this->processBody($body);
    
    return $body;
  }
  
  public function lastBody() {
    return $this->last_response['body'];
  }
  
  public function lastStatus() {
    return $this->last_response['meta']['http_code'];
  }
  
  /**
   * Return the last response header (case insensitive) or NULL if not present.
   * HTTP allows empty headers (e.g. RFC 2616, Section 14.23), thus is_null()
   * and not negation or empty() should be used.
   */
  public function lastHeader($header) {
    if (empty($this->last_headers[strtolower($header)])) {
      return NULL;
    }
    return $this->last_headers[strtolower($header)];
  }
  
  protected function processBody($body) {
    // Override this in classes that extend CXPest.
    // The body of every GET/POST/PUT/DELETE response goes through 
    // here prior to being returned.
    return $body;
  }
  
  protected function processError($body) {
    // Override this in classes that extend CXPest.
    // The body of every erroneous (non-2xx/3xx) GET/POST/PUT/DELETE  
    // response goes through here prior to being used as the 'message'
    // of the resulting CXPest_Exception
    return $body;
  }

  
  protected function prepRequest($opts, $url) {
    if (strncmp($url, $this->base_url, strlen($this->base_url)) != 0) {
      $url = rtrim($this->base_url, '/') . '/' . ltrim($url, '/');
    }
    $curl = curl_init($url);
    
    foreach ($opts as $opt => $val)
      curl_setopt($curl, $opt, $val);
      
    $this->last_request = array(
      'url' => $url
    );
    
    if (isset($opts[CURLOPT_CUSTOMREQUEST]))
      $this->last_request['method'] = $opts[CURLOPT_CUSTOMREQUEST];
    else
      $this->last_request['method'] = 'GET';
    
    if (isset($opts[CURLOPT_POSTFIELDS]))
      $this->last_request['data'] = $opts[CURLOPT_POSTFIELDS];
    
    return $curl;
  }
  
  private function handle_header($ch, $str) {
    if (preg_match('/([^:]+):\s(.+)/m', $str, $match) ) {
      $this->last_headers[strtolower($match[1])] = trim($match[2]);
    }
    return strlen($str);
  }

  private function doRequest($curl) {
    $this->last_headers = array();
    
    $body = curl_exec($curl);
    $meta = curl_getinfo($curl);
    
    //I9 Technologies modification
    $headerEndPos = strpos($body, "\r\n\r\n");
    $header = substr($body, 0, $headerEndPos);
    $body = substr($body, $headerEndPos + 4);
   
    $this->last_response = array(
      'body' => $body,
      'meta' => $meta,
      'header' => $header
    );
    
    curl_close($curl);
    
    $this->checkLastResponseForError();
    
    return $body;
  }
  
  protected function checkLastResponseForError() {
    if ( !$this->throw_exceptions)
      return;

    $meta = $this->last_response['meta'];
    $body = $this->last_response['body'];
    $header = $this->last_response['header'];
        
    if (!$meta)
      return;
    
    $err = null;
    switch ($meta['http_code']) {
      //i9 Technologies modification
      case 302:
    	preg_match("/Location: ([\S]+)/", $header, $matches);
    	throw new CXPest_Found($matches[1]);
    	break;
      //i9 Technologies modification
      case 307:
      	preg_match("/Location: ([\S]+)/", $header, $matches);
      	throw new CXPest_TemporaryRedirect($matches[1]);
      	break;
      case 400:
        throw new CXPest_BadRequest($this->processError($body));
        break;
      case 401:
        throw new CXPest_Unauthorized($this->processError($body));
        break;
      case 403:
        throw new CXPest_Forbidden($this->processError($body));
        break;
      case 404:
        throw new CXPest_NotFound($this->processError($body));
        break;
      case 405:
        throw new CXPest_MethodNotAllowed($this->processError($body));
        break;
      case 409:
        throw new CXPest_Conflict($this->processError($body));
        break;
      case 410:
        throw new CXPest_Gone($this->processError($body));
        break;
      case 422:
        // Unprocessable Entity -- see http://www.iana.org/assignments/http-status-codes
        // This is now commonly used (in Rails, at least) to indicate
        // a response to a request that is syntactically correct,
        // but semantically invalid (for example, when trying to 
        // create a resource with some required fields missing)
        throw new CXPest_InvalidRecord($this->processError($body));
        break;
      default:
        if ($meta['http_code'] >= 400 && $meta['http_code'] <= 499)
          throw new CXPest_ClientError($this->processError($body));
        elseif ($meta['http_code'] >= 500 && $meta['http_code'] <= 599)
          throw new CXPest_ServerError($this->processError($body));
        elseif (!$meta['http_code'] || $meta['http_code'] >= 600) {
          throw new CXPest_UnknownResponse($this->processError($body));
        }
    }
  }  
}

class CXPest_Exception extends Exception { }
class CXPest_UnknownResponse extends CXPest_Exception { }

//i9 Technologies modification
/* 307 */ class CXPest_TemporaryRedirect extends CXPest_ClientError {
	var $redirectUri;	
	
	function CXPest_TemporaryRedirect($redirectUri) {
		$this->redirectUri = $redirectUri;
	}
}

//i9 Technologies modification
/* 302 */class CXPest_Found extends CXPest_ClientError {
	var $redirectUri;

	function CXPest_Found($redirectUri) {
		$this->redirectUri = $redirectUri;
	}
}

/* 401-499 */ class CXPest_ClientError extends CXPest_Exception {}
/* 400 */ class CXPest_BadRequest extends CXPest_ClientError {}
/* 401 */ class CXPest_Unauthorized extends CXPest_ClientError {}
/* 403 */ class CXPest_Forbidden extends CXPest_ClientError {}
/* 404 */ class CXPest_NotFound extends CXPest_ClientError {}
/* 405 */ class CXPest_MethodNotAllowed extends CXPest_ClientError {}
/* 409 */ class CXPest_Conflict extends CXPest_ClientError {}
/* 410 */ class CXPest_Gone extends CXPest_ClientError {}
/* 422 */ class CXPest_InvalidRecord extends CXPest_ClientError {}

/* 500-599 */ class CXPest_ServerError extends CXPest_Exception {}


