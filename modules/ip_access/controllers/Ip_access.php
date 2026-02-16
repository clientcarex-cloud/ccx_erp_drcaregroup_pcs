<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ip_access extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!staff_can('view', 'ip_access')) {
            access_denied('ip_access');
        }
    }

    public function index()
    {
        $this->list_branches();
    }

    public function list_branches()
    {
        $data['title'] = 'IP Access - Branches';

        // Get all customer groups (Branches)
        $this->db->order_by('name', 'asc');
        $branches = $this->db->get(db_prefix() . 'customers_groups')->result_array();

        // Get IP counts per branch
        $ip_counts = $this->db->query('SELECT branch_id, COUNT(*) as count FROM ' . db_prefix() . 'ip_access GROUP BY branch_id')->result_array();
        $params = [];
        foreach ($ip_counts as $row) {
            $params[$row['branch_id']] = $row['count'];
        }

        foreach ($branches as &$branch) {
            $branch['ip_count'] = isset($params[$branch['id']]) ? $params[$branch['id']] : 0;
        }

        $data['branches'] = $branches;

        $this->load->view('ip_access/list', $data);
    }

    public function manage($branch_id)
    {
        if (!staff_can('create', 'ip_access') && !staff_can('edit', 'ip_access')) {
            access_denied('ip_access');
        }

        if ($this->input->post()) {
            $ip = $this->input->post('ip_address');
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->db->insert(db_prefix() . 'ip_access', [
                    'branch_id' => $branch_id,
                    'ip_address' => $ip
                ]);
                set_alert('success', _l('added_successfully', 'IP Address'));
            } else {
                set_alert('warning', 'Invalid IP Address');
            }
            redirect(admin_url('ip_access/manage/' . $branch_id));
        }

        $this->db->where('id', $branch_id);
        $data['branch'] = $this->db->get(db_prefix() . 'customers_groups')->row();

        if (!$data['branch']) {
            show_404();
        }

        $this->db->where('branch_id', $branch_id);
        $data['ips'] = $this->db->get(db_prefix() . 'ip_access')->result_array();
        $data['title'] = 'Manage IP Access - ' . $data['branch']->name;

        $this->load->view('ip_access/manage', $data);
    }

    public function delete($id)
    {
        if (!staff_can('delete', 'ip_access')) {
            access_denied('ip_access');
        }

        $this->db->where('id', $id);
        $ip = $this->db->get(db_prefix() . 'ip_access')->row();

        if ($ip) {
            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'ip_access');
            set_alert('success', _l('deleted', 'IP Address'));
            redirect(admin_url('ip_access/manage/' . $ip->branch_id));
        }

        redirect(admin_url('ip_access'));
    }
}
