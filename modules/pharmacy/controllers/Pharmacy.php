<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pharmacy extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!staff_can('view', 'pharmacy') && !is_admin()) {
            access_denied('Pharmacy');
        }
        $this->load->model('client/client_model');
    }

    public function index()
    {
        $data['title'] = _l('pharmacy');
        $data['branch'] = $this->client_model->get_branch();
        $data['current_branch_id'] = $this->client_model->get_logged_in_staff_branch_id();
        $this->load->view('pharmacy_list', $data);
    }

    public function table($consulted_date = NULL, $consulted_to_date = NULL, $branch_id = NULL)
    {
        $branch_id = ($branch_id === '0' || empty($branch_id)) ? null : $branch_id;

        $data['consulted_from_date'] = $consulted_date;
        $data['consulted_to_date'] = $consulted_to_date;
        $data['branch_id'] = $branch_id;

        $this->app->get_table_data(module_views_path('pharmacy', 'tables/pharmacy_table'), $data);
    }
}
