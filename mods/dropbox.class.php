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

class dropbox_reclaim_module extends reclaim_module {
    private static $timeout = 15;
    private static $count = 30; 
    private static $post_format = 'aside'; 

    public function __construct() {
        $this->shortname = 'dropbox';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);
        register_setting('reclaim-social-settings', 'dropbox_feed_url');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3><?php _e('Dropbox', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Dropbo-Feed URL', 'reclaim'); ?></th>
            <td>
                <input type="text" name="dropbox_feed_url" class="widefat" value="<?php echo get_option('dropbox_feed_url'); ?>" />
                <p class="description"><?php _e('Enter the Dropbox Feed URL. Get it from <a href="https://www.dropbox.com/events">here</a>', 'reclaim'); ?></p>
            </td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('dropbox_feed_url') ) {
            if ( ! class_exists( 'SimplePie' ) )
                require_once( ABSPATH . WPINC . '/class-feed.php' );

            $rss_source = get_option('dropbox_feed_url');
            
            /* Create the SimplePie object */
            $feed = new SimplePie();
            /* Set the URL of the feed you're retrieving */
            $feed->set_feed_url( $rss_source );
            /* Tell SimplePie to cache the feed using WordPress' cache class */
            $feed->set_cache_class( 'WP_Feed_Cache' );
            /* Tell SimplePie to use the WordPress class for retrieving feed files */
            $feed->set_file_class( 'WP_SimplePie_File' );
            /* Tell SimplePie how long to cache the feed data in the WordPress database */
            $feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', get_option('reclaim_update_interval'), $rss_source ) );
            /* Run any other functions or filters that WordPress normally runs on feeds */
            do_action_ref_array( 'wp_feed_options', array( &$feed, $rss_source ) );
            /* Initiate the SimplePie instance */
            $feed->init();
            /* Tell SimplePie to send the feed MIME headers */
            $feed->handle_content_type();

            if ( $feed->error() ) {
                parent::log(sprintf(__('no %s data', 'reclaim'), $this->shortname));
                parent::log($feed->error());
            }
            else {
                $data = self::map_data($feed);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function map_data($feed) {
        $data = array();
        $count = self::$count;

        foreach( $feed->get_items( 0, $count ) as $item ) {
        	
            $title = $item->get_title();
            // $link 	= $item->get_link();
            $link  = $item->get_permalink();
            $guid = $item->get_id();
            // remove DROPBOX-String
            $guid = str_replace('DROPBOX_', '', $guid);
            $published = $item->get_date();
            $description = $item->get_description();

            //  set post meta galore start
            $post_meta["_".$this->shortname."_link_id"] = $guid;
            $post_meta["_post_generator"] = $this->shortname;
            // in case someone uses WordPress Post Formats Admin UI
            // http://alexking.org/blog/2011/10/25/wordpress-post-formats-admin-ui
            // $post_meta["_format_link_url"]  = $link;
            //  set post meta galore end

            $exists = get_posts(array(
            		'post_type' => 'post', 
            		'meta_query' => array( array('key' => 'original_guid', 'value' => $guid, 'compare' => 'like') )
            ));
            if(!$exists) {
	            $data[] = array(
    	            'post_author' => get_option(self::shortName().'_author'),
        	        'post_category' => array(get_option(self::shortName().'_category')),
            	    'post_format' => self::$post_format,
                	'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($published))),
	                'post_content' => $description,
    	            'post_title' => $title,
        	        'post_type' => 'post',
            	    'post_status' => 'publish',
                	'ext_permalink' => '',
	                'ext_guid' => $guid,
    	            'post_meta' => $post_meta
        	    );
				parent::log(sprintf(__('%s posted new status: %s on %s', 'reclaim'), $this->shortname, $title, $data[count($data)-1]["post_date"]));
			}
        }
        
        return $data;
    }
}

