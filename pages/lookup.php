<?php
// pages/lookup.php - Find member by ID or Name (AJAX fragment) with fixed avatar paths
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$church_id = $_SESSION['church_id'] ?? 0;
$search_term = $_GET['search'] ?? '';
$search_type = $_GET['type'] ?? 'id'; // 'id' or 'name'
$members = [];
$error = '';

if ($search_term) {
    if ($search_type === 'id') {
        if (!is_numeric($search_term)) {
            $error = "Member ID must be a number. Please enter a valid ID.";
        } else {
            $query = "SELECT * FROM members WHERE id = ? AND church_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ii", $search_term, $church_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $members[] = $row;
            } else {
                $error = "No member found with ID #$search_term in your church.";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $search_pattern = '%' . $search_term . '%';
        $query = "SELECT * FROM members WHERE name LIKE ? AND church_id = ? ORDER BY name ASC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $search_pattern, $church_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $members = mysqli_fetch_all($result, MYSQLI_ASSOC);

        if (empty($members)) {
            $error = "No members found with name containing '$search_term' in your church.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<style>
    /* ===== (all existing CSS remains exactly as you had it) ===== */
    .search-container {
        background: rgba(255,255,255,0.05);
        padding: 40px;
        border-radius: 15px;
        margin: 30px 0;
        text-align: center;
        border: 1px solid rgba(255, 165, 0, 0.3);
    }

    .search-box {
        padding: 15px 20px;
        width: 100%;
        max-width: 400px;
        font-size: 1.3rem;
        text-align: center;
        border: 2px solid orange;
        border-radius: 10px 0 0 10px;
        background: rgba(255,255,255,0.1);
        color: white;
        font-weight: bold;
        transition: all 0.3s ease;
        margin: 0;
        display: inline-block;
    }

    .search-type-selector {
        display: inline-flex;
        background: rgba(255,255,255,0.1);
        border: 2px solid orange;
        border-left: none;
        border-radius: 0 10px 10px 0;
        overflow: hidden;
        margin: 0;
        vertical-align: top;
    }

    .search-type-btn {
        padding: 15px 20px;
        background: transparent;
        color: #adb5bd;
        border: none;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s ease;
        min-width: 100px;
        text-align: center;
    }

    .search-type-btn.active {
        background: orange;
        color: #0c1a2b;
    }

    .search-type-btn:hover:not(.active) {
        background: rgba(255, 165, 0, 0.2);
        color: white;
    }

    .search-form {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .search-btn {
        padding: 15px 40px;
        font-size: 1.2rem;
        background: linear-gradient(135deg, orange, #ff8c00);
        color: #0c1a2b;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
    }

    .search-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(255, 165, 0, 0.4);
    }

    .search-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none !important;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 2000;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-content {
        background: #0c1a2b;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        border-radius: 12px;
        border: 1px solid rgba(255, 165, 0, 0.3);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .modal-header {
        background: rgba(255, 165, 0, 0.1);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 165, 0, 0.3);
    }

    .modal-header h3 {
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .close-modal {
        background: none;
        border: none;
        color: white;
        font-size: 28px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: scale(1.1);
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }

    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: "Poppins", sans-serif;
        font-size: 1rem;
    }

    .btn-primary {
        background: orange;
        color: #0c1a2b;
    }

    .btn-primary:hover {
        background: #ff8c00;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .btn-danger {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
        border: 1px solid rgba(231, 76, 60, 0.3);
    }

    .btn-danger:hover {
        background: rgba(231, 76, 60, 0.2);
    }

    .message {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .message.success {
        background: rgba(46, 204, 113, 0.1);
        border: 1px solid rgba(46, 204, 113, 0.3);
        color: #2ecc71;
    }

    .message.error {
        background: rgba(231, 76, 60, 0.1);
        border: 1px solid rgba(231, 76, 60, 0.3);
        color: #e74c3c;
    }

    .message.info {
        background: rgba(52, 152, 219, 0.1);
        border: 1px solid rgba(52, 152, 219, 0.3);
        color: #3498db;
    }

    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: orange;
        animation: lookupSpin 1s ease-in-out infinite;
    }

    @keyframes lookupSpin {
        to { transform: rotate(360deg); }
    }

    .photo-upload-area {
        border: 2px dashed rgba(255, 165, 0, 0.5);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        background: rgba(255, 255, 255, 0.05);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .photo-upload-area:hover {
        border-color: orange;
        background: rgba(255, 255, 255, 0.08);
    }

    .upload-preview {
        color: #adb5bd;
    }

    .upload-preview i {
        font-size: 4rem;
        margin-bottom: 15px;
        color: rgba(255, 165, 0, 0.5);
    }

    .photo-preview img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid orange;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .search-results-header {
        color: orange;
        font-size: 2rem;
        text-align: center;
        margin: 30px 0;
        padding: 20px;
        background: rgba(255, 165, 0, 0.1);
        border-radius: 10px;
    }

    .search-results-count {
        font-size: 1.5rem;
        color: #4dabf7;
        margin-bottom: 10px;
    }

    .search-results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .result-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
        border: 1px solid rgba(255, 165, 0, 0.3);
        border-radius: 12px;
        padding: 25px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .result-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(255, 165, 0, 0.2);
    }

    .result-card.single-result {
        max-width: 800px;
        margin: 0 auto;
    }

    .result-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .result-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: 3px solid orange;
        flex-shrink: 0;
        cursor: pointer;
        position: relative;
    }

    .result-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .result-avatar span {
        color: white;
        font-size: 32px;
        font-weight: bold;
    }

    .result-avatar:hover {
        opacity: 0.8;
    }

    .result-info h3 {
        color: #4dabf7;
        margin: 0 0 8px 0;
        font-size: 1.4rem;
    }

    .result-info .member-id {
        color: orange;
        font-size: 1rem;
        font-weight: bold;
        background: rgba(255, 165, 0, 0.1);
        padding: 4px 12px;
        border-radius: 20px;
        display: inline-block;
    }

    .result-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .result-field {
        margin-bottom: 12px;
    }

    .result-label {
        font-size: 0.85rem;
        color: #adb5bd;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .result-value {
        font-size: 1rem;
        color: white;
        font-weight: 500;
        padding: 8px 12px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .result-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .result-btn {
        flex: 1;
        padding: 10px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
    }

    .result-btn:hover {
        background: rgba(255, 165, 0, 0.1);
        border-color: rgba(255, 165, 0, 0.3);
        transform: translateY(-2px);
    }

    .result-btn.view {
        background: rgba(52, 152, 219, 0.1);
        border-color: rgba(52, 152, 219, 0.3);
    }

    .result-btn.edit {
        background: rgba(46, 204, 113, 0.1);
        border-color: rgba(46, 204, 113, 0.3);
    }

    .result-btn.photo {
        background: rgba(155, 89, 182, 0.1);
        border-color: rgba(155, 89, 182, 0.3);
    }

    .quick-links {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .quick-link {
        padding: 10px 20px;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .quick-link:hover {
        background: rgba(255, 165, 0, 0.1);
        transform: translateY(-2px);
    }

    .no-results {
        text-align: center;
        padding: 60px 20px;
        color: #adb5bd;
    }

    .no-results i {
        font-size: 4rem;
        margin-bottom: 20px;
        color: rgba(255, 165, 0, 0.3);
    }

    .search-tips {
        background: rgba(255, 165, 0, 0.1);
        padding: 20px;
        border-radius: 10px;
        margin-top: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .search-tips h4 {
        color: orange;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .search-tips ul {
        text-align: left;
        color: #e9ecef;
        padding-left: 20px;
    }

    .search-tips li {
        margin-bottom: 8px;
    }

    .edit-form .form-group {
        margin-bottom: 20px;
    }

    .edit-form label {
        display: block;
        margin-bottom: 8px;
        color: #adb5bd;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .edit-form input,
    .edit-form select,
    .edit-form textarea {
        width: 100%;
        padding: 12px 15px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        color: white;
        font-family: "Poppins", sans-serif;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .edit-form input:focus,
    .edit-form select:focus,
    .edit-form textarea:focus {
        outline: none;
        border-color: orange;
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(255, 165, 0, 0.2);
    }

    .edit-form .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .member-view-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .view-field {
        margin-bottom: 15px;
    }

    .view-label {
        font-size: 0.9rem;
        color: #adb5bd;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .view-value {
        font-size: 1.1rem;
        color: white;
        padding: 10px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        min-height: 44px;
        display: flex;
        align-items: center;
    }

    .view-field.full-width {
        grid-column: 1 / -1;
    }

    @media (max-width: 1024px) {
        .search-results-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .search-form {
            flex-direction: column;
            align-items: center;
        }

        .search-box {
            border-radius: 10px;
            max-width: 100%;
            margin-bottom: 10px;
        }

        .search-type-selector {
            border-radius: 10px;
            border: 2px solid orange;
            margin-bottom: 20px;
            width: 100%;
            max-width: 400px;
        }

        .search-type-btn {
            flex: 1;
        }

        .search-results-grid {
            grid-template-columns: 1fr;
        }

        .result-details {
            grid-template-columns: 1fr;
        }

        .result-header {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }

        .result-info {
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .search-container {
            padding: 25px;
        }

        .result-card {
            padding: 18px;
        }
    }
</style>

<div class="header-section">
    <h1><i class="fas fa-search"></i> Find Member</h1>
    <p class="subtitle">Search members by ID or Name</p>
</div>

<div class="search-container">
    <form action="pages/lookup.php" class="search-form" id="searchForm">
        <input type="text"
               name="search"
               class="search-box"
               placeholder="<?php echo $search_type === 'id' ? 'Enter Member ID' : 'Enter Name to Search'; ?>"
               value="<?php echo htmlspecialchars($search_term); ?>"
               required
               autofocus>

        <div class="search-type-selector">
            <button type="button"
                    class="search-type-btn <?php echo $search_type === 'id' ? 'active' : ''; ?>"
                    onclick="setSearchType('id')">
                <i class="fas fa-id-card"></i> ID
            </button>
            <button type="button"
                    class="search-type-btn <?php echo $search_type === 'name' ? 'active' : ''; ?>"
                    onclick="setSearchType('name')">
                <i class="fas fa-user"></i> Name
            </button>
        </div>

        <input type="hidden" name="type" id="searchType" value="<?php echo $search_type; ?>">
    </form>

    <button type="submit" form="searchForm" class="search-btn">
        <i class="fas fa-search"></i> Search
    </button>

    <div class="quick-links">
        <a class="quick-link" onclick="loadPage('add-member')">
            <i class="fas fa-user-plus"></i> Add New Member
        </a>
        <a class="quick-link" onclick="loadPage('members')">
            <i class="fas fa-users"></i> View All Members
        </a>
        <?php if (!empty($members)): ?>
            <a class="quick-link" onclick="window.print()">
                <i class="fas fa-print"></i> Print Results
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="no-results">
        <i class="fas fa-user-slash"></i>
        <h3><?php echo $error; ?></h3>
        <p>Please try a different search term or search type.</p>

        <div class="search-tips">
            <h4><i class="fas fa-lightbulb"></i> Search Tips:</h4>
            <ul>
                <?php if ($search_type === 'id'): ?>
                    <li>Member IDs start from 1 and are numbers only</li>
                    <li>IDs are permanent and never reused</li>
                    <li>Check the members list for correct IDs</li>
                <?php else: ?>
                    <li>Search is case-insensitive</li>
                    <li>Partial names work (e.g., "John" finds "John Doe")</li>
                    <li>Try first name, last name, or full name</li>
                <?php endif; ?>
                <li>Switch search type using the ID/Name buttons above</li>
            </ul>
        </div>
    </div>

<?php elseif (!empty($members)): ?>
    <div class="search-results-header">
        <div class="search-results-count">
            <i class="fas fa-check-circle"></i>
            <?php
            $result_count = count($members);
            echo $result_count . ' ' . ($result_count === 1 ? 'Member' : 'Members') . ' Found';
            ?>
        </div>
        <?php if ($search_type === 'name'): ?>
            <p>Search results for: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"</p>
        <?php endif; ?>
    </div>

    <div class="search-results-grid <?php echo count($members) === 1 ? 'single-result' : ''; ?>">
        <?php foreach ($members as $member):
            $member_name = htmlspecialchars($member['name']);
            $member_id = $member['id'];
            $profile_photo = htmlspecialchars($member['profile_photo'] ?? '');
            $first_letter = strtoupper(substr($member['name'], 0, 1));
            $photo_path = !empty($profile_photo) ? 'uploads/profile_photos/' . $profile_photo : '';
        ?>
            <div class="result-card" data-member-id="<?php echo $member_id; ?>">
                <div class="result-header">
                    <div class="result-avatar">
                        <?php if (!empty($photo_path)): ?>
                            <img src="<?php echo $photo_path; ?>" alt="<?php echo $member_name; ?>" onerror="this.style.display='none';this.parentElement.innerHTML='<span>'+'<?php echo $first_letter; ?>'+'</span>'">
                        <?php else: ?>
                            <span><?php echo $first_letter; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="result-info">
                        <h3><?php echo $member_name; ?></h3>
                        <span class="member-id">ID: #<?php echo $member_id; ?></span>
                        <div style="margin-top: 8px; color: #adb5bd; font-size: 0.9rem;">
                            <i class="fas fa-calendar-plus"></i> Joined: <?php echo date('d/m/Y', strtotime($member['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <div class="result-details">
                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-calendar-alt"></i> Date of Birth</div>
                        <div class="result-value"><?php echo !empty($member['dob']) ? date('d/m/Y', strtotime($member['dob'])) : 'Not set'; ?></div>
                    </div>

                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-briefcase"></i> Occupation</div>
                        <div class="result-value"><?php echo htmlspecialchars($member['occupation'] ?: 'Not specified'); ?></div>
                    </div>

                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-map-marker-alt"></i> Residence</div>
                        <div class="result-value"><?php echo htmlspecialchars($member['residence']); ?></div>
                    </div>

                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-phone"></i> Contact</div>
                        <div class="result-value"><?php echo htmlspecialchars($member['contact'] ?: 'Not provided'); ?></div>
                    </div>

                    <?php if (!empty($member['hometown'])): ?>
                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-home"></i> Hometown</div>
                        <div class="result-value"><?php echo htmlspecialchars($member['hometown']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($member['previous_church'])): ?>
                    <div class="result-field">
                        <div class="result-label"><i class="fas fa-church"></i> Previous Church</div>
                        <div class="result-value"><?php echo htmlspecialchars($member['previous_church']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="result-actions">
                    <button onclick="viewMember(<?php echo $member_id; ?>)" class="result-btn view">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button onclick="editMember(<?php echo $member_id; ?>)" class="result-btn edit">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button onclick="uploadPhoto(<?php echo $member_id; ?>)" class="result-btn photo">
                        <i class="fas fa-camera"></i> Photo
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($members) > 1): ?>
        <div class="message info" style="text-align: center; margin-top: 30px;">
            <i class="fas fa-info-circle"></i>
            Showing <?php echo count($members); ?> results. Click on any member card to view full details.
        </div>
    <?php endif; ?>

<?php elseif (!empty($search_term)): ?>
    <div class="no-results">
        <i class="fas fa-search"></i>
        <h3>No Members Found</h3>
        <p>Your search didn't return any results. Please try a different search term.</p>
    </div>

<?php else: ?>
    <div class="no-results">
        <i class="fas fa-search"></i>
        <h3>Search for Members</h3>
        <p>Use the search box above to find members by ID or Name.</p>

        <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; max-width: 800px; margin-left: auto; margin-right: auto;">
            <div class="search-tips">
                <h4><i class="fas fa-id-card"></i> Search by ID</h4>
                <ul>
                    <li>Enter exact member ID number</li>
                    <li>IDs are permanent and unique</li>
                    <li>Start from 1</li>
                    <li>Returns single result</li>
                </ul>
            </div>

            <div class="search-tips">
                <h4><i class="fas fa-user"></i> Search by Name</h4>
                <ul>
                    <li>Partial names work</li>
                    <li>Case-insensitive</li>
                    <li>Returns all matching results</li>
                    <li>Try first or last name</li>
                </ul>
            </div>
        </div>

        <div class="search-tips" style="margin-top: 20px;">
            <h4><i class="fas fa-lightbulb"></i> Quick Actions</h4>
            <ul>
                <li>Switch between ID and Name search using the buttons</li>
                <li>Press Enter to search after typing</li>
                <li>View all members from the sidebar or quick links</li>
                <li>Add new members if you can't find who you're looking for</li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-eye"></i> Member Details</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body" id="viewModalContent"></div>
        <div class="modal-footer">
            <button onclick="openEditModal(currentMemberId)" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Member
            </button>
            <button class="btn btn-secondary close-modal">Close</button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Member</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editMemberForm" class="edit-form">
                <input type="hidden" id="edit_member_id" name="member_id">

                <div id="editMessage" class="message" style="display: none;"></div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_dob"><i class="fas fa-calendar-alt"></i> Date of Birth *</label>
                        <input type="date" id="edit_dob" name="dob" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_occupation"><i class="fas fa-briefcase"></i> Occupation</label>
                        <input type="text" id="edit_occupation" name="occupation">
                    </div>
                    <div class="form-group">
                        <label for="edit_hometown"><i class="fas fa-home"></i> Hometown</label>
                        <input type="text" id="edit_hometown" name="hometown">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_residence"><i class="fas fa-map-marker-alt"></i> Residence *</label>
                        <input type="text" id="edit_residence" name="residence" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_previous_church"><i class="fas fa-church"></i> Previous Church</label>
                        <input type="text" id="edit_previous_church" name="previous_church">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_children"><i class="fas fa-child"></i> Number of Children</label>
                        <input type="number" id="edit_children" name="children" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_contact"><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" id="edit_contact" name="contact">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_notes"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="updateMember()" id="updateMemberBtn" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn btn-secondary close-modal">Cancel</button>
            <button type="button" onclick="confirmDeleteMember()" class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this member? This action cannot be undone.</p>
            <p id="deleteMemberName" style="font-weight: bold; color: #e74c3c; margin-top: 10px;"></p>
        </div>
        <div class="modal-footer">
            <button onclick="deleteMember()" id="confirmDeleteBtn" class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete Member
            </button>
            <button class="btn btn-secondary close-modal">Cancel</button>
        </div>
    </div>
</div>

<!-- Photo Upload Modal -->
<div id="photoModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-camera"></i> Update Profile Photo</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div id="currentPhotoContainer" style="text-align: center; margin-bottom: 20px;"></div>

            <div class="photo-upload-area" id="modalUploadArea" style="margin-bottom: 20px;">
                <div class="upload-preview" id="modalUploadPreview">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag & drop or click to browse</p>
                    <p class="upload-requirements">JPG, PNG or GIF - Max 2MB</p>
                </div>
                <input type="file" id="modalProfilePhoto" accept="image/*" style="display: none;">
            </div>

            <div id="modalPhotoPreview" class="photo-preview" style="display: none; text-align: center;">
                <img id="modalPreviewImage" src="" alt="Preview" style="width: 150px; height: 150px;">
                <div style="margin-top: 15px;">
                    <button type="button" class="btn btn-danger" onclick="removeModalPhoto()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            </div>

            <div id="uploadProgress" style="display: none; text-align: center;">
                <div class="spinner"></div>
                <p>Uploading photo...</p>
            </div>

            <div id="uploadMessage" class="message" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            <button onclick="savePhoto()" id="savePhotoBtn" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Photo
            </button>
            <button class="btn btn-secondary close-modal">Cancel</button>
        </div>
    </div>
</div>

<script>
// Global variables
window.currentMemberId = window.currentMemberId || null;
window.currentMemberName = window.currentMemberName || null;
window.currentMemberIdForPhoto = window.currentMemberIdForPhoto || null;
window.currentPhotoFile = window.currentPhotoFile || null;

// ---- AJAX search ----
function performLookupSearch(term, type) {
    const contentEl = document.getElementById('pageContent');
    if (!contentEl || !term) return;

    contentEl.style.opacity = '0.5';

    const url = 'pages/lookup.php?search=' + encodeURIComponent(term) + '&type=' + encodeURIComponent(type);

    fetch(url)
        .then(function(response) {
            if (!response.ok) throw new Error('Lookup request failed');
            return response.text();
        })
        .then(function(html) {
            contentEl.innerHTML = html;
            contentEl.style.opacity = '1';

            // Re-run scripts
            contentEl.querySelectorAll('script').forEach(function(oldScript) {
                const newScript = document.createElement('script');
                if (oldScript.src) {
                    newScript.src = oldScript.src;
                } else {
                    newScript.textContent = oldScript.textContent;
                }
                oldScript.replaceWith(newScript);
            });

            if (window.history.replaceState) {
                window.history.replaceState(
                    null, null,
                    '?page=lookup&search=' + encodeURIComponent(term) + '&type=' + encodeURIComponent(type)
                );
            }

            document.querySelectorAll('.sidebar-item').forEach(function(el) { el.classList.remove('active'); });
            const activeItem = document.querySelector('.sidebar-item[data-page="lookup"]');
            if (activeItem) activeItem.classList.add('active');
        })
        .catch(function() {
            contentEl.style.opacity = '1';
            alert('Search failed. Please try again.');
        });
}

// Set search type
function setSearchType(type) {
    const typeInput = document.getElementById('searchType');
    if (!typeInput) return;
    typeInput.value = type;

    document.querySelectorAll('.search-type-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.search-type-btn').forEach(function(btn) {
        if (btn.textContent.includes(type === 'id' ? 'ID' : 'Name')) {
            btn.classList.add('active');
        }
    });

    const searchBox = document.querySelector('.search-box');
    if (searchBox) {
        searchBox.placeholder = type === 'id' ? 'Enter Member ID' : 'Enter Name to Search';
        if (searchBox.value && type === 'name' && !isNaN(searchBox.value)) {
            searchBox.value = '';
        } else if (searchBox.value && type === 'id' && isNaN(searchBox.value)) {
            searchBox.value = '';
        }
        searchBox.focus();
    }
}

// View / Edit / Photo functions
function viewMember(memberId) {
    fetchMemberDetails(memberId, 'view');
    document.getElementById('viewModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function editMember(memberId) {
    openEditModal(memberId);
}

function uploadPhoto(memberId) {
    window.currentMemberIdForPhoto = memberId;
    document.getElementById('photoModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    document.getElementById('modalPhotoPreview').style.display = 'none';
    document.getElementById('modalUploadPreview').style.display = 'block';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadMessage').style.display = 'none';
    document.getElementById('modalProfilePhoto').value = '';
    window.currentPhotoFile = null;
}

function fetchMemberDetails(memberId, action) {
    window.currentMemberId = memberId;

    if (action === 'view') {
        document.getElementById('viewModalContent').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="spinner"></div>
                <p style="margin-top: 15px; color: #adb5bd;">Loading member details...</p>
            </div>
        `;
    } else if (action === 'edit') {
        document.getElementById('editMessage').style.display = 'none';
        const modalBody = document.querySelector('#editModal .modal-body');
        if (!document.getElementById('editLoading')) {
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'editLoading';
            loadingDiv.style.textAlign = 'center';
            loadingDiv.style.padding = '40px';
            loadingDiv.innerHTML = `
                <div class="spinner"></div>
                <p style="margin-top: 15px; color: #adb5bd;">Loading member data...</p>
            `;
            modalBody.appendChild(loadingDiv);
        }
        document.getElementById('editMemberForm').style.display = 'none';
    }

    const formData = new FormData();
    formData.append('member_id', memberId);
    formData.append('action', 'get_member');

    fetch('member-actions.php', { method: 'POST', body: formData })
        .then(function(response) {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(function(text) {
                    throw new Error('Server returned: ' + text.substring(0, 100));
                });
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                const member = data.member;
                window.currentMemberName = member.name;

                if (action === 'view') {
                    displayMemberView(member);
                } else if (action === 'edit') {
                    displayMemberEdit(member);
                }
            } else {
                showError('Failed to load member: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Fetch Error:', error);
            showError('Error loading member. Please check console for details.');
        });
}

function displayMemberView(member) {
    const formattedDOB = member.dob ? formatDate(member.dob) : 'Not set';
    const formattedJoined = member.created_at ? formatDate(member.created_at) : 'Not set';

    const html = `
        <div class="member-view-grid">
            <div class="view-field">
                <div class="view-label"><i class="fas fa-user"></i> Full Name</div>
                <div class="view-value">${escapeHtml(member.name)}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-calendar-alt"></i> Date of Birth</div>
                <div class="view-value">${formattedDOB}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-briefcase"></i> Occupation</div>
                <div class="view-value">${escapeHtml(member.occupation || 'Not specified')}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-home"></i> Hometown</div>
                <div class="view-value">${escapeHtml(member.hometown || 'Not specified')}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-map-marker-alt"></i> Residence</div>
                <div class="view-value">${escapeHtml(member.residence)}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-church"></i> Previous Church</div>
                <div class="view-value">${escapeHtml(member.previous_church || 'Not specified')}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-child"></i> Number of Children</div>
                <div class="view-value">${member.children || '0'}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-phone"></i> Contact Number</div>
                <div class="view-value">${escapeHtml(member.contact || 'Not provided')}</div>
            </div>
            <div class="view-field full-width">
                <div class="view-label"><i class="fas fa-sticky-note"></i> Additional Notes</div>
                <div class="view-value">${escapeHtml(member.notes || 'No additional notes')}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-calendar-plus"></i> Date Joined</div>
                <div class="view-value">${formattedJoined}</div>
            </div>
            <div class="view-field">
                <div class="view-label"><i class="fas fa-id-card"></i> Member ID</div>
                <div class="view-value">${member.id}</div>
            </div>
        </div>
    `;

    document.getElementById('viewModalContent').innerHTML = html;
}

function displayMemberEdit(member) {
    const loadingDiv = document.getElementById('editLoading');
    if (loadingDiv) loadingDiv.remove();

    document.getElementById('editMemberForm').style.display = 'block';

    document.getElementById('edit_member_id').value = member.id;
    document.getElementById('edit_name').value = member.name;
    document.getElementById('edit_dob').value = member.dob;
    document.getElementById('edit_occupation').value = member.occupation || '';
    document.getElementById('edit_hometown').value = member.hometown || '';
    document.getElementById('edit_residence').value = member.residence;
    document.getElementById('edit_previous_church').value = member.previous_church || '';
    document.getElementById('edit_children').value = member.children || '0';
    document.getElementById('edit_contact').value = member.contact || '';
    document.getElementById('edit_notes').value = member.notes || '';

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('edit_dob').max = today;
}

function openEditModal(memberId) {
    window.currentMemberId = memberId;

    document.getElementById('editMessage').style.display = 'none';
    document.getElementById('editMemberForm').style.display = 'none';

    const modalBody = document.querySelector('#editModal .modal-body');
    if (!document.getElementById('editLoading')) {
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'editLoading';
        loadingDiv.style.textAlign = 'center';
        loadingDiv.style.padding = '40px';
        loadingDiv.innerHTML = `
            <div class="spinner"></div>
            <p style="margin-top: 15px; color: #adb5bd;">Loading member data...</p>
        `;
        modalBody.appendChild(loadingDiv);
    }

    document.getElementById('editModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    fetchMemberDetails(memberId, 'edit');
}

function updateMember() {
    const form = document.getElementById('editMemberForm');
    const formData = new FormData(form);
    formData.append('action', 'update_member');

    const updateBtn = document.getElementById('updateMemberBtn');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<span class="spinner"></span> Saving...';
    updateBtn.disabled = true;

    fetch('member-actions.php', { method: 'POST', body: formData })
        .then(function(response) {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(function(text) {
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                });
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showEditMessage('Member updated successfully!', 'success');
                setTimeout(function() {
                    document.getElementById('editModal').style.display = 'none';
                    document.body.style.overflow = 'auto';
                    const term = document.querySelector('.search-box') ? document.querySelector('.search-box').value : '';
                    const type = document.getElementById('searchType') ? document.getElementById('searchType').value : 'id';
                    performLookupSearch(term, type);
                }, 1500);
            } else {
                showEditMessage('Error: ' + data.message, 'error');
            }
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        })
        .catch(function(error) {
            console.error('Update Error:', error);
            showEditMessage('Server error. Check console for details.', 'error');
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        });
}

function showEditMessage(message, type) {
    const messageDiv = document.getElementById('editMessage');
    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'flex';
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function confirmDeleteMember() {
    if (!window.currentMemberId) return;

    document.getElementById('deleteMemberName').textContent = window.currentMemberName || 'this member';

    document.getElementById('editModal').style.display = 'none';
    document.getElementById('deleteModal').style.display = 'flex';
}

function deleteMember() {
    if (!window.currentMemberId) return;

    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<span class="spinner"></span> Deleting...';
    deleteBtn.disabled = true;

    const formData = new FormData();
    formData.append('member_id', window.currentMemberId);
    formData.append('action', 'delete_member');

    fetch('member-actions.php', { method: 'POST', body: formData })
        .then(function(response) {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(function(text) {
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                });
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showMessage('Member deleted successfully!', 'success');
                document.getElementById('deleteModal').style.display = 'none';
                document.body.style.overflow = 'auto';
                setTimeout(function() {
                    const term = document.querySelector('.search-box') ? document.querySelector('.search-box').value : '';
                    const type = document.getElementById('searchType') ? document.getElementById('searchType').value : 'id';
                    performLookupSearch(term, type);
                }, 1000);
            } else {
                showEditMessage('Error: ' + data.message, 'error');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showEditMessage('Network error. Please try again.', 'error');
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
}

function showMessage(message, type) {
    let messageDiv = document.getElementById('pageMessage');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'pageMessage';
        messageDiv.className = 'message ' + type;
        messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(messageDiv);
    }

    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'flex';

    setTimeout(function() { messageDiv.style.display = 'none'; }, 5000);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    alert(message);
}

function savePhoto() {
    if (!window.currentPhotoFile || !window.currentMemberIdForPhoto) {
        showUploadMessage('Please select a photo first', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('member_id', window.currentMemberIdForPhoto);
    formData.append('profile_photo', window.currentPhotoFile);
    formData.append('action', 'upload_photo');

    document.getElementById('uploadProgress').style.display = 'block';
    const saveBtn = document.getElementById('savePhotoBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner"></span> Uploading...';
    saveBtn.disabled = true;

    fetch('member-actions.php', { method: 'POST', body: formData })
        .then(function(response) {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(function(text) {
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                });
            }
            return response.json();
        })
        .then(function(data) {
            document.getElementById('uploadProgress').style.display = 'none';
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;

            if (data.success) {
                showUploadMessage(data.message, 'success');
                setTimeout(function() {
                    document.getElementById('photoModal').style.display = 'none';
                    document.body.style.overflow = 'auto';
                    const term = document.querySelector('.search-box') ? document.querySelector('.search-box').value : '';
                    const type = document.getElementById('searchType') ? document.getElementById('searchType').value : 'id';
                    performLookupSearch(term, type);
                }, 1500);
            } else {
                showUploadMessage(data.message, 'error');
            }
        })
        .catch(function(error) {
            document.getElementById('uploadProgress').style.display = 'none';
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
            console.error('Upload Error:', error);
            showUploadMessage('Upload failed. Check console for details.', 'error');
        });
}

function showUploadMessage(message, type) {
    const messageDiv = document.getElementById('uploadMessage');
    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'flex';
}

function removeModalPhoto() {
    window.currentPhotoFile = null;
    document.getElementById('modalPhotoPreview').style.display = 'none';
    document.getElementById('modalUploadPreview').style.display = 'block';
    document.getElementById('modalProfilePhoto').value = '';
}

function handlePhotoSelection(file) {
    if (file.size > 2 * 1024 * 1024) {
        showUploadMessage('Image must be less than 2MB', 'error');
        return;
    }

    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        showUploadMessage('Only JPG, PNG, and GIF images are allowed', 'error');
        return;
    }

    window.currentPhotoFile = file;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('modalPreviewImage').src = e.target.result;
        document.getElementById('modalUploadPreview').style.display = 'none';
        document.getElementById('modalPhotoPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

// ---- Init: runs immediately ----
function initLookupPage() {
    const modals = document.querySelectorAll('.modal');
    const closeButtons = document.querySelectorAll('.close-modal');

    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            modals.forEach(function(modal) { modal.style.display = 'none'; });
            document.body.style.overflow = 'auto';
        });
    });

    modals.forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    });

    const searchBox = document.querySelector('.search-box');
    if (searchBox) {
        searchBox.focus();
        searchBox.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('searchForm');
                if (form.checkValidity()) {
                    performLookupSearch(searchBox.value.trim(), document.getElementById('searchType').value);
                } else {
                    form.reportValidity();
                }
            }
        });
        searchBox.addEventListener('click', function() { this.select(); });
    }

    const form = document.getElementById('searchForm');
    const submitBtn = document.querySelector('.search-btn');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const term = searchBox ? searchBox.value.trim() : '';
            const type = document.getElementById('searchType').value;
            if (!term) return;
            performLookupSearch(term, type);
        });
    }
    if (submitBtn && form) {
        submitBtn.addEventListener('click', function() {
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        });
    }

    const modalPhotoInput = document.getElementById('modalProfilePhoto');
    const modalUploadArea = document.getElementById('modalUploadArea');

    if (modalPhotoInput && modalUploadArea) {
        modalUploadArea.addEventListener('click', function() { modalPhotoInput.click(); });

        modalPhotoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) handlePhotoSelection(this.files[0]);
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
            modalUploadArea.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        modalUploadArea.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) handlePhotoSelection(files[0]);
        });
    }
}

initLookupPage();
</script>