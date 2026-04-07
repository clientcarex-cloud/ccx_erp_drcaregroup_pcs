<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$CI = &get_instance();

$staffId = get_staff_user_id();
$limit   = hooks()->apply_filters('dashboard_my_reminders_widget_limit', 10);

$CI->db->select('id, rel_id, rel_type, description, date, creator');
$CI->db->where('staff', $staffId);
$CI->db->where('isnotified', 0);
$CI->db->order_by('date', 'asc');
$CI->db->limit((int) $limit);
$reminders = $CI->db->get(db_prefix() . 'reminders')->result_array();

$totalReminders = total_rows(db_prefix() . 'reminders', [
    'staff'      => $staffId,
    'isnotified' => 0,
]);
?>
<div class="widget" id="widget-<?php echo create_widget_id(); ?>" data-name="<?php echo _l('my_reminders'); ?>">
    <div class="panel_s">
        <div class="panel-body padding-10">
            <div class="widget-dragger"></div>

            <div class="tw-flex tw-items-center tw-justify-between tw-p-1.5">
                <p class="tw-font-semibold tw-flex tw-items-center tw-mb-0 tw-space-x-1.5 rtl:tw-space-x-reverse">
                    <i class="fa-regular fa-clock tw-text-neutral-500"></i>
                    <span class="tw-text-neutral-700"><?php echo _l('my_reminders'); ?></span>
                    <?php if ($totalReminders > 0) { ?>
                    <span class="badge"><?php echo e($totalReminders); ?></span>
                    <?php } ?>
                </p>
                <a href="<?php echo admin_url('misc/reminders'); ?>" class="tw-text-sm tw-mb-0">
                    <?php echo _l('home_widget_view_all'); ?>
                </a>
            </div>

            <hr class="-tw-mx-3 tw-mt-2 tw-mb-4">

            <?php if (count($reminders) > 0) { ?>
            <ul class="list-unstyled tw-mb-0">
                <?php foreach ($reminders as $reminder) {
                    $rel_data   = get_relation_data($reminder['rel_type'], $reminder['rel_id']);
                    $rel_values = get_relation_values($rel_data, $reminder['rel_type']);
                    ?>
                <li class="tw-px-2 tw-py-2 tw-border-b tw-border-solid tw-border-neutral-100 last:tw-border-b-0">
                    <a href="<?php echo $rel_values['link']; ?>" class="tw-font-medium">
                        <?php echo e($rel_values['name']); ?>
                    </a>
                    <div class="tw-text-neutral-600 tw-text-sm tw-mt-1">
                        <?php echo process_text_content_for_display($reminder['description']); ?>
                    </div>
                    <div class="tw-text-neutral-500 tw-text-xs tw-mt-1">
                        <?php echo e(_dt($reminder['date'])); ?>
                    </div>
                </li>
                <?php } ?>
            </ul>
            <?php } else { ?>
            <p class="tw-text-sm tw-text-neutral-500 tw-mb-0 tw-px-2 tw-py-2">
                No reminders assigned to you.
            </p>
            <?php } ?>
        </div>
    </div>
</div>
