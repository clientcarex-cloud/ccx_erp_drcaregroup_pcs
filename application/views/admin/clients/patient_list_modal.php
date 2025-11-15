<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="patientModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Patient List</h4>
            </div>
            <div class="modal-body">
                <?php if (!empty($patients)) { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered dt-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Created On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient) { ?>
                                    <tr>
                                        <td><?= html_escape($patient['id']); ?></td>
                                        <td><?= html_escape($patient['name']); ?></td>
                                        <td><?= html_escape($patient['phone']); ?></td>
                                        <td><?= !empty($patient['created_on']) ? _dt($patient['created_on']) : ''; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <p class="text-center text-muted mtop20 mbot20">No patients found.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
