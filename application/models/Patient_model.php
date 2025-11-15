<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Patient_model extends App_Model
{
    /**
     * Fetch patients filtered by the provided branch ID.
     *
     * @param  int $branch_id
     * @return array
     */
    public function get_patients_by_branch($branch_id)
    {
        $branch_id = (int) $branch_id;

        if ($branch_id <= 0) {
            return [];
        }

        $clientsTable         = db_prefix() . 'clients';
        $customerGroupsTable  = db_prefix() . 'customer_groups';
        $customersGroupsTable = db_prefix() . 'customers_groups';

        $this->db->select([
            $clientsTable . '.userid as id',
            $clientsTable . '.company as name',
            $clientsTable . '.phonenumber as phone',
            $clientsTable . '.datecreated as created_on',
        ]);
        $this->db->from($clientsTable);
        $this->db->join($customerGroupsTable, $customerGroupsTable . '.customer_id = ' . $clientsTable . '.userid', 'inner');
        $this->db->join($customersGroupsTable, $customersGroupsTable . '.id = ' . $customerGroupsTable . '.groupid', 'inner');
        $this->db->where($customersGroupsTable . '.id', $branch_id);
        $this->db->group_by($clientsTable . '.userid');
        $this->db->order_by($clientsTable . '.company', 'asc');

        return $this->db->get()->result_array();
    }
}
