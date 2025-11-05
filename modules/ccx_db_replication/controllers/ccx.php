<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $data['title'] = 'CCX • Hello World';
        $this->load->view('ccx/hello', $data); // loads the admin-themed view
    }
}
