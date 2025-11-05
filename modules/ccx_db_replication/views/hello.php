<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h3 class="tw-mt-0 tw-mb-3"><?php echo html_escape($title ?? 'Hello'); ?></h3>
            <p class="tw-text-lg">Hello World 👋 — this is rendered inside the CRM layout.</p>

            <!-- Example: link back to dashboard -->
            <a href="<?php echo admin_url(); ?>" class="btn btn-primary">
              <i class="fa fa-home"></i> Back to Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
