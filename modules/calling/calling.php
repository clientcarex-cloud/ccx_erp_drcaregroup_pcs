<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Calling
Description: Patient Calling Module - CPOT, PPOT, Reference, Renewal, Treatment Followup, Medicine Calling
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CALLING_MODULE_NAME', 'calling');

hooks()->add_action('admin_init', 'calling_module_init_menu_items');

function calling_module_init_menu_items()
{
    $CI = &get_instance();

    if (staff_can('calling', 'calling')) {
        $CI->app_menu->add_sidebar_menu_item('calling', [
            'name' => _l('calling'),
            'icon' => 'fa fa-phone',
            'href' => admin_url('calling/calling'),
            'position' => 10,
        ]);
    }

    if (staff_can('cpot_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'CPOT Calling',
            'name' => _l('cpot_calling'),
            'href' => admin_url('calling/calling/cpot_calling'),
            'position' => 1,
            'badge' => [],
        ]);
    }

    if (staff_can('ppot_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'PPOT Calling',
            'name' => _l('ppot_calling'),
            'href' => admin_url('calling/calling/ppot_calling'),
            'position' => 2,
            'badge' => [],
        ]);
    }

    /* if (staff_can('fe_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug'     => 'FE Calling',
            'name'     => _l('fe_calling'),
            'href'     => admin_url('calling/calling/fe_calling'),
            'position' => 3,
            'badge'    => [],
        ]);
    } */

    if (staff_can('reference_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'Refernce Calling',
            'name' => _l('reference_calling'),
            'href' => admin_url('calling/calling/reference_calling'),
            'position' => 4,
            'badge' => [],
        ]);
    }

    if (staff_can('renewal_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'Renewal Calling',
            'name' => _l('renewal_calling'),
            'href' => admin_url('calling/calling/renewal_calling'),
            'position' => 5,
            'badge' => [],
        ]);
    }

    if (staff_can('treatment_followup_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'Treatment Followup Calling',
            'name' => _l('treatment_followup_calling'),
            'href' => admin_url('calling/calling/treatment_followup_calling'),
            'position' => 6,
            'badge' => [],
        ]);
    }

    if (staff_can('medicine_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug' => 'Medicine Calling',
            'name' => _l('medicine_calling'),
            'href' => admin_url('calling/calling/medicine_calling'),
            'position' => 7,
            'badge' => [],
        ]);
    }

    /* if (staff_can('nroc_calling', 'calling')) {
        $CI->app_menu->add_sidebar_children_item('calling', [
            'slug'     => 'NROC Calling',
            'name'     => _l('nroc_calling'),
            'href'     => admin_url('calling/calling/nroc_calling'),
            'position' => 8,
            'badge'    => [],
        ]);
    } */
}

hooks()->add_filter('staff_permissions', function ($permissions) {
    $permissions['calling'] = [
        'name' => _l('calling'),
        'capabilities' => [
            'calling' => _l('permission_calling'),
            'cpot_calling' => _l('permission_cpot_calling'),
            'ppot_calling' => _l('permission_ppot_calling'),
            'fe_calling' => _l('permission_fe_calling'),
            'reference_calling' => _l('permission_reference_calling'),
            'renewal_calling' => _l('permission_renewal_calling'),
            'treatment_followup_calling' => _l('permission_treatment_followup_calling'),
            'medicine_calling' => _l('permission_medicine_calling'),
            'nroc_calling' => _l('permission_nroc_calling'),
        ],
    ];

    return $permissions;
});

/**
 * Register language files
 */
register_language_files(CALLING_MODULE_NAME, [CALLING_MODULE_NAME]);
