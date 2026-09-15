<?php
// index.php - SPA Shell with church support + public music access
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

$is_guest = !isset($_SESSION['user_id']);

// If not logged in and not requesting music, redirect to login
if ($is_guest && ($_GET['page'] ?? 'dashboard') !== 'music') {
    header('Location: login.php');
    exit();
}

if (!$is_guest) {
    require_once __DIR__ . '/init.php';
    $user = getCurrentUser();
    if (!$user) {
        header('Location: logout.php');
        exit();
    }
    $church_name = $user['church_name'] ?? 'No Church';
    $church_username = $user['church_username'] ?? '';
    $user_role = $user['role'] ?? 'member';
    $user_full_name = $user['full_name'] ?? $user['username'] ?? 'User';
    $church_id = $user['church_id'] ?? 0;
} else {
    $church_name = '';
    $church_username = '';
    $user_role = 'guest';
    $user_full_name = 'Visitor';
    $church_id = 0;
}

// Determine initial page
$allowed_pages = ['dashboard', 'add-member', 'lookup', 'attendance', 'music',
                   'member-dashboard', 'privacy-policy', 'profile', 'members', 'add-user'];
$initial_page = $_GET['page'] ?? 'dashboard';
if (!in_array($initial_page, $allowed_pages, true)) {
    $initial_page = $is_guest ? 'music' : 'dashboard';
}
if ($is_guest && $initial_page !== 'music') {
    $initial_page = 'music';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CM - Church Management</title>
    <link rel="shortcut icon" href="images/logo.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/music.css?v=<?php echo filemtime('assets/css/music.css'); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/members.css">
    <link rel="stylesheet" href="assets/css/privacy-policy.css?v=<?php echo filemtime('assets/css/privacy-policy.css'); ?>">
    <style>
        /* ===== FULL SPA STYLES ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a1520;
            color: #e0e0e0;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            height: 100dvh; /* logout visible on mobile */
            background: #0c1a2b;
            border-right: 1px solid rgba(255,255,255,0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 0 10px 0;
        }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }
        .logo-group img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
        .logo-text .name { color: orange; font-size: 22px; font-weight: 700; text-decoration: none; display: block; }
        .logo-text .full-name { color: #adb5bd; font-size: 11px; margin: 0; font-weight: 400; }

        .church-info {
            padding: 10px 16px 16px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,165,0,0.05);
        }
        .church-info .church-name { color: orange; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .church-info .church-name i { font-size: 14px; }
        .church-info .church-username { color: #6c757d; font-size: 11px; margin-top: 2px; }

        .sidebar-items { padding: 8px 0; }
        .sidebar-item { margin: 1px 0; }
        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 22px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 14px;
            cursor: pointer;
        }
        .sidebar-item a:hover { background: rgba(255,255,255,0.05); color: white; }
        .sidebar-item.active a { background: rgba(255,165,0,0.1); color: orange; border-left-color: orange; }
        .sidebar-item a i { width: 20px; text-align: center; font-size: 15px; }

        .logout-wrapper {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding: 8px 0 12px 0;
            flex-shrink: 0;
            background: #0c1a2b;
        }
        .logout-wrapper .sidebar-item a { color: #e74c3c; }
        .logout-wrapper .sidebar-item a:hover { background: rgba(231,76,60,0.1); color: #e74c3c; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            padding: 30px 40px;
            min-height: 100vh;
            min-height: 100dvh;
            background: #0a1520;
        }
        .main-content.guest {
            margin-left: 0;
            padding-top: 90px;
        }

        /* Guest header */
        .guest-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0c1a2b;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .guest-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .guest-header .logo img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .guest-header .logo span { color: orange; font-weight: 700; font-size: 20px; }
        .guest-header .actions { display: flex; gap: 10px; }
        .guest-header .actions a {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            background: orange;
            color: #0c1a2b;
        }
        .guest-header .actions a.secondary {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* ===== LOADING ===== */
        #pageLoader { display: none; text-align: center; padding: 60px 20px; }
        #pageLoader .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top-color: orange;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        #pageContent { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar { width: 70px; transform: translateX(0); transition: transform 0.3s ease; }
            .sidebar .logo-text, .sidebar .sidebar-item span,
            .sidebar .church-info .church-name span,
            .sidebar .church-info .church-username { display: none; }
            .sidebar .church-info { padding: 8px 8px; text-align: center; }
            .sidebar .church-info .church-name { justify-content: center; font-size: 12px; }
            .sidebar .logo-group { padding: 10px 8px 16px 8px; justify-content: center; }
            .sidebar .logo-group img { width: 40px; height: 40px; }
            .sidebar .sidebar-item a { padding: 12px 0; justify-content: center; }
            .sidebar .sidebar-item a i { margin: 0; font-size: 18px; }
            .logout-wrapper .sidebar-item a { padding: 12px 0; justify-content: center; }
            .main-content { margin-left: 70px; padding: 20px; }
            .main-content.guest { margin-left: 0; padding-top: 80px; }
            .mobile-menu-btn { display: flex !important; }
            .sidebar.mobile-hidden { transform: translateX(-100%); }
            .sidebar.mobile-visible { transform: translateX(0); width: 260px; }
            .sidebar.mobile-visible .logo-text,
            .sidebar.mobile-visible .sidebar-item span,
            .sidebar.mobile-visible .church-info .church-name span,
            .sidebar.mobile-visible .church-info .church-username { display: inline; }
            .sidebar.mobile-visible .sidebar-item a { padding: 10px 22px; justify-content: flex-start; }
            .sidebar.mobile-visible .sidebar-item a i { margin: 0 15px 0 0; font-size: 15px; }
            .sidebar.mobile-visible .church-info { padding: 10px 16px; text-align: left; }
            .sidebar.mobile-visible .church-info .church-name { justify-content: flex-start; }
        }
        @media (max-width: 480px) { .main-content { padding: 15px; } }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #0c1a2b;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px 12px;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }

        /* Modals and buttons */
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
        .btn-primary { background: orange; color: #0c1a2b; }
        .btn-primary:hover { background: #ff8c00; transform: translateY(-2px); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
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
            border: 1px solid rgba(255,165,0,0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .modal-header {
            background: rgba(255,165,0,0.1);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,165,0,0.3);
        }
        .modal-header h3 { color: white; margin: 0; display: flex; align-items: center; gap: 10px; }
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
        .close-modal:hover { background: rgba(255,255,255,0.1); transform: scale(1.1); }
        .modal-body { padding: 25px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px; background: rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 15px; }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: orange;
            animation: spin 1s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <?php if (!$is_guest): ?>
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" onclick="closeMobileMenu()"></div>

    <nav class="sidebar mobile-hidden" id="sidebar">
        <div class="logo-group">
            <img src="images/logo.jpg" alt="Church Logo" onerror="this.src='https://via.placeholder.com/80x80?text=LOGO'">
            <div class="logo-text">
                <a class="name" href="#" onclick="loadPage('dashboard')">CM</a>
                <p class="full-name">COC Management</p>
            </div>
        </div>

        <div class="church-info">
            <div class="church-name">
                <i class="fas fa-church"></i>
                <span><?php echo htmlspecialchars($church_name); ?></span>
            </div>
            <?php if ($church_username): ?>
                <div class="church-username"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($church_username); ?></div>
            <?php endif; ?>
        </div>

        <div class="sidebar-scroll">
            <ul>
                <div class="sidebar-items">
                    <li class="sidebar-item<?php echo $initial_page === 'dashboard' ? ' active' : ''; ?>" data-page="dashboard">
                        <a onclick="loadPage('dashboard')"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'add-member' ? ' active' : ''; ?>" data-page="add-member">
                        <a onclick="loadPage('add-member')"><i class="fas fa-user-plus"></i><span>Add Member</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'lookup' ? ' active' : ''; ?>" data-page="lookup">
                        <a onclick="loadPage('lookup')"><i class="fas fa-search"></i><span>Find Member</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'members' ? ' active' : ''; ?>" data-page="members">
                        <a onclick="loadPage('members')"><i class="fas fa-users"></i><span>Members</span></a>
                    </li>
                    <?php if (in_array($user_role, ['developer', 'admin'])): ?>
                    <li class="sidebar-item<?php echo $initial_page === 'add-user' ? ' active' : ''; ?>" data-page="add-user">
                        <a onclick="loadPage('add-user')"><i class="fas fa-user-plus"></i><span>Add User</span></a>
                    </li>
                    <?php endif; ?>
                    <li class="sidebar-item<?php echo $initial_page === 'attendance' ? ' active' : ''; ?>" data-page="attendance">
                        <a onclick="loadPage('attendance')"><i class="fas fa-calendar-check"></i><span>Attendance</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'music' ? ' active' : ''; ?>" data-page="music">
                        <a onclick="loadPage('music')"><i class="fas fa-music"></i><span>Music</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'member-dashboard' ? ' active' : ''; ?>" data-page="member-dashboard">
                        <a onclick="loadPage('member-dashboard')"><i class="fas fa-user-circle"></i><span>My Dashboard</span></a>
                    </li>
                    <li class="sidebar-item<?php echo $initial_page === 'privacy-policy' ? ' active' : ''; ?>" data-page="privacy-policy">
                        <a onclick="loadPage('privacy-policy')"><i class="fas fa-shield-alt"></i><span>Privacy Policy</span></a>
                    </li>
                    <?php if (($user_role ?? '') === 'developer'): ?>
                    <li class="sidebar-item" data-page="developer"><a onclick="loadPage('developer')"><i class="fas fa-code"></i><span>Developer</span></a></li>
                    <?php endif; ?>
                </div>
            </ul>
        </div>

        <div class="logout-wrapper">
            <ul>
                <li class="sidebar-item">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </li>
            </ul>
        </div>
    </nav>
    <?php else: ?>
    <div class="guest-header">
        <div class="logo">
            <img src="images/logo.jpg" alt="Logo">
            <span>COC Management</span>
        </div>
        <div class="actions">
            <a href="login.php">Sign In</a>
            <a href="login.php?register=1" class="secondary">Register Church</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content <?php echo $is_guest ? 'guest' : ''; ?>">
        <div id="pageLoader">
            <div class="spinner"></div>
            <p style="color:#adb5bd;margin-top:15px;">Loading...</p>
        </div>
        <div id="pageContent">
            <?php
            include __DIR__ . '/pages/' . $initial_page . '.php';
            ?>
        </div>
    </div>

    <?php if (!$is_guest): ?>
    <!-- Modals only for logged-in users -->
    <div id="photoViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-image"></i> Profile Photo</h3>
                <button class="close-modal" onclick="closePhotoViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="photo-container">
                    <img id="fullPhotoView" src="" alt="Member Photo">
                    <div class="photo-name" id="photoViewName">Member Name</div>
                    <div class="photo-id" id="photoViewId">ID: #0</div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="uploadPhoto(currentPhotoViewMemberId)" class="btn btn-primary">
                    <i class="fas fa-camera"></i> Change Photo
                </button>
                <button class="btn btn-secondary" onclick="closePhotoViewModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="photoModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Update Profile Photo</h3>
                <button class="close-modal" onclick="closePhotoUploadModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="currentPhotoContainer" style="text-align: center; margin-bottom: 20px;"></div>
                <div class="photo-upload-area" id="modalUploadArea" style="margin-bottom: 20px;">
                    <div class="upload-preview" id="modalUploadPreview">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop or click to browse</p>
                        <p class="upload-requirements">JPG, PNG or GIF • Max 2MB</p>
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
                <button class="btn btn-secondary" onclick="closePhotoUploadModal()">Cancel</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let currentPage = '<?php echo $initial_page; ?>';
        let isLoading = false;
        let currentMemberIdForPhoto = null;
        let currentPhotoFile = null;
        let currentPhotoViewMemberId = null;

        function loadPage(page, queryString = '') {
            if (isLoading && page === currentPage) return;
            isLoading = true;
            let url = 'pages/' + page + '.php';
            if (queryString) url += '?' + queryString;
            currentPage = page;

            document.getElementById('pageLoader').style.display = 'block';
            document.getElementById('pageContent').style.display = 'none';

            document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
            const activeItem = document.querySelector(`.sidebar-item[data-page="${page}"]`);
            if (activeItem) activeItem.classList.add('active');
            closeMobileMenu();

            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Page not found');
                    return response.text();
                })
                .then(html => {
                    const contentEl = document.getElementById('pageContent');
                    contentEl.innerHTML = html;
                    contentEl.style.display = 'block';
                    document.getElementById('pageLoader').style.display = 'none';
                    isLoading = false;
                    contentEl.querySelectorAll('script').forEach(oldScript => {
                        const newScript = document.createElement('script');
                        if (oldScript.src) newScript.src = oldScript.src;
                        else newScript.textContent = oldScript.textContent;
                        oldScript.replaceWith(newScript);
                    });
                    const newQuery = queryString ? '?' + queryString : '';
                    if (window.history.replaceState) {
                        window.history.replaceState(null, null, '?page=' + page + newQuery);
                    }
                })
                .catch(error => {
                    document.getElementById('pageContent').innerHTML = `
                        <div style="text-align:center;padding:60px 20px;">
                            <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#e74c3c;margin-bottom:20px;display:block;"></i>
                            <h3 style="color:white;">Page Not Found</h3>
                            <p style="color:#adb5bd;">The page could not be loaded.</p>
                            <button onclick="loadPage('dashboard')" style="margin-top:20px;padding:10px 25px;background:orange;color:#0c1a2b;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </button>
                        </div>
                    `;
                    document.getElementById('pageContent').style.display = 'block';
                    document.getElementById('pageLoader').style.display = 'none';
                    isLoading = false;
                });
        }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar.classList.contains('mobile-hidden')) {
                sidebar.classList.remove('mobile-hidden');
                sidebar.classList.add('mobile-visible');
                overlay.classList.add('active');
            } else {
                closeMobileMenu();
            }
        }

        function closeMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar) sidebar.classList.add('mobile-hidden');
            if (sidebar) sidebar.classList.remove('mobile-visible');
            if (overlay) overlay.classList.remove('active');
        }

        // Photo functions (only if elements exist)
        function viewFullPhoto(memberId, memberName, photoPath) {
            currentPhotoViewMemberId = memberId;
            const container = document.querySelector('#photoViewModal .photo-container');
            if (!container) return;
            container.innerHTML = photoPath && photoPath !== '' && photoPath !== 'uploads/profile_photos/'
                ? `<img id="fullPhotoView" src="${photoPath}" alt="${memberName}">
                   <div class="photo-name" id="photoViewName">${memberName}</div>
                   <div class="photo-id" id="photoViewId">ID: #${memberId}</div>`
                : `<div style="text-align:center;padding:50px 20px;">
                    <i class="fas fa-user-circle" style="font-size:5rem;color:#adb5bd;"></i>
                    <p style="color:#adb5bd;margin-top:20px;">No profile photo uploaded</p>
                    <button onclick="uploadPhoto(${memberId})" class="btn btn-primary" style="margin-top:15px;">
                        <i class="fas fa-camera"></i> Upload Photo
                    </button>
                   </div>`;
            document.getElementById('photoViewModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closePhotoViewModal() {
            const modal = document.getElementById('photoViewModal');
            if (modal) modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        function uploadPhoto(memberId) {
            closePhotoViewModal();
            currentMemberIdForPhoto = memberId;
            document.getElementById('photoModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('modalPhotoPreview').style.display = 'none';
            document.getElementById('modalUploadPreview').style.display = 'block';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadMessage').style.display = 'none';
            document.getElementById('modalProfilePhoto').value = '';
            currentPhotoFile = null;
        }
        function closePhotoUploadModal() {
            document.getElementById('photoModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        function handlePhotoSelection(file) {
            if (file.size > 2 * 1024 * 1024) { showUploadMessage('Image must be less than 2MB', 'error'); return; }
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) { showUploadMessage('Only JPG, PNG, GIF, and WEBP images are allowed', 'error'); return; }
            currentPhotoFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('modalPreviewImage').src = e.target.result;
                document.getElementById('modalUploadPreview').style.display = 'none';
                document.getElementById('modalPhotoPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
        function showUploadMessage(message, type) {
            const msgDiv = document.getElementById('uploadMessage');
            msgDiv.textContent = message;
            msgDiv.className = 'message ' + type;
            msgDiv.style.display = 'flex';
            setTimeout(() => msgDiv.style.display = 'none', 5000);
        }
        function removeModalPhoto() {
            currentPhotoFile = null;
            document.getElementById('modalPhotoPreview').style.display = 'none';
            document.getElementById('modalUploadPreview').style.display = 'block';
            document.getElementById('modalProfilePhoto').value = '';
        }
        function savePhoto() {
            if (!currentPhotoFile || !currentMemberIdForPhoto) { showUploadMessage('Please select a photo first', 'error'); return; }
            const formData = new FormData();
            formData.append('member_id', currentMemberIdForPhoto);
            formData.append('profile_photo', currentPhotoFile);
            formData.append('action', 'upload_photo');

            document.getElementById('uploadProgress').style.display = 'block';
            const saveBtn = document.getElementById('savePhotoBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="spinner"></span> Uploading...';
            saveBtn.disabled = true;

            fetch('member-actions.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('uploadProgress').style.display = 'none';
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    if (data.success) {
                        showUploadMessage(data.message, 'success');
                        setTimeout(() => { closePhotoUploadModal(); loadPage(currentPage); }, 1500);
                    } else {
                        showUploadMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    document.getElementById('uploadProgress').style.display = 'none';
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    showUploadMessage('Upload failed. Check console for details.', 'error');
                    console.error('Upload error:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('modalProfilePhoto');
            const uploadArea = document.getElementById('modalUploadArea');
            if (photoInput && uploadArea) {
                uploadArea.addEventListener('click', () => photoInput.click());
                photoInput.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) handlePhotoSelection(this.files[0]);
                });
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); });
                });
                uploadArea.addEventListener('drop', function(e) {
                    const files = e.dataTransfer.files;
                    if (files.length > 0) handlePhotoSelection(files[0]);
                });
            }
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (this.id === 'photoViewModal') closePhotoViewModal();
                        else if (this.id === 'photoModal') closePhotoUploadModal();
                    }
                });
            });
        });
    </script>
</body>
</html>