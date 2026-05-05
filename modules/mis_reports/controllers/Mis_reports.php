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

        // Self-healing: create tblgoal_lead_sources if missing
        if (!$this->db->table_exists(db_prefix() . 'goal_lead_sources')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'goal_lead_sources` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `source_id` INT(11) NOT NULL,
                `category` VARCHAR(50) NOT NULL,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_source` (`source_id`)
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
        $selected_branch_id = urldecode($selected_branch_id ?? ''); // decode %2C to ,
        $selected_branch_id = explode(',', $selected_branch_id); // split by comma

        // Clean the array to ensure numeric values only
        $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));

        // Optional: cast to int
        $selected_branch_id = array_map('intval', $selected_branch_id);

        $data['selected_branch_id'] = $selected_branch_id;

        $data['lead_sourceId'] = $lead_sourceId;

        $doctor_id = urldecode($doctor_id ?? ''); // decode %2C to ,
        $doctor_id = explode(',', $doctor_id); // split by comma

        // Clean the array to ensure numeric values only
        $doctor_id = array_filter($doctor_id, fn($id) => is_numeric($id));

        // Optional: cast to int
        $doctor_id = array_map('intval', $doctor_id);

        $data['doctor_id'] = $doctor_id;

        $staff_id = urldecode($staff_id ?? '');
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

    public function gt_report_details($branch_id = '', $from_date = '', $to_date = '', $cell_type = '')
    {
        if ($this->input->is_ajax_request()) {
            $data['branch_id'] = $branch_id;
            $data['from_date'] = $from_date;
            $data['to_date'] = $to_date;
            $data['cell_type'] = $cell_type;

            return $this->app->get_table_data(module_views_path('mis_reports', 'tables/gt_report_details_table'), $data);
        }

        $data['branch_id'] = $branch_id;
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['cell_type'] = $cell_type;
        $data['title'] = 'GT Report Patient Details';

        $this->load->view('mis_reports/reports/gt_report_details', $data);
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
        $data['leads_sources'] = $this->client_model->get_leads_sources();
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

    /**
     * AJAX: save goal lead source assignments for a category
     */
    public function save_goal_lead_sources()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!is_admin() && !staff_can('edit', 'mis_reports')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $category   = $this->db->escape_str($this->input->post('category'));
        $source_ids = $this->input->post('source_ids'); // array or empty

        if (!in_array($category, ['referral', 'enquiry', 'renewal'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid category']);
            exit;
        }

        $table = db_prefix() . 'goal_lead_sources';

        // Check for conflicts: any source_id already assigned to a DIFFERENT category?
        if (!empty($source_ids) && is_array($source_ids)) {
            $source_ids = array_map('intval', $source_ids);
            $this->db->where_in('source_id', $source_ids);
            $this->db->where('category !=', $category);
            $conflicts = $this->db->get($table)->result_array();

            if (!empty($conflicts)) {
                $conflict_ids = array_column($conflicts, 'source_id');
                echo json_encode([
                    'success' => false,
                    'message' => 'Source ID(s) ' . implode(', ', $conflict_ids) . ' already assigned to another category.'
                ]);
                exit;
            }
        }

        // Delete existing assignments for this category
        $this->db->where('category', $category);
        $this->db->delete($table);

        // Insert new assignments
        if (!empty($source_ids) && is_array($source_ids)) {
            foreach ($source_ids as $sid) {
                $this->db->insert($table, [
                    'source_id' => (int) $sid,
                    'category'  => $category,
                ]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Lead sources saved for ' . ucfirst($category)]);
        exit;
    }

    /**
     * AJAX: get all goal lead source assignments
     */
    public function get_goal_lead_sources()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $results = $this->db->get(db_prefix() . 'goal_lead_sources')->result_array();

        $grouped = ['referral' => [], 'enquiry' => [], 'renewal' => []];
        foreach ($results as $row) {
            if (isset($grouped[$row['category']])) {
                $grouped[$row['category']][] = (int) $row['source_id'];
            }
        }

        echo json_encode(['success' => true, 'data' => $grouped]);
        exit;
    }

    /**
     * Staff Referral Reward Report
     * Dedicated method to avoid the monolithic reports() method
     */
    public function staff_referral_reward_report()
    {
        if ($this->input->is_ajax_request()) {
            $data = [];
            $data['from_date'] = $this->input->get('from_date');
            $data['to_date']   = $this->input->get('to_date');
            $data['branch']    = $this->input->get('branch');
            return $this->app->get_table_data(module_views_path('mis_reports', 'tables/staff_referral_reward_report_table'), $data);
        }

        $data['title'] = 'Staff Referral Reward Report';
        $data['branch'] = $this->client_model->get_branch();

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

        $this->load->view('mis_reports/reports/staff_referral_reward_report', $data);
    }

    /**
     * Patient Referral Reward Report
     * Dedicated method to avoid the monolithic reports() method
     */
    public function patient_referral_reward_report()
    {
        if ($this->input->is_ajax_request()) {
            $data = [];
            $data['from_date'] = $this->input->get('from_date');
            $data['to_date']   = $this->input->get('to_date');
            $data['branch']    = $this->input->get('branch');
            return $this->app->get_table_data(module_views_path('mis_reports', 'tables/patient_referral_reward_report_table'), $data);
        }

        $data['title'] = 'Patient Referral Reward Report';
        $data['branch'] = $this->client_model->get_branch();

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

        $this->load->view('mis_reports/reports/patient_referral_reward_report', $data);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MIS QUERY PROFILER — real-time MySQL diagnosis for MIS reports
     * URL: /admin/mis_reports/query_profiler
     * REMOVE THIS METHOD after debugging is complete.
     * ═══════════════════════════════════════════════════════════════
     */
    public function query_profiler()
    {
        if (!is_admin()) {
            access_denied('query_profiler');
        }

        $report     = $this->input->get('report') ?: 'payment_detail_report';
        $from_date  = $this->input->get('from_date') ?: date('Y-m-01');
        $to_date    = $this->input->get('to_date') ?: date('Y-m-d');
        $branch_ids = $this->input->get('branch_ids') ?: '';
        $page_size  = (int) ($this->input->get('page_size') ?: 25);
        if ($page_size < 1) $page_size = 25;

        $data = [
            'title'           => 'MIS Query Profiler',
            'prof_report'     => $report,
            'prof_from_date'  => $from_date,
            'prof_to_date'    => $to_date,
            'prof_branch_ids' => $branch_ids,
            'prof_page_size'  => $page_size,
            'profiler_results'=> [],
            'db_info'         => [],
            'total_time_ms'   => 0,
            'slowest_ms'      => 0,
        ];

        if ($this->input->get('report') !== null) {
            $data = array_merge($data, $this->_run_mis_profiler($report, $from_date, $to_date, $branch_ids, $page_size));
        }

        $this->load->view('mis_query_profiler', $data);
    }

    private function _run_mis_profiler($report, $from_date, $to_date, $branch_ids_str, $page_size)
    {
        $results_out = [];
        $total_time  = 0;
        $slowest     = 0;
        $prefix      = db_prefix();

        $profileQuery = function ($label, $sql) use (&$results_out, &$total_time, &$slowest) {
            $entry = ['label' => $label, 'sql' => $sql, 'time_ms' => 0, 'row_count' => 0, 'error' => '', 'explain' => []];

            $t1 = microtime(true);
            $result = $this->db->query($sql);
            $t2 = microtime(true);
            $entry['time_ms'] = ($t2 - $t1) * 1000;
            $total_time += $entry['time_ms'];
            if ($entry['time_ms'] > $slowest) $slowest = $entry['time_ms'];

            $dbError = $this->db->error();
            if (!empty($dbError['code']) && $dbError['code'] != 0) {
                $entry['error'] = $dbError['code'] . ': ' . $dbError['message'];
            }

            if ($result && is_object($result)) {
                $entry['row_count'] = $result->num_rows();
                $result->free_result();
            }

            $explainResult = $this->db->query('EXPLAIN ' . $sql);
            if ($explainResult && is_object($explainResult)) {
                $entry['explain'] = $explainResult->result_array();
                $explainResult->free_result();
            }

            $results_out[] = $entry;
        };

        // Parse branch IDs
        $branchWhere = '';
        if (!empty($branch_ids_str)) {
            $cleanIds = array_filter(array_map('intval', explode(',', $branch_ids_str)), function($v){ return $v > 0; });
            if (!empty($cleanIds)) {
                $branchWhere = ' AND EXISTS (
                    SELECT 1 FROM ' . $prefix . 'customer_groups cg_filter
                    WHERE cg_filter.customer_id = inv.clientid
                    AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
                )';
            }
        }

        // ═══ Payment Detail Report queries ═══
        if ($report === 'payment_detail_report') {
            $dateWhere = " AND payment.date >= '" . $this->db->escape_str($from_date) . "' AND payment.date <= '" . $this->db->escape_str($to_date) . "'";

            // Q1: Count
            $countSql = 'SELECT COUNT(DISTINCT payment.id) as total_filtered
                FROM ' . $prefix . 'invoicepaymentrecords payment
                LEFT JOIN ' . $prefix . 'invoices inv ON inv.id = payment.invoiceid
                LEFT JOIN ' . $prefix . 'clients c ON c.userid = inv.clientid
                LEFT JOIN ' . $prefix . 'clients_new_fields new ON new.userid = c.userid
                WHERE 1=1' . $dateWhere . $branchWhere;
            $profileQuery('Count Query', $countSql);

            // Q2: Main data
            $mainSql = 'SELECT payment.id, payment.date, payment.amount as paid, payment.received_by,
                payment.transactionid, payment.invoiceid, payment.utr_no,
                c.company, c.userid, item.description as package, new.mr_no,
                mode.name as payment_mode, staff.firstname, staff.lastname,
                sources.name as patient_source, inv.addedfrom, inv.datecreated,
                inv.total as total, branch.name as branch_name,
                payment_category.appointment_type_name
                FROM ' . $prefix . 'invoicepaymentrecords payment
                LEFT JOIN ' . $prefix . 'invoices inv ON inv.id = payment.invoiceid
                LEFT JOIN ' . $prefix . 'itemable item ON item.rel_id = inv.id AND item.rel_type = "invoice"
                LEFT JOIN ' . $prefix . 'payment_modes mode ON mode.id = payment.paymentmode
                LEFT JOIN ' . $prefix . 'staff staff ON staff.staffid = payment.received_by
                LEFT JOIN ' . $prefix . 'clients c ON c.userid = inv.clientid
                LEFT JOIN ' . $prefix . 'clients_new_fields new ON new.userid = c.userid
                LEFT JOIN ' . $prefix . 'customer_groups cc ON cc.customer_id = c.userid
                LEFT JOIN ' . $prefix . 'customers_groups branch ON branch.id = cc.groupid
                LEFT JOIN ' . $prefix . 'leads_sources sources ON sources.id = new.patient_source_id
                LEFT JOIN ' . $prefix . 'appointment_type as payment_category ON payment_category.appointment_type_id = inv.appointment_type_id
                WHERE 1=1' . $dateWhere . $branchWhere . '
                GROUP BY payment.id
                ORDER BY payment.date DESC
                LIMIT ' . $page_size;
            $profileQuery('Main Data Query', $mainSql);

            // Grab payment/user IDs from main query
            $tempResult = $this->db->query($mainSql);
            $paymentIds = [];
            $userIds = [];
            if ($tempResult && is_object($tempResult)) {
                foreach ($tempResult->result_array() as $r) {
                    $paymentIds[] = (int) $r['id'];
                    $userIds[] = (int) $r['userid'];
                }
                $tempResult->free_result();
            }
            $userIds = array_unique($userIds);

            if (!empty($paymentIds)) {
                $pidsStr = implode(',', $paymentIds);
                $uidsStr = implode(',', $userIds);

                // Q3: Cumulative paid batch
                $cumSql = 'SELECT p1.id as payment_id, p1.invoiceid,
                    (SELECT COALESCE(SUM(p2.amount), 0)
                     FROM ' . $prefix . 'invoicepaymentrecords p2
                     WHERE p2.invoiceid = p1.invoiceid
                     AND (p2.date < p1.date OR (p2.date = p1.date AND p2.id <= p1.id))
                    ) as cumulative_paid
                    FROM ' . $prefix . 'invoicepaymentrecords p1
                    WHERE p1.id IN (' . $pidsStr . ')';
                $profileQuery('Cumulative Paid Batch', $cumSql);

                // Q4: Package count batch
                $pkgSql = 'SELECT clientid as userid, COUNT(*) as pkg_count
                    FROM ' . $prefix . 'invoices
                    WHERE clientid IN (' . $uidsStr . ')
                    GROUP BY clientid';
                $profileQuery('Package Count Batch', $pkgSql);

                // Q5: Appointment lookup (simulated)
                $apptSql = 'SELECT a.userid, DATE(a.appointment_date) as appt_date,
                    t.description as treatment_name, type.appointment_type_name
                    FROM ' . $prefix . 'appointment a
                    LEFT JOIN ' . $prefix . 'items t ON t.id = a.treatment_id
                    LEFT JOIN ' . $prefix . 'appointment_type type ON type.appointment_type_id = a.appointment_type_id
                    WHERE a.userid IN (' . $uidsStr . ')
                    AND a.appointment_date >= "' . $this->db->escape_str($from_date) . '"
                    AND a.appointment_date <= "' . $this->db->escape_str($to_date) . ' 23:59:59"
                    ORDER BY a.appointment_date DESC';
                $profileQuery('Appointment Lookup Batch', $apptSql);
            }
        } else {
            // Generic: just run a count on the base tables to show timing
            $profileQuery('Generic: clients count', 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'clients');
            $profileQuery('Generic: invoicepaymentrecords count', 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'invoicepaymentrecords');
            $profileQuery('Generic: appointment count', 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'appointment');
        }

        // ── DB info ──
        $dbInfo = [];
        $vr = $this->db->query('SELECT VERSION() as ver');
        $dbInfo['MySQL Version'] = $vr ? $vr->row()->ver : 'unknown';
        $dbInfo['Database'] = $this->db->database;
        $dbInfo['PHP Version'] = phpversion();
        $dbInfo['Memory Limit'] = ini_get('memory_limit');
        $dbInfo['Max Execution Time'] = ini_get('max_execution_time') . 's';

        $countTables = [
            'tblinvoicepaymentrecords' => 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'invoicepaymentrecords',
            'tblinvoices'              => 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'invoices',
            'tblappointment'           => 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'appointment',
            'tblitemable'              => 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'itemable',
            'tblclients'               => 'SELECT COUNT(*) as cnt FROM ' . $prefix . 'clients',
        ];
        foreach ($countTables as $label => $sql) {
            $r = $this->db->query($sql);
            $dbInfo[$label . ' rows'] = $r ? number_format($r->row()->cnt) : 'error';
        }

        // Index check on key tables
        $indexTables = [$prefix . 'invoicepaymentrecords', $prefix . 'invoices', $prefix . 'itemable'];
        $idxInfo = [];
        foreach ($indexTables as $tbl) {
            $ir = $this->db->query('SHOW INDEX FROM ' . $tbl);
            if ($ir && is_object($ir)) {
                $grouped = [];
                foreach ($ir->result_array() as $idx) {
                    $key = $idx['Key_name'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = $tbl . ' | ' . $key . ' | ' . $idx['Column_name'];
                    } else {
                        $grouped[$key] .= ', ' . $idx['Column_name'];
                    }
                }
                $idxInfo = array_merge($idxInfo, array_values($grouped));
                $ir->free_result();
            }
        }
        $dbInfo['--- Indexes ---'] = '';
        foreach ($idxInfo as $i => $line) {
            $dbInfo['Index ' . ($i + 1)] = $line;
        }

        return [
            'profiler_results' => $results_out,
            'db_info'          => $dbInfo,
            'total_time_ms'    => $total_time,
            'slowest_ms'       => $slowest,
        ];
    }
}

