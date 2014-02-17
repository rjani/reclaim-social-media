<?php
/*  Copyright 2013-2014 diplix

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
User:       http://api.fitbit.com/1/user/-/profile.json 

{
  "user": {
    "avatar": "http://www.fitbit.com/images/profile/defaultProfile_100_male.gif",
    "avatar150": "http://www.fitbit.com/images/profile/defaultProfile_150_male.gif",
    "country": "DE",
    "dateOfBirth": "1968-01-16",
    "displayName": "Ralf",
    "distanceUnit": "METRIC",
    "encodedId": "26VXXB",
    "foodsLocale": "de_DE",
    "fullName": "Ralf",
    "gender": "MALE",
    "glucoseUnit": "METRIC",
    "height": 172,
    "heightUnit": "METRIC",
    "locale": "de_DE",
    "memberSince": "2013-08-14",
    "offsetFromUTCMillis": 3600000,
    "strideLengthRunning": 89.5,
    "strideLengthWalking": 71.4,
    "timezone": "Europe/Berlin",
    "waterUnit": "METRIC",
    "weight": 85,
    "weightUnit": "METRIC"
  }
}
Für Gerätedaten und Badges könnte man einen Shortcut erstellen 
Gerätedaten: http://api.fitbit.com/1/user/-/devices.json
Badges: 	 http://api.fitbit.com/1/user/-/badges.json
Übersicht:	 http://api.fitbit.com/1/user/-/activities.json



activities/calories
activities/steps
activities/distance
activities/floors
activities/elevation


Beginn: 2013-08-14

http://api.fitbit.com/1/user/-/activities/date/2013-08-14.json
http://api.fitbit.com/1/user/-/activities/steps/date/2013-08-14/1d/1min.json
	http://api.fitbit.com/1/user/-/activities/steps/date/2014-01-26/1d/15min.json
http://api.fitbit.com/1/user/-/activities/distance/date/2013-08-14/1d/1min.json
http://api.fitbit.com/1/user/-/activities/calories/date/2013-08-14/1d/1min.json

http://api.fitbit.com/1/user/-/sleep/date/2013-12-11.json

*/


class fitbit_reclaim_module extends reclaim_module {
	private static $api_base_url = 'http://api.fitbit.com/1/user/-/';
    
	// %s for datestring 2013-12-21 
    private static $interval_types = array(
    	'activities' => 'activities/date/%s.json',
    	'steps'      => 'activities/steps/date/%s/1d/1min.json',
    	'distance'   => 'activities/distance/date/%s/1d/1min.json',
    	'calories'   => 'activities/calories/date/%s/1d/1min.json',
    	'sleep'      => 'sleep/date/%s.json'
    );
    
    private static $general_types = array(
    	'profile'    => 'profile.json',
    	'device'     => 'devices.json',
    	'badges'     => 'badges.json',
    	'activities' => 'activities.json'
    );
    
    private static $timeout = 15;
    private static $post_format = 'status'; // or 'status', 'aside'
    
	
// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'fitbit';
        $this->settings  = get_option('fitbit_reclaim_settings');
        
        if (! is_array($this->settings) ) {
        	$this->settings = array(
        		'interval' => array(),
        		'general'  => array()
        	);
        }
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', $this->shortname.'_user_id');
		register_setting('reclaim-social-settings', $this->shortname.'_user_name');
        register_setting('reclaim-social-settings', $this->shortname.'_client_id');
        register_setting('reclaim-social-settings', $this->shortname.'_client_secret');
        register_setting('reclaim-social-settings', $this->shortname.'_access_token');
        
        register_setting('reclaim-social-settings', 'fitbit_reclaim_settings');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])==$this->shortname) && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option($this->shortname.'_user_id',   $user_profile->encodedId);
                update_option($this->shortname.'_user_name', $user_profile->fullName);
                update_option($this->shortname.'_access_token', $user_access_tokens->access_token);
                
                // set last_update
                $last_update = get_option( 'reclaim_'.$this->shortname.'_last_update' );
                if( isset($user_profile->memberSince) && $last_update < strtotime($user_profile->memberSince) ) {
                	update_option('reclaim_'.$this->shortname.'_last_update', strtotime($user_profile->memberSince) );
                }
            }
            if(session_id()) {
                // session_destroy ();
            }
        }
        
		// print_r($_SESSION);
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3 id=""><?php _e('FitBit', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('FitBit user', 'reclaim'); ?></th>
            <td><?php echo get_option('fitbit_user_name'); ?> (ID:<?php echo get_option('fitbit_user_id'); ?>)
            <input type="hidden" name="fitbit_user_id" value="<?php echo get_option('fitbit_user_id'); ?>" />
            <input type="hidden" name="fitbit_user_name" value="<?php echo get_option('fitbit_user_name'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Select FitBit Interval-Types', 'reclaim'); ?></th>
            <td><?php 
            foreach(array_keys(self::$interval_types) as $key) {
				$checked = (in_array($key, $this->settings['interval']) ? 'checked="checked"' : '');
				echo '
    			<label for="chkinterval_'.$key.'">'.ucfirst($key).'</label>
				<input id="chkinterval_'.$key.'" type="checkbox" name="fitbit_reclaim_settings[interval][]" value="'.$key.'" '.$checked.' /> &nbsp; &nbsp; '; 
			}            
            ?></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('FitBit client id', 'reclaim'); ?></th>
            <td><input type="text" class="widefat" name="fitbit_client_id" value="<?php echo get_option('fitbit_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('FitBit client secret', 'reclaim'); ?></th>
            <td><input type="text" class="widefat" name="fitbit_client_secret" value="<?php echo get_option('fitbit_client_secret'); ?>" />
            <input type="hidden" name="fitbit_access_token" value="<?php echo get_option('fitbit_access_token'); ?>" />
            <p class="description">Get your FitBit client and credentials <a href="https://dev.fitbit.com/apps/new">here</a>. 
<?php /*            Use <code><?php echo plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/') ?></code> as "Redirect URI"</p> */ ?>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if ( (get_option('fitbit_client_id')!="") && (get_option('fitbit_client_secret')!="") ) {
                $link_text = __('Authorize with FitBit', 'reclaim');
                
                if ( (get_option('fitbit_user_id')!="") && (get_option('fitbit_access_token')!="") ) {
                    echo sprintf(__('<p>FitBit is authorized as %s</p>', 'reclaim'), get_option('fitbit_user_name'));
                    $link_text = __('Authorize again', 'reclaim');
                }

                // send to helper script
                // put all configuration into session
                // todo
                $config = $this->construct_hybridauth_config();
                $callback =  urlencode(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=reclaim/reclaim.php&link=1&mod='.$this->shortname);

                $_SESSION[$this->shortname]['config'] = $config;

                echo '<a class="button button-secondary" href="'
                    .plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
                    .'?'
                    .'mod='.$this->shortname
                    .'&callbackUrl='.$callback
                    .'">'.$link_text.'</a>';
            }
            else {
                _e('enter FitBit app id and secret', 'reclaim');
            }

            ?>
            </td>
        </tr>

<?php
    }

    public function construct_hybridauth_config() {
        $config = array(
            // "base_url" the url that point to HybridAuth Endpoint (where the index.php and config.php are found)
            "base_url" => plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/'),
/*			"debug_mode" => true,
			"debug_file" => dirname(__FILE__) . '/../reclaim-log.txt',
*/
            "providers" => array (
                "Fitbit" => array(
                    "enabled" => true,
                    "keys"    => array ( "key" => get_option('fitbit_client_id'), "secret" => get_option('fitbit_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/Fitbit.php',
                        "class" => "Hybrid_Providers_Fitbit",
                    ),
                ),
            ),
        );
        return $config;
    }

    public function import() {
/*
        if (get_option('foursquare_user_id') && get_option('foursquare_access_token') ) {
			$last_update = get_option( 'reclaim_foursquare_last_update' );
			
			do {
				if( $last_update > self::$subtract ) {
					$last_update = $last_update - self::$subtract;
				}
				
				$url = sprintf(self::$apiurl, self::$limit, $last_update, get_option('foursquare_access_token'), date('Ymd') );
				$rawData = parent::import_via_curl($url, self::$timeout);
				$rawData = json_decode($rawData, true);
				
				if( $rawData && isset($rawData['response']['checkins']['items']) ){
					// count
					$results = count( $rawData['response']['checkins']['items'] );
				
					if( $results > 0 ) {
						// remember the timestamp (take the last one, sort=oldestfirst)
						$last_update = $rawData['response']['checkins']['items'][ $results-1 ]['createdAt'];
						
						$data = $this->map_data($rawData);
						parent::insert_posts($data);
						update_option('reclaim_foursquare_last_update', $lastUpdate);
					}
				} else {
					parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
				}
			} while( $results >= self::$limit );
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
*/
    }

    /**
     * Maps foursquare checkins data to wp-content data. Check https://developer.foursquare.com/docs/users/checkins for more info.
     * @param array $rawData
     * @return array
     */
    private function map_data(array $rawData) {
        $data = array();
        foreach($rawData['response']['checkins']['items'] as $checkin){

                $id = $checkin['id'];
                // there might be more than one image (or) none. 
                // lets take only the first one
                if (($checkin['photos']['count'] > 0) && ($checkin['photos']['items'][0]['visibility'] == "public") ) {
                    $image_url = $checkin['photos']['items'][0]['prefix']
                                .$checkin['photos']['items'][0]['width']
                                . 'x' 
                                .$checkin['photos']['items'][0]['height']
                                .$checkin['photos']['items'][0]['suffix'];
                } else {
                    $image_url = '';
                }
                $tags = '';
                $link = 'https://foursquare.com/user/'.get_option('foursquare_user_id').'/checkin/'.$id;
                $content = '<p>'.sprintf(__('Checked in to <a href="%s">%s</a>', 'reclaim'), $link, $checkin['venue']['name']).'</p>';
                // added htmlentities() just to be sure
                if (isset($checkin['shout'])) { $content .= '<blockquote>'.htmlentities($checkin['shout'],ENT_NOQUOTES, "UTF-8").'</blockquote>'; }
                //if (isset($checkin['shout'])) { $content .= '<blockquote>'.$checkin['shout'].'</blockquote>'; }
                
                $title = sprintf(__('Checked in to %s', 'reclaim'), $checkin['venue']['name']);

                //$post_meta = $this->construct_post_meta($day);
                $lat = $checkin['venue']['location']['lat'];
                $lon = $checkin['venue']['location']['lng'];
                $post_meta["geo_latitude"] = $lat;
                $post_meta["geo_longitude"] = $lon;
                $post_meta["venueCountry"] = $checkin['venue']['location']['country'];
                $post_meta["venueName"] = $checkin['venue']['name'];
                $post_meta["foursquareVenueId"] = $checkin['venue']['id'];

                // verwende als id einen Teil der url http://api.fitbit.com/1/user/-/activities/date/2013-08-14.json
                $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
                $post_meta["_post_generator"] = $this->shortname;

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => self::$post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $checkin["createdAt"])),
                    'post_content' => $content,
                    'post_title' => $title,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'tags_input' => $tags,
                    'ext_permalink' => $link,
                    'ext_image' => $image_url,
                    'ext_guid' => $id,
                    'post_meta' => $post_meta
                );
                }
        return $data;
    }
    
    
    public function count_items() {
		return false;
    }
    

    private function construct_content(array $checkin) {}

    
    /**
     * Returns meta data for every activity in a foursquare summary data day.
     * @param array $day Data return from moves api. Known possible keys so far:
     *  activity, distance, duration, steps (not if activity == cyc), calories
     * @return array
     */
    private function construct_post_meta(array $checkin) {}
    
    
}