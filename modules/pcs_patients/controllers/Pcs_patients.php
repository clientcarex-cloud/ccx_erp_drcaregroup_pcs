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
        $this->load->model('client/master_model');
        $this->load->model('leads_model');
        $this->load->helper('client/custom');
    }

    public function index()
    {
        $data['title'] = _l('pcs_patients');
        $data['branch'] = $this->client_model->get_branch();

        // Doctors for appointments tab
        $this->db->select('s.staffid, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'staff s');
        $this->db->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left');
        $this->db->where('s.active', 1);
        $this->db->where_in('r.name', ['Doctor', 'Emergency Doctor', 'FDO', 'Service Doctor']);
        $data['doctors'] = $this->db->get()->result_array();

        // Lead statuses for appointments tab
        $data['statuses'] = $this->leads_model->get_status();

        // Appointment types
        $data['appointment_type'] = $this->master_model->get_all('appointment_type');

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

    public function appointments($id = NULL, $consulted_date = NULL, $consulted_to_date = NULL, $enquiry_doctor_id = NULL, $visit_status = NULL, $branch_id = NULL, $selected_branch_id = NULL, $appointment_type_id = NULL)
    {
        // Normalize incoming values
        $enquiry_doctor_id = ($enquiry_doctor_id === '0' || empty($enquiry_doctor_id)) ? null : $enquiry_doctor_id;
        $branch_id = ($branch_id === '0' || empty($branch_id)) ? null : $branch_id;
        $visit_status = ($visit_status === 'All' || empty($visit_status)) ? null : str_replace('_', ' ', $visit_status);

        // Logged-in staff info
        $staff_id = get_staff_user_id();
        $staff_data = $this->db
            ->select('s.staffid, r.name as role_name')
            ->from(db_prefix() . 'staff s')
            ->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left')
            ->where('s.staffid', $staff_id)
            ->get()
            ->row();

        $data = [
            'title' => _l('Appointments'),
            'consulted_from_date' => $consulted_date,
            'consulted_to_date' => $consulted_to_date,
            'enquiry_doctor_id' => $enquiry_doctor_id,
            'branch_id' => $branch_id,
            'appointment_type_id' => $appointment_type_id,
            'visit_status' => $visit_status,
            'staff_data' => $staff_data,
        ];

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('pcs_patients', 'tables/appointments_table'), $data);
            return;
        }
    }
}
