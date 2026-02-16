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
                        <div class="clearfix"></div>
                        <table class="table dt-table" data-order-col="0" data-order-type="asc">
                            <thead>
                                <tr>
                                    <th>
                                        <?php echo _l('branch'); ?>
                                    </th>
                                    <th>IP Count</th>
                                    <th>
                                        <?php echo _l('options'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branches as $branch) { ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('ip_access/manage/' . $branch['id']); ?>">
                                                <?php echo $branch['name']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($branch['ip_count'] == 0) { ?>
                                                <span class="label label-warning">Unrestricted</span>
                                            <?php } else { ?>
                                                <span class="label label-info">
                                                    <?php echo $branch['ip_count']; ?>
                                                </span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('ip_access/manage/' . $branch['id']); ?>"
                                                class="btn btn-default btn-icon"><i class="fa fa-pencil-square-o"></i></a>
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