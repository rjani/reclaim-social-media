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
		
		// $this->api->api_base_url      = "http://api.fitbit.com/1/user/-/";
		$this->api->authorize_url     = "http://api.fitbit.com/oauth/authorize";
		$this->api->request_token_url = "http://api.fitbit.com/oauth/request_token";
		$this->api->access_token_url  = "http://api.fitbit.com/oauth/access_token";
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
