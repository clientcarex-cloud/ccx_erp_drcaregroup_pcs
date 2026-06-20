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

        // Increase limits to prevent large dataset crashes
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);

        try {
            // -- POST request: generate and download the file --
            $from_date     = $this->input->post('from_date');
            $to_date       = $this->input->post('to_date');
            $branch_ids    = $this->input->post('branch_ids');
            $export_format = $this->input->post('export_format');
            $branch_ids    = !empty($branch_ids) ? array_filter(explode(',', $branch_ids), 'is_numeric') : [];

            if (empty($export_format)) {
                $export_format = 'excel';
            }

            // -- Branch-wise ZIP: one file per branch, bundled into a single .zip --
            if ($export_format === 'zip') {
                $this->_stream_branchwise_zip($from_date, $to_date, $branch_ids);
                return;
            }

            // -- Fetch all data --
            $export = $this->_build_export_data($from_date, $to_date, $branch_ids);

            if ($export === false) {
                set_alert('warning', 'No patients found for the selected date range.');
                redirect(admin_url('pcs_patients/export_patients'));
                return;
            }

            if (is_string($export)) {
                // _build_export_data returned an error message
                set_alert('danger', $export);
                redirect(admin_url('pcs_patients/export_patients'));
                return;
            }

            // Collect all patient user IDs for detail sheet queries
            $allUserIds = array_map('intval', array_column($export['rows'], 1)); // col index 1 = Patient ID

            // Build detail sheets once — shared by Excel and JSON
            $detailSheets = array();
            if ($export_format === 'excel' || $export_format === 'json') {
                $detailSheets = $this->_build_detail_sheets($allUserIds);
            }

            if ($export_format === 'json') {
                $this->_stream_json($export['headers'], $export['rows'], $detailSheets, $from_date, $to_date);
            } elseif ($export_format === 'print') {
                echo "<html><head><title>Live Data Print / Console</title></head><body style='font-family:sans-serif;'>";
                echo "<h2>Live Data Print / Console Debug</h2>";
                echo "<p>Total Records: " . count($export['rows']) . "</p>";
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width:100%; font-size:12px;'>";
                echo "<thead style='background:#f4f4f4; position:sticky; top:0;'><tr>";
                foreach ($export['headers'] as $h) {
                    echo "<th>$h</th>";
                }
                echo "</tr></thead><tbody>";
                foreach ($export['rows'] as $r) {
                    echo "<tr>";
                    foreach ($r as $c) {
                        echo "<td>" . htmlspecialchars((string)$c) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody></table></body></html>";
                exit;
            } elseif ($export_format === 'csv') {
                $this->_stream_csv($export['headers'], $export['rows'], $from_date, $to_date);
            } else {
                // Default: Multi-sheet XLSX with detail tabs
                $this->_stream_xlsx($export['headers'], $export['rows'], $detailSheets, $from_date, $to_date);
            }
        } catch (\Throwable $th) {
            echo "<div style='font-family:monospace; background:#1e1e1e; color:#00ff00; padding:20px;'>";
            echo "<h2 style='color:#ff0000;'>[!] EXPORT CRASHED</h2>";
            echo "<strong>Error Message:</strong> " . $th->getMessage() . "<br><br>";
            echo "<strong>File:</strong> " . $th->getFile() . " on line " . $th->getLine() . "<br><br>";
            echo "<strong>Stack Trace:</strong><br><pre>" . $th->getTraceAsString() . "</pre>";
            echo "</div>";
            die();
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
            c.userid, c.company, c.phonenumber, contact.email, c.city, c.state, c.address, c.zip, c.datecreated, c.default_language,
            new.mr_no, new.salutation, new.age, new.gender, new.dob, new.email_id,
            new.marital_status, new.area, new.pincode,
            new.whatsapp_number, new.alt_number1, new.alt_number2,
            new.patient_status, new.current_status,
            new.registration_start_date, new.registration_end_date,
            new.patient_source_id, new.reg_by, new.pro_ownership, new.is_refunded,
            source.name as patient_source_name,
            CONCAT_WS(' ', reg_staff.firstname, reg_staff.lastname) as registered_by_name,
            country.short_name as country_name,
            CONCAT_WS(' ', pro_staff.firstname, pro_staff.lastname) as pro_ownership_name
        ", false);
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts contact', 'contact.userid = c.userid AND contact.is_primary = 1', 'left');
        $this->db->join(db_prefix() . 'clients_new_fields new', 'new.userid = c.userid', 'left');
        $this->db->join(db_prefix() . 'leads_sources source', 'source.id = new.patient_source_id', 'left');
        $this->db->join(db_prefix() . 'staff reg_staff', 'reg_staff.staffid = new.reg_by', 'left');
        $this->db->join(db_prefix() . 'countries country', 'country.country_id = c.country', 'left');
        $this->db->join(db_prefix() . 'staff pro_staff', 'pro_staff.staffid = new.pro_ownership', 'left');

        $applyBranchFilter($this->db);

        if (!empty($from_date) && !empty($to_date)) {
            $this->db->where('new.registration_start_date >=', $from_date . ' 00:00:00');
            $this->db->where('new.registration_start_date <=', $to_date . ' 23:59:59');
        }

        $this->db->group_by('c.userid');
        $this->db->order_by('c.userid', 'desc');
        
        $main_query = $this->db->get();
        if (!$main_query) {
            $db_error = $this->db->error();
            throw new \Exception("Main Query Error: " . ($db_error['message'] ?? 'Unknown Error'));
        }
        
        $results = $main_query->result_array();

        if (empty($results)) {
            return false;
        }

        // ── Headers ──
        $headers = array(
            'S.No', 'Patient ID', 'Patient Name', 'Salutation', 'MR No', 'Branch',
            'Age', 'Gender', 'DOB', 'Marital Status',
            'Phone', 'WhatsApp', 'Alt Number 1', 'Alt Number 2',
            'Email', 'Address', 'City', 'State', 'Country', 'Pincode', 'Area',
            'Language Known', 'Source', 'Consultation Fee', 'Treatment', 'Assigned Doctor', 'Registered By', 'PRO Ownership',
            'Registration Start', 'Registration End', 'Renewal Start Date', 'Renewal End Date', 'Medicine End Date', 'Date Created',
            'Current Status', 'Patient Status', 'Journey Status',
            'Call Logs Count', 'Last Calling Date', 'Next Calling Date', 'Call Comments', 'Better Patient',
            'Case Sheet Count', 'Prescription Count', 'Package Count', 'Visits Count',
            'Invoice Count', 'Total Amount (Gross)', 'Net Total', 'Total Paid',
            'Due Amount', 'Payment Count', 'First Invoice Date', 'Last Invoice Date', 'Last Payment Date',
            'Is Refunded', 'Profile Link'
        );

        $rows = array();
        $i = 1;
        $baseUrl = admin_url('clients/client/');
        
        // Chunk results to avoid SQL max_allowed_packet errors and memory exhaustion on heavy joins
        $chunks = array_chunk($results, 500);

        foreach ($chunks as $chunk) {
            $userIds    = array_map('intval', array_column($chunk, 'userid'));
            $userIdsStr = implode(',', $userIds);

            // ── Batch: Branch names ──
            $branchNameMap = array();
            $this->db->select("cg_rel.customer_id, GROUP_CONCAT(DISTINCT cg_names.name ORDER BY cg_names.name SEPARATOR ', ') AS branch_names", false);
            $this->db->from(db_prefix() . 'customer_groups cg_rel');
            $this->db->join(db_prefix() . 'customers_groups cg_names', 'cg_names.id = cg_rel.groupid', 'left');
            $this->db->where_in('cg_rel.customer_id', $userIds);
            $this->db->group_by('cg_rel.customer_id');
            $branch_query = $this->db->get();
            if (!$branch_query) throw new \Exception("Branch Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($branch_query->result_array() as $br) {
                $branchNameMap[$br['customer_id']] = $br['branch_names'];
            }

            // ── Batch: Latest appointment ──
            $treatmentMap = array();
            $doctorMap = array();
            $consultationFeeMap = array();
            $this->db->select("a.userid, i.description AS treatment_name, CONCAT_WS(' ', s.firstname, s.lastname) AS doctor_name, inv.total AS consultation_fee", false);
            $this->db->from(db_prefix() . 'appointment a');
            $this->db->join(
                '(SELECT MAX(appointment_id) AS max_id, userid FROM ' . db_prefix() . 'appointment WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) AS latest',
                'a.appointment_id = latest.max_id', 'INNER'
            );
            $this->db->join(db_prefix() . 'items i', 'i.id = a.treatment_id', 'LEFT');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = a.enquiry_doctor_id', 'LEFT');
            $this->db->join(db_prefix() . 'invoices inv', 'a.invoice_id = inv.id', 'LEFT');
            $app_query = $this->db->get();
            if (!$app_query) throw new \Exception("Appointment Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($app_query->result_array() as $app) {
                $treatmentMap[$app['userid']] = isset($app['treatment_name']) ? $app['treatment_name'] : '';
                $doctorMap[$app['userid']]    = isset($app['doctor_name']) ? $app['doctor_name'] : '';
                $consultationFeeMap[$app['userid']] = isset($app['consultation_fee']) ? $app['consultation_fee'] : 0;
            }

            // ── Batch: Medicine End Date & Case Sheet Count ──
            $medicineEndMap = array();
            $casesheetCountMap = array();
            $this->db->select("userid, MAX(followup_date) as medicine_end_date, COUNT(id) as casesheet_count");
            $this->db->from(db_prefix() . 'casesheet');
            $this->db->where_in('userid', $userIds);
            $this->db->group_by('userid');
            $cs_query = $this->db->get();
            if (!$cs_query) throw new \Exception("Casesheet Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($cs_query->result_array() as $cs) {
                $medicineEndMap[$cs['userid']] = $cs['medicine_end_date'];
                $casesheetCountMap[$cs['userid']] = $cs['casesheet_count'];
            }

            // ── Batch: Renewal Dates & Package Count ──
            $renewalMap = array();
            $packageCountMap = array();
            $this->db->select("clientid, date, expirydate");
            $this->db->from(db_prefix() . 'estimates');
            $this->db->where_in('clientid', $userIds);
            $est_query = $this->db->get();
            if (!$est_query) throw new \Exception("Estimates Query Error: " . ($this->db->error()['message'] ?? ''));
            $client_estimates = [];
            foreach ($est_query->result_array() as $est) {
                $cid = $est['clientid'];
                if (!isset($client_estimates[$cid])) {
                    $client_estimates[$cid] = [];
                }
                $client_estimates[$cid][] = $est;
            }
            foreach ($client_estimates as $cid => $estimates) {
                $packageCountMap[$cid] = count($estimates);
                $latest_est = null;
                foreach ($estimates as $e) {
                    if ($latest_est === null) {
                        $latest_est = $e;
                    } else {
                        if (strtotime($e['expirydate']) > strtotime($latest_est['expirydate'])) {
                            $latest_est = $e;
                        }
                    }
                }
                $renewalMap[$cid] = [
                    'start_date' => $latest_est['date'],
                    'end_date'   => $latest_est['expirydate']
                ];
            }

            // ── Batch: Prescription Count ──
            $prescriptionCountMap = array();
            $this->db->select("userid, COUNT(patient_prescription_id) as prescription_count");
            $this->db->from(db_prefix() . 'patient_prescription');
            $this->db->where_in('userid', $userIds);
            $this->db->group_by('userid');
            $pres_query = $this->db->get();
            if (!$pres_query) throw new \Exception("Prescription Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($pres_query->result_array() as $pres) {
                $prescriptionCountMap[$pres['userid']] = $pres['prescription_count'];
            }

            // ── Batch: Visits Count ──
            $visitsCountMap = array();
            $this->db->select("userid, COUNT(appointment_id) as visits_count");
            $this->db->from(db_prefix() . 'appointment');
            $this->db->where_in('userid', $userIds);
            $this->db->where('visit_status', 1);
            $this->db->group_by('userid');
            $vis_query = $this->db->get();
            if (!$vis_query) throw new \Exception("Visits Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($vis_query->result_array() as $vis) {
                $visitsCountMap[$vis['userid']] = $vis['visits_count'];
            }

            // ── Batch: Call log count ──
            $callLogCountMap = array();
            $this->db->select('patientid, COUNT(id) as call_log_count');
            $this->db->from(db_prefix() . 'patient_call_logs');
            $this->db->where_in('patientid', $userIds);
            $this->db->group_by('patientid');
            $clc_query = $this->db->get();
            if (!$clc_query) throw new \Exception("Call Log Count Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($clc_query->result_array() as $clc) {
                $callLogCountMap[$clc['patientid']] = $clc['call_log_count'];
            }

            // ── Batch: Latest call log ──
            $callLogMap = array();
            $this->db->select('cl.patientid, cl.created_date as last_calling_date, cl.next_calling_date, cl.comments as call_comments, cl.better_patient');
            $this->db->from(db_prefix() . 'patient_call_logs cl');
            $this->db->join(
                '(SELECT MAX(id) as max_id, patientid FROM ' . db_prefix() . 'patient_call_logs WHERE patientid IN (' . $userIdsStr . ') GROUP BY patientid) as cl_latest',
                'cl.id = cl_latest.max_id', 'inner'
            );
            $call_query = $this->db->get();
            if (!$call_query) throw new \Exception("Call Log Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($call_query->result_array() as $log) {
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
            $journey_query = $this->db->get();
            if (!$journey_query) throw new \Exception("Journey Status Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($journey_query->result_array() as $s) {
                $leadStatuses[$s['userid']] = isset($s['status_name']) ? $s['status_name'] : '';
            }

            // ── Batch: Invoice totals ──
            $invoiceMap = array();
            $this->db->select('clientid, COUNT(*) as invoice_count, SUM(subtotal) as total_amount, SUM(total) as net_total, MIN(date) as first_invoice_date, MAX(date) as last_invoice_date', false);
            $this->db->from(db_prefix() . 'invoices');
            $this->db->where_in('clientid', $userIds);
            $this->db->group_by('clientid');
            $inv_query = $this->db->get();
            if (!$inv_query) throw new \Exception("Invoice Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($inv_query->result_array() as $inv) {
                $invoiceMap[$inv['clientid']] = $inv;
            }

            // ── Batch: Payment totals ──
            $paymentMap = array();
            $this->db->select('inv2.clientid, SUM(pay2.amount) as total_paid, COUNT(pay2.id) as payment_count, MAX(pay2.date) as last_payment_date', false);
            $this->db->from(db_prefix() . 'invoicepaymentrecords pay2');
            $this->db->join(db_prefix() . 'invoices inv2', 'inv2.id = pay2.invoiceid', 'inner');
            $this->db->where_in('inv2.clientid', $userIds);
            $this->db->group_by('inv2.clientid');
            $pay_query = $this->db->get();
            if (!$pay_query) throw new \Exception("Payment Query Error: " . ($this->db->error()['message'] ?? ''));
            foreach ($pay_query->result_array() as $pay) {
                $paymentMap[$pay['clientid']] = $pay;
            }

            // Map data to rows for this chunk
            foreach ($chunk as $row) {
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

                $renStart = '';
                $renEnd = '';
                if (isset($renewalMap[$uid])) {
                    $renStart = $renewalMap[$uid]['start_date'];
                    $renEnd = $renewalMap[$uid]['end_date'];
                }
                
                $medEnd = '';
                if (isset($medicineEndMap[$uid]) && $medicineEndMap[$uid] != '0000-00-00') {
                    $medEnd = $medicineEndMap[$uid];
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
                    isset($row['country_name']) ? $row['country_name'] : '',
                    isset($row['pincode']) ? $row['pincode'] : (isset($row['zip']) ? $row['zip'] : ''),
                    isset($row['area']) ? $row['area'] : '',
                    isset($row['default_language']) ? $row['default_language'] : '',
                    isset($row['patient_source_name']) ? $row['patient_source_name'] : '',
                    isset($consultationFeeMap[$uid]) ? $consultationFeeMap[$uid] : 0,
                    isset($treatmentMap[$uid]) ? $treatmentMap[$uid] : '',
                    isset($doctorMap[$uid]) ? $doctorMap[$uid] : '',
                    isset($row['registered_by_name']) ? $row['registered_by_name'] : '',
                    isset($row['pro_ownership_name']) ? $row['pro_ownership_name'] : '',
                    isset($row['registration_start_date']) ? $row['registration_start_date'] : '',
                    $regEnd,
                    $renStart,
                    $renEnd,
                    $medEnd,
                    isset($row['datecreated']) ? $row['datecreated'] : '',
                    isset($row['current_status']) ? $row['current_status'] : '',
                    isset($row['patient_status']) ? $row['patient_status'] : '',
                    isset($leadStatuses[$uid]) ? $leadStatuses[$uid] : '',
                    isset($callLogCountMap[$uid]) ? $callLogCountMap[$uid] : 0,
                    isset($callLog['last_calling_date']) ? $callLog['last_calling_date'] : '',
                    isset($callLog['next_calling_date']) ? $callLog['next_calling_date'] : '',
                    isset($callLog['call_comments']) ? $callLog['call_comments'] : '',
                    isset($callLog['better_patient']) ? $callLog['better_patient'] : '',
                    isset($casesheetCountMap[$uid]) ? $casesheetCountMap[$uid] : 0,
                    isset($prescriptionCountMap[$uid]) ? $prescriptionCountMap[$uid] : 0,
                    isset($packageCountMap[$uid]) ? $packageCountMap[$uid] : 0,
                    isset($visitsCountMap[$uid]) ? $visitsCountMap[$uid] : 0,
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
        }

        return array('headers' => $headers, 'rows' => $rows);
    }

    // ════════════════════════════════════════════════════════════════
    // Build Detail Sheets — fetches full records for each tab
    // ════════════════════════════════════════════════════════════════

    private function _build_detail_sheets($userIds)
    {
        if (empty($userIds)) return array();

        $sheets = array();

        // Build a Patient ID => Name lookup for display in detail rows
        $nameMap = array();
        $this->db->select('userid, company');
        $this->db->from(db_prefix() . 'clients');
        $this->db->where_in('userid', $userIds);
        $nameQ = $this->db->get();
        if ($nameQ) {
            foreach ($nameQ->result_array() as $n) {
                $nameMap[$n['userid']] = $n['company'];
            }
        }

        // Process in chunks to avoid max_allowed_packet issues
        $chunks = array_chunk($userIds, 500);

        // ── Sheet: Case Sheets ──
        $csHeaders = array('Patient ID', 'Patient Name', 'Casesheet ID', 'Date', 'Presenting Complaints', 'Complaint', 'Clinical Observation', 'Progress', 'Follow-up Date', 'Medicine Days', 'Doctor', 'Treatment', 'Duration Value', 'Patient Status');
        $csRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("c.userid, c.id as casesheet_id, c.date, c.presenting_complaints, c.complaint, c.clinical_observation, c.progress, c.followup_date, c.medicine_days, CONCAT_WS(' ', s.firstname, s.lastname) as doctor_name, i.description as treatment_name, pt.duration_value, c.patient_status", false);
            $this->db->from(db_prefix() . 'casesheet c');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = c.staffid', 'left');
            $this->db->join(db_prefix() . 'patient_treatment pt', 'pt.casesheet_id = c.id', 'left');
            $this->db->join(db_prefix() . 'items i', 'i.id = pt.treatment_type_id', 'left');
            $this->db->where_in('c.userid', $chunk);
            $this->db->order_by('c.userid', 'ASC');
            $this->db->order_by('c.date', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $csRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['casesheet_id'],
                        $r['date'],
                        strip_tags($r['presenting_complaints'] ?? ''),
                        strip_tags($r['complaint'] ?? ''),
                        strip_tags($r['clinical_observation'] ?? ''),
                        $r['progress'],
                        $r['followup_date'],
                        $r['medicine_days'],
                        $r['doctor_name'],
                        $r['treatment_name'],
                        $r['duration_value'],
                        $r['patient_status'],
                    );
                }
            }
        }
        $sheets['Case Sheets'] = array('headers' => $csHeaders, 'rows' => $csRows);

        // ── Sheet: Prescriptions ──
        $prHeaders = array('Patient ID', 'Patient Name', 'Prescription ID', 'Casesheet ID', 'Prescription Data', 'Created By', 'Created Date', 'Medicine Given By', 'Medicine Given Date', 'Status');
        $prRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("pp.userid, pp.patient_prescription_id, pp.casesheet_id, pp.prescription_data, CONCAT_WS(' ', sc.firstname, sc.lastname) as created_by_name, pp.created_datetime, CONCAT_WS(' ', sg.firstname, sg.lastname) as given_by, pp.medicine_given_date, pp.patient_prescription_status", false);
            $this->db->from(db_prefix() . 'patient_prescription pp');
            $this->db->join(db_prefix() . 'staff sc', 'sc.staffid = pp.created_by', 'left');
            $this->db->join(db_prefix() . 'staff sg', 'sg.staffid = pp.medicine_given_by', 'left');
            $this->db->where_in('pp.userid', $chunk);
            $this->db->order_by('pp.userid', 'ASC');
            $this->db->order_by('pp.created_datetime', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $prRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['patient_prescription_id'],
                        $r['casesheet_id'],
                        $r['prescription_data'],
                        $r['created_by_name'],
                        $r['created_datetime'],
                        $r['given_by'],
                        $r['medicine_given_date'],
                        $r['patient_prescription_status'],
                    );
                }
            }
        }
        $sheets['Prescriptions'] = array('headers' => $prHeaders, 'rows' => $prRows);

        // ── Sheet: Packages ──
        $pkHeaders = array('Patient ID', 'Patient Name', 'Package ID', 'Treatment', 'Start Date', 'Expiry Date', 'Total Amount', 'Invoice ID', 'Package Invoice No', 'Status', 'Created Date');
        $pkRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("e.clientid as userid, e.id as estimate_id, it.description as treatment_name, e.date, e.expirydate, e.total, e.invoiceid, e.status, e.datecreated, inv.id as inv_id, inv.number as inv_number", false);
            $this->db->from(db_prefix() . 'estimates e');
            $this->db->join(db_prefix() . 'itemable ita', "ita.rel_id = e.id AND ita.rel_type = 'estimate'", 'left');
            $this->db->join(db_prefix() . 'items it', 'it.id = ita.description', 'left');
            $this->db->join(db_prefix() . 'invoices inv', 'inv.id = e.invoiceid', 'left');
            $this->db->where_in('e.clientid', $chunk);
            $this->db->order_by('e.clientid', 'ASC');
            $this->db->order_by('e.date', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    // Estimate status mapping
                    $statusText = '';
                    switch ($r['status']) {
                        case 1: $statusText = 'Draft'; break;
                        case 2: $statusText = 'Sent'; break;
                        case 3: $statusText = 'Declined'; break;
                        case 4: $statusText = 'Accepted'; break;
                        case 5: $statusText = 'Expired'; break;
                        default: $statusText = $r['status'];
                    }

                    // Raw invoice number for the package's linked invoice (e.g. 122323, no prefix)
                    $packageInvoiceNo = '';
                    if (!empty($r['inv_id']) && isset($r['inv_number'])) {
                        $packageInvoiceNo = $r['inv_number'];
                    }

                    $pkRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['estimate_id'],
                        $r['treatment_name'],
                        $r['date'],
                        $r['expirydate'],
                        $r['total'],
                        $r['invoiceid'],
                        $packageInvoiceNo,
                        $statusText,
                        $r['datecreated'],
                    );
                }
            }
        }
        $sheets['Packages'] = array('headers' => $pkHeaders, 'rows' => $pkRows);

        // ── Sheet: Visits ──
        $viHeaders = array('Patient ID', 'Patient Name', 'Visit ID', 'Appointment Date', 'Treatment', 'Visit Status', 'Consulted Date', 'Medicine Days', 'Appointment Type', 'Doctor');
        $viRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("a.userid, a.visit_id, a.appointment_date, i.description as treatment_name, a.visit_status, a.consulted_date, cs.medicine_days, atype.appointment_type_name, CONCAT_WS(' ', s.firstname, s.lastname) as doctor_name", false);
            $this->db->from(db_prefix() . 'appointment a');
            $this->db->join(db_prefix() . 'items i', 'i.id = a.treatment_id', 'left');
            $this->db->join(db_prefix() . 'appointment_type atype', 'atype.appointment_type_id = a.appointment_type_id', 'left');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = a.enquiry_doctor_id', 'left');
            $this->db->join(db_prefix() . 'casesheet cs', 'cs.date = DATE(a.appointment_date) AND cs.userid = a.userid AND a.visit_status = 1', 'left');
            $this->db->where_in('a.userid', $chunk);
            $this->db->order_by('a.userid', 'ASC');
            $this->db->order_by('a.appointment_date', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $visitStatusText = ($r['visit_status'] == 1) ? 'Visited' : 'Not Visited';
                    $viRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['visit_id'],
                        $r['appointment_date'],
                        $r['treatment_name'],
                        $visitStatusText,
                        $r['consulted_date'],
                        $r['medicine_days'],
                        $r['appointment_type_name'],
                        $r['doctor_name'],
                    );
                }
            }
        }
        $sheets['Visits'] = array('headers' => $viHeaders, 'rows' => $viRows);

        // ── Sheet: Payments ──
        $payHeaders = array('Patient ID', 'Patient Name', 'Invoice No', 'Invoice Date', 'Invoice Total', 'Payment ID', 'Payment Amount', 'Payment Date', 'Payment Mode', 'UTR No', 'Note');
        $payRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("inv.clientid as userid, inv.number as invoice_no, inv.date as invoice_date, inv.total as invoice_total, pay.id as payment_id, pay.amount, pay.date as payment_date, pm.name as payment_mode, pay.transactionid as utr_no, pay.note", false);
            $this->db->from(db_prefix() . 'invoicepaymentrecords pay');
            $this->db->join(db_prefix() . 'invoices inv', 'inv.id = pay.invoiceid', 'inner');
            $this->db->join(db_prefix() . 'payment_modes pm', 'pm.id = pay.paymentmode', 'left');
            $this->db->where_in('inv.clientid', $chunk);
            $this->db->order_by('inv.clientid', 'ASC');
            $this->db->order_by('pay.date', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $payRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['invoice_no'],
                        $r['invoice_date'],
                        $r['invoice_total'],
                        $r['payment_id'],
                        $r['amount'],
                        $r['payment_date'],
                        $r['payment_mode'],
                        $r['utr_no'],
                        $r['note'],
                    );
                }
            }
        }
        $sheets['Payments'] = array('headers' => $payHeaders, 'rows' => $payRows);

        // ── Sheet: Call Logs ──
        $clHeaders = array('Patient ID', 'Patient Name', 'Call Log ID', 'Called By', 'Call Type', 'Next Calling Date', 'Better Patient', 'Pharmacy Medicine Days', 'Patient Took Medicine Days', 'Created Date', 'Comments');
        $clRows = array();
        foreach ($chunks as $chunk) {
            $this->db->select("cl.patientid as userid, cl.id as call_log_id, CONCAT_WS(' ', s.firstname, s.lastname) as called_by, cr.criteria_name as call_type, cl.next_calling_date, cl.better_patient, cl.pharmacy_medicine_days, cl.patient_took_medicine_days, cl.created_date, cl.comments", false);
            $this->db->from(db_prefix() . 'patient_call_logs cl');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = cl.called_by', 'left');
            $this->db->join(db_prefix() . 'criteria cr', 'cr.criteria_id = cl.criteria_id', 'left');
            $this->db->where_in('cl.patientid', $chunk);
            $this->db->order_by('cl.patientid', 'ASC');
            $this->db->order_by('cl.created_date', 'DESC');
            $q = $this->db->get();
            if ($q) {
                foreach ($q->result_array() as $r) {
                    $clRows[] = array(
                        $r['userid'],
                        isset($nameMap[$r['userid']]) ? $nameMap[$r['userid']] : '',
                        $r['call_log_id'],
                        $r['called_by'],
                        $r['call_type'],
                        $r['next_calling_date'],
                        $r['better_patient'],
                        $r['pharmacy_medicine_days'],
                        $r['patient_took_medicine_days'],
                        $r['created_date'],
                        $r['comments'],
                    );
                }
            }
        }
        $sheets['Call Logs'] = array('headers' => $clHeaders, 'rows' => $clRows);

        return $sheets;
    }

    // ════════════════════════════════════════════════════════════════
    // Multi-Sheet XLSX Export using XLSXWriter
    // ════════════════════════════════════════════════════════════════

    // ════════════════════════════════════════════════════════════════
    // Branch-wise ZIP export — one XLSX per branch, all bundled together
    // ════════════════════════════════════════════════════════════════

    /**
     * Build one XLSX file per branch and stream them all back as a single ZIP.
     * If $branch_ids is empty, every branch is exported; otherwise only the
     * selected branches. Branches with no patients in the range are skipped.
     */
    private function _stream_branchwise_zip($from_date, $to_date, $branch_ids)
    {
        // Resolve the branches to export.
        $allBranches = $this->client_model->get_branch();
        if (!empty($branch_ids)) {
            $selected = array_map('intval', $branch_ids);
            $branches = array_filter($allBranches, function ($b) use ($selected) {
                return in_array((int)$b['id'], $selected, true);
            });
        } else {
            $branches = $allBranches;
        }

        if (empty($branches)) {
            set_alert('warning', 'No branches available to export.');
            redirect(admin_url('pcs_patients/export_patients'));
            return;
        }

        // Temp working directory for the per-branch files.
        $tmpDir = rtrim(sys_get_temp_dir(), '/\\') . '/pcs_export_' . uniqid('', true);
        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \Exception('Unable to create temporary export directory.');
        }

        $tmpFiles    = array();
        $usedNames   = array();
        $exportedAny = false;

        try {
            foreach ($branches as $branch) {
                $branchId   = (int)$branch['id'];
                $branchName = isset($branch['name']) ? $branch['name'] : ('Branch ' . $branchId);

                // Fetch this branch's data only.
                $export = $this->_build_export_data($from_date, $to_date, array($branchId));

                // false = no patients, string = error message — skip either way.
                if ($export === false || is_string($export)) {
                    continue;
                }

                $allUserIds   = array_map('intval', array_column($export['rows'], 1)); // col 1 = Patient ID
                $detailSheets = $this->_build_detail_sheets($allUserIds);

                $writer = $this->_make_xlsx_writer($export['headers'], $export['rows'], $detailSheets);

                // Safe, unique file name per branch.
                $safeName = $this->_safe_filename($branchName);
                $baseName = $safeName . '_' . $branchId;
                $entry    = $baseName . '.xlsx';
                $suffix   = 1;
                while (isset($usedNames[$entry])) {
                    $entry = $baseName . '_' . (++$suffix) . '.xlsx';
                }
                $usedNames[$entry] = true;

                $filePath = $tmpDir . '/' . $entry;
                $writer->writeToFile($filePath);
                $tmpFiles[$entry] = $filePath;
                $exportedAny = true;
            }

            if (!$exportedAny) {
                set_alert('warning', 'No patients found for the selected date range in the chosen branches.');
                redirect(admin_url('pcs_patients/export_patients'));
                return;
            }

            // Bundle everything into a single ZIP.
            $zipName = 'pcs_patients_branchwise_' . $this->_build_date_suffix($from_date, $to_date) . '.zip';
            $zipPath = $tmpDir . '/' . $zipName;

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Unable to create ZIP archive.');
            }
            foreach ($tmpFiles as $entry => $path) {
                $zip->addFile($path, $entry);
            }
            $zip->close();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipPath));
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            readfile($zipPath);

            // Clean up before exiting.
            $this->_rrmdir($tmpDir);
            exit;
        } catch (\Throwable $th) {
            $this->_rrmdir($tmpDir);
            throw $th;
        }
    }

    /**
     * Turn an arbitrary branch name into a filesystem-safe file name fragment.
     */
    private function _safe_filename($name)
    {
        $name = trim((string)$name);
        $name = preg_replace('/[^A-Za-z0-9 _\-]/', '', $name); // drop unsafe chars
        $name = preg_replace('/\s+/', '_', $name);             // spaces → underscores
        $name = trim($name, '_-');
        return $name !== '' ? $name : 'branch';
    }

    /**
     * Recursively delete a temporary directory and its contents.
     */
    private function _rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->_rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Ensure the XLSXWriter library is loaded (local copy preferred, fallbacks otherwise).
     */
    private function _load_xlsx_writer()
    {
        if (class_exists('XLSXWriter')) {
            return;
        }
        $candidatePaths = array(
            APPPATH . '../modules/pcs_patients/assets/plugins/XLSXWriter/xlsxwriter.class.php',
            APPPATH . '../modules/accounting/assets/plugins/XLSXWriter/xlsxwriter.class.php',
            APPPATH . '../modules/hr_profile/assets/plugins/XLSXWriter/xlsxwriter.class.php',
        );
        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                require_once($path);
                return;
            }
        }
        throw new \Exception('XLSXWriter library not found. Please ensure the plugin exists in modules/pcs_patients/assets/plugins/XLSXWriter/');
    }

    /**
     * Build a fully-populated XLSXWriter (Patient Details + detail tabs).
     * Returned writer can be sent to stdout or written to a file.
     */
    private function _make_xlsx_writer($headers, $rows, $detailSheets = array())
    {
        $this->_load_xlsx_writer();

        $writer = new \XLSXWriter();
        $writer->setTitle('PCS Patients Export');
        $writer->setAuthor('PCS System');

        // Sheet 1: Patient Details
        $headerTypes = array();
        foreach ($headers as $h) {
            $headerTypes[$h] = 'string';
        }
        $writer->writeSheetHeader('Patient Details', $headerTypes, array('freeze_rows' => 1, 'auto_filter' => true));
        foreach ($rows as $row) {
            $cleanRow = array();
            foreach ($row as $cell) {
                $cleanRow[] = (string)$cell;
            }
            $writer->writeSheetRow('Patient Details', $cleanRow);
        }

        // Detail Sheets (Case Sheets, Prescriptions, Packages, Visits, Payments, Call Logs)
        foreach ($detailSheets as $sheetName => $sheetData) {
            $sheetHeaderTypes = array();
            foreach ($sheetData['headers'] as $h) {
                $sheetHeaderTypes[$h] = 'string';
            }
            $writer->writeSheetHeader($sheetName, $sheetHeaderTypes, array('freeze_rows' => 1, 'auto_filter' => true));
            foreach ($sheetData['rows'] as $row) {
                $cleanRow = array();
                foreach ($row as $cell) {
                    $cleanRow[] = (string)($cell ?? '');
                }
                $writer->writeSheetRow($sheetName, $cleanRow);
            }
        }

        return $writer;
    }

    private function _stream_xlsx($headers, $rows, $detailSheets = array(), $from_date = '', $to_date = '')
    {
        $writer = $this->_make_xlsx_writer($headers, $rows, $detailSheets);

        $filename = 'pcs_patients_export_' . $this->_build_date_suffix($from_date, $to_date) . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer->writeToStdOut();
        exit;
    }

    // ════════════════════════════════════════════════════════════════
    // CSV Export (fallback format)
    // ════════════════════════════════════════════════════════════════

    private function _stream_csv($headers, $rows, $from_date = '', $to_date = '')
    {
        $filename = 'pcs_patients_export_' . $this->_build_date_suffix($from_date, $to_date) . '.csv';

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

    private function _stream_json($headers, $rows, $detailSheets = array(), $from_date = '', $to_date = '')
    {
        $filename = 'pcs_patients_export_' . $this->_build_date_suffix($from_date, $to_date) . '.json';

        // Build associative array using headers as keys — Patient Details
        $jsonData = array();
        foreach ($rows as $row) {
            $record = array();
            foreach ($headers as $idx => $header) {
                $record[$header] = isset($row[$idx]) ? $row[$idx] : '';
            }
            $jsonData[] = $record;
        }

        // Build detail sheet sections — same data as Excel tabs
        $detailSections = array();
        // Map sheet names to JSON-friendly keys
        $sheetKeyMap = array(
            'Case Sheets'   => 'case_sheets',
            'Prescriptions' => 'prescriptions',
            'Packages'      => 'packages',
            'Visits'        => 'visits',
            'Payments'      => 'payments',
            'Call Logs'     => 'call_logs',
        );
        foreach ($detailSheets as $sheetName => $sheetData) {
            $key = isset($sheetKeyMap[$sheetName]) ? $sheetKeyMap[$sheetName] : strtolower(str_replace(' ', '_', $sheetName));
            $sheetRecords = array();
            foreach ($sheetData['rows'] as $sRow) {
                $rec = array();
                foreach ($sheetData['headers'] as $hIdx => $hName) {
                    $rec[$hName] = isset($sRow[$hIdx]) ? $sRow[$hIdx] : '';
                }
                $sheetRecords[] = $rec;
            }
            $detailSections[$key] = array(
                'total_records' => count($sheetRecords),
                'records'       => $sheetRecords,
            );
        }

        $output = array(
            'exported_at'   => date('Y-m-d H:i:s'),
            'total_records' => count($jsonData),
            'patients'      => $jsonData,
        );

        // Merge detail sections into the root
        foreach ($detailSections as $sectionKey => $sectionData) {
            $output[$sectionKey] = $sectionData;
        }

        $jsonString = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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

    // ════════════════════════════════════════════════════════════════
    // Build filename date suffix from user-selected date range
    // ════════════════════════════════════════════════════════════════

    private function _build_date_suffix($from_date = '', $to_date = '')
    {
        if (!empty($from_date) && !empty($to_date)) {
            // Sanitize: keep only date part (YYYY-MM-DD), strip any time component
            $from = date('Y-m-d', strtotime($from_date));
            $to   = date('Y-m-d', strtotime($to_date));
            return $from . '_to_' . $to;
        }

        // Fallback: use today's date if no range was selected
        return date('Ymd_His');
    }
}
