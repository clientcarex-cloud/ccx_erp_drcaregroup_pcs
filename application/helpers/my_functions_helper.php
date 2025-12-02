<?php

hooks()->add_action('app_init','my_change_default_url_to_admin');

function my_change_default_url_to_admin(){
    $CI = &get_instance();

    if(!is_client_logged_in() && !$CI->uri->segment(1)){
        redirect(site_url('admin/authentication'));
    }
}

hooks()->add_filter('settings_tabs', 'my_filter_settings_tabs');

function my_filter_settings_tabs($tabs){
    foreach($tabs as $key => $tab){
        if($key != 'general'){
            unset($tabs[$key]);
        }
    }
    return $tabs;
}