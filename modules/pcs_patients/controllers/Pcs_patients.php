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
    }

    public function index()
    {
        $data['title'] = _l('pcs_patients');

        $this->load->model('client_model');
        $this->load->model('doctor_model');

        $current_branch_id = $this->client_model->get_logged_in_staff_branch_id();
        $staff_branch_ids = [];
        if (!empty($current_branch_id)) {
            $staff_branch_ids = explode(',', $current_branch_id);
        }

        $all_branches = $this->client_model->get_branch();
        if (!empty($staff_branch_ids)) {
            $visible_branches = array_filter($all_branches, function ($branch) use ($staff_branch_ids) {
                return in_array((int) $branch['id'], $staff_branch_ids, true);
            });
            $data['branches'] = empty($visible_branches) ? $all_branches : array_values($visible_branches);
        } else {
            $data['branches'] = $all_branches;
        }

        $data['doctors'] = $this->doctor_model->get_doctors();

        $this->load->view('patients_list', $data);
    }
}
