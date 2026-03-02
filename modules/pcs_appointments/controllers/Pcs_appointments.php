<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pcs_appointments extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!staff_can('view', 'pcs_appointments') && !is_admin()) {
            access_denied('PCS Appointments');
        }
        $this->load->model('client/client_model');
        $this->load->model('client/master_model');
        $this->load->model('client/doctor_model');
        $this->load->model('leads_model');
        $this->load->helper('custom');
        $this->current_branch_id = $this->client_model->get_logged_in_staff_branch_id();
    }

    public function index()
    {
        $data['title'] = _l('pcs_appointments');

        // Branch data
        $staff_branch_ids = $this->_normalize_branch_ids($this->current_branch_id);
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
        $data['current_branch_id'] = $this->current_branch_id;

        // Dropdown data
        $data['doctors'] = $this->doctor_model->get_doctors();
        $data['statuses'] = $this->leads_model->get_status();
        $data['appointment_type'] = $this->master_model->get_all('appointment_type');

        $this->load->view('manage', $data);
    }

    /**
     * AJAX endpoint for appointments DataTable
     */
    public function appointments($id = NULL, $consulted_date = NULL, $consulted_to_date = NULL, $enquiry_doctor_id = NULL, $visit_status = NULL, $branch_id = NULL, $selected_branch_id = NULL, $appointment_type_id = NULL)
    {
        $enquiry_doctor_id = ($enquiry_doctor_id === '0' || empty($enquiry_doctor_id)) ? null : $enquiry_doctor_id;
        $branch_id = ($branch_id === '0' || empty($branch_id)) ? null : $branch_id;
        $visit_status = ($visit_status === 'All' || empty($visit_status)) ? null : str_replace('_', ' ', $visit_status);

        $selected_branch_id = urldecode($selected_branch_id);
        $selected_branch_id = explode(',', $selected_branch_id);
        $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));
        $selected_branch_id = array_map('intval', $selected_branch_id);

        $staff_id = get_staff_user_id();
        $staff_data = $this->db
            ->select('s.staffid, r.name as role_name')
            ->from(db_prefix() . 'staff s')
            ->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left')
            ->where('s.staffid', $staff_id)
            ->get()
            ->row();

        $data = [
            'title' => _l('pcs_appointments'),
            'consulted_from_date' => $consulted_date,
            'consulted_to_date' => $consulted_to_date,
            'enquiry_doctor_id' => $enquiry_doctor_id,
            'branch_id' => $branch_id,
            'appointment_type_id' => $appointment_type_id,
            'selected_branch_id' => $selected_branch_id,
            'visit_status' => $visit_status,
            'staff_data' => $staff_data,
        ];

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('pcs_appointments', 'tables/appointments_table'), $data);
            return;
        }
    }

    /**
     * AJAX endpoint for appointment summary card counts
     */
    public function get_appointment_summary()
    {
        $from = $this->input->post('from_date');
        $to = $this->input->post('to_date');
        $enquiry_doctor_id = $this->input->post('enquiry_doctor_id');
        $branch_id = $this->input->post('branch_id');
        $appointment_type_id = $this->input->post('appointment_type_id');

        $decodeParam = static function ($value) {
            if ($value === null)
                return '';
            $value = (string) $value;
            if ($value === '' || strtolower($value) === 'null')
                return '';
            do {
                $decoded = rawurldecode($value);
                if ($decoded === $value)
                    break;
                $value = $decoded;
            } while (true);
            return $value;
        };

        $selected_branch_decoded = $decodeParam($branch_id);
        $selected_branch_id = array_filter(array_map('intval', array_filter(explode(',', $selected_branch_decoded), fn($id) => $id !== '' && is_numeric($id))));

        $staff_id = get_staff_user_id();
        $staff_data = $this->db
            ->select('s.staffid, r.name as role_name')
            ->from(db_prefix() . 'staff s')
            ->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left')
            ->where('s.staffid', $staff_id)
            ->get()
            ->row();

        $from_date = $from ? to_sql_date($from) : null;
        $to_date = $to ? to_sql_date($to) : null;

        // Total
        $this->db->from(db_prefix() . 'appointment');
        if ($from_date && $to_date) {
            $this->db->where("DATE(appointment_date) BETWEEN '$from_date' AND '$to_date'");
        } elseif ($from_date) {
            $this->db->where('DATE(appointment_date)', $from_date);
        } else {
            $from_date = date('Y-m-d');
            $to_date = date('Y-m-d');
            $this->db->where("DATE(appointment_date) BETWEEN '$from_date' AND '$to_date'");
        }
        if (!staff_can('view_global_appointments', 'customers')) {
            if (!empty($staff_data) && in_array(strtolower($staff_data->role_name), ['doctor'])) {
                $this->db->where('enquiry_doctor_id', $staff_data->staffid);
            } else {
                if (!empty($enquiry_doctor_id)) {
                    $this->db->where('enquiry_doctor_id', $enquiry_doctor_id);
                }
            }
        }
        if (!empty($appointment_type_id)) {
            $this->db->where('appointment_type_id', $appointment_type_id);
        }
        if (!empty($selected_branch_id)) {
            if ($branch_id != 0) {
                $this->db->where_in('branch_id', $selected_branch_id);
            }
        }
        $total_appointments = $this->db->count_all_results();

        // Missed
        $this->db->from(db_prefix() . 'appointment');
        $this->db->where('visit_status', 0);
        if ($from_date && $to_date) {
            $this->db->where("DATE(appointment_date) BETWEEN '$from_date' AND '$to_date'");
            $this->db->where('DATE(appointment_date) <', date('Y-m-d'));
        } elseif ($from_date) {
            $this->db->where('DATE(appointment_date)', $from_date);
            $this->db->where('DATE(appointment_date) <', date('Y-m-d'));
        } else {
            $this->db->where('DATE(appointment_date) <', date('Y-m-d'));
        }
        if (!empty($staff_data) && in_array(strtolower($staff_data->role_name), ['doctor'])) {
            $this->db->where('enquiry_doctor_id', $staff_data->staffid);
        } elseif (!empty($enquiry_doctor_id)) {
            $this->db->where('enquiry_doctor_id', $enquiry_doctor_id);
        }
        if (!empty($selected_branch_id)) {
            if ($branch_id != 0) {
                $this->db->where_in('branch_id', $selected_branch_id);
            }
        }
        if (!empty($appointment_type_id)) {
            $this->db->where('appointment_type_id', $appointment_type_id);
        }
        $missed = $this->db->count_all_results();

        // Consulted
        $this->db->from(db_prefix() . 'appointment');
        $this->db->where('visit_status', 1);
        $this->db->where('consulted_date IS NOT NULL', null, false);
        if ($from_date && $to_date) {
            $this->db->where("DATE(appointment_date) BETWEEN '$from_date' AND '$to_date'");
        } elseif ($from_date) {
            $this->db->where('DATE(appointment_date)', $from_date);
        }
        if (!empty($staff_data) && in_array(strtolower($staff_data->role_name), ['doctor'])) {
            $this->db->where('enquiry_doctor_id', $staff_data->staffid);
        } else {
            if (!empty($enquiry_doctor_id)) {
                $this->db->where('enquiry_doctor_id', $enquiry_doctor_id);
            }
        }
        if (!empty($selected_branch_id)) {
            if ($branch_id != 0) {
                $this->db->where_in('branch_id', $selected_branch_id);
            }
        }
        if (!empty($appointment_type_id)) {
            $this->db->where('appointment_type_id', $appointment_type_id);
        }
        $consulted = $this->db->count_all_results();

        echo json_encode([
            'total' => $total_appointments,
            'missed' => $missed,
            'consulted' => $consulted
        ]);
    }

    /**
     * Normalize branch IDs from various input formats into a clean int array
     */
    private function _normalize_branch_ids($value)
    {
        if ($value === null || $value === '')
            return [];
        if (is_array($value)) {
            $list = $value;
        } else {
            $list = explode(',', (string) $value);
        }
        $normalized = [];
        foreach ($list as $item) {
            if ($item === null)
                continue;
            $item = (string) $item;
            if ($item === '' || strtolower($item) === 'null' || !is_numeric($item))
                continue;
            $normalized[] = (int) $item;
        }
        return array_values(array_unique($normalized));
    }
}
