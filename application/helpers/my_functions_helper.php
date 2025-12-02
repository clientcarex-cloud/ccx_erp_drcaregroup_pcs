<?php

hooks()->add_action('app_init', 'my_change_default_url_to_admin');

function my_change_default_url_to_admin()
{
    $CI = &get_instance();

    if (!is_client_logged_in() && !$CI->uri->segment(1)) {
        redirect(site_url('admin/authentication'));
    }
}

hooks()->add_action('admin_init', 'my_filter_settings_sections', 999);

function my_filter_settings_sections()
{
    $CI = &get_instance();
    // Check if app library is loaded
    if (!isset($CI->app)) {
        return;
    }

    $app = $CI->app;

    try {
        $reflection = new ReflectionClass($app);
        $property = $reflection->getProperty('settingsSections');
        $property->setAccessible(true);
        $sections = $property->getValue($app);

        $newSections = [];
        if (isset($sections['general'])) {
            $newSections['general'] = $sections['general'];
        }

        $property->setValue($app, $newSections);
    } catch (Exception $e) {
        log_message('error', 'Failed to filter settings sections: ' . $e->getMessage());
    }
}