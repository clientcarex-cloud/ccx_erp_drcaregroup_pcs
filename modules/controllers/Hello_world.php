<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Hello_world extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->load->view('hello_world');
    }
    
    public function custom_tab_1() {
        $data['title'] = 'Custom Tab 1 Content';
        $this->load->view('hello_world/custom_tab1', $data);
    }

    public function custom_tab_2() {
        $data['title'] = 'Custom Tab 2 Content';
        $this->load->view('hello_world/custom_tab2', $data);
    }
}
