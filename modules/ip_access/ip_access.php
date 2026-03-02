<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: IP Access
Description: Restrict staff access based on Branch IP
Version: 1.0.0
Requires at least: 2.3.*
*/

define('IP_ACCESS_MODULE_NAME', 'ip_access');

hooks()->add_action('admin_init', 'ip_access_init_menu_items');
hooks()->add_action('admin_init', 'ip_access_permissions');
hooks()->add_action('after_staff_login', 'ip_access_check_login');
hooks()->add_action('admin_auth_init', 'ip_access_login_check_error');

/**
 * Register activation module hook
 */
register_activation_hook(IP_ACCESS_MODULE_NAME, 'ip_access_activation_hook');

function ip_access_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register permissions
 */
function ip_access_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit' => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('ip_access', $capabilities, _l('ip_access'));
}

/**
 * Init menu
 */
function ip_access_init_menu_items()
{
    $CI = &get_instance();
    if (staff_can('view', 'ip_access')) {
        $CI->app_menu->add_sidebar_menu_item('ip_access', [
            'name' => 'IP Access',
            'href' => admin_url('ip_access'),
            'position' => 8,
            'icon' => 'fa fa-shield-alt',
        ]);
    }
}

/**
 * Check IP on login
 */
function ip_access_check_login()
{
    $CI = &get_instance();
    $staff_id = get_staff_user_id();

    if (is_admin($staff_id)) {
        return;
    }

    $staff = $CI->staff_model->get($staff_id);
    if (!$staff || empty($staff->branch_id)) {
        return;
    }

    // branch_id is comma separated string
    $branch_ids = explode(',', $staff->branch_id);
    $current_ip = $CI->input->ip_address();

    // Get all allowed IPs for these branches
    $CI->db->select('ip_address, branch_id');
    $CI->db->from(db_prefix() . 'ip_access');
    $CI->db->where_in('branch_id', $branch_ids);
    $allowed_ips_result = $CI->db->get()->result_array();

    $branches_with_restrictions = array_unique(array_column($allowed_ips_result, 'branch_id'));

    // Logic:
    // If a user belongs to a branch that has NO IPs defined, they can access from anywhere (for that branch scope).
    // If a user belongs to multiple branches:
    // - If at least ONE of their branches has NO restrictions (not in $branches_with_restrictions), ALLOW.
    // - If ALL of their branches have restrictions, they must match one of the allowed IPs.

    $user_branches_count = count($branch_ids);
    $restricted_branches_count = count($branches_with_restrictions);

    if ($user_branches_count > $restricted_branches_count) {
        // At least one branch has no restrictions
        return;
    }

    // All branches are restricted. Check if current IP is allowed.
    $allowed_ips = array_column($allowed_ips_result, 'ip_address');

    if (!in_array($current_ip, $allowed_ips)) {
        $CI->authentication_model->logout();
        redirect(admin_url('authentication?ip_restricted=true'));
    }
}

/**
 * Show error if IP restricted
 */
function ip_access_login_check_error()
{
    $CI = &get_instance();
    if ($CI->input->get('ip_restricted') === 'true') {
        set_alert('danger', 'Access Denied: Your IP is not authorized for this branch.');
    }
}
