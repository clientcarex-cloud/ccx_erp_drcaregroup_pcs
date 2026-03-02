<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pcs_patients extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!staff_can('view', 'pcs_patients') && !is_admin()) {
            access_denied('PCS Patients');
        }
        $this->load->model('client/client_model');
    }

    public function index()
    {
        $data['title'] = _l('pcs_patients');
        $data['branch'] = $this->client_model->get_branch();
        $this->load->view('patients_list', $data);
    }

    public function get_patient_list()
    {
        $branch_ids = $this->input->post('branch_ids');
        $from_date = $this->input->post('from_date_filter');
        $to_date = $this->input->post('to_date_filter');

        $data['consulted_from_date'] = !empty($from_date) ? $from_date : null;
        $data['consulted_to_date'] = !empty($to_date) ? $to_date : null;
        $data['selected_branch_ids'] = !empty($branch_ids) ? array_filter(explode(',', $branch_ids), 'is_numeric') : [];

        $this->app->get_table_data(module_views_path('pcs_patients', 'tables/get_patient_list'), $data);
    }
}
