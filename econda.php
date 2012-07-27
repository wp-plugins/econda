<?php
/*
Plugin Name: econda
Plugin URI: http://www.econda.de/
Description: This plugin enables econda analytics on your site. econda is one of the leading specialists for intelligent web analysis. (D) Dieses Plugin erm&ouml;glicht econda Analysen auf Ihrem Online-Auftritt. econda ist einer der f&uuml;hrenden Spezialisten f&uuml;r intelligente Web-Analysen. (<a href="http://www.econda.de/">Visit econda</a>)
Version: 1.1.1
Author: Edgar Gaiser
Author URI: http://www.econda.de/
*/
/*
Copyright (c) 2004 - 2012 ECONDA GmbH Karlsruhe
All rights reserved.

ECONDA GmbH
Eisenlohrstr. 43
76135 Karlsruhe
Tel.: 0721/663035-0
Fax.: 0721 663035-10
info@econda.de
www.econda.de

author: Edgar Gaiser <gaiser@econda.de>

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of the ECONDA GmbH nor the names of its contributors may
      be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

add_action('activate_econda/econda.php', array('econda','econda_init'));
add_action('admin_menu', array('econda','econda_setup'));
add_action('wp_footer', array('econda','econda_out'));
add_action('comment_post', array('econda', 'trigger_comment'), 10, 0);
add_filter("plugin_action_links", array('econda','econda_settings_link'));

if(isset($_POST['info_update'])) {
    if(trim($_POST['siteid']) != "") {
        $psiteid = trim($_POST['siteid']);
    }
    else {
        $ecOptions = get_option('econda_options');
        $psiteid = $ecOptions['siteid'];
    }
    $pactivate = $_POST['activate'];
    $pdebug = $_POST['debugmode'];  
    $poptional1 = $_POST['optional1'];
    $poptional2 = $_POST['optional2'];
    $poptional3 = $_POST['optional3'];
    econda::set_updates($psiteid, $pactivate, $pdebug, $poptional1, $poptional2, $poptional3);
}

class econda {

    function econda_out() {
        global $wp_query, $post;
        
        $ecOptions = get_option('econda_options');
        if($ecOptions['activ'] == '0') {
           return;
        } 
        $newComment = get_option('econda_newcomment');
        include(WP_PLUGIN_DIR . '/econda/emos.php');
        
        $pathToEmos = WP_PLUGIN_URL . '/econda/';
        $emos = new EMOS($pathToEmos,'emos2.js'); 
        $emos->addCdata();
        $emos->trackMode(2); 
        if($ecOptions['debug'] == '1') {
          $emos->debugMode(1);
        }
        
        $ecout = "";
        $ecout .= "\n\n<!-- econda wp110 begin -->\n";
        
        //content
        $content = econda::get_content();
        $emos->addContent($content);

        //siteid
        $siteId = $ecOptions['siteid'];
        $emos->addSiteID($siteId);

        //pageid
        $pageid = econda::get_pageid();
        $emos->addPageID($pageid);

        //search
        if(is_search()) {
            $phrase = $wp_query->query_vars['s'];
            $hits = $wp_query->found_posts;
            $emos->addSearch($phrase, $hits);
        }
         
        //get new comment as target
        if($newComment) {
            if(substr($ecOptions['optional'], 2, 1) == '1') {
                $emos->addTarget('Wordpress Comments', the_title('','', FALSE), 1, 'a');
                update_option('econda_newcomment', false);
            }
        }

        //get article tags and categories as marker
        $getTags = econda::get_tags();
        if(is_single() && $getTags != false && !$newComment && substr($ecOptions['optional'], 0, 1) == '1') {
            for($i = 0; $i < sizeof($getTags); $i++) {
                $emos->addMarker('Wordpress Tags/'.$getTags[$i]);
            }
        }
        
        $getCategories = econda::get_categories();
        if(is_single() && $getCategories != false && !$newComment && substr($ecOptions['optional'], 1, 1) == '1') {
            for($i = 0; $i < sizeof($getCategories); $i++) {
                $emos->addMarker('Wordpress Categories/'.$getCategories[$i]);
            }
        }
        
        $ecout .= $emos->toString();
        $ecout .= "<!-- econda end -->\n\n";
        echo $ecout;
    }

    function trigger_comment() {
        update_option('econda_newcomment', true);
    }

    function econda_settings() {
        econda::localize();
        ?>
        <div class=wrap>
        <form method="post">
            <h2><?php _e('econda Settings', 'econda') ?></h2><br />
            <fieldset name="econda Settings">
                <ul style="list-style-type: none;">
                    <li>
                        <label for="siteid"><?php _e('Site ID', 'econda') ?></label>
                        <input type="text" name="siteid" value="<?php echo econda::get_siteid(); ?>" id="siteid" /> <?php if(isset($_POST['siteid']) && trim($_POST['siteid']) == "") {echo "Don't leave this empty!";} ?><br />
                        <span class="description"><?php _e('If you have a econda monitor with multi-site feature, you can set an individual value for this blog here.', 'econda') ?></span><br /><br />
                    </li>
                    <li>
                        <label for="optional"><?php _e('Optional Tracking Values', 'econda') ?></label><br />
                        <input type="checkbox" name="optional1" <?php if(econda::get_optional(1)) echo 'checked'; ?> id="optional1"></input>
                        <span class="description"><?php _e('Use markers to measure post tags.', 'econda') ?></span><br />
                    
                        <input type="checkbox" name="optional2" <?php if(econda::get_optional(2)) echo 'checked'; ?> id="optional2"></input>
                        <span class="description"><?php _e('Use markers to measure post categories.', 'econda') ?></span><br />                   
                    
                        <input type="checkbox" name="optional3" <?php if(econda::get_optional(3)) echo 'checked'; ?> id="optional3"></input>
                        <span class="description"><?php _e('Use targets to measure new comments.', 'econda') ?></span><br /><br />                  
                    </li>                      
                    <li>
                        <label for="debugmode"><?php _e('Debug mode', 'econda') ?></label>
                        <input type="checkbox" name="debugmode" <?php if(econda::get_debug()) echo 'checked'; ?> id="debugmode"></input><br />
                        <span class="description"><?php _e('Do not use this option if your site is in Live mode! This option will display the generated code directly on your page.', 'econda') ?></span><br /><br />
                    </li>
                    <li>
                        <label for="activate"><?php _e('Activate econda', 'econda') ?></label>
                        <input type="checkbox" name="activate" <?php if(econda::get_activation()) echo 'checked'; ?> id="activate"></input><br /><br>
                        <span class="description"><strong><?php _e('Thus econda can record your data, copy the file "emos2.js", which you received within the activation e-mail to the Wordpress directory "wp-content/plugins/econda".', 'econda') ?></strong></span><br />
                    </li>
                </ul>
            </fieldset>
            <div class="submit">
                <input type="submit" name="info_update" value="<?php _e('Save Changes', 'econda') ?> &raquo;" />
            </div>
        </form>
       </div>
       <div class=wrap>
       <?php 
       _e('econda is one of the leading specialists for intelligent web analysis. <br />For your Wordpress blog you can choose between the econda Site Monitor or econda Click Monitor.<br /><br />For further informations visit <a href="http://www.econda.de" target="_blank">econda</a>.<br />Try the econda Site Monitor now free of charge for 14 days! <a href="http://www.econda.de/produkte/site-monitor/testen.html" target="_blank">Test now!</a>', 'econda');
       ?>
       </div>
       <?php
   }

    function econda_setup() {
        if(function_exists('add_options_page') ) {
            add_options_page('econda','econda',10,basename(__FILE__),array('econda','econda_settings'));
        }
    }

    function econda_init() {
        load_plugin_textdomain('econda', false, 'econda/languages');
        $ecOptions = array(
            'siteid' => '1',
            'activ' => '0',
            'debug' => '0',
            'optional' => '000'
        );
        add_option('econda_options', $ecOptions);
        add_option('econda_newcomment', false);
    }

    function econda_settings_link($links) {
        $setLink = '<a href="options-general.php?page=econda.php\">'.__('Settings').'</a>';
        array_push($links, $setLink);  
        return $links;
    }

    function localize() {
        if (function_exists('load_plugin_textdomain')) {
            if (!defined('WP_PLUGIN_DIR')) {
                load_plugin_textdomain('econda', str_replace( ABSPATH, '', dirname(__FILE__)).'/languages');
            } else {
                load_plugin_textdomain('econda', false, dirname(plugin_basename(__FILE__)).'/languages');
            }
        } 
    }

    function get_content() {
        global $wp_query;

        $trail = "home/";
        if (!is_home()){
            if (is_category()) {
                $catT = single_cat_title("", false);
                $cat = get_cat_ID($catT);
                $trail .= strip_tags(get_category_parents($cat, TRUE, "/"));
            }
            else if(is_tag()) {
                $tagT = single_tag_title("/", false);
                $trail .= "archives/tag/".$tagT;
            }
            else if(is_archive() && !is_category()) {
                $trail .= "archives/".get_the_time('Y/m/');
            }
            else if(is_search()) {
                $trail .= "search/"; 
            }
            else if(is_404()) {
                $trail .= "404/";
            }
            else if(is_single()) {
                $catA = get_the_category();
                $catId = get_cat_ID($catA[0]->cat_name);
                $trail .= strip_tags(get_category_parents($catId, TRUE, "/"));
                $trail .= the_title('','', FALSE)."/";
            }
            else if(is_page()) {
                $post = $wp_query->get_queried_object();
                if($post->post_parent == 0){
                    $trail .= the_title('','', FALSE)."/";
                } 
                else {
                    $title = the_title('','', FALSE);
                    $ancA = array_reverse(get_post_ancestors($post->ID));
                    array_push($ancA, $post->ID);
                    foreach ($ancA as $ancS){
                        if($ancS != end($ancA)){
                            $trail .= strip_tags(apply_filters('single_post_title', get_the_title($ancS)))."/";
                        } 
                        else {
                            $trail .= strip_tags(apply_filters('single_post_title', get_the_title($ancS)))."/";
                        }
                    }
              }
          }
      }
      if(substr($trail,-1) == "/") {
        $trail = substr($trail,0,-1);
      }
      return $trail;
    }

    function get_pageid() {
        global $wp_query;

        $pageId = "home_";
        if(isset($wp_query->query['p']) && trim($wp_query->query['p']) != "")  {
            $pageId .= $wp_query->query['p'];
        }
        else {
            if(trim($wp_query->query_vars['year'])!= "" && $wp_query->query_vars['year']!= "0") {
                $pageId .= "archive_".$wp_query->query_vars['year']."_";
                if(trim($wp_query->query_vars['monthnum'])!= "" && $wp_query->query_vars['monthnum']!= "0") {
                    $pageId .= $wp_query->query_vars['monthnum']."_"; 
                }
                if(trim($wp_query->query_vars['day'])!= "" && $wp_query->query_vars['day']!= "0") {
                    $pageId .= $wp_query->query_vars['day']."_"; 
                }
            } 
            else if(trim($wp_query->query_vars['category_name']) != "") {
                $pageId .= "category_".$wp_query->query_vars['category_name']."_";
            }
            else if(trim($wp_query->query_vars['tag']) != "") {
                $pageId .= "tag_".$wp_query->query_vars['tag']."_";
            }
            else if(is_search()) {
                 $pageId .= "search";
            }
            else {
                $pageId .= $wp_query->query_vars['pagename'];
            }
        }
        return md5($pageId);
    }

    function get_siteid() {
        if(function_exists('get_option')) {
            $ecOptions = get_option('econda_options');
            $ret = $ecOptions['siteid'];
        }
        return $ret;
    }

    function get_tags() {
        global $wp_query, $post;

        $retValue = false;
        if(is_single()) {
            $tags = wp_get_post_tags($post->ID);
            for($i=0; $i < sizeof($tags); $i++) {
                if(trim($tags[$i]->name) != "") {
                    $retValue[] = $tags[$i]->name;
                }
            }
        }
        return $retValue;
    }

    function get_categories() {
        global $wp_query, $post;

        $retValue = false;
        $postId = $post->ID;
        $postCategories = wp_get_post_categories($postId);
        foreach($postCategories as $cat){
            $category = get_category($cat);
            $retValue[] = $category->name;
        } 
        return $retValue;
    }

    function get_activation() {
        if(function_exists('get_option')) {
            $ecOptions = get_option('econda_options');
            $ret = $ecOptions['activ'];
            if($ret == '1') {
               return true; 
            }
            return false;
        }
        return false;
    } 

    function get_debug() {
        if(function_exists('get_option')) {
            $ecOptions = get_option('econda_options');
            $ret = $ecOptions['debug'];
            if($ret == '1') {
               return true; 
            }
            return false;
        }
        return false;
    } 

    function get_optional($option) {
        if(function_exists('get_option')) {
            $ecOptions = get_option('econda_options');
            $ret = $ecOptions['optional'];
            if(substr($ret, 0, 1) == '1' && $option == 1) {
               return true; 
            }
            if(substr($ret, 1, 1) == '1' && $option == 2) {
               return true; 
            }
            if(substr($ret, 2, 1) == '1' && $option == 3) {
               return true; 
            }
            return false;
        }
        return false;
    }

    function set_updates($psiteid, $pactivate, $pdebug, $poptional1, $poptional2, $poptional3) {
        $sactivate = '0';
        $sdebug = '0';
        $soptional = '000';
        if($pactivate == "on") {
           $sactivate = '1'; 
        }
        if($pdebug == "on") {
           $sdebug = '1'; 
        }
        if($poptional1 == "on") {
           $soptional = '1'.substr($soptional, 1, 1).substr($soptional, 2, 1); 
        }
        if($poptional2 == "on") {
           $soptional = substr($soptional, 0, 1).'1'.substr($soptional, 2, 1); 
        }
        if($poptional3 == "on") {
           $soptional = substr($soptional, 0, 1).substr($soptional, 1, 1).'1'; 
        }    
        if( function_exists('update_option') ) {
            $ecOptions = array(
               'siteid' => $psiteid,
               'activ' => $sactivate,
               'debug' => $sdebug,
               'optional' => $soptional
            );
            update_option( 'econda_options', $ecOptions );
        }
    }
}
?>
