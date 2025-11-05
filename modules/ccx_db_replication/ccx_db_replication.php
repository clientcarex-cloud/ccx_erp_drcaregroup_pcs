<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX DB Replication
Description: Lightweight helper for database replication checks.
Version: 1.0.0
Author: Fahad Ahmed
*/

define('CCX_DB_REPLICATION_MODULE_NAME', 'ccx_db_replication');

register_activation_hook(CCX_DB_REPLICATION_MODULE_NAME, 'ccx_db_replication_module_activation_hook');
register_deactivation_hook(CCX_DB_REPLICATION_MODULE_NAME, 'ccx_db_replication_module_deactivation_hook');
register_uninstall_hook(CCX_DB_REPLICATION_MODULE_NAME, 'ccx_db_replication_module_uninstall_hook');

hooks()->add_action('admin_init', 'ccx_db_replication_init_menu_items');

function ccx_db_replication_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
    ccx_db_replication_install();
}

function ccx_db_replication_module_deactivation_hook()
{
    require_once __DIR__ . '/install.php';
    ccx_db_replication_uninstall();
}

function ccx_db_replication_module_uninstall_hook()
{
    require_once __DIR__ . '/install.php';
    ccx_db_replication_uninstall();
}

function ccx_db_replication_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('customers', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('ccx_db_replication', [
            'name'     => 'CCX DB Replication',
            'href'     => admin_url('ccx_db_replication/index'),
            'icon'     => 'fa fa-cube',
            'position' => 10,
        ]);
    }
}
