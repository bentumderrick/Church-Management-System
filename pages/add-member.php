<?php
// pages/add-member.php - Add member form with immediate JS execution
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$church_id = 0;
$church_query = "SELECT church_id FROM users WHERE id = ?";
$church_stmt = mysqli_prepare($conn, $church_query);
if ($church_stmt) {
    mysqli_stmt_bind_param($church_stmt, "i", $user_id);
    mysqli_stmt_execute($church_stmt);
    mysqli_stmt_bind_result($church_stmt, $church_id);
    mysqli_stmt_fetch($church_stmt);
    mysqli_stmt_close($church_stmt);
}
if ($church_id == 0 && isset($_SESSION['church_id'])) {
    $church_id = (int)$_SESSION['church_id'];
}
if ($church_id <= 0) {
    echo '<div class="error-message">You are not assigned to a valid church. Please contact your church administrator.</div>';
    exit();
}
$_SESSION['church_id'] = $church_id;

if (empty($_SESSION['church_name'])) {
    $name_query = "SELECT church_name FROM churches WHERE id = ?";
    $name_stmt = mysqli_prepare($conn, $name_query);
    if ($name_stmt) {
        mysqli_stmt_bind_param($name_stmt, "i", $church_id);
        mysqli_stmt_execute($name_stmt);
        mysqli_stmt_bind_result($name_stmt, $church_name);
        mysqli_stmt_fetch($name_stmt);
        mysqli_stmt_close($name_stmt);
        if (!empty($church_name)) {
            $_SESSION['church_name'] = $church_name;
        }
    }
}

$success_message = "";
$error_message = "";
$new_member_id = 0;
$member_data = [];

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Member added successfully!";
    if (isset($_GET['id'])) {
        $new_member_id = (int)$_GET['id'];
        $fetch_stmt = mysqli_prepare($conn, "SELECT * FROM members WHERE id = ? AND church_id = ? LIMIT 1");
        if ($fetch_stmt) {
            mysqli_stmt_bind_param($fetch_stmt, "ii", $new_member_id, $church_id);
            mysqli_stmt_execute($fetch_stmt);
            $result = mysqli_stmt_get_result($fetch_stmt);
            $member_data = mysqli_fetch_assoc($result) ?: [];
            mysqli_stmt_close($fetch_stmt);
        }
    }
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>
<div class="header-section">
    <h1><i class="fas fa-user-plus"></i> Add New Member</h1>
    <p class="subtitle">Register a new member for your church</p>
    <a href="#" class="back-btn" onclick="loadPage('members')">
        <i class="fas fa-arrow-left"></i> Back to Members
    </a>
</div>

<?php if (!empty($error_message)): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="form-container">
    <div class="member-form">
        <form method="POST" action="" id="memberForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="ajax" value="1">

            <!-- Profile Photo Upload Section -->
            <div class="photo-upload-container">
                <div class="photo-preview" id="photoPreview">
                    <span class="placeholder"><i class="fas fa-camera"></i></span>
                </div>
                <div class="photo-upload-controls">
                    <div class="file-input-wrapper">
                        <button type="button" class="photo-upload-btn">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Photo
                        </button>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
                    </div>
                    <button type="button" class="photo-upload-btn remove" id="removePhotoBtn" style="display:none;">
                        <i class="fas fa-times"></i> Remove
                    </button>
                    <div class="photo-help-text">
                        <i class="fas fa-info-circle"></i> JPG, PNG, GIF, WEBP • Max 2MB
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Full Name <span style="color:#e74c3c">*</span></label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                           placeholder="Enter full name" autocomplete="name">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Date of Birth <span style="color:#e74c3c">*</span></label>
                    <div class="date-input-group">
                        <select name="dob_day" id="dob_day" required>
                            <option value="">Day</option>
                            <?php for ($i = 1; $i <= 31; $i++): 
                                $val = sprintf('%02d', $i);
                                $sel = (isset($_POST['dob_day']) && $_POST['dob_day'] == $val) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>

                        <select name="dob_month" id="dob_month" required>
                            <option value="">Month</option>
                            <?php
                            $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                                       7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
                            foreach ($months as $num => $label):
                                $val = sprintf('%02d', $num);
                                $sel = (isset($_POST['dob_month']) && $_POST['dob_month'] == $val) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $val; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="dob_year" id="dob_year" required>
                            <option value="">Year</option>
                            <?php 
                            $current_year = (int)date('Y');
                            for ($year = $current_year; $year >= $current_year - 120; $year--):
                                $sel = (isset($_POST['dob_year']) && $_POST['dob_year'] == $year) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $year; ?>" <?php echo $sel; ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" id="dob" name="dob" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="occupation"><i class="fas fa-briefcase"></i> Occupation</label>
                    <input type="text" id="occupation" name="occupation"
                           value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>"
                           placeholder="Enter occupation">
                </div>

                <div class="form-group">
                    <label for="hometown"><i class="fas fa-home"></i> Hometown</label>
                    <input type="text" id="hometown" name="hometown"
                           value="<?php echo isset($_POST['hometown']) ? htmlspecialchars($_POST['hometown']) : ''; ?>"
                           placeholder="Enter hometown">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="residence"><i class="fas fa-map-marker-alt"></i> Current Residence <span style="color:#e74c3c">*</span></label>
                    <input type="text" id="residence" name="residence" required
                           value="<?php echo isset($_POST['residence']) ? htmlspecialchars($_POST['residence']) : ''; ?>"
                           placeholder="Enter current residence">
                </div>

                <div class="form-group">
                    <label for="previous_church"><i class="fas fa-church"></i> Previous Church</label>
                    <input type="text" id="previous_church" name="previous_church"
                           value="<?php echo isset($_POST['previous_church']) ? htmlspecialchars($_POST['previous_church']) : ''; ?>"
                           placeholder="Enter previous church">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="children"><i class="fas fa-child"></i> Number of Children</label>
                    <input type="number" id="children" name="children" min="0" max="50"
                           value="<?php echo isset($_POST['children']) ? htmlspecialchars($_POST['children']) : '0'; ?>"
                           placeholder="0">
                </div>

                <div class="form-group">
                    <label for="contact"><i class="fas fa-phone"></i> Contact Number</label>
                    <input type="tel" id="contact" name="contact"
                           value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>"
                           placeholder="Enter contact number">
                </div>
            </div>

            <div class="form-group full-width">
                <label for="notes"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                <textarea id="notes" name="notes" rows="3"
                          placeholder="Any additional information about the member"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-save"></i> Save Member
                </button>
                <button type="reset" class="reset-btn">
                    <i class="fas fa-redo"></i> Clear Form
                </button>
            </div>
        </form>
    </div>

    <div class="form-instructions">
        <h3><i class="fas fa-info-circle"></i> Important Notes</h3>
        <ul>
            <li>Fields marked with <span style="color:#e74c3c">*</span> are required</li>
            <li>Each member gets a unique permanent ID</li>
            <li>Profile photo is optional</li>
            <li>Photos are resized to fit the profile</li>
            <li>Deleted member IDs are never reused</li>
        </ul>

        <div class="current-stats">
            <h4><i class="fas fa-chart-bar"></i> Current Statistics</h4>
            <?php
            $total = 0;
            $last_id = 0;
            $stats_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total, MAX(id) as last_id FROM members WHERE church_id = ?");
            if ($stats_stmt) {
                mysqli_stmt_bind_param($stats_stmt, "i", $church_id);
                mysqli_stmt_execute($stats_stmt);
                $stats_result = mysqli_stmt_get_result($stats_stmt);
                if ($stats_row = mysqli_fetch_assoc($stats_result)) {
                    $total = (int)($stats_row['total'] ?? 0);
                    $last_id = (int)($stats_row['last_id'] ?? 0);
                }
                mysqli_stmt_close($stats_stmt);
            }
            echo "<p>Total Members: <strong>" . number_format($total) . "</strong></p>";
            echo "<p>Last Member ID: <strong>#" . $last_id . "</strong></p>";
            echo "<p>Next Member ID: <strong>#" . ($last_id + 1) . "</strong></p>";
            ?>
        </div>
    </div>
</div>

<!-- ===== SUCCESS POPUP ===== -->
<?php if (!empty($success_message) && $new_member_id > 0 && !empty($member_data)): ?>
<div id="successPopup" class="success-popup" style="display: flex;">
    <div class="popup-content">
        <div class="popup-header">
            <i class="fas fa-check-circle"></i>
            <h3>Success!</h3>
        </div>
        <div class="popup-body">
            <p class="popup-message">Member has been successfully registered.</p>
            <div class="member-id-display">
                <div class="id-label">MEMBER ID</div>
                <div class="id-number">#<?php echo $new_member_id; ?></div>
                <div class="id-label" style="font-size:0.7rem; margin-top:4px;">This ID is permanent and unique</div>
            </div>
            <?php if (!empty($member_data)): ?>
            <div class="member-details-summary">
                <h4><i class="fas fa-user"></i> Member Details</h4>
                <div class="summary-item">
                    <span class="summary-label">Full Name</span>
                    <span class="summary-value"><?php echo htmlspecialchars($member_data['name'] ?? ''); ?></span>
                </div>
                <?php if (!empty($member_data['dob'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Date of Birth</span>
                    <span class="summary-value"><?php echo date('d/m/Y', strtotime($member_data['dob'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($member_data['residence'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Residence</span>
                    <span class="summary-value"><?php echo htmlspecialchars($member_data['residence']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($member_data['contact'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Contact</span>
                    <span class="summary-value"><?php echo htmlspecialchars($member_data['contact']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($member_data['occupation'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Occupation</span>
                    <span class="summary-value"><?php echo htmlspecialchars($member_data['occupation']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($member_data['profile_photo'])): ?>
                <div class="summary-item">
                    <span class="summary-label">Profile Photo</span>
                    <span class="summary-value"><i class="fas fa-check-circle" style="color:#2ecc71;"></i> Uploaded</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <button class="copy-id-btn" onclick="copyMemberId(<?php echo $new_member_id; ?>, '<?php echo htmlspecialchars($member_data['name'] ?? 'Member', ENT_QUOTES); ?>')">
                <i class="far fa-copy"></i> Copy Member Details
            </button>
            <div id="copySuccess" class="copy-success">
                <i class="fas fa-check-circle"></i> Details copied to clipboard!
            </div>
            <div class="popup-buttons">
                <button onclick="loadPage('add-member')" class="popup-btn secondary">
                    <i class="fas fa-user-plus"></i> Add Another
                </button>
                <button onclick="loadPage('members')" class="popup-btn primary">
                    <i class="fas fa-users"></i> View All Members
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== SCRIPT: executes immediately after the form ===== -->
<script>
(function() {
    // Get form element
    var form = document.getElementById('memberForm');
    if (!form) return;

    // Validate function
    function validateForm() {
        var day   = document.getElementById('dob_day').value;
        var month = document.getElementById('dob_month').value;
        var year  = document.getElementById('dob_year').value;
        var name  = document.getElementById('name').value.trim();
        var residence = document.getElementById('residence').value.trim();

        if (!name) {
            alert('Please enter the member\'s full name.');
            document.getElementById('name').focus();
            return false;
        }
        if (!day || !month || !year) {
            alert('Please select a complete date of birth (Day, Month and Year).');
            return false;
        }

        var d = parseInt(day, 10);
        var m = parseInt(month, 10);
        var y = parseInt(year, 10);
        var dateObj = new Date(y, m - 1, d);
        if (dateObj.getFullYear() !== y || dateObj.getMonth() !== m - 1 || dateObj.getDate() !== d) {
            alert('Please enter a valid date of birth.');
            return false;
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (dateObj > today) {
            alert('Date of Birth cannot be in the future.');
            return false;
        }

        document.getElementById('dob').value = year + '-' + month + '-' + day;

        if (!residence) {
            alert('Please enter the member\'s current residence.');
            document.getElementById('residence').focus();
            return false;
        }

        return true;
    }

    // Handle submit event
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        var btn = document.getElementById('submitBtn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Saving...';
        btn.disabled = true;

        var formData = new FormData(form);
        formData.append('action', 'create_member');

        fetch('member-actions.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;

            if (data.success) {
                if (typeof loadPage === "function") {
                    loadPage("add-member", "success=1&id=" + data.id);
                } else {
                    window.location.href = window.location.pathname + "?success=1&id=" + data.id;
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error('Error:', error);
            alert('An error occurred while saving. Please try again.');
        });
    });

    // Photo preview
    var photoInput = document.getElementById('profile_photo');
    var photoPreview = document.getElementById('photoPreview');
    var removePhotoBtn = document.getElementById('removePhotoBtn');
    var selectedFile = null;

    if (photoInput) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image must be less than 2MB.');
                    this.value = '';
                    return;
                }
                var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
                    this.value = '';
                    return;
                }
                selectedFile = file;
                var reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Profile Photo">';
                    removePhotoBtn.style.display = 'inline-flex';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', function() {
            photoInput.value = '';
            photoPreview.innerHTML = '<span class="placeholder"><i class="fas fa-camera"></i></span>';
            this.style.display = 'none';
            selectedFile = null;
        });
    }

    // Auto-close success popup after 12 seconds
    var popup = document.getElementById('successPopup');
    if (popup) {
        setTimeout(function() {
            popup.style.display = 'none';
        }, 12000);
    }

    // Copy to clipboard function
    window.copyMemberId = function(memberId, memberName) {
        var text = 'Church Member\nID: #' + memberId + '\nName: ' + memberName + '\nAdded: ' + new Date().toLocaleDateString();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(showCopySuccess).catch(function() { fallbackCopy(text); });
        } else {
            fallbackCopy(text);
        }
    };

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (e) {
            alert('Copy failed. Please copy manually:\n\n' + text);
        }
        ta.remove();
    }

    function showCopySuccess() {
        var el = document.getElementById('copySuccess');
        if (el) {
            el.style.display = 'block';
            setTimeout(function() { el.style.display = 'none'; }, 2500);
        }
    }

    // Escape key closes popup
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var popup = document.getElementById('successPopup');
            if (popup) popup.style.display = 'none';
        }
    });

    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
})();
</script>