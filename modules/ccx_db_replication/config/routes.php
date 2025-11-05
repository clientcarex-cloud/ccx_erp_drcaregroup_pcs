<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Respect custom admin URI if defined
$adminSegment = defined('ADMIN_URI') ? trim(ADMIN_URI, '/') : 'admin';

// Admin routes
$route[$adminSegment . '/ccx_db_replication']        = 'ccx_db_replication/index';
$route[$adminSegment . '/ccx_db_replication/(:any)'] = 'ccx_db_replication/$1';

// Optional direct access without admin prefix (legacy/CLI)
$route['ccx_db_replication']        = 'ccx_db_replication/index';
$route['ccx_db_replication/(:any)'] = 'ccx_db_replication/$1';
