<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module Name: CCX
 * Description: A simple example module that prints "Hello World"
 * Version: 1.0.0
 * Requires at least: 2.9.0
 * Author: Fahad Ahmed
 */

hooks()->add_action('admin_init', 'ccx_init_menu_items');

function ccx_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('customers', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('ccx', [
            'name'     => 'CCX',
            'href'     => admin_url('ccx'),
            'icon'     => 'fa fa-cube',
            'position' => 10,
        ]);
    }
}
