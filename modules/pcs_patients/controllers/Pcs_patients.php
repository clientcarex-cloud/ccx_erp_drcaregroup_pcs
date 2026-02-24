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
    }

    public function index()
    {
        $data['title'] = _l('pcs_patients');
        $this->load->view('patients_list', $data);
    }
}
