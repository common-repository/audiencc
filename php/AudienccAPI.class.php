<?php
/**
 * This module requires the Curl PHP module, available in PHP 4 and 5
 */
assert(function_exists("curl_init"));

/**
 * Description of audienccapi
 *
 * @author sudar
 */
class AudienccAPI {
    var $version = '0.1';
    var $baseurl = 'http://audien.cc';

    var $username;
    var $password;

    /**
     * Default to a 300 second timeout on server calls
     */
    var $timeout = 300;

    var $response;
    var $errorMessage;
    var $errorCode;

    function AudienccAPI($username = null, $password = null) {
        $this->username = $username;
        $this->password = $password;
    }

    function setTimeout($seconds){
        if (is_int($seconds)){
            $this->timeout = $seconds;
            return true;
        }
    }
    function getTimeout(){
        return $this->timeout;
    }

    function get_account_data() {
        return $this->_url_open('/account.json', 'GET');
    }

    function check_availability($subdomain, $email = '', $url = '') {
        $args = array(
            'subdomain' => $subdomain,
        );

        if ($email != '') {
            $args['email'] = $email;
        }
        if ($url != '') {
            $args['url'] = $url;
        }
        return $this->_url_open('/account/check_availability.json', 'GET', $args);
    }

    function create_account($args) {
        return $this->_url_open('/account.json', 'POST', $args);
    }

    function update_account($args) {
        return $this->_url_open('/account.json', 'POST', $args);
    }

	/**
	 * just open the url and get back the content
	 */
	function _url_open($api_method, $method, $post_args = null ) {
        $url = $this->baseurl . $api_method;
		$req = new HTTPRequest();
		$this->response = $req->get_response($url, $method, $this->username, $this->password, $post_args);

		if (isset($this->response->error)) {
			$this->errorCode =  $this->response->error['code'];
            $this->errorMessage = $this->response->error['message'];
        } else if (!in_array($this->response->code, array(200, 204))) {
			$this->errorCode =  $this->response->code;
            $this->errorMessage = $this->response->body;
        }

        return $this->response->body;
	}

    function call_server($api_method, $method = 'GET'){
        $url = $this->baseurl . $api_method;
        
        $cred = sprintf('Authorization: Basic %s', base64_encode("{$this->username}:{$this->password}") );
        $opts = array(
            'http'=>array(
            'method'=>$method,
            'header'=>$cred)
        );
        $ctx = stream_context_create($opts);
        $handle = fopen ( $url, 'r', false,$ctx);

        if (!$handle) {
            $this->errorMessage = __('There was some problem while logging in. Either the username password combination is wrong or there was some problem in contectivity');
        } else {
            return stream_get_contents($handle);
        }
    }
}

/**
 * Private Class HTTP the request
 */
class HTTPRequest
{

	/**
	 * Get the reponse
	 * @param the url
	 */
	public function get_response($url, $method  = "GET", $username = null, $password = null, $post_args = null)	{
		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'HTTP/php');

        if ($method == "PUT") {

            $method = "POST";

            $post_args['_method'] = "put";
//			$data  = array();
//			curl_setopt($curl, CURLOPT_PUT,true);
//            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
//			$data_string = $this->build_http_query($post_args);
//			curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        }

		if ($method == "POST") {
			$data  = array();
			curl_setopt($curl, CURLOPT_POST,count($post_args));
			$data_string = $this->build_http_query($post_args);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
		} else if ($method == "GET" && $post_args != null) {
			$url .= "?" . $this->build_http_query($post_args);
		}

        // provide credentials if they're established at the beginning of the script
        if(!empty($username) && !empty($password)) {
            curl_setopt($curl,CURLOPT_USERPWD,$username . ":" . $password);
        }

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, '__responseHeaderCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      
		// Execute, grab errors
		if (curl_exec($curl))
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		else
			$this->response->error = array(
				'code' => curl_errno($curl),
				'message' => curl_error($curl)
			);

		@curl_close($curl);

//		if (isset($this->response->error))
//			throw new Exception($this->response->error['code'] . ': ' . $this->response->error['message']);
//		else if (!in_array($this->response->code, array(200, 204)))
//			throw new Exception(json_decode($this->response->body)->{'errorCode'});

		return $this->response;
	}

	/**
	* CURL write callback
	*
	* @param resource &$curl CURL resource
	* @param string &$data Data
	* @return integer
	*/
	private function __responseWriteCallback(&$curl, &$data) {
		$this->response->body .= $data;
		return strlen($data);
	}


	/**
	* CURL header callback
	*
	* @param resource &$curl CURL resource
	* @param string &$data Data
	* @return integer
	*/
	private function __responseHeaderCallback(&$curl, &$data) {
		if (($strlen = strlen($data)) <= 2) return $strlen;
		if (substr($data, 0, 4) == 'HTTP')
			$this->response->code = (int)substr($data, 9, 3);
		else {
			list($header, $value) = explode(': ', trim($data), 2);
			if ($header == 'Last-Modified')
				$this->response->headers['time'] = strtotime($value);
			elseif ($header == 'Content-Length')
				$this->response->headers['size'] = (int)$value;
			elseif ($header == 'Content-Type')
				$this->response->headers['type'] = $value;
			elseif ($header == 'ETag')
				$this->response->headers['hash'] = $value{0} == '"' ? substr($value, 1, -1) : $value;
			elseif (preg_match('/^x-amz-meta-.*$/', $header))
				$this->response->headers[$header] = is_numeric($value) ? (int)$value : $value;
		}
		return $strlen;
	}

    public function build_http_query($params) {
        if (!$params) return '';

        // Urlencode both keys and values
        $keys = $this->urlencode_rfc3986(array_keys($params));
        $values = $this->urlencode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);

        // Parameters are sorted by name, using lexicographical byte value ordering.
        // Ref: Spec: 9.1.1 (1)
        uksort($params, 'strcmp');

        $pairs = array();
        foreach ($params as $parameter => $value) {
            if (is_array($value)) {
            // If two or more parameters share the same name, they are sorted by their value
            // Ref: Spec: 9.1.1 (1)
                natsort($value);
                foreach ($value as $duplicate_value) {
                    $pairs[] = $parameter . '=' . $duplicate_value;
                }
            } else {
                $pairs[] = $parameter . '=' . $value;
            }
        }
        // For each parameter, the name is separated from the corresponding value by an '=' character (ASCII code 61)
        // Each name-value pair is separated by an '&' character (ASCII code 38)
        return implode('&', $pairs);
    }

    public function urlencode_rfc3986($input) {
        if (is_array($input)) {
            return array_map(array(&$this, 'urlencode_rfc3986'), $input);
        } else if (is_scalar($input)) {
                return str_replace(
                '+',
                ' ',
                str_replace('%7E', '~', rawurlencode($input))
                );
            } else {
                return '';
            }
    }

}

?>