<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: MIS Reports
Description: MIS Reports module extracted from Client.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('MIS_REPORTS_MODULE_NAME', 'mis_reports');

hooks()->add_action('admin_init', 'mis_reports_module_init_menu_items');
hooks()->add_action('admin_init', 'mis_reports_permissions');

function mis_reports_permissions()
{
	$capabilities = [];

	$capabilities['capabilities'] = [
		'view' => _l('permission_view') . ' (' . _l('permission_global') . ')',
		'create' => _l('permission_create'),
		'edit' => _l('permission_edit'),
		'delete' => _l('permission_delete'),
	];

	register_staff_capabilities('mis_reports', $capabilities, _l('mis_reports'));
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(MIS_REPORTS_MODULE_NAME, [MIS_REPORTS_MODULE_NAME]);

/**
 * Init misc reports module menu items in setup in admin_init hook
 * @return null
 */
function mis_reports_module_init_menu_items()
{
	$CI = &get_instance();

	if (is_admin() || staff_can('view', 'mis_reports')) {
		$CI->app_menu->add_sidebar_menu_item('mis_reports', [
			'name' => _l('mis_reports'),
			'icon' => 'fa fa-chart-line',
			'position' => 31,
			'href' => admin_url('mis_reports/reports/mis_reports'),
		]);
	}
}
