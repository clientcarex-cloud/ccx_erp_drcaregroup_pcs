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

        // --- Branch filter data (same pattern as client/get_patient_list) ---
        $normalizeBranchIds = static function ($value) {
            if ($value === null || $value === '') {
                return [];
            }
            $list = is_array($value) ? $value : explode(',', (string) $value);
            $normalized = [];
            foreach ($list as $item) {
                $item = (string) $item;
                if ($item === '' || strtolower($item) === 'null' || !is_numeric($item)) {
                    continue;
                }
                $normalized[] = (int) $item;
            }
            return array_values(array_unique($normalized));
        };

        $staff_branch_ids = $normalizeBranchIds($this->current_branch_id);
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
        $data['selected_branch_id'] = !empty($staff_branch_ids) ? $staff_branch_ids : [];
        $data['accessible_branch_ids'] = $staff_branch_ids;
        $data['current_branch_id'] = $this->current_branch_id;

        $this->load->view('patients_list', $data);
    }
}
