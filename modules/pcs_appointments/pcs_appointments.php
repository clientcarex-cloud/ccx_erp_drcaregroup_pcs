<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PCS Appointments
Description: PCS Appointments management module.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PCS_APPOINTMENTS_MODULE_NAME', 'pcs_appointments');

hooks()->add_action('admin_init', 'pcs_appointments_module_init_menu_items');
hooks()->add_action('admin_init', 'pcs_appointments_permissions');

function pcs_appointments_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . ' (' . _l('permission_global') . ')',
    ];

    register_staff_capabilities('pcs_appointments', $capabilities, _l('pcs_appointments'));
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(PCS_APPOINTMENTS_MODULE_NAME, [PCS_APPOINTMENTS_MODULE_NAME]);

/**
 * Init PCS Appointments module menu items in setup in admin_init hook
 * @return null
 */
function pcs_appointments_module_init_menu_items()
{
    $CI =& get_instance();

    if (staff_can('view', 'pcs_appointments') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('pcs_appointments', [
            'name' => _l('pcs_appointments'),
            'icon' => 'fa fa-calendar',
            'position' => 3,
            'href' => admin_url('pcs_appointments'),
        ]);
    }
}
