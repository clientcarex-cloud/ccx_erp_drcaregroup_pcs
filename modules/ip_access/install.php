<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'ip_access')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'ip_access` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ip_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ip_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;');
}
