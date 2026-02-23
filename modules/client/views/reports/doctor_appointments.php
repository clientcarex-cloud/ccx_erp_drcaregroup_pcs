<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    .swal2-popup {
        font-size: 1.6rem !important;
    }

    .tw-bg-white {
        --tw-bg-opacity: 1 !important;
    }

    .summary-card {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 3px 8px rgba(15, 23, 42, 0.05);
        cursor: pointer;
        transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
        --card-accent: #2563eb;
        --card-accent-rgb: 37, 99, 235;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
    }

    .summary-card:focus-visible {
        outline: 3px solid rgba(var(--card-accent-rgb), 0.35);
        outline-offset: 2px;
    }

    .summary-card__content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .summary-card__count {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        line-height: 1.2;
    }

    .summary-card__label {
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
        letter-spacing: 0.02em;
    }

    .summary-card__indicator {
        align-self: center;
        width: 12px;
        height: 12px;
        border-radius: 999px;
        background-color: rgba(var(--card-accent-rgb), 0.18);
        box-shadow: 0 0 0 4px rgba(var(--card-accent-rgb), 0.12);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .summary-card.is-active {
        border-color: var(--card-accent);
        background: linear-gradient(135deg, rgba(var(--card-accent-rgb), 0.1), #ffffff);
        box-shadow: 0 18px 30px rgba(var(--card-accent-rgb), 0.28);
    }

    .summary-card.is-active .summary-card__indicator {
        transform: scale(1.25);
        background-color: var(--card-accent);
        box-shadow: 0 0 0 6px rgba(var(--card-accent-rgb), 0.2);
    }

    .summary-card.is-active .summary-card__count,
    .summary-card.is-active .summary-card__label {
        color: var(--card-accent);
    }
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?= _l($title); ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>

                        <div id="appointmentSummaryCards"
                            class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-6 tw-gap-2 mb-4">
                            <!-- Filled via JS -->
                        </div>
                        <br>

                        <div class="row">
                            <?php
                            $logged_in_staff_id = get_staff_user_id();

                            // Filter $doctors to include only the logged-in staff member
                            $filtered_doctors = array_filter($doctors, function ($doctor) use ($logged_in_staff_id) {
                                return $doctor['staffid'] == $logged_in_staff_id;
                            });

                            // If match found, use filtered list and preselect it
                            if (!empty($filtered_doctors)) {
                                $final_doctor_list = array_values($filtered_doctors); // Reindex
                                $selected_doctor_id = $logged_in_staff_id;
                            } else {
                                $final_doctor_list = $doctors;
                                $selected_doctor_id = ''; // No default
                            }
                            ?>

                            <?php if (count($final_doctor_list) > 0 && count($final_doctor_list) != 1): ?>
                                <div class="col-md-4">
                                    <?= render_select(
                                        'appointment_branch_id',
                                        $branch,
                                        ['id', 'name'],
                                        _l('branch') . '*',
                                        isset($current_branch_id) ? $current_branch_id : '',
                                        [],
                                        [],
                                        '',
                                        ['id' => 'appointment_branch_id', 'required' => 'required', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    ) ?>
                                </div>
                                <div class="col-md-4">
                                    <?= render_select(
                                        'enquiry_doctor_id',
                                        $final_doctor_list,
                                        ['staffid', ['firstname', 'lastname']],
                                        _l('doctor'),
                                        $selected_doctor_id,
                                        ['id' => 'enquiry_doctor_id', 'data-none-selected-text' => _l('dropdown_non_selected_tex')]
                                    ) ?>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label for="appointment_status">
                                    <?= _l('status'); ?>
                                </label>
                                <select class="form-control appointment_status" name="appointment_status"
                                    id="appointment_status">
                                    <option value="">
                                        <?= _l('select_response'); ?>
                                    </option>
                                    <option value="Only Consulted">Only Consulted</option>
                                    <option value="Visited">Visited</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <label>
                                    <?= _l('from_date'); ?>
                                </label>
                                <input type="date" class="form-control" name="consulted_date" id="consulted_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label>
                                    <?= _l('to_date'); ?>
                                </label>
                                <input type="date" class="form-control" name="consulted_to_date" id="consulted_to_date"
                                    value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <?= render_select('appointment_type_id', $appointment_type, ['appointment_type_id', 'appointment_type_name'], _l('appointment_type') . '*', '', ['data-none-selected-text' => _l('dropdown_non_selected_tex'), 'required' => 'required']) ?>
                            </div>
                            <div class="col-md-2 d-flex align-items-end" style="margin-top: 26px;">
                                <button id="searchAppointmentsBtn" class="btn btn-success w-100">
                                    <?= _l('Search'); ?>
                                </button>
                            </div>
                        </div>
                        <br>

                        <!-- Table -->
                        <?php echo render_datatable([
                            _l('patient_name'),
                            _l('patient_mobile'),
                            _l('assigned_doctor'),
                            _l('appointment_date'),
                            _l('consulted_date'),
                            _l('consultation_duration'),
                            _l('treatment'),
                            _l('appointment_type'),
                            _l('branch'),
                            _l('registration_end_date'),
                            _l('consultation_fee'),
                            _l('payment_status'),
                            _l('action'),
                        ], 'doctor-appointments'); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    const buildSummaryCard = (count, label, filter, accentHex, accentRgb) => `
    <div class="summary-card tw-flex tw-items-start" data-filter="${filter}" role="button" tabindex="0" aria-pressed="false" style="--card-accent:${accentHex}; --card-accent-rgb:${accentRgb};">
        <div class="summary-card__content">
            <span class="summary-card__count">${count}</span>
            <span class="summary-card__label">${label}</span>
        </div>
        <span class="summary-card__indicator" aria-hidden="true"></span>
    </div>
`;

    let activeAppointmentSummaryFilter = null;

    function loadAppointmentSummary(from_date = '', to_date = '', enquiry_doctor_id = '', branch_id = '', appointment_type_id = '') {
        $.ajax({
            url: admin_url + 'client/get_appointment_summary',
            type: 'POST',
            data: {
                from_date: from_date,
                to_date: to_date,
                enquiry_doctor_id: enquiry_doctor_id,
                branch_id: branch_id,
                appointment_type_id: appointment_type_id,
            },
            dataType: 'json',
            success: function (res) {
                $('#appointmentSummaryCards').html([
                    buildSummaryCard(res.total, '<?= _l('appointments'); ?>', 'all', '#2563eb', '37, 99, 235'),
                    buildSummaryCard(res.missed, '<?= _l('missed'); ?>', 'missed', '#ef4444', '239, 68, 68'),
                    buildSummaryCard(res.consulted, '<?= _l('consulted'); ?>', 'consulted', '#10b981', '16, 185, 129')
                ].join(''));

                const $cards = $('#appointmentSummaryCards .summary-card');

                if (activeAppointmentSummaryFilter) {
                    const $activeCard = $cards.filter('[data-filter="' + activeAppointmentSummaryFilter + '"]');
                    if ($activeCard.length) {
                        $activeCard.addClass('is-active').attr('aria-pressed', 'true');
                    }
                }

                $cards.on('click', function () {
                    const $card = $(this);
                    const filterType = $card.data('filter');
                    const from = $('#consulted_date').val();
                    const to = $('#consulted_to_date').val();
                    const doctor_id = $('#enquiry_doctor_id').val();
                    const branch_val = $('#appointment_branch_id').val();
                    const appointment_type_id_val = $('#appointment_type_id').val();

                    $cards.removeClass('is-active').attr('aria-pressed', 'false');
                    $card.addClass('is-active').attr('aria-pressed', 'true');
                    activeAppointmentSummaryFilter = filterType;

                    if ($.fn.DataTable.isDataTable('.table-doctor-appointments')) {
                        let table = $('.table-doctor-appointments').DataTable();
                        table.settings()[0].ajax.data = function (d) {
                            d.from_date = from;
                            d.to_date = to;
                            d.enquiry_doctor_id = doctor_id;
                            d.branch_id = branch_val;
                            d.summary_filter = filterType;
                            d.appointment_type_id = appointment_type_id_val;
                        };
                        table.ajax.reload();
                    }
                });
            }
        });
    }

    $(document).ready(function () {

        let from = $('#consulted_date').val();
        let to = $('#consulted_to_date').val();
        let enquiry_doctor_id = $('#enquiry_doctor_id').val() || '0';
        let appointment_type_id = $('#appointment_type_id').val() || '0';
        let branch_id = $('#appointment_branch_id').val() || '0';

        loadAppointmentSummary(from, to, enquiry_doctor_id, branch_id, appointment_type_id);

        var url = `<?= admin_url("client/reports/doctor_appointments/null/") ?>${from}/${to}/${enquiry_doctor_id}/All/${branch_id}/0/${appointment_type_id}`;

        var table = initDataTable(
            '.table-doctor-appointments',
            url,
            [1],
            [1]
        );

        $('#searchAppointmentsBtn').click(function () {
            from = $('#consulted_date').val();
            to = $('#consulted_to_date').val();
            enquiry_doctor_id = $('#enquiry_doctor_id').val() || '0';
            appointment_type_id = $('#appointment_type_id').val() || '0';
            branch_id = $('#appointment_branch_id').val() || '0';

            let visit_status = $('#appointment_status option:selected').text().trim();
            visit_status = visit_status && visit_status !== 'Select Response' ? visit_status.replace(/\s+/g, '_') : 'All';

            activeAppointmentSummaryFilter = null;
            loadAppointmentSummary(from, to, enquiry_doctor_id, branch_id, appointment_type_id);

            if ($.fn.DataTable.isDataTable('.table-doctor-appointments')) {
                const newUrl = `<?= admin_url("client/reports/doctor_appointments/null/") ?>${from}/${to}/${enquiry_doctor_id}/${visit_status}/${branch_id}/0/${appointment_type_id}`;
                $('.table-doctor-appointments').DataTable().ajax.url(newUrl).load();
            }
        });
    });
</script>

</body>

</html>