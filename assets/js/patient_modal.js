(function ($) {
    "use strict";

    function getBranchIdFromToken(token) {
        if (typeof token !== "string") {
            return null;
        }

        var parts = token.split("||");

        if (parts.length !== 2) {
            return null;
        }

        if ($.trim(parts[0]) !== "patientlist") {
            return null;
        }

        var branchId = $.trim(parts[1]);

        return branchId === "" || !/^\d+$/.test(branchId) ? null : branchId;
    }

    function getRequestData() {
        var payload = {};

        if (typeof csrfData !== "undefined") {
            payload[csrfData["token_name"]] = csrfData["hash"];
        }

        return payload;
    }

    function ensureModalWrapper() {
        var $wrapper = $("#ccx-patient-modal-wrapper");

        if (!$wrapper.length) {
            $wrapper = $('<div id="ccx-patient-modal-wrapper"></div>').appendTo("body");
        }

        return $wrapper;
    }

    $("body").on("click", ".ccx-open", function (e) {
        var token = $(this).data("task");
        var branchId = getBranchIdFromToken(typeof token === "undefined" ? "" : token.toString());

        if (!branchId) {
            return true;
        }

        e.preventDefault();

        $("body").append('<div class="dt-loader"></div>');

        $.ajax({
            url: admin_url + "clients/get_patient_list_modal/" + encodeURIComponent(branchId),
            type: "POST",
            data: getRequestData(),
            dataType: "json",
        })
            .done(function (response) {
                if (response && response.success && response.html) {
                    var $wrapper = ensureModalWrapper();
                    $wrapper.html(response.html);
                    $("#patientModal").modal("show");
                } else if (response && response.message) {
                    alert_float("danger", response.message);
                } else {
                    alert_float("danger", app.lang ? app.lang.unknown_error : "Unable to load patient list.");
                }
            })
            .fail(function (xhr) {
                var message =
                    (xhr && xhr.responseJSON && xhr.responseJSON.message) ||
                    xhr.responseText ||
                    "Unable to load patient list.";
                alert_float("danger", message);
            })
            .always(function () {
                $("body").find(".dt-loader").remove();
            });

        return false;
    });
})(jQuery);
