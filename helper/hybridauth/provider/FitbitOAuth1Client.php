<?php

class FitbitClient extends OAuth1Client {

	/**
	 * Make http request
	 */
	function request( $url, $method, $postfields = NULL, $auth_header = null )
	{
		Hybrid_Logger::info( "Enter FitbitClient::request( $method, $url )" );
		Hybrid_Logger::debug( "FitbitClient::request(). dump post fields: ", serialize( $postfields ) );
		
		$this->http_info = array();
		$ci = curl_init();
		
		// get Fitbit provider config 
		$cfg = Hybrid_Auth::$config['providers']['Fitbit'];

		// Authorization: OAuth oauth_consumer_key="fitbit-example-client-application",
		$header = array(
				'Authorization: OAuth oauth_consumer_key="'.$cfg["keys"]["key"].'"'
		);
		
		/* Curl settings */
		curl_setopt( $ci, CURLOPT_USERAGENT     , $this->curl_useragent );
		curl_setopt( $ci, CURLOPT_CONNECTTIMEOUT, $this->curl_connect_time_out );
		curl_setopt( $ci, CURLOPT_TIMEOUT       , $this->curl_time_out );
		curl_setopt( $ci, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ci, CURLOPT_HTTPHEADER    , $header );
		curl_setopt( $ci, CURLOPT_SSL_VERIFYPEER, $this->curl_ssl_verifypeer );
		curl_setopt( $ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader') );
		curl_setopt( $ci, CURLOPT_HEADER        , FALSE );

		if($this->curl_proxy){
			curl_setopt( $ci, CURLOPT_PROXY        , $this->curl_proxy);
		}

		switch ($method){
			case 'POST':
				curl_setopt( $ci, CURLOPT_POST, TRUE );

				if ( !empty($postfields) ){
					curl_setopt( $ci, CURLOPT_POSTFIELDS, $postfields );
				}

				if ( !empty($auth_header) && $this->curl_auth_header ){
					curl_setopt( $ci, CURLOPT_HTTPHEADER, array( 'Content-Type: application/atom+xml', $auth_header ) );
				}
				break;
			case 'DELETE':
				curl_setopt( $ci, CURLOPT_CUSTOMREQUEST, 'DELETE' );
				if ( !empty($postfields) ){
					$url = "{$url}?{$postfields}";
				}
		}

		curl_setopt($ci, CURLOPT_URL, $url);
		$response = curl_exec($ci);

		Hybrid_Logger::debug( "FitbitClient::request(). dump request info: ", serialize( curl_getinfo($ci) ) );
		Hybrid_Logger::debug( "FitbitClient::request(). dump request result: ", serialize( $response ) );

		$this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
		$this->http_info = array_merge($this->http_info, curl_getinfo($ci));

		curl_close ($ci);

		return $response;
	}

}