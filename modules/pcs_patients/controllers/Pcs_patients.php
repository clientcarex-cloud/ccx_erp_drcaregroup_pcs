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

        // Show ALL branches in dropdown (no staff restriction for PCS)
        $data['branch'] = $this->client_model->get_branch();
        $data['selected_branch_id'] = [];
        $data['accessible_branch_ids'] = [];
        $data['current_branch_id'] = '';

        $this->load->view('patients_list', $data);
    }

    /**
     * Dedicated AJAX endpoint for PCS patients DataTable.
     * Mirrors the MIS reports pattern: reads branch[], dates from GET/POST.
     */
    public function table($id = null, $consulted_date = null, $consulted_to_date = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $data = [];
        $data['consulted_from_date'] = ($consulted_date && strtolower($consulted_date) !== 'null') ? $consulted_date : null;
        $data['consulted_to_date'] = ($consulted_to_date && strtolower($consulted_to_date) !== 'null') ? $consulted_to_date : null;

        // Read branch[] from GET query string (same as MIS reports)
        $selected_branch_id = $this->input->get('branch');
        if (!$selected_branch_id) {
            $selected_branch_id = $this->input->post('branch');
        }

        if (is_array($selected_branch_id)) {
            $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));
            $selected_branch_id = array_map('intval', $selected_branch_id);
        } elseif ($selected_branch_id) {
            $selected_branch_id = urldecode($selected_branch_id);
            $selected_branch_id = explode(',', $selected_branch_id);
            $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));
            $selected_branch_id = array_map('intval', $selected_branch_id);
        } else {
            $selected_branch_id = [];
        }

        // Pass directly — NO staff restriction (same as MIS reports)
        $data['selected_branch_id'] = $selected_branch_id;
        $data['branch_filter_ids'] = $selected_branch_id;
        $data['accessible_branch_ids'] = []; // empty = no restriction
        $data['current_branch_id'] = '';

        $this->app->get_table_data(module_views_path('client', 'tables/get_patient_list'), $data);
    }
}
