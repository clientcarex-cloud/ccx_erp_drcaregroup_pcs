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
     * Bypasses staff branch restriction so ANY branch can be filtered.
     */
    public function table($id = null, $consulted_date = null, $consulted_to_date = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $data = [];
        $data['consulted_from_date'] = ($consulted_date && strtolower($consulted_date) !== 'null') ? $consulted_date : null;
        $data['consulted_to_date'] = ($consulted_to_date && strtolower($consulted_to_date) !== 'null') ? $consulted_to_date : null;

        // Read branch_ids from POST (sent by preXhr.dt)
        $branch_ids_raw = $this->input->post('branch_ids');
        $branch_filter = [];
        if ($branch_ids_raw) {
            $parts = explode(',', (string) $branch_ids_raw);
            foreach ($parts as $part) {
                $part = trim($part);
                if (is_numeric($part) && (int) $part > 0) {
                    $branch_filter[] = (int) $part;
                }
            }
            $branch_filter = array_values(array_unique($branch_filter));
        }

        // Pass branch filter directly — NO staff restriction
        $data['branch_filter_ids'] = $branch_filter;
        $data['selected_branch_id'] = $branch_filter;
        $data['accessible_branch_ids'] = []; // empty = no restriction
        $data['current_branch_id'] = '';

        $this->app->get_table_data(module_views_path('client', 'tables/get_patient_list'), $data);
    }
}
