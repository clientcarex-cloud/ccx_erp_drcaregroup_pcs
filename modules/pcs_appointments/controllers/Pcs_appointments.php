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
    }

    public function index()
    {
        $data['title'] = _l('pcs_appointments');
        $this->load->view('manage', $data);
    }
}
