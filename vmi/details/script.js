document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded and parsed");

    /* -------------------------------------------------- *
     *  LOGO UPLOAD LOGIC
     * -------------------------------------------------- */
    function checkFile() {
        const file = document.getElementById('logoInput').files[0];
        if (!file) {
            toastr.error('Please select a file.');
            return;
        }
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('checkExistence', 'true');

        const companyId = document.getElementById('companyId').value;
        formData.append('companyId', companyId);

        fetch('logo-upload.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                if (confirm('File already exists. Replace it?')) {
                    uploadFile(file, true, companyId);
                } else {
                    toastr.info('Upload cancelled.');
                }
            } else {
                uploadFile(file, false, companyId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Upload failed!');
        });
    }

    function uploadFile(file, overwrite, companyId) {
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('overwrite', overwrite);
        formData.append('companyId', companyId);

        fetch('logo-upload.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.uploaded) {
                toastr.success('Upload successful!');
            } else {
                toastr.error('Upload failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Upload failed!');
        });
    }

    window.checkFile = checkFile; // expose to <button onclick="checkFile()">

    /* -------------------------------------------------- *
     *  TANK VOLUME REPORT
     * -------------------------------------------------- */
    const submitButton = document.getElementById('submitButton');
    if (submitButton) {
        submitButton.addEventListener('click', function() {
            const userInput   = document.getElementById('userInputField').value;
            const groupList   = document.getElementById('groupList').value;
            const startHour   = document.getElementById('startHour').value;
            const finishHour  = document.getElementById('finishHour').value;
            const interval    = document.getElementById('interval').value;

            const data = { userInput, groupList, startHour, finishHour, interval };
            console.log('Sending data:', data);

            fetch('submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            })
            .then(r => { if (!r.ok) throw new Error('Network response was not ok'); return r.json(); })
            .then(data => {
                if (data.status === 'success') {
                    toastr.success(data.message || 'Tank volume report updated!');
                } else {
                    toastr.error(data.message || 'Error updating tank volume report');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                toastr.error('Failed to update tank volume report! ' + err);
            });
        });
    } else {
        console.debug('submitButton not present on this page (expected).');
    }

    /* -------------------------------------------------- *
     *  OFFLINE REPORT
     * -------------------------------------------------- */
    const offSubmitButton = document.getElementById('off_submitButton');
    const offScheduleType = document.getElementById('off_schedule_type');
    const weeklyWrap      = document.getElementById('off_weekly_wrap');
    const monthlyWrap     = document.getElementById('off_monthly_wrap');
    const offMonthDaySel  = document.getElementById('off_month_day_select');
    const offWeekdaySel   = document.getElementById('off_weekday_select');

    // Initial toggle state
    if (offScheduleType && weeklyWrap && monthlyWrap) {
        const applyToggle = () => {
            const mode = offScheduleType.value;
            if (mode === 'monthly') {
                weeklyWrap.style.display = 'none';
                monthlyWrap.style.display = '';
            } else {
                weeklyWrap.style.display = '';
                monthlyWrap.style.display = 'none';
            }
        };
        applyToggle();
        offScheduleType.addEventListener('change', applyToggle);
    }
    if (offSubmitButton) {
        offSubmitButton.addEventListener('click', function() {
            const off_userInput = document.getElementById('off_userInputField').value;
            const off_groupList = document.getElementById('off_groupList').value;
            const schedule_type = offScheduleType ? offScheduleType.value : 'weekly';
            const off_weekday   = offWeekdaySel ? offWeekdaySel.value : '';
            const off_month_day = offMonthDaySel ? offMonthDaySel.value : '';

            if (schedule_type === 'weekly') {
                if (!off_weekday) {
                    toastr.error('Please select a weekday.');
                    return;
                }
            } else if (schedule_type === 'monthly') {
                if (!off_month_day) {
                    toastr.error('Please select a day of month.');
                    return;
                }
            }

            const data = { off_userInput, off_groupList, schedule_type, off_weekday, off_month_day };

            fetch('submit_transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            })
            .then(r => { if (!r.ok) throw new Error('Network response was not ok'); return r.json(); })
            .then(data => {
                if (data.status === 'success') {
                    toastr.success('Transaction report updated!');
                } else {
                    toastr.error('Error updating transaction report: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                toastr.error('Network Error!');
            });
        });
    } else {
        console.debug('off_submitButton not present on this page (expected).');
    }

    /* ================================================== *
     *  NEW  —  DATATABLE INITIALISATION & ROW HANDLER
     * ================================================== */

    // Only run if the table exists and jQuery/DataTables have loaded
    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable && $('#users').length) {

        // Detect if Company column exists (check table headers)
        const $headers = $('#users thead th');
        let roleColumnIndex = 3; // Default (User, Name, Surname, Role)
        let hasCompanyColumn = false;
        
        $headers.each(function(index) {
            if ($(this).text().trim() === 'Company') {
                hasCompanyColumn = true;
                roleColumnIndex = 4; // Role is after Company
                return false; // break
            }
        });

        // Initialise or reuse existing DataTable instance
        const usersTable = $.fn.DataTable.isDataTable('#users')
            ? $('#users').DataTable()
            : $('#users').DataTable({
                dom: 'lrtip',
                order: [[0,'asc']],
                pageLength: 25,
                language: {
                    lengthMenu: 'Show _MENU_ users per page',
                    info: 'Showing _START_ to _END_ of _TOTAL_ users',
                    infoEmpty: 'No users found',
                    infoFiltered: '(filtered from _MAX_ total)'
                }
            });

        // Text search (idempotent bindings)
        $('#globalSearch')
            .off('keyup.vmiUserFilters change.vmiUserFilters')
            .on('keyup.vmiUserFilters change.vmiUserFilters', function(){
                usersTable.search(this.value).draw();
            });

        // Role dropdown filter (idempotent bindings)
        $('#roleFilter')
            .off('change.vmiUserFilters')
            .on('change.vmiUserFilters', function () {
                const val = this.value;
                usersTable.column(roleColumnIndex).search(val ? '^'+val+'$' : '', true, false).draw();
            });

        // Company filter (only if company column exists)
        if (hasCompanyColumn && $('#companyFilter').length) {
            // Custom search function for company filtering
            let currentCompanyFilter = '';
            
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (!currentCompanyFilter || settings.nTable.id !== 'users') {
                        return true;
                    }
                    const row = usersTable.row(dataIndex).node();
                    const rowCompanyId = $(row).attr('data-company-id');
                    return rowCompanyId == currentCompanyFilter;
                }
            );
            
            $('#companyFilter')
                .off('change.vmiUserFilters')
                .on('change.vmiUserFilters', function() {
                    currentCompanyFilter = this.value;
                    usersTable.draw();
                });
        }

        /* Check if user has admin permissions and disable submit buttons if not */
        const isAdmin = typeof ADMIN_LEVELS !== 'undefined' && 
                        typeof USER_ACCESS_LEVEL !== 'undefined' && 
                        ADMIN_LEVELS.includes(USER_ACCESS_LEVEL);
        
        if (!isAdmin) {
            $('.submit_delete').prop('disabled', true).css('opacity', '0.5').attr('title', 'Admin access required');
        }

        /* row-level "Submit" button (class .submit_delete) */
        $(document)
            .off('click.submitDelete')
            .on('click.submitDelete', '.submit_delete', function(e){
            e.preventDefault();

            // Check admin permissions
            if (!isAdmin) {
                toastr.error('You do not have permission to perform this action. Admin access required.');
                return;
            }

            const $row       = $(this).closest('tr');
            const username   = $row.find('input[name="edit_username"]').val();
            const userId     = $row.find('input[name="edit_usernameid"]').val();
            const newLevel   = $row.find('select[name="edit_user"]').val();

            if (!username || !userId) {
                toastr.error('Row data missing.');
                return;
            }

            $.post('user_update.php', {
                    edit_username:   username,
                    edit_usernameid: userId,
                    edit_user:       newLevel
                }, 'json')
             .done(resp => {
                 if (resp && resp.success) {
                     toastr.success('User updated.');
                     // invalidate the row so the new Role text shows without reload
                     usersTable.row($row).invalidate().draw(false);
                 } else {
                     toastr.error(resp.error || 'Update failed.');
                 }
             })
             .fail(xhr => {
                 toastr.error('Server error: ' + xhr.status);
             });
        });
    } // end table check
});
