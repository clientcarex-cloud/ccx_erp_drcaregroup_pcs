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
        $data['branch'] = $this->client_model->get_branch();
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

    /**
     * Render the Export page with date range + branch filter form.
     * Alias kept for backward-compatible URLs.
     */
    public function export_page()
    {
        $data['title']  = 'Export Patients Data';
        $data['branch'] = $this->client_model->get_branch();
        $this->load->view('export_patients', $data);
    }

    /**
     * Export patients — handles BOTH GET (render page) and POST (download file).
     * URL: admin/pcs_patients/export_patients
     */
    public function export_patients()
    {
        if (!staff_can('view', 'pcs_patients') && !is_admin()) {
            access_denied('PCS Patients Export');
        }

        // ── GET request → show the export form page ──
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            $data['title']  = 'Export Patients Data';
            $data['branch'] = $this->client_model->get_branch();
            $this->load->view('export_patients', $data);
            return;
        }

        // ── POST request → generate and download the file ──
        $from_date     = $this->input->post('from_date');
        $to_date       = $this->input->post('to_date');
        $branch_ids    = $this->input->post('branch_ids');
        $export_format = $this->input->post('export_format');
        $branch_ids    = !empty($branch_ids) ? array_filter(explode(',', $branch_ids), 'is_numeric') : [];

        if (empty($export_format)) {
            $export_format = 'excel';
        }

        // ── Fetch all data with error handling ──
        try {
            $export = $this->_build_export_data($from_date, $to_date, $branch_ids);
        } catch (Exception $e) {
            log_message('error', 'PCS Export error: ' . $e->getMessage());
            set_alert('danger', 'An error occurred while generating the export. Please try again.');
            redirect(admin_url('pcs_patients/export_patients'));
            return;
        }

        if ($export === false) {
            set_alert('warning', 'No patients found for the selected date range.');
            redirect(admin_url('pcs_patients/export_patients'));
            return;
        }

        if ($export_format === 'json') {
            $this->_stream_json($export['headers'], $export['rows']);
        } else {
            $this->_stream_csv($export['headers'], $export['rows']);
        }
    }

    /**
     * Build the full export dataset — shared between Excel/JSON exports.
     */
    private function _build_export_data($from_date, $to_date, $branch_ids)
    {
        // ── Build branch filter ──
        $applyBranchFilter = function ($query) use ($branch_ids) {
            if (empty($branch_ids)) return;
            $cleanIds = array_filter(array_map('intval', $branch_ids), function ($v) { return $v > 0; });
            if (empty($cleanIds)) return;
            $query->where('EXISTS (
                SELECT 1 FROM ' . db_prefix() . 'customer_groups cg_filter
                WHERE cg_filter.customer_id = c.userid
                AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
            )', null, false);
        };

        // ── Main query ──
        $this->db->reset_query();
        $this->db->select("
            c.userid, c.company, c.phonenumber, c.email, c.city, c.state, c.address, c.zip, c.datecreated,
            new.mr_no, new.salutation, new.age, new.gender, new.dob, new.email_id,
            new.marital_status, new.area, new.pincode,
            new.whatsapp_number, new.alt_number1, new.alt_number2,
            new.patient_status, new.current_status,
            new.registration_start_date, new.registration_end_date,
            new.patient_source_id, new.reg_by, new.pro_ownership, new.is_refunded,
            source.name as patient_source_name,
            CONCAT_WS(' ', reg_staff.firstname, reg_staff.lastname) as registered_by_name
        ");
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
        $this->db->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');
        $this->db->join(db_prefix() . 'staff reg_staff', 'reg_staff.staffid = new.reg_by', 'left');

        $applyBranchFilter($this->db);

        if (!empty($from_date) && !empty($to_date)) {
            $this->db->where('new.registration_start_date >=', $from_date . ' 00:00:00');
            $this->db->where('new.registration_start_date <=', $to_date . ' 23:59:59');
        }

        $this->db->group_by('c.userid');
        $this->db->order_by('c.userid', 'desc');
        $results = $this->db->get()->result_array();

        if (empty($results)) {
            return false;
        }

        $userIds    = array_map('intval', array_column($results, 'userid'));
        $userIdsStr = implode(',', $userIds);

        // ── Batch: Branch names ──
        $branchNameMap = array();
        $this->db->select('cg_rel.customer_id, GROUP_CONCAT(DISTINCT cg_names.name ORDER BY cg_names.name SEPARATOR ", ") AS branch_names');
        $this->db->from(db_prefix() . 'customer_groups cg_rel');
        $this->db->join(db_prefix() . 'customers_groups cg_names', 'cg_names.id = cg_rel.groupid', 'left');
        $this->db->where_in('cg_rel.customer_id', $userIds);
        $this->db->group_by('cg_rel.customer_id');
        foreach ($this->db->get()->result_array() as $br) {
            $branchNameMap[$br['customer_id']] = $br['branch_names'];
        }

        // ── Batch: Latest appointment ──
        $treatmentMap = array();
        $doctorMap = array();
        $this->db->select("a.userid, i.description AS treatment_name, CONCAT_WS(' ', s.firstname, s.lastname) AS doctor_name");
        $this->db->from(db_prefix() . 'appointment a');
        $this->db->join(
            '(SELECT MAX(appointment_id) AS max_id, userid FROM ' . db_prefix() . 'appointment WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) AS latest',
            'a.appointment_id = latest.max_id', 'INNER'
        );
        $this->db->join(db_prefix() . 'items i', 'i.id = a.treatment_id', 'LEFT');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.enquiry_doctor_id', 'LEFT');
        foreach ($this->db->get()->result_array() as $app) {
            $treatmentMap[$app['userid']] = isset($app['treatment_name']) ? $app['treatment_name'] : '';
            $doctorMap[$app['userid']]    = isset($app['doctor_name']) ? $app['doctor_name'] : '';
        }

        // ── Batch: Latest call log ──
        $callLogMap = array();
        $this->db->select('cl.patientid, cl.created_date as last_calling_date, cl.next_calling_date, cl.comments as call_comments, cl.better_patient');
        $this->db->from(db_prefix() . 'patient_call_logs cl');
        $this->db->join(
            '(SELECT MAX(id) as max_id, patientid FROM ' . db_prefix() . 'patient_call_logs WHERE patientid IN (' . $userIdsStr . ') GROUP BY patientid) as cl_latest',
            'cl.id = cl_latest.max_id', 'inner'
        );
        foreach ($this->db->get()->result_array() as $log) {
            $callLogMap[$log['patientid']] = $log;
        }

        // ── Batch: Latest journey status ──
        $leadStatuses = array();
        $this->db->select('j.userid, ls.name as status_name');
        $this->db->from(db_prefix() . 'lead_patient_journey j');
        $this->db->join(
            '(SELECT userid, MAX(id) AS max_id FROM ' . db_prefix() . 'lead_patient_journey WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) lj_latest',
            'lj_latest.max_id = j.id', 'inner'
        );
        $this->db->join(db_prefix() . 'leads_status ls', 'ls.id = j.status', 'left');
        foreach ($this->db->get()->result_array() as $s) {
            $leadStatuses[$s['userid']] = isset($s['status_name']) ? $s['status_name'] : '';
        }

        // ── Batch: Invoice totals ──
        $invoiceMap = array();
        $this->db->select('clientid, COUNT(*) as invoice_count, SUM(subtotal) as total_amount, SUM(total) as net_total, MIN(date) as first_invoice_date, MAX(date) as last_invoice_date');
        $this->db->from(db_prefix() . 'invoices');
        $this->db->where_in('clientid', $userIds);
        $this->db->group_by('clientid');
        foreach ($this->db->get()->result_array() as $inv) {
            $invoiceMap[$inv['clientid']] = $inv;
        }

        // ── Batch: Payment totals ──
        $paymentMap = array();
        $this->db->select('inv2.clientid, SUM(pay2.amount) as total_paid, COUNT(pay2.id) as payment_count, MAX(pay2.date) as last_payment_date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords pay2');
        $this->db->join(db_prefix() . 'invoices inv2', 'inv2.id = pay2.invoiceid', 'inner');
        $this->db->where_in('inv2.clientid', $userIds);
        $this->db->group_by('inv2.clientid');
        foreach ($this->db->get()->result_array() as $pay) {
            $paymentMap[$pay['clientid']] = $pay;
        }

        // ── Headers ──
        $headers = array(
            'S.No', 'Patient ID', 'Patient Name', 'Salutation', 'MR No', 'Branch',
            'Age', 'Gender', 'DOB', 'Marital Status',
            'Phone', 'WhatsApp', 'Alt Number 1', 'Alt Number 2',
            'Email', 'Address', 'City', 'State', 'Pincode', 'Area',
            'Source', 'Treatment', 'Assigned Doctor', 'Registered By',
            'Registration Start', 'Registration End', 'Date Created',
            'Current Status', 'Patient Status', 'Journey Status',
            'Last Calling Date', 'Next Calling Date', 'Call Comments', 'Better Patient',
            'Invoice Count', 'Total Amount (Gross)', 'Net Total', 'Total Paid',
            'Due Amount', 'Payment Count', 'First Invoice Date', 'Last Invoice Date', 'Last Payment Date',
            'Is Refunded', 'Profile Link'
        );

        // ── Rows ──
        $rows = array();
        $i = 1;
        $baseUrl = admin_url('clients/client/');

        foreach ($results as $row) {
            $uid      = $row['userid'];
            $callLog  = isset($callLogMap[$uid]) ? $callLogMap[$uid] : array();
            $inv      = isset($invoiceMap[$uid]) ? $invoiceMap[$uid] : array();
            $pay      = isset($paymentMap[$uid]) ? $paymentMap[$uid] : array();
            $netTotal = floatval(isset($inv['net_total']) ? $inv['net_total'] : 0);
            $totalPaid = floatval(isset($pay['total_paid']) ? $pay['total_paid'] : 0);

            $regEnd = '';
            if (!empty($row['registration_end_date']) && $row['registration_end_date'] !== '1970-01-01') {
                $regEnd = $row['registration_end_date'];
            }

            $rows[] = array(
                $i++,
                $uid,
                strip_tags(html_entity_decode(isset($row['company']) ? $row['company'] : '', ENT_QUOTES, 'UTF-8')),
                isset($row['salutation']) ? $row['salutation'] : '',
                isset($row['mr_no']) ? $row['mr_no'] : '',
                isset($branchNameMap[$uid]) ? $branchNameMap[$uid] : '',
                isset($row['age']) ? $row['age'] : '',
                isset($row['gender']) ? $row['gender'] : '',
                isset($row['dob']) ? $row['dob'] : '',
                isset($row['marital_status']) ? $row['marital_status'] : '',
                isset($row['phonenumber']) ? $row['phonenumber'] : '',
                isset($row['whatsapp_number']) ? $row['whatsapp_number'] : '',
                isset($row['alt_number1']) ? $row['alt_number1'] : '',
                isset($row['alt_number2']) ? $row['alt_number2'] : '',
                isset($row['email_id']) ? $row['email_id'] : (isset($row['email']) ? $row['email'] : ''),
                isset($row['address']) ? $row['address'] : '',
                isset($row['city']) ? $row['city'] : '',
                isset($row['state']) ? $row['state'] : '',
                isset($row['pincode']) ? $row['pincode'] : (isset($row['zip']) ? $row['zip'] : ''),
                isset($row['area']) ? $row['area'] : '',
                isset($row['patient_source_name']) ? $row['patient_source_name'] : '',
                isset($treatmentMap[$uid]) ? $treatmentMap[$uid] : '',
                isset($doctorMap[$uid]) ? $doctorMap[$uid] : '',
                isset($row['registered_by_name']) ? $row['registered_by_name'] : '',
                isset($row['registration_start_date']) ? $row['registration_start_date'] : '',
                $regEnd,
                isset($row['datecreated']) ? $row['datecreated'] : '',
                isset($row['current_status']) ? $row['current_status'] : '',
                isset($row['patient_status']) ? $row['patient_status'] : '',
                isset($leadStatuses[$uid]) ? $leadStatuses[$uid] : '',
                isset($callLog['last_calling_date']) ? $callLog['last_calling_date'] : '',
                isset($callLog['next_calling_date']) ? $callLog['next_calling_date'] : '',
                isset($callLog['call_comments']) ? $callLog['call_comments'] : '',
                isset($callLog['better_patient']) ? $callLog['better_patient'] : '',
                isset($inv['invoice_count']) ? $inv['invoice_count'] : 0,
                isset($inv['total_amount']) ? $inv['total_amount'] : 0,
                isset($inv['net_total']) ? $inv['net_total'] : 0,
                isset($pay['total_paid']) ? $pay['total_paid'] : 0,
                round($netTotal - $totalPaid, 2),
                isset($pay['payment_count']) ? $pay['payment_count'] : 0,
                isset($inv['first_invoice_date']) ? $inv['first_invoice_date'] : '',
                isset($inv['last_invoice_date']) ? $inv['last_invoice_date'] : '',
                isset($pay['last_payment_date']) ? $pay['last_payment_date'] : '',
                (!empty($row['is_refunded']) && $row['is_refunded'] == 1) ? 'Yes' : 'No',
                $baseUrl . $uid,
            );
        }

        return array('headers' => $headers, 'rows' => $rows);
    }

    // ════════════════════════════════════════════════════════════════
    // CSV/Excel Export (universal compatibility, no ZipArchive needed)
    // ════════════════════════════════════════════════════════════════

    private function _stream_csv($headers, $rows)
    {
        $filename = 'pcs_patients_export_' . date('Ymd_His') . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // BOM for Excel UTF-8 recognition
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, $headers);

        // Data rows
        foreach ($rows as $row) {
            $clean = array();
            foreach ($row as $cell) {
                $clean[] = (string) $cell;
            }
            fputcsv($output, $clean);
        }

        fclose($output);
        exit;
    }

    // ════════════════════════════════════════════════════════════════
    // JSON Export
    // ════════════════════════════════════════════════════════════════

    private function _stream_json($headers, $rows)
    {
        $filename = 'pcs_patients_export_' . date('Ymd_His') . '.json';

        // Build associative array using headers as keys
        $jsonData = array();
        foreach ($rows as $row) {
            $record = array();
            foreach ($headers as $idx => $header) {
                $record[$header] = isset($row[$idx]) ? $row[$idx] : '';
            }
            $jsonData[] = $record;
        }

        $jsonString = json_encode(array(
            'exported_at' => date('Y-m-d H:i:s'),
            'total_records' => count($jsonData),
            'patients' => $jsonData,
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . strlen($jsonString));

        echo $jsonString;
        exit;
    }
}
