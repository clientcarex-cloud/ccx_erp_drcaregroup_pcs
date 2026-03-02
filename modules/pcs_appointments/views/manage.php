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

                <div id="appointmentSummaryCards"
                    class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-6 tw-gap-2">
                    <!-- Filled via JS -->
                </div>

                <br>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-6" style="margin-top: 7px;">
                                    <h4 class="no-margin"><?= _l('pcs_appointments'); ?></h4>
                                </div>
                                <div class="col-md-6 text-right d-flex justify-content-end gap-2">
                                    <?php if (staff_can('create', 'customers')) { ?>
                                        <a href="<?= admin_url('client/client/add_client'); ?>" class="btn btn-primary">
                                            <i class="fa-regular fa-plus tw-mr-1"></i>
                                            <?= _l('new_client'); ?>
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        <div id="appointment_table_div">

                            <div class="row">
                                <?php
                                $logged_in_staff_id = get_staff_user_id();

                                $filtered_doctors = array_filter($doctors, function ($doctor) use ($logged_in_staff_id) {
                                    return $doctor['staffid'] == $logged_in_staff_id;
                                });

                                if (!empty($filtered_doctors)) {
                                    $final_doctor_list = array_values($filtered_doctors);
                                    $selected_doctor_id = $logged_in_staff_id;
                                } else {
                                    $final_doctor_list = $doctors;
                                    $selected_doctor_id = '';
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
                                    <label for="appointment_status"><?= _l('status'); ?></label>
                                    <select class="form-control appointment_status" name="appointment_status"
                                        id="appointment_status">
                                        <option value=""><?= _l('select_response'); ?></option>
                                        <?php
                                        $allowed_status_names = ['Only Consulted', 'Visited'];
                                        $allowed_status_names_lower = array_map('strtolower', $allowed_status_names);
                                        foreach ($statuses as $status) {
                                            if (in_array(strtolower($status['name']), $allowed_status_names_lower)) {
                                                echo '<option value="' . $status['id'] . '">' . $status['name'] . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <label><?= _l('from_date'); ?></label>
                                    <input type="date" class="form-control" name="consulted_date" id="consulted_date"
                                        value="<?= date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label><?= _l('to_date'); ?></label>
                                    <input type="date" class="form-control" id="consulted_to_date"
                                        value="<?= date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <?= render_select('appointment_type_id', $appointment_type, ['appointment_type_id', 'appointment_type_name'], _l('appointment_type') . '*', '', ['data-none-selected-text' => _l('dropdown_non_selected_tex'), 'required' => 'required']) ?>
                                </div>
                                <div class="col-md-2 d-flex align-items-end" style="margin-top: 26px;">
                                    <button id="searchAppointmentsBtn"
                                        class="btn btn-success w-100"><?= _l('Search'); ?></button>
                                </div>
                            </div>
                            <br>

                            <?= render_datatable([
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
                            ], 'appointments'); ?>

                        </div>
                        <div id="paymentFormContainer" style="display: none;">
                            <div class="text-end mb-3" style="margin-top: 10px">
                                <button type="button" class="btn btn-secondary" id="backToPaymentsBtn">←
                                    <?php echo _l('back'); ?>
                                </button>
                            </div>

                            <?= form_open(admin_url('invoices/record_payment'), [
                                'id' => 'record_payment_form',
                            ]); ?>

                            <?= form_hidden('invoiceid', '', ['id' => 'invoiceid']) ?>

                            <div class="row" style="width: 99%">
                                <div class="form-group col-md-2">
                                    <label for="paymentmode"
                                        class="control-label"><?= _l('record_payment_date'); ?></label>
                                    <?php $today = date('d-m-Y'); ?>
                                    <input type="text" name="date" class="form-control" value="<?= $today; ?>" readonly>
                                </div>

                                <div class="form-group col-md-4">
                                    <label
                                        class="control-label d-block"><?= _l('Amount Received / Payment Mode'); ?></label>
                                    <div style="display: flex; gap: 0;">
                                        <div style="flex: 1; max-width: 60%;">
                                            <input type="number" name="amount" class="form-control"
                                                placeholder="<?= _l('amount'); ?>"
                                                value="<?= htmlspecialchars($amount ?? '') ?>">
                                        </div>
                                        <div style="flex: 1; max-width: 40%;">
                                            <select class="form-control selectpicker" name="paymentmode"
                                                data-width="100%"
                                                data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                                <!-- populate options dynamically -->
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <?= render_input('note', 'remarks'); ?>
                                </div>

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-success" name="submit_action" value="pay"
                                        style="margin-top: 24px"><?= _l('pay'); ?></button>
                                    <button type="submit" class="btn btn-success" name="submit_action" value="pay_print"
                                        style="margin-top: 24px"><?= _l('pay_print'); ?></button>
                                </div>
                            </div>

                            <?= form_close(); ?>

                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const form = document.getElementById('record_payment_form');

                                form.addEventListener('submit', function (e) {
                                    const clickedButton = document.activeElement;
                                    if (clickedButton.name === 'submit_action') {
                                        if (clickedButton.value === 'pay') {
                                            form.removeAttribute('target');
                                        } else if (clickedButton.value === 'pay_print') {
                                            form.setAttribute('target', '_blank');
                                        }
                                    }
                                });
                            });
                        </script>

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
            url: admin_url + 'pcs_appointments/get_appointment_summary',
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
                    const branch_val = $('#appointment_branch_id').val() || '0';
                    const appointment_type_id_val = $('#appointment_type_id').val();

                    $cards.removeClass('is-active').attr('aria-pressed', 'false');
                    $card.addClass('is-active').attr('aria-pressed', 'true');
                    activeAppointmentSummaryFilter = filterType;

                    if ($.fn.DataTable.isDataTable('.table-appointments')) {
                        let table = $('.table-appointments').DataTable();
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

                $cards.on('keydown', function (e) {
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        $(this).trigger('click');
                    }
                });
            }
        });
    }

    $(function () {
        // Initialize the appointments DataTable
        let fromDate = $('#consulted_date').val();
        let toDate = $('#consulted_to_date').val();
        initDataTable(
            '.table-appointments',
            '<?= admin_url("pcs_appointments/appointments/1/") ?>' + fromDate + '/' + toDate,
            [1],
            [1]
        );

        // Load summary cards
        const initialBranch = $('#appointment_branch_id').val() || '0';
        loadAppointmentSummary('', '', '', initialBranch);

        // Search button handler
        $('#searchAppointmentsBtn').click(function () {
            const from = $('#consulted_date').val();
            const to = $('#consulted_to_date').val();

            let enquiry_doctor_id = $('#enquiry_doctor_id').val();
            enquiry_doctor_id = enquiry_doctor_id ? enquiry_doctor_id : '0';

            let appointment_type_id = $('#appointment_type_id').val();
            let branch_id = $('#appointment_branch_id').val();
            branch_id = branch_id ? branch_id : '0';

            let visit_status = $('#appointment_status option:selected').text().trim();
            visit_status = visit_status && visit_status !== 'Select Response' ? visit_status.replace(/\s+/g, '_') : 'All';

            activeAppointmentSummaryFilter = null;
            loadAppointmentSummary(from, to, enquiry_doctor_id, branch_id, appointment_type_id);

            if ($.fn.DataTable.isDataTable('.table-appointments')) {
                const url = `<?= admin_url("pcs_appointments/appointments/1/") ?>${from}/${to}/${enquiry_doctor_id}/${visit_status}/${branch_id}/0/${appointment_type_id}`;
                $('.table-appointments').DataTable().ajax.url(url).load();
            }
        });
    });

    function confirmBooking(id) {
        if (confirm("Are you sure you want to confirm this visit?")) {
            $.post("<?= site_url('client/confirm_booking/0/yes'); ?>", { id: id }, function (response) {
                if (response.success) {
                    alert_float("success", response.message || "Visit confirmed.");
                    setTimeout(function () {
                        window.location.href = "<?= admin_url('pcs_appointments'); ?>";
                    }, 1000);
                } else {
                    alert_float("danger", response.message || "Failed to confirm Visit.");
                }
            }, 'json');
        }
    }

    function showPaymentForm(invoiceId) {
        $('#paymentFormContainer').show();
        $('.table-responsive').hide();
        $('#appointment_table_div').hide();

        $('#record_payment_form')[0].reset();
        $('#record_payment_form input[name="invoiceid"]').val(invoiceId);

        $.get(admin_url + 'client/get_invoice_data/' + invoiceId, function (response) {
            let data;

            if (typeof response === 'string') {
                try {
                    data = JSON.parse(response);
                } catch (e) {
                    alert_float('danger', 'Invalid JSON response.');
                    return;
                }
            } else {
                data = response;
            }

            if (!data.amount_left) {
                alert_float('danger', 'Amount left to pay not found in response.');
                return;
            }

            $('input[name="amount"]').val(data.amount_left);
            $('input[name="amount"]').attr('max', data.amount_left);

            let paymentModesSelect = $('select[name="paymentmode"]');
            paymentModesSelect.empty().append('<option value=""></option>');

            if (Array.isArray(data.payment_modes)) {
                let selectedByDefault = false;

                data.payment_modes.forEach(function (mode) {
                    let selected = '';
                    if (!selectedByDefault && mode.name.toLowerCase().includes('bank')) {
                        selected = 'selected';
                        selectedByDefault = true;
                    }

                    paymentModesSelect.append(
                        '<option value="' + mode.id + '" ' + selected + '>' + mode.name + '</option>'
                    );
                });

                paymentModesSelect.selectpicker('refresh');
            } else {
                alert_float('warning', 'No valid payment modes available.');
            }

            $('#paymentFormContainer').show();
        }).fail(function (xhr) {
            alert_float('danger', 'Failed to load payment data.');
        });
    }

    $('#backToPaymentsBtn').on('click', function () {
        $('#paymentFormContainer').hide();
        $('.table-responsive').show();
        $('#appointment_table_div').show();
    });
</script>
<script>
    document.getElementById('record_payment_form').addEventListener('submit', function () {
        setTimeout(function () {
            location.reload();
        }, 500);
    });
</script>

</body>

</html>