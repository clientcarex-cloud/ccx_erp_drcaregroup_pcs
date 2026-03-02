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
        $this->load->model('client/client_model');

        $data['title'] = _l('pcs_patients');

        // Branch filter data (same pattern as Client::get_patient_list)
        $staff_branch_ids = $this->client_model->get_logged_in_staff_branch_id();
        if (!is_array($staff_branch_ids)) {
            $staff_branch_ids = $staff_branch_ids ? [$staff_branch_ids] : [];
        }
        $staff_branch_ids = array_map('intval', array_filter($staff_branch_ids));

        $all_branches = $this->client_model->get_branch();
        $visible_branches = $all_branches;
        if (!empty($staff_branch_ids)) {
            $visible_branches = array_values(array_filter($all_branches, function ($branch) use ($staff_branch_ids) {
                return in_array((int) $branch['id'], $staff_branch_ids, true);
            }));
            if (empty($visible_branches)) {
                $visible_branches = $all_branches;
            }
        }

        $data['branch'] = $visible_branches;
        $data['accessible_branch_ids'] = $staff_branch_ids;
        $data['current_branch_id'] = $staff_branch_ids;

        $this->load->view('patients_list', $data);
    }
}
