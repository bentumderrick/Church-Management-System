
<script>
(function () {
    // ---- Check if modal already exists in the container ----
    var container = document.getElementById('memberModalContainer');
    if (!container) {
        // If container doesn't exist, create it at the body level
        container = document.createElement('div');
        container.id = 'memberModalContainer';
        document.body.appendChild(container);
    }

    // Check if modal is already rendered
    if (container.querySelector('#memberModal')) {
        // Modal exists, just ensure it's initialized
        var existingModal = container.querySelector('#memberModal');
        if (existingModal.dataset.init === 'true') return;
    }

    // ---- Build modal HTML ----
    var modalHTML = `
        <div class="mm-overlay" id="memberModal">
            <div class="mm-dialog">
                <div class="mm-head">
                    <h2 id="mm-title">Member</h2>
                    <button class="mm-x" type="button" onclick="closeMemberModal()">&times;</button>
                </div>
                <div class="mm-body" id="mm-body">
                    <div class="mm-loading"><i class="fas fa-spinner fa-spin"></i> Loading&hellip;</div>
                </div>
                <div class="mm-foot" id="mm-foot">
                    <button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Close</button>
                </div>
            </div>
        </div>
        <input type="file" id="mm-photo-input" accept="image/*" style="display:none">
        <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    `;

    // Append to container
    container.innerHTML = modalHTML;

  
    if (!document.getElementById('mm-styles')) {
        var style = document.createElement('style');
        style.id = 'mm-styles';
        style.textContent = `
            .mm-overlay {
                position: fixed; inset: 0; z-index: 9000; display: none;
                align-items: center; justify-content: center; padding: 20px;
                background: rgba(4, 10, 18, 0.72); backdrop-filter: blur(4px);
                pointer-events: none;
            }
            .mm-overlay.open { display: flex; pointer-events: auto; }
            .mm-dialog {
                width: 100%; max-width: 620px; max-height: 88vh; display: flex; flex-direction: column;
                background: #12233a; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;
                box-shadow: 0 24px 70px rgba(0,0,0,0.6); animation: mmIn 0.18s ease;
                font-family: 'Poppins', sans-serif;
                pointer-events: auto;
            }
            @keyframes mmIn { from { opacity:0; transform: scale(0.95); } to { opacity:1; transform: none; } }
            .mm-head {
                display: flex; align-items: center; justify-content: space-between; gap: 12px;
                padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
                background: rgba(255,165,0,0.05);
            }
            .mm-head h2 { margin: 0; font-size: 1.2rem; color: #fff; }
            .mm-x { background: none; border: 0; color: #adb5bd; font-size: 1.8rem; cursor: pointer; padding: 0 4px; }
            .mm-x:hover { color: #fff; }
            .mm-body { padding: 20px; overflow-y: auto; flex: 1; }
            .mm-foot {
                display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;
                padding: 14px 20px; border-top: 1px solid rgba(255,255,255,0.08);
                background: rgba(255,255,255,0.02);
            }
            .mm-btn {
                border: 0; border-radius: 8px; padding: 8px 16px; font-weight: 600; cursor: pointer;
                display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem;
                font-family: 'Poppins', sans-serif; transition: all 0.2s;
            }
            .mm-btn.close { background: rgba(255,255,255,0.1); color: #adb5bd; }
            .mm-btn.close:hover { background: rgba(255,255,255,0.2); color: #fff; }
            .mm-btn.edit { background: orange; color: #0c1a2b; }
            .mm-btn.edit:hover { background: #ff8c00; }
            .mm-btn.save { background: #2ecc71; color: #0c1a2b; }
            .mm-btn.save:hover { background: #27ae60; }
            .mm-btn.delete { background: rgba(231,76,60,0.2); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }
            .mm-btn.delete:hover { background: rgba(231,76,60,0.4); }
            .mm-btn.photo { background: rgba(155,89,182,0.2); color: #9b59b6; border: 1px solid rgba(155,89,182,0.3); }
            .mm-btn.photo:hover { background: rgba(155,89,182,0.4); }
            .mm-btn.cancel { background: rgba(255,255,255,0.05); color: #adb5bd; border: 1px solid rgba(255,255,255,0.1); }
            .mm-btn.cancel:hover { background: rgba(255,255,255,0.1); }
            .mm-btn:disabled { opacity: 0.6; cursor: not-allowed; }

            .mm-profile { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
            .mm-profile .mm-avatar {
                width: 72px; height: 72px; border-radius: 50%; overflow: hidden;
                background: linear-gradient(135deg, #667eea, #764ba2);
                display: flex; align-items: center; justify-content: center;
                font-size: 2rem; font-weight: bold; color: #fff; flex-shrink: 0;
            }
            .mm-profile .mm-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .mm-profile .mm-name { font-size: 1.2rem; font-weight: 600; color: #fff; }
            .mm-profile .mm-id { font-size: 0.85rem; color: #adb5bd; }
            .mm-rows { display: grid; gap: 4px; }
            .mm-row {
                display: flex; justify-content: space-between; gap: 16px;
                padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
            }
            .mm-row span:first-child { color: #adb5bd; font-size: 0.85rem; }
            .mm-row span:last-child { font-weight: 500; color: #fff; text-align: right; }
            .mm-loading { text-align: center; padding: 30px 0; color: #adb5bd; }
            .mm-empty { text-align: center; padding: 30px 0; color: #6c757d; }
            .mm-empty i { font-size: 3rem; display: block; margin-bottom: 15px; opacity: 0.3; }
            .mm-photo-full { max-width: 100%; max-height: 60vh; border-radius: 8px; display: block; margin: 0 auto; }

            .mm-form-group { margin-bottom: 16px; }
            .mm-form-group label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: #adb5bd; font-weight: 500; }
            .mm-form-group input, .mm-form-group textarea, .mm-form-group select {
                width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.06);
                border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #fff;
                font-family: 'Poppins', sans-serif; font-size: 0.95rem; transition: border 0.2s;
            }
            .mm-form-group input:focus, .mm-form-group textarea:focus, .mm-form-group select:focus {
                outline: none; border-color: orange; background: rgba(255,255,255,0.08);
            }
            .mm-form-group textarea { resize: vertical; min-height: 60px; }
            .mm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            @media (max-width: 520px) { .mm-form-row { grid-template-columns: 1fr; } }

            /* ---- Toasts ---- */
            .toast-container {
                position: fixed; top: 20px; right: 20px; z-index: 10000;
                display: flex; flex-direction: column; gap: 10px;
                pointer-events: none;
            }
            .toast {
                background: #1a2a3a; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;
                padding: 14px 18px; color: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                display: flex; align-items: center; gap: 12px; pointer-events: auto;
                min-width: 280px; max-width: 360px; font-family: 'Poppins', sans-serif;
                animation: toastIn 0.25s ease both;
            }
            .toast.leaving { animation: toastOut 0.25s ease both; }
            .toast i { font-size: 1.3rem; flex-shrink: 0; }
            .toast.success { border-color: rgba(46,204,113,0.45); background: #0f2a1a; }
            .toast.success i { color: #2ecc71; }
            .toast.error { border-color: rgba(231,76,60,0.45); background: #2a0f0f; }
            .toast.error i { color: #e74c3c; }
            .toast.info { border-color: rgba(52,152,219,0.45); background: #0f1f2a; }
            .toast.info i { color: #3498db; }
            .toast .toast-msg { flex: 1; font-size: 0.92rem; line-height: 1.35; }
            .toast .toast-close { background: none; border: 0; color: #adb5bd; cursor: pointer; font-size: 1.25rem; line-height: 1; padding: 0 2px; }
            .toast .toast-close:hover { color: #fff; }
            @keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
            @keyframes toastOut { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateX(24px); } }
            @media (max-width: 520px) {
                .toast-container { left: 12px; right: 12px; top: 12px; }
                .toast { min-width: 0; max-width: none; }
            }
        `;
        document.head.appendChild(style);
    }

    // ---- Now run the modal logic ----
    initModalFunctions();

    function initModalFunctions() {
        var modal = document.getElementById('memberModal');
        if (!modal) return;
        if (modal.dataset.init === 'true') return;
        modal.dataset.init = 'true';

        var bodyEl = document.getElementById('mm-body');
        var footEl = document.getElementById('mm-foot');
        var titleEl = document.getElementById('mm-title');

        /* ---------------- Toasts ---------------- */
        function getToastContainer() {
            var c = document.getElementById('toast-container');
            if (!c) {
                c = document.createElement('div');
                c.id = 'toast-container';
                c.className = 'toast-container';
                c.setAttribute('aria-live', 'polite');
                document.body.appendChild(c);
            }
            return c;
        }

        function removeToast(toast) {
            if (!toast || !toast.parentNode) return;
            toast.classList.add('leaving');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
        }

        function showToast(message, type, duration) {
            type = type || 'success';
            duration = typeof duration === 'number' ? duration : 3200;

            var container = getToastContainer();
            var toast = document.createElement('div');
            toast.className = 'toast ' + type;

            var icon = document.createElement('i');
            icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle'
                                    : type === 'error' ? 'fa-exclamation-circle'
                                    : 'fa-info-circle');

            var msg = document.createElement('span');
            msg.className = 'toast-msg';
            msg.textContent = message == null ? '' : String(message);

            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'toast-close';
            close.innerHTML = '&times;';
            close.addEventListener('click', function () { removeToast(toast); });

            toast.appendChild(icon);
            toast.appendChild(msg);
            toast.appendChild(close);
            container.appendChild(toast);

            while (container.children.length > 4) removeToast(container.firstElementChild);

            if (duration > 0) setTimeout(function () { removeToast(toast); }, duration);
            return toast;
        }

        window.showToast = showToast;
        window.toastSuccess = function (m) { return showToast(m, 'success'); };
        window.toastError = function (m) { return showToast(m, 'error'); };
        window.toastInfo = function (m) { return showToast(m, 'info'); };

        /* ---------------- Helpers ---------------- */
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text).replace(/[&<>"']/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'Not set';
            var d = new Date(String(dateStr).replace(' ', 'T'));
            if (isNaN(d.getTime())) return 'Not set';
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        function post(action, fields) {
            var fd = fields instanceof FormData ? fields : new FormData();
            fd.append('action', action);
            if (fields && !(fields instanceof FormData)) {
                Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
            }
            return fetch('member-actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (res) {
                    return res.text().then(function (txt) {
                        try { return JSON.parse(txt); }
                        catch (e) {
                            console.error('member-actions.php returned non-JSON:', txt);
                            throw new Error('Server error. Please try again.');
                        }
                    });
                });
        }

        function refreshMembers() {
            if (typeof window.loadPage === 'function') window.loadPage('members');
            else window.location.reload();
        }

        /* ---------------- Modal ---------------- */
        function openModal() {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        window.closeMemberModal = function () {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        };

        function setError(message) {
            titleEl.textContent = 'Error';
            bodyEl.innerHTML = '<div class="mm-empty"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(message) + '</p></div>';
            footEl.innerHTML = '<button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Close</button>';
            showToast(message, 'error');
        }

        function loading(text) {
            bodyEl.innerHTML = '<div class="mm-loading"><i class="fas fa-spinner fa-spin"></i> ' + escapeHtml(text) + '</div>';
        }

        /* ---- View mode ---- */
        window.showViewMode = function (id) {
            titleEl.textContent = 'Loading…';
            loading('Loading member details…');
            footEl.innerHTML = '<button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Close</button>';
            openModal();

            post('get_member', { member_id: id })
                .then(function (data) {
                    if (data && data.success) renderView(data.member);
                    else setError((data && data.message) || 'Could not load member.');
                })
                .catch(function (err) { setError(err.message || 'Network error. Please try again.'); });
        };
        window.openMemberModal = window.showViewMode;

        function renderView(m) {
            titleEl.textContent = m.name || 'Member';
            var avatar = m.profile_photo
                ? '<img src="uploads/profile_photos/' + escapeHtml(m.profile_photo) + '" alt="' + escapeHtml(m.name) + '">'
                : '<span>' + escapeHtml(String(m.name || '?').charAt(0).toUpperCase()) + '</span>';

            var rows = [
                ['Date of Birth', m.dob ? formatDate(m.dob) : 'Not set'],
                ['Phone', m.contact || 'Not provided'],
                ['Occupation', m.occupation || 'Not specified'],
                ['Hometown', m.hometown || 'Not specified'],
                ['Residence', m.residence || 'Not specified'],
                ['Children', (m.children === null || m.children === undefined || m.children === '') ? '0' : m.children],
                ['Previous Church', m.previous_church || 'None'],
                ['Joined', m.created_at ? formatDate(m.created_at) : 'Not set']
            ];

            var rowsHtml = rows.map(function (r) {
                return '<div class="mm-row"><span>' + escapeHtml(r[0]) + '</span><span>' + escapeHtml(r[1]) + '</span></div>';
            }).join('');

            bodyEl.innerHTML =
                '<div class="mm-profile">' +
                    '<div class="mm-avatar">' + avatar + '</div>' +
                    '<div>' +
                        '<div class="mm-name">' + escapeHtml(m.name) + '</div>' +
                        '<div class="mm-id">ID: ' + escapeHtml(m.id) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="mm-rows">' + rowsHtml + '</div>';

            var mid = parseInt(m.id, 10);
            footEl.innerHTML =
                '<button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Close</button>' +
                '<button class="mm-btn edit" type="button" onclick="showEditMode(' + mid + ')"><i class="fas fa-edit"></i> Edit</button>' +
                '<button class="mm-btn delete" type="button" onclick="deleteMember(' + mid + ')"><i class="fas fa-trash"></i> Delete</button>' +
                '<button class="mm-btn photo" type="button" onclick="openPhotoModal(' + mid + ')"><i class="fas fa-camera"></i> Photo</button>';
        }

        /* ---- Edit mode (inline) ---- */
        window.showEditMode = function (id) {
            titleEl.textContent = 'Edit Member';
            loading('Loading member data…');
            footEl.innerHTML = '';
            openModal();

            post('get_member', { member_id: id })
                .then(function (data) {
                    if (data && data.success) renderEditForm(data.member);
                    else setError((data && data.message) || 'Could not load member.');
                })
                .catch(function (err) { setError(err.message || 'Network error. Please try again.'); });
        };
        window.openEditModal = window.showEditMode;

        function field(label, name, value, type, extra) {
            return '<div class="mm-form-group"><label>' + escapeHtml(label) + '</label>' +
                '<input type="' + (type || 'text') + '" name="' + name + '" value="' + escapeHtml(value || '') + '" ' + (extra || '') + '></div>';
        }

        function renderEditForm(m) {
            bodyEl.innerHTML =
                '<form id="mm-edit-form" onsubmit="return false;">' +
                    '<input type="hidden" name="member_id" value="' + escapeHtml(m.id) + '">' +
                    '<div class="mm-form-row">' +
                        field('Full Name *', 'name', m.name, 'text', 'required') +
                        field('Date of Birth', 'dob', m.dob, 'date') +
                    '</div>' +
                    '<div class="mm-form-row">' +
                        field('Occupation', 'occupation', m.occupation) +
                        field('Hometown', 'hometown', m.hometown) +
                    '</div>' +
                    '<div class="mm-form-row">' +
                        field('Residence *', 'residence', m.residence, 'text', 'required') +
                        field('Previous Church', 'previous_church', m.previous_church) +
                    '</div>' +
                    '<div class="mm-form-row">' +
                        field('Children', 'children', (m.children || 0), 'number', 'min="0"') +
                        field('Contact', 'contact', m.contact) +
                    '</div>' +
                    '<div class="mm-form-group"><label>Additional Notes</label>' +
                        '<textarea name="notes" rows="2">' + escapeHtml(m.notes || '') + '</textarea></div>' +
                '</form>';

            var mid = parseInt(m.id, 10);
            footEl.innerHTML =
                '<button class="mm-btn cancel" type="button" onclick="showViewMode(' + mid + ')"><i class="fas fa-times"></i> Cancel</button>' +
                '<button class="mm-btn save" type="button" onclick="saveEditMember(' + mid + ')"><i class="fas fa-save"></i> Save Changes</button>';
        }

        window.saveEditMember = function (id) {
            var form = document.getElementById('mm-edit-form');
            if (!form) return;

            var fd = new FormData(form);
            fd.set('member_id', id);

            if (!String(fd.get('name') || '').trim() || !String(fd.get('residence') || '').trim()) {
                showToast('Name and Residence are required.', 'error');
                return;
            }

            var saveBtn = footEl.querySelector('.mm-btn.save');
            var original = saveBtn ? saveBtn.innerHTML : '';
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
                saveBtn.disabled = true;
            }

            post('update_member', fd)
                .then(function (data) {
                    if (data && data.success) {
                        showToast(data.message || 'Member updated successfully!', 'success');
                        window.closeMemberModal();
                        refreshMembers();
                    } else {
                        showToast((data && data.message) || 'Update failed.', 'error');
                        if (saveBtn) { saveBtn.innerHTML = original; saveBtn.disabled = false; }
                    }
                })
                .catch(function (err) {
                    showToast(err.message || 'Network error. Please try again.', 'error');
                    if (saveBtn) { saveBtn.innerHTML = original; saveBtn.disabled = false; }
                });
        };

        /* ---- Delete ---- */
        window.deleteMember = function (id) {
            if (!window.confirm('Are you sure you want to delete this member? This cannot be undone.')) return;

            post('delete_member', { member_id: id })
                .then(function (data) {
                    if (data && data.success) {
                        showToast(data.message || 'Member deleted successfully.', 'success');
                        window.closeMemberModal();
                        var card = document.querySelector('.member-card[data-member-id="' + id + '"]');
                        if (card) card.remove();
                        refreshMembers();
                    } else {
                        showToast((data && data.message) || 'Delete failed.', 'error');
                    }
                })
                .catch(function (err) { showToast(err.message || 'Network error. Please try again.', 'error'); });
        };

        /* ---- Photo view ---- */
        window.viewFullPhoto = function (id, name, path) {
            titleEl.textContent = name || 'Member';
            bodyEl.innerHTML = path
                ? '<img class="mm-photo-full" src="' + escapeHtml(path) + '" alt="' + escapeHtml(name) + '">'
                : '<div class="mm-empty"><i class="fas fa-user-slash"></i><p>No photo uploaded.</p></div>';
            footEl.innerHTML =
                '<button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Close</button>' +
                '<button class="mm-btn photo" type="button" onclick="openPhotoModal(' + parseInt(id, 10) + ')"><i class="fas fa-camera"></i> Change Photo</button>';
            openModal();
        };

        /* ---- Photo upload ---- */
        window.openPhotoModal = function (id) {
            var input = document.getElementById('mm-photo-input');
            if (!input) { showToast('Photo picker is unavailable.', 'error'); return; }

            input.value = '';
            input.onchange = function () {
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                var ok = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

                if (ok.indexOf(file.type) === -1) {
                    showToast('Only JPG, PNG, GIF and WEBP images are allowed.', 'error');
                    this.value = '';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    showToast('Image must be less than 2MB.', 'error');
                    this.value = '';
                    return;
                }

                var fd = new FormData();
                fd.append('member_id', id);
                fd.append('profile_photo', file);

                titleEl.textContent = 'Uploading…';
                loading('Uploading photo…');
                footEl.innerHTML = '<button class="mm-btn close" type="button" onclick="closeMemberModal()"><i class="fas fa-times"></i> Cancel</button>';
                openModal();

                post('upload_photo', fd)
                    .then(function (data) {
                        if (data && data.success) {
                            showToast(data.message || 'Photo uploaded successfully!', 'success');
                            window.closeMemberModal();
                            refreshMembers();
                        } else {
                            setError((data && data.message) || 'Upload failed.');
                        }
                    })
                    .catch(function (err) { setError(err.message || 'Network error. Please try again.'); });
            };
            input.click();
        };

        /* ---- Dismiss ---- */
        modal.addEventListener('click', function (e) { if (e.target === modal) window.closeMemberModal(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) window.closeMemberModal();
        });
    }
})();
</script>