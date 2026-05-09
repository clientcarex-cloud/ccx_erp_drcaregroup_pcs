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
     * Export ALL patient data as XLSX for the given date range / branch filters.
     * Accessed via GET: admin/pcs_patients/export_patients?from_date=...&to_date=...&branch_ids=...
     */
    public function export_patients()
    {
        if (!staff_can('view', 'pcs_patients') && !is_admin()) {
            access_denied('PCS Patients Export');
        }

        $this->load->helper('client/custom');

        $from_date  = $this->input->get('from_date');
        $to_date    = $this->input->get('to_date');
        $branch_ids = $this->input->get('branch_ids');
        $branch_ids = !empty($branch_ids) ? array_filter(explode(',', $branch_ids), 'is_numeric') : [];

        // ── Build branch filter ──
        $applyBranchFilter = static function ($query) use ($branch_ids) {
            if (empty($branch_ids)) return;
            $cleanIds = array_filter(array_map('intval', $branch_ids), function ($v) { return $v > 0; });
            if (empty($cleanIds)) return;
            $query->where('EXISTS (
                SELECT 1 FROM ' . db_prefix() . 'customer_groups cg_filter
                WHERE cg_filter.customer_id = c.userid
                AND cg_filter.groupid IN (' . implode(',', $cleanIds) . ')
            )', null, false);
        };

        // ── Main query: fetch ALL patients matching filters ──
        $this->db->reset_query();
        $this->db->select('
            c.userid, c.company, c.phonenumber, c.email, c.city, c.state, c.address, c.zip, c.datecreated,
            new.mr_no, new.salutation, new.age, new.gender, new.dob, new.email_id,
            new.marital_status, new.area, new.pincode,
            new.whatsapp_number, new.alt_number1, new.alt_number2,
            new.patient_status, new.current_status,
            new.registration_start_date, new.registration_end_date,
            new.patient_source_id, new.reg_by, new.pro_ownership, new.is_refunded,
            source.name as patient_source_name,
            CONCAT_WS(" ", reg_staff.firstname, reg_staff.lastname) as registered_by_name
        ');
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
            set_alert('warning', 'No patients found for the selected filters.');
            redirect(admin_url('pcs_patients'));
            return;
        }

        $userIds    = array_column($results, 'userid');
        $userIdsStr = implode(',', $userIds);

        // ── Batch: Branch names ──
        $branchNameMap = [];
        $this->db->select('cg_rel.customer_id, GROUP_CONCAT(DISTINCT cg_names.name ORDER BY cg_names.name SEPARATOR ", ") AS branch_names');
        $this->db->from(db_prefix() . 'customer_groups cg_rel');
        $this->db->join(db_prefix() . 'customers_groups cg_names', 'cg_names.id = cg_rel.groupid', 'left');
        $this->db->where_in('cg_rel.customer_id', $userIds);
        $this->db->group_by('cg_rel.customer_id');
        foreach ($this->db->get()->result_array() as $br) {
            $branchNameMap[$br['customer_id']] = $br['branch_names'];
        }

        // ── Batch: Latest appointment (treatment + doctor) ──
        $treatmentMap = $doctorMap = [];
        $this->db->select('a.userid, a.enquiry_doctor_id, a.appointment_date, a.consulted_date,
            i.description AS treatment_name,
            CONCAT_WS(" ", s.firstname, s.lastname) AS doctor_name');
        $this->db->from(db_prefix() . 'appointment a');
        $this->db->join(
            '(SELECT MAX(appointment_id) AS max_id, userid FROM ' . db_prefix() . 'appointment WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) AS latest',
            'a.appointment_id = latest.max_id', 'INNER'
        );
        $this->db->join(db_prefix() . 'items i', 'i.id = a.treatment_id', 'LEFT');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.enquiry_doctor_id', 'LEFT');
        foreach ($this->db->get()->result_array() as $app) {
            $treatmentMap[$app['userid']] = $app['treatment_name'] ?? '-';
            $doctorMap[$app['userid']]    = $app['doctor_name'] ?? '-';
        }

        // ── Batch: Latest call log ──
        $callLogMap = [];
        $this->db->select('c.patientid, c.created_date as last_calling_date, c.next_calling_date, c.comments as call_comments, c.better_patient');
        $this->db->from(db_prefix() . 'patient_call_logs c');
        $this->db->join(
            "(SELECT MAX(id) as max_id, patientid FROM " . db_prefix() . "patient_call_logs WHERE patientid IN (" . $userIdsStr . ") GROUP BY patientid) as latest",
            'c.id = latest.max_id', 'inner'
        );
        foreach ($this->db->get()->result_array() as $log) {
            $callLogMap[$log['patientid']] = $log;
        }

        // ── Batch: Latest journey status ──
        $leadStatuses = [];
        $this->db->select('j.userid, s.name as status_name');
        $this->db->from(db_prefix() . 'lead_patient_journey j');
        $this->db->join(
            '(SELECT userid, MAX(id) AS max_id FROM ' . db_prefix() . 'lead_patient_journey WHERE userid IN (' . $userIdsStr . ') GROUP BY userid) latest_journey',
            'latest_journey.max_id = j.id', 'inner'
        );
        $this->db->join(db_prefix() . 'leads_status s', 's.id = j.status', 'left');
        foreach ($this->db->get()->result_array() as $s) {
            $leadStatuses[$s['userid']] = $s['status_name'] ?? '';
        }

        // ── Batch: Invoice totals per patient ──
        $invoiceMap = [];
        $this->db->select('clientid,
            COUNT(*) as invoice_count,
            SUM(subtotal) as total_amount,
            SUM(total) as net_total,
            SUM(CASE WHEN status = 2 THEN total ELSE 0 END) as paid_invoice_total,
            MIN(date) as first_invoice_date,
            MAX(date) as last_invoice_date');
        $this->db->from(db_prefix() . 'invoices');
        $this->db->where_in('clientid', $userIds);
        $this->db->group_by('clientid');
        foreach ($this->db->get()->result_array() as $inv) {
            $invoiceMap[$inv['clientid']] = $inv;
        }

        // ── Batch: Payment totals per patient ──
        $paymentMap = [];
        $this->db->select('inv.clientid,
            SUM(pay.amount) as total_paid,
            COUNT(pay.id) as payment_count,
            MAX(pay.date) as last_payment_date');
        $this->db->from(db_prefix() . 'invoicepaymentrecords pay');
        $this->db->join(db_prefix() . 'invoices inv', 'inv.id = pay.invoiceid', 'inner');
        $this->db->where_in('inv.clientid', $userIds);
        $this->db->group_by('inv.clientid');
        foreach ($this->db->get()->result_array() as $pay) {
            $paymentMap[$pay['clientid']] = $pay;
        }

        // ── Build headers ──
        $headers = [
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
        ];

        // ── Build rows ──
        $rows = [];
        $i = 1;
        $baseUrl = admin_url('clients/client/');

        foreach ($results as $row) {
            $uid      = $row['userid'];
            $callLog  = $callLogMap[$uid] ?? [];
            $inv      = $invoiceMap[$uid] ?? [];
            $pay      = $paymentMap[$uid] ?? [];
            $netTotal = floatval($inv['net_total'] ?? 0);
            $totalPaid = floatval($pay['total_paid'] ?? 0);

            $rows[] = [
                $i++,
                $uid,
                strip_tags(html_entity_decode($row['company'] ?? '', ENT_QUOTES, 'UTF-8')),
                $row['salutation'] ?? '',
                $row['mr_no'] ?? '',
                $branchNameMap[$uid] ?? '',
                $row['age'] ?? '',
                $row['gender'] ?? '',
                $row['dob'] ?? '',
                $row['marital_status'] ?? '',
                $row['phonenumber'] ?? '',
                $row['whatsapp_number'] ?? '',
                $row['alt_number1'] ?? '',
                $row['alt_number2'] ?? '',
                $row['email_id'] ?? $row['email'] ?? '',
                $row['address'] ?? '',
                $row['city'] ?? '',
                $row['state'] ?? '',
                $row['pincode'] ?? $row['zip'] ?? '',
                $row['area'] ?? '',
                $row['patient_source_name'] ?? '',
                $treatmentMap[$uid] ?? '',
                $doctorMap[$uid] ?? '',
                $row['registered_by_name'] ?? '',
                $row['registration_start_date'] ?? '',
                (!empty($row['registration_end_date']) && $row['registration_end_date'] !== '1970-01-01') ? $row['registration_end_date'] : '',
                $row['datecreated'] ?? '',
                $row['current_status'] ?? '',
                $row['patient_status'] ?? '',
                $leadStatuses[$uid] ?? '',
                $callLog['last_calling_date'] ?? '',
                $callLog['next_calling_date'] ?? '',
                $callLog['call_comments'] ?? '',
                $callLog['better_patient'] ?? '',
                $inv['invoice_count'] ?? 0,
                $inv['total_amount'] ?? 0,
                $inv['net_total'] ?? 0,
                $pay['total_paid'] ?? 0,
                round($netTotal - $totalPaid, 2),
                $pay['payment_count'] ?? 0,
                $inv['first_invoice_date'] ?? '',
                $inv['last_invoice_date'] ?? '',
                $pay['last_payment_date'] ?? '',
                (!empty($row['is_refunded']) && $row['is_refunded'] == 1) ? 'Yes' : 'No',
                $baseUrl . $uid,
            ];
        }

        // ── Stream XLSX ──
        $this->_stream_xlsx('PCS_Patients_Export', $headers, $rows);
    }

    // ════════════════════════════════════════════════════════════════
    // Self-contained XLSX builder (ZipArchive, zero dependencies)
    // ════════════════════════════════════════════════════════════════

    private function _stream_xlsx(string $baseName, array $headers, array $rows): void
    {
        $filename = strtolower(preg_replace('/[^A-Za-z0-9_\-]+/', '_', $baseName)) . '-' . date('Ymd-His') . '.xlsx';

        if (!class_exists('ZipArchive')) {
            // Fallback: HTML-based XLS
            $this->_stream_html_xls($filename, $headers, $rows);
            return;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'pcs_xlsx_');
        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->_stream_html_xls($filename, $headers, $rows);
            return;
        }

        $zip->addFromString('[Content_Types].xml', $this->_xlsx_content_types());
        $zip->addFromString('_rels/.rels', $this->_xlsx_root_rels());
        $zip->addFromString('xl/workbook.xml', $this->_xlsx_workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->_xlsx_workbook_rels());
        $zip->addFromString('xl/styles.xml', $this->_xlsx_styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->_xlsx_sheet($headers, $rows));
        $zip->close();

        $binary = file_get_contents($tempFile);
        @unlink($tempFile);

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }

    private function _stream_html_xls(string $filename, array $headers, array $rows): void
    {
        $filename = str_replace('.xlsx', '.xls', $filename);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo '<table border="1"><thead><tr>';
        foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) { echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table>';
        exit;
    }

    private function _xlsx_col(int $idx): string
    {
        $l = '';
        while ($idx > 0) { $r = ($idx - 1) % 26; $l = chr(65 + $r) . $l; $idx = (int)(($idx - $r - 1) / 26); }
        return $l ?: 'A';
    }

    private function _xlsx_esc(string $v): string
    {
        return str_replace("\n", '&#10;', htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $v), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function _xlsx_sheet(array $headers, array $rows): string
    {
        $allRows   = array_merge([$headers], $rows);
        $colCount  = count($headers);
        $rowCount  = count($allRows);
        $lastCol   = $this->_xlsx_col($colCount);
        $dim       = 'A1:' . $lastCol . $rowCount;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="' . $dim . '"/>';
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/><sheetData>';

        foreach ($allRows as $ri => $row) {
            $excelRow = $ri + 1;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $ci => $val) {
                $ref = $this->_xlsx_col($ci + 1) . $excelRow;
                $val = (string)$val;
                if ($val === '') { $xml .= '<c r="' . $ref . '"/>'; continue; }
                if (is_numeric($val) && !preg_match('/^0\d+$/', $val)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $this->_xlsx_esc($val) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function _xlsx_content_types(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function _xlsx_root_rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function _xlsx_workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Patients" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function _xlsx_workbook_rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function _xlsx_styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }
}
