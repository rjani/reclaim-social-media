<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html 
*/

/**
 * Hybrid_Providers_Fitbit provider adapter based on OAuth1 protocol
 * 
 * http://hybridauth.sourceforge.net/userguide/IDProvider_info_MySpace.html
 */
class Hybrid_Providers_Fitbit extends Hybrid_Provider_Model_OAuth1
{
	/**
	* IDp wrappers initializer 
	*/
	function initialize() 
	{
		parent::initialize();
/*
		// 1 - check application credentials
		if ( ! $this->config["keys"]["key"] || ! $this->config["keys"]["secret"] ){
			throw new Exception( "Your application key and secret are required in order to connect to {$this->providerId}.", 4 );
		}
		
		// 2 - include OAuth lib and client
		require_once Hybrid_Auth::$config["path_libraries"] . "OAuth/OAuth.php";
		require_once Hybrid_Auth::$config["path_libraries"] . "OAuth/OAuth1Client.php";
		require_once dirname(__FILE__) . '/FitbitOAuth1Client.php';
		
		// 3.1 - setup access_token if any stored
		if( $this->token( "access_token" ) ){
			$this->api = new FitbitClient(
					$this->config["keys"]["key"], $this->config["keys"]["secret"],
					$this->token( "access_token" ), $this->token( "access_token_secret" )
			);
		}
		
		// 3.2 - setup request_token if any stored, in order to exchange with an access token
		elseif( $this->token( "request_token" ) ){
			$this->api = new FitbitClient(
					$this->config["keys"]["key"], $this->config["keys"]["secret"],
					$this->token( "request_token" ), $this->token( "request_token_secret" )
			);
		}
		
		// 3.3 - instanciate OAuth client with client credentials
		else{
			$this->api = new FitbitClient( $this->config["keys"]["key"], $this->config["keys"]["secret"] );
		}
		
		// Set curl proxy if exist
		if( isset( Hybrid_Auth::$config["proxy"] ) ){
			$this->api->curl_proxy = Hybrid_Auth::$config["proxy"];
		}
*/
		
		$this->api->api_base_url      = "https://api.fitbit.com/1/user/-/";
		$this->api->authorize_url     = "https://api.fitbit.com/oauth/authorize";
		$this->api->request_token_url = "https://api.fitbit.com/oauth/request_token";
		$this->api->access_token_url  = "https://api.fitbit.com/oauth/access_token";
	}

	
	/**
	* load the user profile from the IDp api client
	*/
	function getUserProfile()
	{
		$data = $this->api->get( 'http://api.fitbit.com/1/user/-/profile.json' );

		if ( ! is_object( $data ) ){
			throw new Exception( "User profile request failed! {$this->providerId} returned an invalid response.", 6 );
		}
		
		$this->user->profile  = $data->user;
		
		return $this->user->profile;
	}

}

