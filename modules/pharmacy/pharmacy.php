<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Pharmacy
Description: Independent Pharmacy module for managing prescriptions and medicine dispensing.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PHARMACY_MODULE_NAME', 'pharmacy');

hooks()->add_action('admin_init', 'pharmacy_module_init_menu_items');
hooks()->add_action('admin_init', 'pharmacy_permissions');

function pharmacy_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . ' (' . _l('permission_global') . ')',
    ];

    register_staff_capabilities('pharmacy', $capabilities, _l('pharmacy'));
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(PHARMACY_MODULE_NAME, [PHARMACY_MODULE_NAME]);

/**
 * Init pharmacy module menu items in setup in admin_init hook
 * @return null
 */
function pharmacy_module_init_menu_items()
{
    $CI =& get_instance();

    if (staff_can('view', 'pharmacy') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('pharmacy', [
            'name' => _l('pharmacy'),
            'icon' => 'fa fa-medkit',
            'position' => 33,
            'href' => admin_url('pharmacy'),
        ]);
    }
}
