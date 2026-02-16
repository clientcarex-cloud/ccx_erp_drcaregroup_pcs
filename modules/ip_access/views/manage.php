<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('ip_access/manage/' . $branch->id)); ?>
                        <div class="row">
                            <div class="col-md-8">
                                <?php echo render_input('ip_address', 'IP Address', '', 'text', ['placeholder' => 'e.g. 192.168.1.1']); ?>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-info mtop25">
                                    <?php echo _l('submit'); ?>
                                </button>
                            </div>
                        </div>
                        <?php echo form_close(); ?>
                        <div class="clearfix"></div>
                        <hr />
                        <table class="table dt-table" data-order-col="0" data-order-type="asc">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Added At</th>
                                    <th>
                                        <?php echo _l('options'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ips as $ip) { ?>
                                    <tr>
                                        <td>
                                            <?php echo $ip['ip_address']; ?>
                                        </td>
                                        <td>
                                            <?php echo _dt($ip['created_at']); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('ip_access/delete/' . $ip['id']); ?>"
                                                class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>

</html>