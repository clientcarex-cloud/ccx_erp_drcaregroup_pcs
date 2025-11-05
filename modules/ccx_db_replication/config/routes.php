<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Respect custom admin URI if defined
$adminSegment = defined('ADMIN_URI') ? trim(ADMIN_URI, '/') : 'admin';

// Primary admin route -> module controller
$route[$adminSegment . '/ccx_db_replication'] = 'ccx_db_replication/ccx_db_replication/index';

// Optional: allow direct access without admin prefix (legacy)
$route['ccx_db_replication'] = 'ccx_db_replication/ccx_db_replication/index';
