<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Calling extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('client/client_model');
        $this->load->model('master/master_model');
        $this->load->model('client/doctor_model');
        $this->load->helper('custom');
        $this->current_branch_id = $this->client_model->get_logged_in_staff_branch_id();
    }

    public function calling($type, $id = NULL, $date_filter = NULL, $from_date = NULL, $to_date = NULL, $selected_branch_id = NULL)
    {
        $data['title'] = _l($type);
        $data['type'] = $type;
        $data['date_filter'] = $date_filter;
        $data['from_date'] = $from_date;
        $data['to_date'] = $to_date;
        $data['master_settings'] = $this->master_model->get_all('master_settings');
        $data['branch'] = $this->client_model->get_branch();

        $selected_branch_id = urldecode($selected_branch_id); // decode %2C to ,
        $selected_branch_id = explode(',', $selected_branch_id); // split by comma

        // Clean the array to ensure numeric values only
        $selected_branch_id = array_filter($selected_branch_id, fn($id) => is_numeric($id));

        // Optional: cast to int
        $selected_branch_id = array_map('intval', $selected_branch_id);

        $data['selected_branch_id'] = $selected_branch_id;

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('calling', 'tables/calling_table'), $data);
        }

        $this->load->model('leads_model');
        $statuses = $this->leads_model->get_status();
        $data['statuses'] = $statuses;

        if (!function_exists('get_counter_by_doctor_id')) {
            function get_counter_by_doctor_id($doctor_id)
            {
                $CI =& get_instance();
                $CI->db->where('doctor_id', $doctor_id);
                return $CI->db->get(db_prefix() . 'counter')->row();
            }
        }

        if ($id) {
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

                $estimate['description'] = $items ? $items->description : '';
            }

            $customer_new_fields = $this->client_model->get_customer_new_fields($id);
            $currencies = $this->currencies_model->get();
            $taxes = $this->taxes_model->get();
            $items_groups = $this->invoice_items_model->get_groups();
            $staff = $this->staff_model->get('', ['active' => 1]);
            $estimate_statuses = $this->estimates_model->get_statuses();
            $base_currency = $this->currencies_model->get_base_currency();

            $items = $this->invoice_items_model->get_grouped();
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

            if (!function_exists('get_estimation_payment_summary')) {
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
            }

            $callback_url = "calling/calling/" . $type;
            $branch = $this->client_model->get_branch();
            // Pass the data to the view - load from client module
            $data['client_modal'] = $this->load->view(module_views_path('client', 'client_model_popup'), [
                'latest_casesheet' => $latest_casesheet,
                'latest_casesheet_package' => $latest_casesheet_package,
                'callback_url' => $callback_url,
                'branch' => $branch,
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
        $this->load->view('calling', $data);
    }
}
