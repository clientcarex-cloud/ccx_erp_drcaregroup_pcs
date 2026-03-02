<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PCS Patients
Description: Simplified PCS Patients list module containing only the patient table.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PCS_PATIENTS_MODULE_NAME', 'pcs_patients');

hooks()->add_action('admin_init', 'pcs_patients_module_init_menu_items');
hooks()->add_action('admin_init', 'pcs_patients_permissions');

function pcs_patients_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . ' (' . _l('permission_global') . ')',
    ];

    register_staff_capabilities('pcs_patients', $capabilities, _l('pcs_patients'));
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(PCS_PATIENTS_MODULE_NAME, [PCS_PATIENTS_MODULE_NAME]);

/**
 * Init pcs patients module menu items in setup in admin_init hook
 * @return null
 */
function pcs_patients_module_init_menu_items()
{
    $CI =& get_instance();

    if (staff_can('view', 'pcs_patients') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('pcs_patients', [
            'name' => _l('pcs_patients'),
            'icon' => 'fa fa-users',
            'position' => 2,
            'href' => admin_url('pcs_patients'),
        ]);
    }
}
