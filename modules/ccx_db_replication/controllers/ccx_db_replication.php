<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_db_replication extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $data['title'] = 'CCX DB Replication • Hello World';
        $this->load->view(module_views_path('ccx_db_replication', 'hello'), $data);
    }
}
