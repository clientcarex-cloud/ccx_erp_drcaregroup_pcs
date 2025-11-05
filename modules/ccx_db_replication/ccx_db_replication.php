<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module Name: CCX DB Replication
 * Description: A simple example module that prints "Hello World"
 * Version: 1.0.0
 * Author: Fahad Ahmed
 */

hooks()->add_action('admin_init', 'ccx_db_replication_init_menu_items');

function ccx_db_replication_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('customers', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('ccx_db_replication', [
            'name'     => 'CCX DB Replication',
            'href'     => admin_url('ccx_db_replication'),
            'icon'     => 'fa fa-cube',
            'position' => 10,
        ]);
    }
}
