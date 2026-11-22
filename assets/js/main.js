document.addEventListener('DOMContentLoaded', function () {

    // open and close the user menu in the top bar
    var userBtn = document.getElementById('userBtn');
    var userDropdown = document.getElementById('userDropdown');

    if (userBtn && userDropdown) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('open');
        });
    }

    // ask before activating or deactivating
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // user form: show department or company depending on the role
    var role = document.getElementById('role');
    if (role) {
        var deptRoles = role.getAttribute('data-dept-roles').split(',');
        var deptField = document.getElementById('field-department');
        var companyField = document.getElementById('field-company');

        function showFields() {
            deptField.style.display = deptRoles.indexOf(role.value) !== -1 ? '' : 'none';
            companyField.style.display = role.value === 'contractor' ? '' : 'none';
        }
        role.addEventListener('change', showFields);
        showFields();
    }

    // open the settings of another user when one is picked
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    // ask before buttons that need a confirmation
    document.querySelectorAll('[data-confirm-click]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            if (!confirm(button.getAttribute('data-confirm-click'))) {
                e.preventDefault();
            }
        });
    });

    // switch between the tabs on the settings page
    document.querySelectorAll('.tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.tab').forEach(function (t) {
                t.classList.remove('active');
            });
            document.querySelectorAll('.tab-panel').forEach(function (panel) {
                panel.classList.remove('active');
            });
            tab.classList.add('active');
            document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        });
    });

    // settings page: untick and grey out a module's options when its access is set to no
    var options = document.querySelectorAll('input[data-module]');
    if (options.length) {
        function updateOptions() {
            options.forEach(function (box) {
                var allow = document.querySelector('input[data-access="' + box.getAttribute('data-module') + '"][value="1"]');
                if (allow.disabled) {
                    return;
                }
                box.disabled = !allow.checked;
                if (!allow.checked) {
                    box.checked = false;
                }
            });
        }
        document.querySelectorAll('input[data-access]').forEach(function (radio) {
            radio.addEventListener('change', updateOptions);
        });
        updateOptions();
    }

    // action form: the stage details are only needed when forwarding
    var stageFields = document.querySelector('.stage-fields');
    if (stageFields) {
        document.querySelectorAll('.choice input').forEach(function (radio) {
            radio.addEventListener('change', function () {
                stageFields.style.display = (radio.value === 'approve') ? '' : 'none';
            });
        });
    }
});
