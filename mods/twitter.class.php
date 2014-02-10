<?php
/*  Copyright 2013-2014 diplix
                   2014 Christian Muehlhaeuser <muesli@gmail.com>

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

class twitter_reclaim_module extends reclaim_module {
    private static $apiurl = "https://api.twitter.com/1.1/statuses/user_timeline.json";
    private static $fav_apiurl = "https://api.twitter.com/1.1/favorites/list.json";
    private static $apiurl_profile = "https://api.twitter.com/1.1/users/show.json";

    private static $count = 50;
    private static $max_import_loops = 1;
    private static $lang = 'en';

//    const TWITTER_TWEET_TPL = '<blockquote class="twitter-tweet imported"><p>%s</p>%s&mdash; %s (<a href="https://twitter.com/%s/">@%s</a>) <a href="http://twitter.com/%s/status/%s">%s</a></blockquote><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';

    public function __construct() {
        $this->shortname = 'twitter';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'twitter_username');
        register_setting('reclaim-social-settings', 'twitter_consumer_key');
        register_setting('reclaim-social-settings', 'twitter_consumer_secret');
        register_setting('reclaim-social-settings', 'twitter_user_token');
        register_setting('reclaim-social-settings', 'twitter_user_secret');
        register_setting('reclaim-social-settings', 'twitter_favs_category');
        register_setting('reclaim-social-settings', 'twitter_import_favs');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3><?php _e('Twitter', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Get Favs?', 'reclaim'); ?></th>
            <td><input type="checkbox" name="twitter_import_favs" value="1" <?php checked(get_option('twitter_import_favs')); ?> />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category for Favs', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'twitter_favs_category', 'hide_empty' => 0, 'selected' => get_option('twitter_favs_category'))); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter username', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_username" value="<?php echo get_option('twitter_username'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter consumer key', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_consumer_key" value="<?php echo get_option('twitter_consumer_key'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter consumer secret', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_consumer_secret" value="<?php echo get_option('twitter_consumer_secret'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter user token', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_user_token" value="<?php echo get_option('twitter_user_token'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter user secret', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_user_secret" value="<?php echo get_option('twitter_user_secret'); ?>" /></td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('twitter_consumer_key') && get_option('twitter_consumer_secret') && get_option('twitter_user_token') && get_option('twitter_user_secret')) {

            parent::log(sprintf(__('starting %s import', 'reclaim'), $this->shortname));
            self::import_tweets($forceResync, "tweets");
            if (get_option('twitter_import_favs')) {
                parent::log(sprintf(__('starting %s-favs import', 'reclaim'), $this->shortname));
                self::import_tweets($forceResync, "favs");
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function import_tweets( $forceResync, $type = "tweets" ) {

            $lastseenid = get_option('reclaim_'.$this->shortname.'_'.$type.'_last_seen_id');
            $reqOptions = array(
                'lang' => substr(get_bloginfo('language'), 0, 2),
                'count' => self::$count,
                'screen_name' => get_option('twitter_username'),
                'include_rts' => "false",
                'exclude_replies' => "true",
                'include_entities' => "true"
            );
            if (strlen($lastseenid) > 0 && !$forceResync) {
                $reqOptions['since_id'] = $lastseenid;
            }
            $i = 0;
            do {
                $tmhOAuth = new tmhOAuth(array(
                    'consumer_key' => get_option('twitter_consumer_key'),
                    'consumer_secret' => get_option('twitter_consumer_secret'),
                    'user_token' => get_option('twitter_user_token'),
                    'user_secret' => get_option('twitter_user_secret'),
                ));

                if (isset($lastid)) {
                    $reqOptions['max_id'] = $lastid;
                }
                if ($type == "tweets") {
                    $tmhOAuth->request('GET', self::$apiurl, $reqOptions, true);
                }
                else {
                    $tmhOAuth->request('GET', self::$fav_apiurl, $reqOptions, true);
                }

                if ($tmhOAuth->response['code'] == 200) {
                    $data = self::map_data(json_decode($tmhOAuth->response['response'], true), $type);
                    parent::insert_posts($data);

                    $reqOk = count($data) > 0 && $data[count($data)-1]["ext_guid"] != $lastid;
                    if ( self::$max_import_loops > 0 && $i >= self::$max_import_loops )
                        $reqOk = false;

                    if (!isset($lastid) && $reqOk) {
                        // store the last-seen-id, which is the first message of the first request
                        $lastseenid = $data[0]["ext_guid"];
                    }
                    $lastid = $data[count($data)-1]["ext_guid"];
                    parent::log(sprintf(__('Retrieved set of twitter messages (%s): %d, last seen id: %s, last id in batch: %s, req-ok: %d', 'reclaim'), $type, count($data), $lastseenid, $lastid, $reqOk));
                }
                else {
                    $reqOk = false;
                    parent::log(sprintf(__('GET failed with: %s', 'reclaim'), $tmhOAuth->response['code']));
                }
                $i++;
            } while ($reqOk);

            update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            update_option('reclaim_'.$this->shortname.'_'.$type.'_last_seen_id', $lastseenid);
            }

    private function map_data($rawData, $type = "tweets") {
        $data = array();
        $tags = array();
        foreach($rawData as $entry){
            $content = self::construct_content($entry);
            $tags = self::get_hashtags($entry);

            if ($type == "favs") {
                $post_format = 'status';
            } elseif ($entry['entities']['media'][0]['type']=="photo") {
                $post_format = 'image';
            } else {
                $post_format = 'status';
            }
            $link = 'http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"];
            unset($post_meta["geo_latitude"]);
            unset($post_meta["geo_longitude"]);

            if ($type == "tweets") {
                // save geo coordinates?
                // "location":{"latitude":52.546969779,"name":"Simit Evi - Caf\u00e9 \u0026 Simit House","longitude":13.357669574,"id":17207108},
                // http://codex.wordpress.org/Geodata
                $lat = $entry['geo']['coordinates'][0];
                $lon = $entry['geo']['coordinates'][1];

                $post_meta["geo_latitude"] = $lat;
                $post_meta["geo_longitude"] = $lon;
                $post_meta['favorite_count'] = $entry['favorite_count'];
                $title = strip_tags($content['original']);
                $post_content = $content['embed_code'];
                $image = $content['image'];
                $category = array(get_option($this->shortname.'_category'));
            } else {
                $title = sprintf(__('I favorited a tweet by @%s', 'reclaim'), $entry['user']['screen_name']); 
                $source = sprintf(__('I favorited a tweet by <a href="%s">@%s</a>', 'reclaim'), $link, $entry['user']['screen_name']); 
                $post_content = '<p>'. $source . ':</p> [embed_code]';
                $image = "";
                $category = array(get_option($this->shortname.'_favs_category'));
            }

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            // setting for social plugin (https://github.com/crowdfavorite/wp-social/)
            // to be able to retrieve twitter replies (if wp-social is installed)
            $tweet_id = $entry["id_str"];
            $user_id = $entry['user']['id_str'];  //screen_name / id_str?
            $broadcasted_ids = array();
            $broadcasted_ids[$this->shortname][$user_id][$tweet_id] = array('message' => '','urls' => '');
            $post_meta["_social_broadcasted_ids"] = $broadcasted_ids;

            // http://codex.wordpress.org/Function_Reference/wp_insert_post
            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => $category,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created_at"]))),
                'post_format' => $post_format,
                'post_content'   => $post_content,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => $link,
                'ext_embed_code' => $content['embed_code_twitter'],
                'ext_image' => $image,
                'ext_guid' => $entry["id_str"],
                'post_meta' => $post_meta
            );
        }
        return $data;
    }

    private function get_hashtags($entry) {
        $tags = array();
        if (count($entry['entities']['hashtags'])) {
            foreach ($entry['entities']['hashtags'] as $hashtag) {
                $tags[] = $hashtag['text'];
            }
        }
        return $tags;
    }

    public function count_items() {
        $reqOptions = array(
            'screen_name' => get_option('twitter_username'),
            'include_entities' => "false"
        );

        $tmhOAuth = new tmhOAuth(array(
            'consumer_key' => get_option('twitter_consumer_key'),
            'consumer_secret' => get_option('twitter_consumer_secret'),
            'user_token' => get_option('twitter_user_token'),
            'user_secret' => get_option('twitter_user_secret'),
        ));

        $tmhOAuth->request('GET', self::$apiurl_profile, $reqOptions, true);

        if ($tmhOAuth->response['code'] == 200) {
            $data = json_decode($tmhOAuth->response['response'], true);
            return $data['statuses_count'];
        }
        else {
    		return false;
        }
    }

    private function construct_content($entry) {
        // lets make this a setting?
        $unshorten_urls = true;

        $post_content = $entry['text'];
        //$post_content = html_entity_decode($post_content); // ohne trim?
        $post_content = $post_content; // ohne trim?
        //replace t.co links
        if (count($entry['entities']['urls'])) {
            foreach ($entry['entities']['urls'] as $url) {
                if ($unshorten_urls) {
                    $resolver = new URLResolver();
                    $expanded_url = $resolver->resolveURL($url['expanded_url'])->getURL();
                    $display_url  = parse_url($expanded_url); // minus protocol, no longer that 30 chars
                    $display_url  = $display_url['host'].$display_url['path'];
                    if (strlen($display_url) > 30) { $display_url = substr($display_url, 0, 30)."&hellip;"; }
                }
                else {
                    $expanded_url = $url['expanded_url'];
                    $display_url  = $url['display_url'];
                }
                $post_content = str_replace( $url['url'], '<a href="'.$expanded_url.'">'.$display_url.'</a>', $post_content);
            }
        }
        // any embeded media/images?
        $image_url = "";
        $image_html = "";
        if (isset($entry['entities']['media']) && $entry['entities']['media']) {
            foreach ($entry['entities']['media'] as $media) {
                $post_content = str_replace( $media['url'], '<a href="'.$media['expanded_url'].'">'.$media['display_url'].'</a>', $post_content);
                if ($media['type']=="photo") {
                    $image_url = $media['media_url'];
                    $image_html = '<div class="twitter-image">'
//                    .'<a href="'.$media['expanded_url'].'">'
//                    .'<img src="'.$image_url.'" alt="">'
                    .'[gallery size="large" columns="1" link="file"]'
//                    .'</a>'
                    .'</div>';
                }
            }
        }
        $post_content = preg_replace( "/\s((http|ftp)+(s)?:\/\/[^<>\s]+)/i", " <a href=\"\\0\" target=\"_blank\">\\0</a>",$post_content);
        $post_content = preg_replace('/[@]+([A-Za-z0-9-_]+)/', '<a href="http://twitter.com/\\1" target="_blank">\\0</a>', $post_content );

        // Autolink hashtags (wordpress funktion)
        $post_content = preg_replace('/(^|[^0-9A-Z&\/]+)(#|\xef\xbc\x83)([0-9A-Z_]*[A-Z_]+[a-z0-9_\xc0-\xd6\xd8-\xf6\xf8\xff]*)/iu', '${1}<a href="http://twitter.com/search?q=%23${3}" title="#${3}">${2}${3}</a>', $post_content);

        // original twitter embed code (more or less), will be shown as native embed
        $embed_code_twitter = '<blockquote class="twitter-tweet imported"><p>'.$post_content.'</p>'.$image_html.'&mdash; '.$entry['user']['name'].' (<a href="https://twitter.com/'.$entry['user']['screen_name'].'/">@'.$entry['user']['screen_name'].'</a>) <a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.date('d.m.Y H:i', strtotime($entry["created_at"])).'</a></blockquote><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';
        // if these are one's *own* tweets, there is no point to mark the username or blockqoue. also the date and time is supeficial.
        //$embedcode_reclaim = '<blockquote class="twitter-tweet imported"><p>'.$post_content.'</p><div class="twimage">'.$image_html.'</div><span style="display: none;">&mdash; '.$entry['user']['name'].' (<a href="https://twitter.com/'.$entry['user']['screen_name'].'/">@'.$entry['user']['screen_name'].'</a>) <a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.date('d.m.Y H:i', strtotime($entry["created_at"])).'</a></span><p class="twviewpost-twitter">(<a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.__('View on Twitter', 'reclaim').'</a>)</p></blockquote>';
        $embed_code_reclaim = '<div class="twitter-tweet imported">'.$post_content.'<div class="twimage">'.$image_html.'</div><p class="twviewpost-twitter">(<a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.__('View on Twitter', 'reclaim').'</a>)</p></div>';

/*
        setlocale (LC_ALL, get_bloginfo ( 'language' ) );
        $embedcode = sprintf(
                self::TWITTER_TWEET_TPL,
                $post_content,
                $image_html,
                $entry['user']['name'],
                $entry['user']['screen_name'],
                $entry['user']['screen_name'],
                get_option('twitter_username'),
                $entry["id_str"],
                date('d.m.Y H:i', strtotime($entry["created_at"]))
                date(get_option('date_format'), strtotime($entry["created_at"]))
        );
*/
        $content = array(
            'original' =>  $post_content,
            'embed_code_twitter' => $embed_code_twitter,
            'embed_code' => $embed_code_reclaim,
            'image' => $image_url
        );

        return $content;
    }
}
