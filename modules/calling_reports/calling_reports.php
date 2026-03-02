<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Calling Reports
Description: Independent Calling Reports module — cloned calling pages.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CALLING_REPORTS_MODULE_NAME', 'calling_reports');

hooks()->add_action('admin_init', 'calling_reports_module_init_menu_items');
hooks()->add_action('admin_init', 'calling_reports_permissions');

function calling_reports_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . ' (' . _l('permission_global') . ')',
        'cpot_calling' => _l('permission_cpot_calling'),
        'ppot_calling' => _l('permission_ppot_calling'),
        'reference_calling' => _l('permission_reference_calling'),
        'renewal_calling' => _l('permission_renewal_calling'),
        'treatment_followup_calling' => _l('permission_treatment_followup_calling'),
        'medicine_calling' => _l('permission_medicine_calling'),
    ];

    register_staff_capabilities('calling_reports', $capabilities, _l('calling_reports'));
}

/**
 * Register language files
 */
register_language_files(CALLING_REPORTS_MODULE_NAME, [CALLING_REPORTS_MODULE_NAME]);

/**
 * Init calling reports module menu items
 */
function calling_reports_module_init_menu_items()
{
    $CI =& get_instance();

    $has_any = is_admin()
        || staff_can('view', 'calling_reports')
        || staff_can('cpot_calling', 'calling_reports')
        || staff_can('ppot_calling', 'calling_reports')
        || staff_can('reference_calling', 'calling_reports')
        || staff_can('renewal_calling', 'calling_reports')
        || staff_can('treatment_followup_calling', 'calling_reports')
        || staff_can('medicine_calling', 'calling_reports');

    if ($has_any) {
        $CI->app_menu->add_sidebar_menu_item('calling_reports', [
            'name' => _l('calling_reports'),
            'icon' => 'fa fa-phone-square',
            'href' => '#',
            'position' => 10,
        ]);
    }

    if (staff_can('cpot_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_cpot_calling',
            'name' => _l('cpot_calling'),
            'href' => admin_url('calling_reports/calling/cpot_calling'),
            'position' => 1,
        ]);
    }

    if (staff_can('ppot_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_ppot_calling',
            'name' => _l('ppot_calling'),
            'href' => admin_url('calling_reports/calling/ppot_calling'),
            'position' => 2,
        ]);
    }

    if (staff_can('reference_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_reference_calling',
            'name' => _l('reference_calling'),
            'href' => admin_url('calling_reports/calling/reference_calling'),
            'position' => 3,
        ]);
    }

    if (staff_can('renewal_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_renewal_calling',
            'name' => _l('renewal_calling'),
            'href' => admin_url('calling_reports/calling/renewal_calling'),
            'position' => 4,
        ]);
    }

    if (staff_can('treatment_followup_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_treatment_followup_calling',
            'name' => _l('treatment_followup_calling'),
            'href' => admin_url('calling_reports/calling/treatment_followup_calling'),
            'position' => 5,
        ]);
    }

    if (staff_can('medicine_calling', 'calling_reports') || is_admin()) {
        $CI->app_menu->add_sidebar_children_item('calling_reports', [
            'slug' => 'cr_medicine_calling',
            'name' => _l('medicine_calling'),
            'href' => admin_url('calling_reports/calling/medicine_calling'),
            'position' => 6,
        ]);
    }
}
