<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mis_reports extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin() && !staff_can('view', 'mis_reports')) {
            access_denied('MIS Reports');
        }
        $this->load->model('client/client_model');
        $this->load->model('client/master_model');
        $this->load->model('client/doctor_model');
        $this->load->model('client/staff_model');
        $this->load->helper('client/custom');

        // Self-healing: create tblreport_goals if missing
        if (!$this->db->table_exists(db_prefix() . 'report_goals')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'report_goals` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `branch_id` INT(11) NOT NULL,
                `year` INT(4) NOT NULL,
                `month` INT(2) NOT NULL,
                `goal_type` VARCHAR(50) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_goal` (`branch_id`, `year`, `month`, `goal_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }
    }

    public function index()
    {
        // Fallback to load the main MIS reports view if anyone visits the root module route
        redirect(admin_url('mis_reports/reports/mis_reports'));
    }

    public function reports($type, $id = NULL, $consulted_date = NULL, $consulted_to_date = NULL, $appointment_type = NULL, $selected_branch_id = NULL, $doctor_id = NULL, $staff_id = NULL, $category = NULL, $lead_sourceId = NULL)
    {
        $data['title'] = _l($type);
        $data['type'] = $type;
        $data['category'] = $category;

        // Override with POST data if available (from DataTables ajax)
        if ($this->input->post('consulted_date')) {
            $consulted_date = $this->input->post('consulted_date');
        }
        if ($this->input->post('consulted_to_date')) {
            $consulted_to_date = $this->input->post('consulted_to_date');
        }
        if ($this->input->post('appointment_type')) {
            $appointment_type = $this->input->post('appointment_type');
        }
        if ($this->input->post('branch')) {
            $selected_branch_id = $this->input->post('branch');
            if (is_array($selected_branch_id)) {
                $selected_branch_id = implode(',', $selected_branch_id);
            }
        }
        if ($this->input->post('doctor_id')) {
            $doctor_id = $this->input->post('doctor_id');
        }
        if ($this->input->post('staff_id')) {
            $staff_id = $this->input->post('staff_id');
        }

        $data['consulted_from_date'] = $consulted_date;
        $data['consulted_to_date'] = $consulted_to_date;
        $data['appointment_type_id'] = $appointment_type;
        $selected_branch_id = urldecode($selected_branch_id); // decode %2C to ,
        $selected_branch_id = explode(',', $selected_branch_id); // split by comma

        // Clean the array to ensure numeric values only
        $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));

        // Optional: cast to int
        $selected_branch_id = array_map('intval', $selected_branch_id);

        $data['selected_branch_id'] = $selected_branch_id;

        $data['lead_sourceId'] = $lead_sourceId;

        $doctor_id = urldecode($doctor_id); // decode %2C to ,
        $doctor_id = explode(',', $doctor_id); // split by comma

        // Clean the array to ensure numeric values only
        $doctor_id = array_filter($doctor_id, fn($id) => is_numeric($id));

        // Optional: cast to int
        $doctor_id = array_map('intval', $doctor_id);

        $data['doctor_id'] = $doctor_id;

        $staff_id = urldecode($staff_id);
        $staff_id = explode(',', $staff_id);
        $staff_id = array_filter($staff_id, fn($id) => is_numeric($id));
        $staff_id = array_map('intval', $staff_id);
        $data['staff_id_filter'] = $staff_id;

        //Getting payment modes
        $this->db->select('name');
        $this->db->from(db_prefix() . 'payment_modes');
        $this->db->where('active', 1);
        $payment_modes = $this->db->get()->result_array();

        $data['payment_modes'] = $payment_modes;
        $data['master_settings'] = $this->master_model->get_all('master_settings');
        $data['branch'] = $this->client_model->get_branch();

        // Filter staff by specific roles
        $this->db->select('s.staffid, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'staff s');
        $this->db->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left');
        $this->db->where('s.active', 1);
        $this->db->where_in('r.name', ['Doctor', 'Emergency Doctor', 'FDO', 'Service Doctor']);
        $data['staff'] = $this->db->get()->result_array();

        $data['roles'] = $this->client_model->get_roles();
        $data['leads_sources'] = $this->client_model->get_leads_sources();
        $data['appointment_type'] = $this->master_model->get_all('appointment_type');
        $data['doctors'] = $this->doctor_model->get_doctors();
        $current_staff_user_id = get_staff_user_id();
        if ($current_staff_user_id) {
            $this->db->select('branch_id');
            $this->db->from(db_prefix() . 'staff');
            $this->db->where('staffid', $current_staff_user_id);
            $row = $this->db->get()->row();
            if ($row) {
                $data['branch_id'] = $row->branch_id;
            }
        }

        function get_counter_by_doctor_id($doctor_id)
        {
            $CI =& get_instance();
            $CI->db->where('doctor_id', $doctor_id);
            return $CI->db->get(db_prefix() . 'counter')->row(); // returns single row (object)
        }

        $statuses = $this->leads_model->get_status();

        if ($id && $id != 'NULL') {
            $this->load->model('leads_model');
            $this->load->model('currencies_model');
            $this->load->model('taxes_model');
            $this->load->model('invoice_items_model');
            $this->load->model('estimates_model');
            $data['clientid'] = $id;
            // Fetch the existing patient data
            $client = $this->client_model->get($id);
            $estimates = $this->client_model->get_estimates($id);
            foreach ($estimates as &$estimate) {
                $this->db->select('description');
                $this->db->where('rel_type', 'estimate');
                $this->db->where('rel_id', $estimate['id']);
                $items = $this->db->get('tblitemable')->row();

                $estimate['description'] = $items->description; // append to estimate
            }

            $customer_new_fields = $this->client_model->get_customer_new_fields($id);
            $currencies = $this->currencies_model->get();
            $taxes = $this->taxes_model->get();
            $items_groups = $this->invoice_items_model->get_groups();
            $staff = $this->staff_model->get('', ['active' => 1]);
            $estimate_statuses = $this->estimates_model->get_statuses();
            $base_currency = $this->currencies_model->get_base_currency();

            $items = $this->invoice_items_model->get_grouped();
            $first_appointment = $this->client_model->get_first_appointment($id);
            $appointment_data = $this->client_model->get_appointment_data($id);
            $patient_activity_log = $this->client_model->get_patient_activity_log($id);
            $patient_prescription = $this->client_model->get_patient_prescription($id);

            $patient_treatment = $this->client_model->get_patient_treatment($id);
            $casesheet = $this->client_model->get_casesheet($id);
            // Fetch patient call logs
            $patient_call_logs = $this->client_model->get_patient_call_logs($id);
            $invoices = $this->client_model->get_invoices($id);
            $invoice_payments = $this->client_model->get_invoice_payments($id);
            $shared_requests = $this->client_model->get_shared_requests($id);

            // Fetch medicine data (names, potencies, doses, timings)
            $medicines = $this->master_model->get_all('medicine');
            $potencies = $this->master_model->get_all('medicine_potency');
            $doses = $this->master_model->get_all('medicine_dose');
            $timings = $this->master_model->get_all('medicine_timing');
            $appointment_type = $this->master_model->get_all('appointment_type');
            $criteria = $this->master_model->get_all('criteria');
            $treatments = $this->master_model->get_all('treatment');
            $patient_status = $this->master_model->get_all('patient_status');
            $master_settings = $this->master_model->get_all('master_settings');
            $suggested_diagnostics = $this->master_model->get_all('suggested_diagnostics');
            $testimonials = $this->client_model->get_testimonial();
            $latest_casesheet = $this->client_model->get_latest_casesheet($id);
            $latest_casesheet_package = $this->client_model->get_latest_casesheet_package($id);

            $payment_modes = $this->client_model->get_payment_modes();

            $doctors = $this->doctor_model->get_doctors();

            $today_appointment_data = $this->client_model->get_today_appointment_data($id);

            $staff_id_current = get_staff_user_id();

            $staff_data = $this->db
                ->select('s.staffid, r.name as role_name')
                ->from(db_prefix() . 'staff s')
                ->join(db_prefix() . 'roles r', 'r.roleid = s.role', 'left')
                ->where('s.staffid', $staff_id_current)
                ->get()
                ->row();

            function get_estimation_payment_summary($estimation_id)
            {
                $CI =& get_instance();

                // 1. Get the estimate row
                $CI->db->select('total, invoiceid, currency, date, expirydate');
                $CI->db->where('id', $estimation_id);
                $estimate = $CI->db->get(db_prefix() . 'estimates')->row();

                if (!$estimate || !$estimate->invoiceid) {
                    return [
                        'total' => 0,
                        'paid' => 0,
                        'dues' => 0,
                        'currency' => '',
                        'invoice_id' => null,
                    ];
                }

                // 2. Sum payments from invoicepaymentrecords
                $CI->db->select_sum('amount');
                $CI->db->where('invoiceid', $estimate->invoiceid);
                $paid_row = $CI->db->get(db_prefix() . 'invoicepaymentrecords')->row();
                $paid = $paid_row ? (float) $paid_row->amount : 0;

                return [
                    'total' => (float) $estimate->total,
                    'paid' => $paid,
                    'dues' => (float) $estimate->total - $paid,
                    'currency' => $estimate->currency,
                    'invoice_id' => $estimate->invoiceid,
                    'date' => $estimate->date,
                    'expirydate' => $estimate->expirydate,
                ];
            }
            $callback_url = "reports/" . $type;
            $home_branch_id = $this->client_model->get_client_branch($id);
            $branch_list = $this->client_model->get_branch();
            // Pass the data to the view
            $data['client_modal'] = $this->load->view('client/client_model_popup', [
                'latest_casesheet' => $latest_casesheet,
                'latest_casesheet_package' => $latest_casesheet_package,
                'first_appointment' => $first_appointment,
                'callback_url' => $callback_url,
                'home_branch_id' => $home_branch_id,
                'branch' => $branch_list,
                'doctors' => $doctors,
                'statuses' => $statuses,
                'estimates' => $estimates,
                'payment_modes' => $payment_modes,
                'client' => $client,
                'casesheet' => $casesheet,
                'testimonials' => $testimonials,
                'shared_requests' => $shared_requests,
                'master_settings' => $master_settings,
                'suggested_diagnostics' => $suggested_diagnostics,
                'customer_new_fields' => $customer_new_fields,
                'currencies' => $currencies,
                'staff_data' => $staff_data,
                'taxes' => $taxes,
                'items' => $items,
                'base_currency' => $base_currency,
                'items_groups' => $items_groups,
                'staff' => $staff,
                'estimate_statuses' => $estimate_statuses,
                'appointment_data' => $appointment_data,
                'today_appointment_data' => $today_appointment_data,
                'patient_activity_log' => $patient_activity_log,
                'patient_call_logs' => $patient_call_logs,
                'patient_prescriptions' => $patient_prescription,
                'patient_treatment' => $patient_treatment,
                'medicines' => $medicines,
                'potencies' => $potencies,
                'appointment_type' => $appointment_type,
                'criteria' => $criteria,
                'doses' => $doses,
                'treatments' => $treatments,
                'patient_status' => $patient_status,
                'invoices' => $invoices,
                'invoice_payments' => $invoice_payments,
                'timings' => $timings
            ], true);
        }

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mis_reports', 'tables/' . $type . "_table"), $data);
        } else {
            $this->load->view('mis_reports/reports/' . $type, $data);
        }
    }

    public function source_enquiry_detail_report($source_id, $from = null, $to = null, $subtype = null)
    {
        if ($this->input->is_ajax_request()) {
            $data['source_id'] = $source_id;
            $data['from_date'] = ($from !== '-' ? $from : null);
            $data['to_date'] = ($to !== '-' ? $to : null);
            $data['subtype'] = $subtype;

            return $this->app->get_table_data(module_views_path('mis_reports', 'tables/' . $subtype . '_table'), $data);
        }

        $data['source_id'] = $source_id;
        $data['from_date'] = ($from !== '-' ? $from : null);
        $data['to_date'] = ($to !== '-' ? $to : null);
        $data['subtype'] = $subtype;
        $data['title'] = 'Source Enquiry Detail Report';

        $this->load->view('mis_reports/reports/source_enquiry_detail_report', $data);
    }

    public function get_branch_report_data()
    {
        $data = [
            [
                'branch_name' => 'TEST BRANCH',
                'total_registrations' => 3,
                'ref_reg' => 0,
                'package_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'walkin_reg' => 0,

                'cc_reg' => 3,
                'enquiry_package_amount' => '38,200',
                'enquiry_paid_amount' => '37,300',
                'enquiry_due_amount' => '900',
                'enquiry_blank1' => '',
                'enquiry_blank2' => '',

                'renewal_reg' => 0,
                'renewal_package_amount' => 0,
                'renewal_paid_amount' => 0,
                'renewal_due_amount' => 0,
            ],
            [
                'branch_name' => '<strong>Grand Total</strong>',
                'total_registrations' => 3,
                'ref_reg' => 0,
                'package_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'walkin_reg' => 0,

                'cc_reg' => 3,
                'enquiry_package_amount' => '<strong>38,200</strong>',
                'enquiry_paid_amount' => '<strong>37,300</strong>',
                'enquiry_due_amount' => '<strong>900</strong>',
                'enquiry_blank1' => '',
                'enquiry_blank2' => '',

                'renewal_reg' => 0,
                'renewal_package_amount' => 0,
                'renewal_paid_amount' => 0,
                'renewal_due_amount' => 0,
            ],
        ];

        echo json_encode($data);
        exit;
    }

    /**
     * Reports Goals settings page
     */
    public function report_goals()
    {
        if (!is_admin() && !staff_can('edit', 'mis_reports')) {
            access_denied('MIS Reports');
        }

        $data['title'] = 'Reports Goals';
        $data['branch'] = $this->client_model->get_branch();
        $this->load->view('mis_reports/reports/report_goals', $data);
    }

    /**
     * AJAX: save report goals
     */
    public function save_report_goals()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!is_admin() && !staff_can('edit', 'mis_reports')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $goals = $this->input->post('goals');
        if (!$goals || !is_array($goals)) {
            echo json_encode(['success' => false, 'message' => 'No data received']);
            exit;
        }

        $table = db_prefix() . 'report_goals';
        foreach ($goals as $goal) {
            $branch_id = (int) $goal['branch_id'];
            $year      = (int) $goal['year'];
            $month     = (int) $goal['month'];
            $goal_type = $this->db->escape_str($goal['goal_type']);
            $amount    = (float) $goal['amount'];

            // Upsert: INSERT ... ON DUPLICATE KEY UPDATE
            $this->db->query("
                INSERT INTO `$table` (`branch_id`, `year`, `month`, `goal_type`, `amount`)
                VALUES ($branch_id, $year, $month, '$goal_type', $amount)
                ON DUPLICATE KEY UPDATE `amount` = $amount
            ");
        }

        echo json_encode(['success' => true, 'message' => 'Goals saved successfully']);
        exit;
    }

    /**
     * AJAX: get report goals for a branch + year
     */
    public function get_report_goals()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $branch_id = (int) $this->input->get('branch_id');
        $year      = (int) $this->input->get('year');

        $this->db->where('branch_id', $branch_id);
        $this->db->where('year', $year);
        $results = $this->db->get(db_prefix() . 'report_goals')->result_array();

        // Return keyed by "month_goaltype" for easy JS lookup
        $goals = [];
        foreach ($results as $row) {
            $key = $row['month'] . '_' . $row['goal_type'];
            $goals[$key] = (float) $row['amount'];
        }

        echo json_encode(['success' => true, 'goals' => $goals]);
        exit;
    }
}
