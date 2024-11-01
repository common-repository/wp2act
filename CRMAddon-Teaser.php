<?php
/*
Plugin Name: WP2Act
Author: CrmAddon
Plugin URI: https://wordpress.org/plugins/wp2act
Text Domain: wordpress.org/plugins/wp2act
Domain Path: /languages
Description: a tool that connects Wordpress to ACT! Web-API
Version: 1.3
Author URI: http://www.crmaddon.com
License:GPLv2 or later (license.txt)
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/



// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

class WP2Act
{
    private $icon_url = '/images/icon.png';
    private $act_group="crmaddon_act";
    private $pageShow_setting_group="crmaddon_pageShow_setting";
    private $link_title_setting="link_title_setting";

    public function __construct()
    {

        add_action('admin_menu',array($this,'wp2act_setting_menu'));
        add_action('admin_init',array($this,'wp2act_act_setting_parameters'));
        add_action('admin_init',array($this,'wp2act_showPage_setting_parameters'));
        add_action('admin_init',array($this,'wp2act_link_setting_parameters'));

        wp_enqueue_script('bootstrap-js',plugins_url('js/libs/bootstrap.min.js',__FILE__),array('jquery'));

        wp_enqueue_script('crm_manager', plugins_url('js/content-info.js', __FILE__), array('jquery'));
        wp_localize_script('crm_manager', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));

//        wp_enqueue_style('contact_css',plugins_url('css/common.css',__FILE__));
//        wp_enqueue_style('bootstrap-css',plugins_url('css/bootstrap.min.css',__FILE__));

        add_action('init',array($this,'wp2act_language_init'));
//          add_action('plugins_loaded',array($this,'my_plugin_load_plugin_textdomain'));
//        add_action('wp_footer',array($this,'wp2act_contact_tip_show'));
        add_action( 'wp', array($this,'wp2act_page_init') );
        add_action('init',array($this,'wp2act_act_bearer_init'));
        add_action('phpmailer_init','wp2act_main_init');



        //filter module
        add_filter('plugin_action_links', array($this,'wp2act_plugin_action_links'), 10, 2 );
        add_filter( 'dynamic_sidebar_has_widgets', array($this,'wpb_force_sidebar') );

        //ajax request
        add_action('wp_ajax_ajax_send_contact_data',array($this,'wp2act_ajax_send_contact_data'));
        add_action('wp_ajax_check_act_configuration',array($this,'wp2act_check_act_configuration'));
        add_action('wp_ajax_nopriv_ajax_send_contact_data',array($this,'wp2act_ajax_send_contact_data'));
        add_action('wp_ajax_nopriv_check_act_configuration',array($this,'wp2act_check_act_configuration'));

        //note for the admin for the wrong user and pwd of the crmaddon' wep api account
        add_action('admin_notices', array($this,'showAdminMessages'));

    }


    function showMessage($message, $errormsg = false)
    {
        $wp2act_setting_url = admin_url().'admin.php?page=wp2act_setting';
        if ($errormsg) {
            echo '<div id="message" class="error">';
                    }
        else {
            echo '<div id="message" class="updated fade">';
             }
        echo "<strong style='color: red'><a href='$wp2act_setting_url' style='color: red'>$message</a></strong></div>";
    }

    function showAdminMessages()
    {
        $act_info = get_option("crmaddon_actSetting_option");
        $link_status = isset($act_info['link_status']) ? $act_info['link_status'] : '';
        if(empty($link_status)){
            $this->showMessage("No account information of WP2ACT wep api has been filled in,please fill it!", true);
        }
        if(!empty($link_status) && $link_status != 'successful'){
            $this->showMessage("You input the wrong account information of the WP2ACT wep api or other causes for can not login the api service,please check it and save it again!", true);
        }
    }


    /**
     * modify the siderbar show in the pages
     * @param $info
     */
    function wpb_force_sidebar($info)
    {
        $sendContactUrl = get_home_url().'/sendContact';
        $showPageTags = get_option("crmaddon_pageShow_option");
        $linkInfo = get_option("crmaddon_link_option");
        $linkInfo['title'] = (isset($linkInfo['title']) && !empty($linkInfo['title'])) ? $linkInfo['title'] : 'Send Contact';
        $linkInfo['content'] = (isset($linkInfo['content']) && !empty($linkInfo['content'])) ? $linkInfo['content'] : 'Contact';
        if($this->wp2act_checkContactTipShow($showPageTags['contact_pages'])){
            echo '<section id="actcontact-2" class="widget widget_actcontact"><h2 class="widget-title">'.$linkInfo['title'].'</h2>
                    <ul>
                        <li><a href="'.$sendContactUrl.'">'.$linkInfo['content'].'</a></li>			
                    </ul>
                </section>';
        }

    }

    function wp2act_main_init(PHPMailer $phpmailer)
    {
        $phpmailer->isSMTP();
        $phpmailer->SMTPAuth = true; // Force it to use Username and Password to authenticate
        $phpmailer->Port = 25;
    }

    /**
     * judge the url and jump to the special page
     */
    function wp2act_page_init()
    {
        global $wpdb;
        require_wp_db();

        $currentUrl = $this->wp2act_getCurPageURL();
        $refererUrl = !empty(wp_get_referer()) ? wp_get_referer() : $currentUrl;
        $endCode = '/';
        if(substr_compare($refererUrl,$endCode,-strlen($endCode)) === 0){
            $refererUrl = substr($refererUrl,0,-strlen($endCode));
        }

        if(strpos($currentUrl,'sendContact')){
            $act_info = get_option("crmaddon_actSetting_option");
            $url = $act_info['url'];
            $bearer = $_SESSION['act_bearer'];
            $header = array(
                "Authorization"=> "Bearer ".$bearer,
                "Accept"=> "application/json",
            );
            $args = array(
                'timeout'     => 100,
                'headers'     => $header,
            );

            $countries = array();
            $items = array();

            $tableName = $wpdb->prefix.'options';


            $country_mark = 1;
            $db_countries = $wpdb->get_row($wpdb->prepare( "SELECT option_id,option_name,option_value FROM $tableName WHERE option_name = %s LIMIT 1", 'wp2act_countries_list' ));

            if(!empty($db_countries) && count($db_countries)>0){
                $country_value = unserialize($db_countries->{'option_value'});
                if(time()-$country_value['setTime']<=86400){
                    $countries = $country_value['countries'];
                }else{
                    $country_mark = 2;
                }
            }else{
                $country_mark = 2;
            }

            if($country_mark == 2){
                if(!empty($url) && !empty($bearer)){
                    $countryUrl = $url.'/api/metadata/picklists?recordType=gruppe';
                    $countryGroup = $this->getActInfo($countryUrl,$args);
                    $countryId = '';

                    foreach ($countryGroup as $ck=>$cv){
                        if($cv->{'name'} == 'Länder' || $cv->{'name'} == 'countries'){
                            $countryId = $cv->{'id'};
                        }
                    }
                    if(!empty($countryId)){
                        $itemsUrl = $url.'/api/metadata/picklists/'.$countryId.'/items';
                        $countries = $this->getActInfo($itemsUrl,$args);
                        $countries_option_value['countries'] = $countries;
                        $countries_option_value['setTime'] = time();
                        $insertInfo['option_name'] = 'wp2act_countries_list';
                        $insertInfo['autoload'] = 'no';
                        $insertInfo['option_value'] = serialize($countries_option_value);


                        if(!empty($db_countries) && count($db_countries)>0){
                            //setTime
                            $country_id = $db_countries->{'option_id'};
                            $country_value = unserialize($db_countries->{'option_value'});
                            if(time()-$country_value['setTime']>86400){
                                $wpdb->update($tableName,$insertInfo,array('option_id'=>$country_id));
                            }
                        }else{
                            $wpdb->insert($wpdb->prefix.'options',$insertInfo);
                        }

                    }
                }
            }



            /**
             * get the what he wants list info
             */
            $items_mark = 1;
            $db_items = $wpdb->get_row($wpdb->prepare( "SELECT option_id,option_name,option_value FROM $tableName WHERE option_name = %s LIMIT 1", 'wp2act_items_list' ));
            if(!empty($db_items) && count($db_items)>0){
                $items_value = unserialize($db_items->{'option_value'});
                if(time()-$items_value['setTime']<=86400){
                    $items = $items_value['items'];
                }else{
                    $items_mark = 2;
                }
            }else{
                $items_mark = 2;
            }

            if($items_mark == 2){
                if(!empty($url) && !empty($bearer)){
                    $pickliskUrl = $url.'/api/metadata/picklists?recordType=kontakt';
                    $picklis = $this->getActInfo($pickliskUrl,$args);

                    $wp2actId = '';
                    foreach ($picklis as $pk=>$pv){
                        if($pv->{'name'} == 'wp2act'){
                            $wp2actId = $pv->{'id'};
                        }
                    }
                    if(!empty($wp2actId)){
                        $itemsUrl = $url.'/api/metadata/picklists/'.$wp2actId.'/items';
                        $items = $this->getActInfo($itemsUrl,$args);
                        $items_option_value['items'] = $items;
                        $items_option_value['setTime'] = time();
                        $insertInfo['option_name'] = 'wp2act_items_list';
                        $insertInfo['autoload'] = 'no';
                        $insertInfo['option_value'] = serialize($items_option_value);
                        $wpdb->insert($wpdb->prefix.'options',$insertInfo);


                        if(!empty($db_items) && count($db_items)>0){
                            //setTime
                            $items_id = $db_items->{'option_id'};
                            $items_value = unserialize($db_items->{'option_value'});
                            if(time()-$items_value['setTime']>86400){
                                $wpdb->update($tableName,$insertInfo,array('option_id'=>$items_id));
                            }
                        }else{
                            $wpdb->insert($wpdb->prefix.'options',$insertInfo);
                        }

                    }
                }
            }


            $dir = plugin_dir_path( __FILE__ );
            include($dir."frontend-form.php");
            die();
        }
    }


    /**
     * init the act and store in the session
     */
    function wp2act_act_bearer_init()
    {
        session_start();
        if(!isset($_SESSION['act_bearer']) || empty($_SESSION['act_bearer']) || time() - $_SESSION['act_lastTime']>1200){
            $this->wp2act_initActSession();
        }
    }

    private function wp2act_initActSession()
    {
        global $wpdb;
        require_wp_db();
        $act_info = get_option("crmaddon_actSetting_option");
        $userName = $act_info['username'];
        $password = $act_info['password'];
        $link_status = (isset($act_info['link_status']) && !empty(isset($act_info['link_status']))) ? $act_info['link_status'] : 'successful' ;

        if(isset($act_info['username']) && isset($act_info['database']) && !empty($act_info['username']) && !empty($act_info['database']) && $link_status == 'successful'){
            $userName = trim($userName).':'.trim($password);
            $base64UserName = base64_encode($userName);
            $db = $act_info['database'];
            $url = (empty($act_info['url'])) ? 'https://statistik.crmaddon.com/act.web.api/authorize' : $act_info['url'] . '/authorize';
            $headers = array(
                "Authorization"=> "Basic " .$base64UserName,
                "Act-Database-Name"=>$db,
            );
            $args = array(
                'timeout'     => 100,
                'headers'     => $headers,
            );

            if(time() - $_SESSION['act_last'] > 1200){
                try{
                    for($i = 1;$i<=5;$i++){
                        $act_service = wp_remote_get($url,$args);
                        $response = array();
                        if(count($act_service->{'errors'}['http_request_failed'])<=0){
                            $response = $act_service['response'];
                            if(is_array($response) && !is_wp_error($response)){
                                if($response['code'] == 200){
                                    $bearer = $act_service['body'];
                                    $_SESSION['act_bearer'] = $bearer;
                                    $_SESSION['act_lastTime'] = time();
                                    $act_info['link_status'] = 'successful';
                                    $update['option_value'] = serialize($act_info);
                                    $wpdb->update('wp_options',$update,array('option_name'=>'crmaddon_actSetting_option'));
                                    break;
                                }else{
//                                if($response['message'] == 'Unauthorized'){
                                    $act_info['link_status'] = 'fail';
                                    $update['option_value'] = serialize($act_info);
                                    $wpdb->update('wp_options',$update,array('option_name'=>'crmaddon_actSetting_option'));
                                    $logInfo = current_time('Y-m-d H:i:s').'|'.'Fail to init the ACT Connection,Error code:'.$response['code'].',error info:'.$response['message'].PHP_EOL;
                                    $this->recordOperationLog($logInfo);
                                    break;
                                }
                            }
                        }
                        $message = isset($act_service->{'errors'}['http_request_failed']) ? $act_service->{'errors'}['http_request_failed'] : '';
                        $logInfo = current_time('Y-m-d H:i:s').'|'.'Fail to init the ACT Connection,Error Info:'.json_encode($message).PHP_EOL;
                        $this->recordOperationLog($logInfo);
                    }
                }catch (Exception $e){
//                    echo $e->getMessage();
                }
            }
        }

        return;
    }
    /**
     * auto load the language file
     */
    function wp2act_language_init()
    {
        $currentInfo = get_locale();
        if(!empty($currentInfo)){
            $moFile = dirname(__FILE__)."/languages/wp2act-{$currentInfo}.mo";
        }else{
            $moFile = dirname(__FILE__)."/languages/wp2act-en_US.mo";
        }
        if(@file_exists($moFile) && is_readable($moFile)){
            load_textdomain('wordpress.org/plugins/wp2act',$moFile);
        }
    }

    /**
     * load the multilingual file
     */
//    function my_plugin_load_plugin_textdomain() {
//        load_plugin_textdomain( 'wp2act', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
//    }


    /**
     * @param $links
     * @param $file
     * @return mixed
     */
    function wp2act_plugin_action_links($links, $file)
    {
        if(isset($links['edit'])){
            unset($links['edit']);
        }
        if ( $file == plugin_basename( __FILE__ ) ) {
            $pps_links = '<a href="'.get_admin_url().'admin.php?page=wp2act_setting">'.__('Settings','wordpress.org/plugins/wp2act').'</a>';
            // make the 'Settings' link appear first
//            array_unshift( $links, $pps_links );
            array_push( $links, $pps_links );
        }
        if ( $file == plugin_basename( __FILE__ ) ) {
            $categoryPath = plugin_dir_path(__FILE__).'log';
            $filePath = plugin_dir_path(__FILE__).'log/error.log';

            if(file_exists($filePath) && file_exists($categoryPath)){
                $logUrl = plugins_url('wp2act/log/error.log');
                $pps_links = '<a href="'.$logUrl.'" download="error.log">'.__('Log File Download','wordpress.org/plugins/wp2act').'</a>';
                array_push( $links, $pps_links );
            }

        }
        return $links;
    }

    /**
     * the setting menu
     */
    function wp2act_setting_menu()
    {
        // top menu, including the first default setting page!
        add_menu_page(
            __('WP2Act setting page','wordpress.org/plugins/wp2act'),
            __('WP2Act Setting','wordpress.org/plugins/wp2act'),
            'manage_options',
            'wp2act_setting',
            array($this,'wp2act_default_menu_page'),
            plugins_url( $this->icon_url, __FILE__ )
            
        );
        // add sub menu
        add_submenu_page(
            'wp2act_setting',
            __('WP2Act show page title','wordpress.org/plugins/wp2act'),//page title
            __('Show Pages','wordpress.org/plugins/wp2act'),//menu title
            'manage_options',
            'show_page_menu',
            array($this,'wp2act_showPage_menu')
        );

        // add sub menu
        add_submenu_page(
            'wp2act_setting',
            __('Link Title','wordpress.org/plugins/wp2act'),//page title
            __('Link Title','wordpress.org/plugins/wp2act'),//menu title
            'manage_options',
            'link_title_menu',
            array($this,'wp2act_link_title')
        );

    }

    /**
     * setting of the act management info
     */
    function wp2act_default_menu_page()
    {
        ?>
        <div class="wrap">
            <h2><?php _e('ACT Information Setting','wordpress.org/plugins/wp2act');?></h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->act_group );
                do_settings_sections( $this->act_group );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * act management field show
     */
    function wp2act_act_setting_parameters()
    {
        register_setting($this->act_group,'crmaddon_actSetting_option');
        $setting_section = "crmaddon_actSetting_section";

        add_settings_section(
            $setting_section,
            '',
            '',
            $this->act_group
        );
        //act username
        add_settings_field(
            'crmaddon_act_username',
            __('ACT UserName','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_username_function' ),
            $this->act_group,
            $setting_section
        );

        //act pwd
        add_settings_field(
            'crmaddon_act_password',
            __('ACT Password','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_password_function' ),
            $this->act_group,
            $setting_section
        );

        //act database
        add_settings_field(
            'crmaddon_act_database',
            __('ACT Database','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_database_function' ),
            $this->act_group,
            $setting_section
        );
        //act url
        add_settings_field(
            'crmaddon_act_url',
            __('ACT URL','wordpress.org/plugins/wp2act'),
            array($this,'wp2act_url_function'),
            $this->act_group,
            $setting_section
        );

    }

    //contact data field function
    function wp2act_username_function()
    {
        $act_field = get_option("crmaddon_actSetting_option");
        ?>
        <input placeholder="ACT Username" id="act_username" name='crmaddon_actSetting_option[username]' type='text' value='<?php echo $act_field["username"]; ?>' />
        <p style="color: grey">eg:Chris Huffman</p>
        <font id="error_username"></font></div>
        <?php

    }

    function wp2act_password_function()
    {
        $act_field = get_option("crmaddon_actSetting_option");
        ?>
        <input id="act_password" name='crmaddon_actSetting_option[password]' type='password' value='<?php echo $act_field["password"]; ?>' />
        <input name='crmaddon_actSetting_option[link_status]' type='text' value='successful' style="display: none"/>
        <font id="error_password"></font></div>
        <?php
    }

    function wp2act_database_function()
    {
        $act_field = get_option("crmaddon_actSetting_option");
        ?>
        <input placeholder="ACT Database" id="act_database" name='crmaddon_actSetting_option[database]' type='text' value='<?php echo $act_field["database"]; ?>' />
        <p style="color: grey">eg:Act2018Demo</p>
        <font id="error_database"></font></div>
        <?php
    }
    function wp2act_url_function()
    {
        $act_field = get_option("crmaddon_actSetting_option");
        ?>
        <input placeholder="ACT URL" id="act_url" name='crmaddon_actSetting_option[url]' type='text' value='<?php echo $act_field["url"]; ?>' />
        <button type="button" id="check_act_configuration">Connection Test</button>
        <p style="color: grey">eg:https://act20.act-hosting.eu/act.web.api</p>
        <p id="error_url" style="color: red"></p></div>
        <?php
    }


    /**
     * to control the get contact page show
     */
    function wp2act_showPage_menu()
    {
        ?>
        <div class="wrap">
            <h2><?php _e('Management Of The Contact Link Show','wordpress.org/plugins/wp2act');?></h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->pageShow_setting_group );
                do_settings_sections( $this->pageShow_setting_group );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    /**
     * set the contact link page
     */
    function wp2act_link_title()
    {
        ?>
        <div class="wrap">
            <h2><?php _e('Link Title Setting','wordpress.org/plugins/wp2act');?></h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->link_title_setting );
                do_settings_sections( $this->link_title_setting );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * link title set module
     */
    function wp2act_link_setting_parameters()
    {
        register_setting($this->link_title_setting,'crmaddon_link_option');
        $page_linkTitle_section = "crmaddon_linkTitlesection";

        add_settings_section(
            $page_linkTitle_section,
            '',
            '',
            $this->link_title_setting
        );

        //act username
        add_settings_field(
            'crmaddon_link_title',
            __('Title','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_linkTitle_function' ),
            $this->link_title_setting,
            $page_linkTitle_section
        );

        //act pwd
        add_settings_field(
            'crmaddon_link_content',
            __('Link Content','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_linkContent_function' ),
            $this->link_title_setting,
            $page_linkTitle_section
        );

    }

    public function wp2act_linkTitle_function()
    {
        $act_field = get_option("crmaddon_link_option");
        ?>
        <input placeholder="<?php _e('Link Title','wordpress.org/plugins/wp2act');?>" id="act_title" name='crmaddon_link_option[title]' type='text' value='<?php echo $act_field["title"]; ?>' />
        <font id="act_title"></font></div>
        <?php
    }

    public function wp2act_linkContent_function()
    {
        $act_field = get_option("crmaddon_link_option");
        ?>
        <input placeholder="<?php _e('Link Content','wordpress.org/plugins/wp2act');?>" id="act_content" name='crmaddon_link_option[content]' type='text' value='<?php echo $act_field["content"]; ?>' />
        <font id="act_content"></font></div>
        <?php
    }

    /**
     * set module of the link show page!
     */
    function wp2act_showPage_setting_parameters()
    {
        register_setting($this->pageShow_setting_group,'crmaddon_pageShow_option');
        $page_setting_section = "crmaddon_pageShow_section";

        add_settings_section(
            $page_setting_section,
            '',
            '',
            $this->pageShow_setting_group
        );
        add_settings_field(
            'contact_pages',
            __('Select the show pages','wordpress.org/plugins/wp2act'),
            array( $this, 'wp2act_pageShow_function' ),
            $this->pageShow_setting_group,
            $page_setting_section
        );

    }

    public function wp2act_pageShow_function()
    {
        $selectInfo = get_option("crmaddon_pageShow_option");
        $contact_pages = array();
        if(!empty($selectInfo) && count($selectInfo)>0){
            $contact_pages = array_flip($selectInfo['contact_pages']);
        }
        ?>
        <select name="crmaddon_pageShow_option[contact_pages][]" multiple="multiple">
            <option value="all" <?php $this->wp2act_multiSelectCheck( 'all', $contact_pages ); ?>><?php _e('All','wordpress.org/plugins/wp2act');?></option>
            <option value="homePage" <?php $this->wp2act_multiSelectCheck( 'homePage', $contact_pages ); ?>><?php _e('HomePage','wordpress.org/plugins/wp2act');?></option>
            <option value="frontPage" <?php $this->wp2act_multiSelectCheck( 'frontPage', $contact_pages ); ?>><?php _e('FrontPage','wordpress.org/plugins/wp2act');?></option>
            <option value="articleDetailPage" <?php $this->wp2act_multiSelectCheck( 'articleDetailPage', $contact_pages ); ?>><?php _e('ArticleDetailPage','wordpress.org/plugins/wp2act');?></option>
            <option value="searchPage" <?php $this->wp2act_multiSelectCheck( 'searchPage', $contact_pages ); ?>><?php _e('SearchPage','wordpress.org/plugins/wp2act');?></option>
            <option value="categoryPage" <?php $this->wp2act_multiSelectCheck( 'categoryPage', $contact_pages ); ?>><?php _e('CategoryPage','wordpress.org/plugins/wp2act');?></option>
        </select>
        <font id="error_color"></font></div>
        <p style="font-weight: bold"><?php _e('Note:You can press \"CTRL\" then click the option to select more.','wordpress.org/plugins/wp2act');?></p>
        <?php
    }


    /**
     * the show of the contact tip
     */
    function wp2act_contact_tip_show()
    {
        $sendContactUrl = get_home_url().'/sendContact';
        $showPageTags = get_option("crmaddon_pageShow_option");
        if($this->wp2act_checkContactTipShow($showPageTags['contact_pages'])){
        ?>
            <a class="bounceIn" style="cursor: pointer" href="<?php echo $sendContactUrl;?>">
                <div class="contact-tip-div">
<!--                    Send Contact Data-->
                    <img alt="Send Contact Data" width="80px;" src="<?php echo plugins_url('/images/contact_data.png',__FILE__);?>">
                </div>
            </a>
        <?php }
    }

    /**
     *
     */
    function wp2act_check_act_configuration()
    {

        $currentUserInfo = wp_get_current_user();
        $logUserName = $currentUserInfo->{'data'}->{'user_login'};
        $logInfo = $logUserName.'|'.current_time('Y-m-d H:i:s').'|'.'test the connection of the act'.PHP_EOL;
        $this->recordOperationLog($logInfo);

        $userName = $_POST['username'];
        $password = $_POST['password'];
        $db = $_POST['database'];
        $url = trim($_POST['url']).'/authorize';
        $userName = trim($userName).':'.trim($password);
        $base64UserName = base64_encode($userName);

        $headers = array(
            "Authorization"=> "Basic " .$base64UserName,
            "Act-Database-Name"=>$db,
        );
        $args = array(
            'timeout'     => 100,
            'headers'     => $headers,
        );


        $retInfo = array();
        for($i = 1;$i<=5;$i++){
            $act_service = wp_remote_get($url,$args);
            $response = array();
            if(count($act_service->{'errors'}['http_request_failed'])<=0){
                $response = $act_service['response'];
                $retInfo['response'] = $response;
                if(is_array($response) && !is_wp_error($response)){
                    if($response['code'] == 200){
                        $retInfo['bearer'] = $act_service['body'];
                        break;
                    }
                }
            }else{
                $retInfo = $act_service->{'errors'};
            }

            $logInfo = $logUserName.'|'.current_time('Y-m-d H:i:s').'|'.'test the connection of the act but fail to get the connection,Error code:'.$response['code'].',error info:'.$response['message'].PHP_EOL;
            $this->recordOperationLog($logInfo);
        }
        echo json_encode($retInfo);
        wp_die();
    }
    

    private function wp2act_createActivity($str)
    {

        $statusCode = 'fail_activity';
        $act_info = get_option("crmaddon_actSetting_option");
        $url = $act_info['url'];
        $bearer = $_SESSION['act_bearer'];
        $header = array(
            "Authorization"=> "Bearer ".$bearer,
            "Accept"=> "application/json",
            "Content-Type"=> "application/json",
        );

        $createActivityUrl = $url.'/api/activities';
        $activityArgs = array(
            'method'      => 'POST',
            'timeout'     => 100,
            'headers'     => $header,
            'body'        => $str,
        );

        for($i=1;$i<=2;$i++){
            $ret = wp_remote_request($createActivityUrl,$activityArgs);
            $response = isset($ret['response']) ? $ret['response'] : '';
            if(count($ret->{'errors'}['http_request_failed'])<=0){
                if(is_array($ret) && !is_wp_error($ret)){
                    $arr_ret = $ret['response'];
                    if($arr_ret['code'] == 201 || $arr_ret['code'] == 200){
                        $statusCode = 'add_activity';
                        break;
                    }else{
                        $statusCode = 'fail_activity';
                    }
                }
            }else{
                $code = isset($response['code']) ? $response['code'] : '';
                $message = isset($response['message']) ? $response['message'] : '';
                $logInfo = current_time('Y-m-d H:i:s').'|'.'fail to create the activity,Error code:'.$code.',error info:'.$message.PHP_EOL;
                $this->recordOperationLog($logInfo);
            }

        }
        return $statusCode;
    }

    /**
     * ajax call the api and do the operation
     */
    function wp2act_ajax_send_contact_data()
    {

        $statusCode = 'fail';
        $tempCode = 'fail';
        $nowHour = current_time('H');
        $nowMinute = current_time('i');
        $activityStartTime = date('c',strtotime(current_time('Y-m-d'))+3600*12);
        $activityEndTime = date('c',strtotime(current_time('Y-m-d'))+3600*36);
        if($nowHour > 12 || ($nowHour=12 && $nowMinute >0)){
            $activityStartTime = date('c',strtotime(current_time('Y-m-d'))+(3600*36+600));
            $activityEndTime = date('c',strtotime(current_time('Y-m-d'))+(3600*60+600));
        }


        $contactInfo = $_POST['contactInfo'];
        $sendField = array();
        foreach ($contactInfo as $k=>$v){
            $key = sanitize_key($v['name']);
            if($key == 'emailaddress'){
                $sendField[$key] = sanitize_email($v['value']);
            }elseif ($key  == 'firstname' || $key == 'lastname'){
                $sendField[$key] = sanitize_user($v['value'],true);
            }else{
                $sendField[$key] = sanitize_text_field($v['value']);
            }
        }

        $sendField['countryname'] = 'United States';
        if(isset($sendField['country']) && !empty($sendField['country'])){
            $tempCountry = explode('=',$sendField['country']);
            $sendField['countryid'] = $tempCountry[0];
            $sendField['countryname'] = $tempCountry[1];
        }


        if(!isset($_SESSION['act_bearer']) || empty($_SESSION['act_bearer']) || time() - $_SESSION['act_lastTime']>1200){
            wp_die('act_null');
        }else{

            $act_info = get_option("crmaddon_actSetting_option");
            $url = $act_info['url'];

            $emailCheck = array();
            $emailArrInfo = array();
            $bearer = $_SESSION['act_bearer'];
            $email = $sendField['emailaddress'];
            $filter = "(emailAddress eq '{$email}' )";
            $filter = urlencode($filter);

            $header = array(
                "Authorization"=> "Bearer ".$bearer,
                "Accept"=> "application/json",
                "Content-Type"=> "application/json",
            );
            $args = array(
                'timeout'     => 100,
                'headers'     => $header,
            );

            $emailUrl = $url.'/api/contacts?$filter='.$filter;
            for($checkNum = 1;$checkNum<=3;$checkNum++){
                $emailCheck = wp_remote_get($emailUrl,$args);
                if(count($emailCheck->{'errors'}['http_request_failed'])<=0){
                    $response = $emailCheck['response'];
                    if(is_array($response) && !is_wp_error($response)){
                        if($response['code'] == 200 || $response['code'] == 201){
                            $emailArrInfo = json_decode($emailCheck['body']);
                            if(!empty($emailArrInfo) && count($emailArrInfo)>0){
                                break;
                            }
                        }else{
                            $code = isset($response['code']) ? $response['code'] : '';
                            $message = isset($response['message']) ? $response['message'] : '';
                            $logInfo = current_time('Y-m-d H:i:s').'|'.'fail to get the contact info filtered by email,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                            $this->recordOperationLog($logInfo);
                        }
                    }
                }else{
                    $message = isset($emailCheck->{'errors'}['http_request_failed']) ? $emailCheck->{'errors'}['http_request_failed'] : '';
                    $logInfo = current_time('Y-m-d H:i:s').'|'.'fail to get the contact info filtered by email,Error:'.$message.PHP_EOL;
                    $this->recordOperationLog($logInfo);
                }

            }

            $arr_ret = array();
            $retContactInfo = array();
            $contactPostInfo = array(
                "isUser"=>true,
                "firstName"=>$sendField['firstname'],
                "lastName" =>$sendField['lastname'],
                "emailAddress"=>$sendField['emailaddress'],
                "businessPhone" =>$sendField['mobilephone'],
                "businessAddress" =>array(
                    "line1"=>$sendField['street'],
                    "country"=>$sendField['countryname'],
                    "city"=>$sendField['city'],
                    "postalCode"=>$sendField['postalcode'],
                )
            );
            for($do_number = 1;$do_number<=2;$do_number++){
                if(count($emailArrInfo)>0){
                    $id = $emailArrInfo[0]->{'id'};
                    unset($contactPostInfo['isUser']);
                    $contactPostInfo['id'] = $id;
                    $updateUrl = $url.'/api/Contacts/'.$id;
                    $args = array(
                        'method'      => 'PATCH',
                        'timeout'     => 100,
                        'headers'     => $header,
                        'body'        => json_encode($contactPostInfo),
                    );

                    $ret = wp_remote_request($updateUrl,$args);

                    if(is_array($ret) && !is_wp_error($ret)){
                        $retContactInfo = json_decode($ret['body']);
                        $arr_ret = $ret['response'];
                    }

                }else{
                    $args = array(
                        'method'      => 'POST',
                        'timeout'     => 100,
                        'headers'     => $header,
                        'body'        => json_encode($contactPostInfo),
                    );

                    $createUrl = $url."/api/Contacts";
                    $ret = wp_remote_request($createUrl,$args);
                    if(count($ret->{'errors'}['http_request_failed'])<=0){
                        if(is_array($ret) && !is_wp_error($ret)){
                            $arr_ret = $ret['response'];
                            $retContactInfo = json_decode($ret['body']);
                        }
                    }
                }
                if($arr_ret['code'] == 201 || $arr_ret['code'] == 200){
                    break;
                }else{
                    $response = isset($ret['response']) ? $ret['response'] : '';
                    $code = isset($response['code']) ? $response['code'] : '';
                    $message = isset($response['message']) ? $response['message'] : '';
                    $logInfo = current_time('Y-m-d H:i:s').'|'.'fail to create the contact,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                    $this->recordOperationLog($logInfo);
                }
            }

            if($arr_ret['code'] == 201 || $arr_ret['code'] == 200){
                if($arr_ret['message'] == 'Created'){
                    $tempCode = "create";
                    $statusCode = '<span style="color: red">'. __('Successful to create or update the contact but fail to add it to the group and create an activity','wordpress.org/plugins/wp2act').'</span>';
                }else{
                    $tempCode = "update";
                    $statusCode = '<span style="color: red">'. __('Successful to update or update the contact but fail to add it to the group and create an activity','wordpress.org/plugins/wp2act').'</span>';
                }
                    $groupUrl = $sendField['act_group_url'];
                    $groupFilter = "(description eq '{$groupUrl}' )";
                    $groupFilter = urlencode($groupFilter);
                    $header = array(
                        "Authorization"=> "Bearer ".$bearer,
                        "Accept"=> "application/json",
                        "Content-Type"=> "application/json",
                    );
                    $args = array(
                        'timeout'     => 100,
                        'headers'     => $header,
                    );
                    $findGroupUrl = $url.'/api/groups?$filter='.$groupFilter;
                    $groupInfo = $this->getActInfo($findGroupUrl,$args);

                    if(empty($groupInfo) || count($groupInfo)<=0){
                        /**
                         * id there is no the group, create first
                         */
                        $createGroupUrl = $url.'/api/groups';
                        $groupPostInfo= array(
                            "name"=>'contact users',
                            "description" =>$groupUrl,
                        );

                        $groupArgs = array(
                            'method'      => 'POST',
                            'timeout'     => 100,
                            'headers'     => $header,
                            'body'        => json_encode($groupPostInfo),
                        );
                        for($checkNum = 1;$checkNum<=3;$checkNum++){
                            $createInfo = wp_remote_get($createGroupUrl,$groupArgs);
                            if(count($createInfo->{'errors'}['http_request_failed'])<=0){
                                $response = $createInfo['response'];
                                $code = isset($response['code']) ? $response['code'] : '';
                                $message = isset($response['message']) ? $response['message'] : '';
                                if(is_array($response) && !is_wp_error($response)){
                                    if($response['code'] == 200 || $response['code'] == 201){
                                        $groupInfo = json_decode($createInfo['body']);
                                        break;
                                    }else{
                                        $logInfo = current_time('Y-m-d H:i:s').'|'.'Fail to create the group,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                                        $this->recordOperationLog($logInfo);
                                    }
                                }else{
                                    $logInfo = current_time('Y-m-d H:i:s').'|'.'Fail to create the group,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                                    $this->recordOperationLog($logInfo);
                                }
                            }else{
                                $logInfo = current_time('Y-m-d H:i:s').'|'.'Fail to create the group,Error:'.json_encode($createInfo->{'errors'}).PHP_EOL;
                                $this->recordOperationLog($logInfo);
                            }
                        }
                    }else{
                        $groupInfo = $groupInfo[0];
                    }

                    if(!empty($groupInfo) && count($groupInfo)>0){
//                        $groupId = $groupInfo[0]->{'id'};
//                        $recordOwner = $groupInfo[0]->{'recordOwner'};
                        $groupId = $groupInfo->{'id'};
                        $recordOwner = $groupInfo->{'recordOwner'};
                        $contactId = $retContactInfo->{'id'};
                        $addToGroupUrl = $url.'/api/groups/'.$groupId.'/contacts/'.$contactId;
                        $groupHeader = array(
                            "Authorization"=> "Bearer ".$bearer,
                            "Accept"=> "application/json",
                            "Content-Type"=> "application/json",
                            "Content-Length"=> 0,
                        );
                        $addToGroupArgs = array(
                            'method'      => 'PUT',
                            'timeout'     => 100,
                            'headers'     => $groupHeader,
                            "Content-Length"=> strlen($addToGroupUrl)
                        );
                        $ret = wp_remote_request($addToGroupUrl,$addToGroupArgs);
                        if(count($ret->{'errors'}['http_request_failed'])<=0){
                            if(is_array($ret) && !is_wp_error($ret)){
                                $arr_ret = $ret['response'];
                                if($arr_ret['code'] == 201 || $arr_ret['code'] == 200){
                                    $tempCode = 'add_group';
                                }else{
                                    $code = isset($response['code']) ? $response['code'] : '';
                                    $message = isset($response['message']) ? $response['message'] : '';
                                    $logInfo = $retContactInfo->{'name'}.'|'.current_time('Y-m-d H:i:s').'|'.'Fail to add the group,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                                    $this->recordOperationLog($logInfo);
                                }
                            }else{
                                $response = isset($ret['response']) ? $ret['response'] : '';
                                $code = isset($response['code']) ? $response['code'] : '';
                                $message = isset($response['message']) ? $response['message'] : '';
                                $logInfo = $retContactInfo->{'name'}.'|'.current_time('Y-m-d H:i:s').'|'.'Fail to add the group,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                                $this->recordOperationLog($logInfo);
                            }
                        }else{
                            $logInfo = $retContactInfo->{'name'}.'|'.current_time('Y-m-d H:i:s').'|'.'Fail to add the group,Error:'.json_encode($ret).PHP_EOL;
                            $this->recordOperationLog($logInfo);
                        }

                        if(isset($sendField['what_he_wants']) && $sendField['what_he_wants'] !='no'){
                            $english = array(
                                'no','call back','call for Appointment','call later','Send Flyer'
                            );
                            $german = array(
                                'nr','ruf zurück','aufruf zur ernennung','später anrufen','senden - flyer'
                            );

//                            $activityType = (in_array($sendField['what_he_wants'],$german)) ? 'Anruf' : 'Call';
                            $activityType = 'Anruf';

                            $regarding = explode('=',$sendField['what_he_wants']);
                            $regarding = (count($regarding)>1 && isset($regarding[1])) ? $regarding[1] : 'no';
                            $str = '{"startTime":"'.$activityStartTime.'","endTime":"'.$activityEndTime.'","activityTypeName":"'.$activityType.'","scheduledBy":"'.$recordOwner.'","scheduledFor":"'.$retContactInfo->{'fullName'}.'","details":"'.$sendField['comment'].'","subject":"'.$regarding.'","contacts":[{"id":"'.$contactId.'","displayName":"'.$retContactInfo->{'fullName'}.'"}],"groups":[{"id":"'.$groupInfo->{'id'}.'","name":"'.$groupInfo->{'recordOwner'}.'"}]}';
                            $statusCode = $this->wp2act_createActivity($str);
                            if($tempCode == 'add_group' && $statusCode == 'add_activity'){
                                $statusCode = '<span style="color: green">'. __('Contact Sent','wordpress.org/plugins/wp2act').'</span>';
                            }elseif ($tempCode == 'add_group' && $statusCode !='add_activity'){
                                $statusCode = '<span style="color: red">'. __('Successful to create or update the contact and add it to the group, but fail to create the activity','wordpress.org/plugins/wp2act').'</span>';
                            }elseif ($tempCode != 'add_group' && $statusCode =='add_activity'){
                                $statusCode = '<span style="color: red">'. __('Successful to to create or update the contact and create the activity,but fail to add it to the group','wordpress.org/plugins/wp2act').'</span>';
                            }else{
                                $statusCode = '<span style="color: red">'. __('Successful to create or update the contact but fail to add it to the group and create the activity','wordpress.org/plugins/wp2act').'</span>';
                            }
                        }else{
                            if($tempCode == 'add_group'){
                                $statusCode = '<span style="color: green">'. __('Contact Sent','wordpress.org/plugins/wp2act').'</span>';
                            }else{
                                $statusCode = '<span style="color: red">'. __('Successful to create or update the contact but fail to add it to the group','wordpress.org/plugins/wp2act').'</span>';
                            }
                        }
                    }else{
                        $statusCode = '<span style="color: red">'. __('Successful to create or update the contact but fail to add it to the group and create an activity','wordpress.org/plugins/wp2act').'</span>';

                    }
//                }
//                else{
//                    $statusCode = "update";
//                }
            }else{
                $statusCode = '<span style="color: red">'. __('Send failed','wordpress.org/plugins/wp2act').'</span>';
            }

        }
        echo $statusCode;
        wp_die();

    }

    /**
     * deal the content show field judge!
     * @param $selected
     * @param $current
     */
    private function wp2act_multiSelectCheck($selected,$current)
    {
        if(array_key_exists($selected,$current))
            echo 'selected';
    }

    /**
     * deal the url and get the current url info
     * @return string
     */
    private function wp2act_getCurPageURL()
    {
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    /**
     * to manager the tip show
     * @param $option
     * @return bool
     */
    private function wp2act_checkContactTipShow($option)
    {
        $arr = array(
            'homePage'=>is_home(),
            'frontPage'=>is_front_page(),
            'articleDetailPage'=>is_single(),
            'searchPage'=>is_search(),//search page
            'archivePage'=>is_archive(),
            'categoryPage'=>is_category(),
            'all'=>true,
        );
        $flag = false;
        if(!empty($option) && count($option)>0){
            $flipOptions = array_flip($option);
            if(array_key_exists('all',$flipOptions)){
                $flag = true;
            }else{
                foreach ($option as $k=>$v){
                    if($arr[$v]){
                        $flag = true;
                        break;
                    }
                }
            }
        }
        return $flag;
    }

    /**
     * operation log info
     * @param $info
     */
    private function recordOperationLog($info)
    {
//        $fileName = plugin_dir_path(__FILE__).'log/'.current_time('Y-m-d').'.log';
        $filePath = plugin_dir_path(__FILE__).'log';
        if(!file_exists($filePath)){
            mkdir($filePath);
        }
        $fileName = $filePath.'/error.log';
        @file_put_contents($fileName,$info,FILE_APPEND | LOCK_EX);
    }

    /**
     * @param $url
     * @param $args
     * @return array|mixed|object
     */
    private function getActInfo($url,$args)
    {
        $retInfo = array();
        for($checkNum = 1;$checkNum<=3;$checkNum++){
            try{
                $countryInfo = wp_remote_get($url,$args);
                if(count($countryInfo->{'errors'}['http_request_failed'])<=0){
                    $response = $countryInfo['response'];
                    if(is_array($response) && !is_wp_error($response)){
                        if($response['code'] == 200 || $response['code'] == 201){
                            $retInfo = json_decode($countryInfo['body']);
                            break;
                        }
                    }
                }else{
                    $response = isset($countryInfo['response']) ? $countryInfo['response'] : '';
                    $code = isset($response['code']) ? $response['code'] : '';
                    $message = isset($response['message']) ? $response['message'] : '';
                    $logInfo = current_time('Y-m-d H:i:s').'|'.'fail to get the information,Error Code:'.$code.',error info:'.$message.PHP_EOL;
                    $this->recordOperationLog($logInfo);
                }
            }catch (Exception $e){
//                echo $e->getMessage();
            }
        }
        return $retInfo;
    }



}


new WP2Act();

?>