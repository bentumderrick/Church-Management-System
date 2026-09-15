<?php
// pages/music.php – SPA partial (guest‑friendly, developer/editor‑managed)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

// Use absolute paths for filesystem operations (prevents CWD issues)
define('UPLOAD_BASE', __DIR__ . '/../uploads/');

$is_logged_in = isset($_SESSION['user_id']);
$user_id = intval($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

// Only users with music_editor = 1 (or developer role) can manage hymns.
$is_music_manager = false;
if ($is_logged_in) {
    $stmt = mysqli_prepare($conn, "SELECT music_editor, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $music_editor_flag, $db_role);
    if (mysqli_stmt_fetch($stmt)) {
        $is_music_manager = ($music_editor_flag == 1 || $db_role === 'developer');
    }
    mysqli_stmt_close($stmt);
}

// For guests, church_id = 0 means "all hymns"
$church_id = $is_logged_in ? (int)($_SESSION['church_id'] ?? 0) : 0;

$upload_error = $upload_success = $edit_error = $edit_success = '';

// ---- Default cover data URI ----
$default_cover_uri = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300' viewBox='0 0 300 300'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23222'/%3E%3Cstop offset='100%25' style='stop-color:%23444'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='300' height='300' fill='url(%23g)'/%3E%3Ctext x='150' y='160' font-size='80' text-anchor='middle' fill='%23ffc107'%3E🎵%3C/text%3E%3C/svg%3E";

// ---------- HANDLE UPLOAD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_hymn']) && $is_music_manager) {
    $hymn_number = trim($_POST['hymn_number'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lyrics = trim($_POST['lyrics'] ?? '');
    $audio_file = '';
    $cover_image = 'default-cover.png';

    if (empty($title) || empty($hymn_number)) {
        $upload_error = 'Please provide both a title and hymn number.';
    } else {
        // Audio file
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $audio = $_FILES['audio_file'];
            $allowed_types = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a'];
            $allowed_ext = ['mp3', 'wav', 'ogg', 'm4a'];
            $ext = strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION));
            if ($audio['size'] > 15 * 1024 * 1024) {
                $upload_error = 'Audio file must be less than 15MB.';
            } elseif (!in_array($audio['type'], $allowed_types) || !in_array($ext, $allowed_ext)) {
                $upload_error = 'Only MP3, WAV, OGG, and M4A files are allowed.';
            } else {
                $upload_dir = UPLOAD_BASE . 'hymns/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'hymn_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($audio['tmp_name'], $upload_dir . $filename)) {
                    $audio_file = $filename;
                } else {
                    $upload_error = 'Failed to upload audio file.';
                }
            }
        } else {
            $upload_error = 'Please select an audio file.';
        }

        // Cover image (optional)
        if (empty($upload_error) && isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $cover = $_FILES['cover_image'];
            $cover_ext = strtolower(pathinfo($cover['name'], PATHINFO_EXTENSION));
            $allowed_cover = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($cover_ext, $allowed_cover) && $cover['size'] < 2 * 1024 * 1024) {
                $cover_dir = UPLOAD_BASE . 'covers/';
                if (!is_dir($cover_dir)) mkdir($cover_dir, 0755, true);
                $cover_filename = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $cover_ext;
                if (move_uploaded_file($cover['tmp_name'], $cover_dir . $cover_filename)) {
                    $cover_image = $cover_filename;
                }
            }
        }

        if (empty($upload_error) && $audio_file) {
            $uploaded_by = $_SESSION['username'] ?? 'Developer';
            $query = "INSERT INTO hymns (church_id, hymn_number, title, description, lyrics, audio_file, cover_image, uploaded_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            $manager_church_id = $is_logged_in ? $church_id : 0;
            mysqli_stmt_bind_param($stmt, "isssssss", $manager_church_id, $hymn_number, $title, $description, $lyrics, $audio_file, $cover_image, $uploaded_by);
            if (mysqli_stmt_execute($stmt)) {
                $upload_success = 'Hymn uploaded successfully!';
            } else {
                $upload_error = 'Error saving to database: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ---------- HANDLE EDIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_hymn']) && $is_music_manager) {
    $hymn_id = intval($_POST['hymn_id'] ?? 0);
    $hymn_number = trim($_POST['edit_hymn_number'] ?? '');
    $title = trim($_POST['edit_title'] ?? '');
    $description = trim($_POST['edit_description'] ?? '');
    $lyrics = trim($_POST['edit_lyrics'] ?? '');

    if ($hymn_id > 0 && !empty($title) && !empty($hymn_number)) {
        $query = "UPDATE hymns SET hymn_number = ?, title = ?, description = ?, lyrics = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssi", $hymn_number, $title, $description, $lyrics, $hymn_id);
        if (mysqli_stmt_execute($stmt)) {
            $edit_success = 'Hymn updated successfully!';
        } else {
            $edit_error = 'Error updating hymn: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $edit_error = 'Please fill in all required fields.';
    }
}

// ---------- HANDLE DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hymn']) && $is_music_manager) {
    $hymn_id = intval($_POST['hymn_id'] ?? 0);
    if ($hymn_id > 0) {
        $query = "SELECT audio_file, cover_image FROM hymns WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $hymn_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hymn = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($hymn) {
            if ($hymn['audio_file'] && file_exists(UPLOAD_BASE . 'hymns/' . $hymn['audio_file'])) {
                unlink(UPLOAD_BASE . 'hymns/' . $hymn['audio_file']);
            }
            if ($hymn['cover_image'] && $hymn['cover_image'] !== 'default-cover.png' && file_exists(UPLOAD_BASE . 'covers/' . $hymn['cover_image'])) {
                unlink(UPLOAD_BASE . 'covers/' . $hymn['cover_image']);
            }
        }

        $query = "DELETE FROM hymns WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $hymn_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $edit_success = 'Hymn deleted successfully!';
    }
}

// ---------- FETCH HYMNS (no JOIN, prevents duplicates) ----------
if ($is_logged_in && $church_id > 0) {
    $hymns_query = "SELECT h.* 
                    FROM hymns h 
                    WHERE h.church_id = ? 
                    ORDER BY h.created_at DESC";
    $hymns_stmt = mysqli_prepare($conn, $hymns_query);
    mysqli_stmt_bind_param($hymns_stmt, "i", $church_id);
} else {
    $hymns_query = "SELECT h.* 
                    FROM hymns h 
                    ORDER BY h.created_at DESC";
    $hymns_stmt = mysqli_prepare($conn, $hymns_query);
}
mysqli_stmt_execute($hymns_stmt);
$hymns_result = mysqli_stmt_get_result($hymns_stmt);

$total_hymns = mysqli_num_rows($hymns_result);
$total_plays = 0;
$total_downloads = 0;
$hymns_data = [];
while ($row = mysqli_fetch_assoc($hymns_result)) {
    $hymns_data[] = $row;
    $total_plays += $row['play_count'];
    $total_downloads += $row['download_count'];
}
mysqli_data_seek($hymns_result, 0);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!-- ===== PAGE CONTENT ===== -->
<style id="musicFullscreenStyles">
    .fullscreen-player {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.95); z-index: 10000; color: white; flex-direction: column;
        justify-content: center; align-items: center; text-align: center;
    }
    .fullscreen-player.active { display: flex; }
    .fullscreen-player .close-fs { position: absolute; top: 20px; right: 30px; font-size: 2rem; cursor: pointer; color: white; }
    .fullscreen-player .cover-large { width: 300px; height: 300px; border-radius: 12px; object-fit: cover; margin-bottom: 20px; }
    .fullscreen-player .song-title { font-size: 2rem; margin: 10px 0; }
    .fullscreen-player .artist { color: #adb5bd; margin-bottom: 20px; }
    .fullscreen-player .progress-large { width: 80%; max-width: 600px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 3px; margin: 20px auto; cursor: pointer; }
    .fullscreen-player .progress-fill-large { height: 100%; background: #ffc107; border-radius: 3px; width: 0%; }
    .fullscreen-player .time-large { display: flex; justify-content: space-between; width: 80%; max-width: 600px; color: #adb5bd; }
    .fullscreen-player .controls-large { display: flex; gap: 20px; font-size: 2rem; margin: 20px 0; }
    .fullscreen-player .controls-large i { cursor: pointer; }
    .fullscreen-player .lyrics-panel { max-width: 600px; max-height: 200px; overflow-y: auto; color: #adb5bd; white-space: pre-line; margin-top: 20px; }
</style>

<div class="header-section">
    <h1><i class="fas fa-music"></i> Church Music</h1>
    <p class="subtitle">Listen to hymns, view lyrics, and download songs</p>
    <?php if ($is_logged_in): ?>
    <a href="#" class="back-btn" onclick="loadPage('dashboard')">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <?php else: ?>
    <a href="login.php" class="back-btn">
        <i class="fas fa-sign-in-alt"></i> Sign In / Register
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="music-stats">
    <div class="stat-box"><div class="number"><?php echo $total_hymns; ?></div><div class="label">Total Hymns</div></div>
    <div class="stat-box"><div class="number"><?php echo number_format($total_plays); ?></div><div class="label">Total Plays</div></div>
    <div class="stat-box"><div class="number"><?php echo number_format($total_downloads); ?></div><div class="label">Total Downloads</div></div>
    <div class="stat-box"><div class="number"><?php echo $total_hymns > 0 ? round($total_plays / $total_hymns) : 0; ?></div><div class="label">Avg Plays/Hymn</div></div>
</div>

<!-- Messages -->
<?php if ($upload_error): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i><div><p><?php echo htmlspecialchars($upload_error); ?></p></div></div><?php endif; ?>
<?php if ($upload_success): ?><div class="success-message"><i class="fas fa-check-circle"></i><div><p><?php echo htmlspecialchars($upload_success); ?></p></div></div><?php endif; ?>
<?php if ($edit_error): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i><div><p><?php echo htmlspecialchars($edit_error); ?></p></div></div><?php endif; ?>
<?php if ($edit_success): ?><div class="success-message"><i class="fas fa-check-circle"></i><div><p><?php echo htmlspecialchars($edit_success); ?></p></div></div><?php endif; ?>

<!-- Upload Form (music manager only) -->
<?php if ($is_music_manager): ?>
<div class="upload-section" id="uploadSection">
    <h2><i class="fas fa-upload"></i> Upload New Hymn</h2>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="hymn_number">Hymn Number <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="hymn_number" name="hymn_number" required placeholder="e.g., 123">
            </div>
            <div class="form-group">
                <label for="title">Title <span style="color:#e74c3c;">*</span></label>
                <input type="text" id="title" name="title" required placeholder="Enter hymn title">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" placeholder="Brief description">
        </div>
        <div class="form-group">
            <label for="lyrics">Lyrics</label>
            <textarea id="lyrics" name="lyrics" rows="4" placeholder="Enter hymn lyrics..."></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="audio_file">Audio File <span style="color:#e74c3c;">*</span> (Max 15MB)</label>
                <input type="file" id="audio_file" name="audio_file" accept="audio/*" required>
            </div>
            <div class="form-group">
                <label for="cover_image">Cover Image (Optional, Max 2MB)</label>
                <input type="file" id="cover_image" name="cover_image" accept="image/*">
            </div>
        </div>
        <button type="submit" name="upload_hymn" class="submit-btn"><i class="fas fa-upload"></i> Upload Hymn</button>
    </form>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="music-toolbar">
    <div class="search-box">
        <i class="fas fa-search" style="color:#adb5bd;"></i>
        <input type="text" id="searchInput" placeholder="Search by title or number..." onkeyup="filterHymns()">
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <span style="color:#adb5bd;font-size:0.8rem;">Sort:</span>
        <select id="sortSelect" onchange="filterHymns()" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:8px 12px;color:white;">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="title">Title</option>
            <option value="number">Number</option>
            <option value="popular">Most Played</option>
        </select>
        <div class="view-toggle">
            <button class="active" onclick="setView('grid')"><i class="fas fa-th"></i></button>
            <button onclick="setView('list')"><i class="fas fa-list"></i></button>
        </div>
    </div>
</div>

<!-- Hymns Display -->
<div id="hymnsContainer">
    <?php if ($total_hymns > 0): ?>
        <div class="hymns-grid" id="hymnsGrid">
            <?php while ($hymn = mysqli_fetch_assoc($hymns_result)):
                $uploaded_cover_path = '/uploads/covers/' . $hymn['cover_image'];
                $cover_file_exists = (!empty($hymn['cover_image']) && $hymn['cover_image'] !== 'default-cover.png' && file_exists(UPLOAD_BASE . 'covers/' . $hymn['cover_image']));
                $cover_path = $cover_file_exists ? $uploaded_cover_path : $default_cover_uri;
                $audio_path = '/uploads/hymns/'.$hymn['audio_file'];
            ?>
                <?php if ($is_music_manager): ?>
                <form method="POST" style="display:none;" id="editForm_<?php echo $hymn['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="edit_hymn" value="1">
                    <input type="hidden" name="hymn_id" value="<?php echo $hymn['id']; ?>">
                    <input type="hidden" name="edit_hymn_number" value="<?php echo htmlspecialchars($hymn['hymn_number']); ?>">
                    <input type="hidden" name="edit_title" value="<?php echo htmlspecialchars($hymn['title']); ?>">
                    <input type="hidden" name="edit_description" value="<?php echo htmlspecialchars($hymn['description']); ?>">
                    <input type="hidden" name="edit_lyrics" value="<?php echo htmlspecialchars($hymn['lyrics']); ?>">
                </form>
                <?php endif; ?>

                <div class="hymn-card" data-id="<?php echo $hymn['id']; ?>" data-title="<?php echo htmlspecialchars($hymn['title']); ?>" data-number="<?php echo htmlspecialchars($hymn['hymn_number']); ?>" data-audio="<?php echo $audio_path; ?>" data-lyrics="<?php echo htmlspecialchars($hymn['lyrics']); ?>" data-cover="<?php echo htmlspecialchars($cover_path); ?>" data-uploaded="<?php echo htmlspecialchars($hymn['uploaded_by']); ?>" data-plays="<?php echo $hymn['play_count']; ?>" data-downloads="<?php echo $hymn['download_count']; ?>">
                    <div class="cover" onclick="playHymn(this.parentElement)">
                        <img src="<?php echo htmlspecialchars($cover_path); ?>" alt="<?php echo htmlspecialchars($hymn['title']); ?>">
                        <div class="play-overlay"><i class="fas fa-play-circle"></i></div>
                    </div>
                    <div class="info">
                        <span class="number">#<?php echo htmlspecialchars($hymn['hymn_number']); ?></span>
                        <h3><?php echo htmlspecialchars($hymn['title']); ?></h3>
                        <div class="meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($hymn['uploaded_by']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($hymn['created_at'])); ?></span>
                            <span><i class="fas fa-play"></i> <?php echo number_format($hymn['play_count']); ?></span>
                        </div>
                    </div>
                    <?php if ($is_music_manager): ?>
                    <div class="admin-actions" style="display:flex; gap:5px; justify-content:flex-end; padding:5px 10px 10px;">
                        <button onclick="editHymn(<?php echo $hymn['id']; ?>)" title="Edit" style="background:none;border:none;color:#ffc107;cursor:pointer;font-size:14px;"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this hymn permanently?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="delete_hymn" value="1">
                            <input type="hidden" name="hymn_id" value="<?php echo $hymn['id']; ?>">
                            <button type="submit" title="Delete" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px;"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="hymns-list" id="hymnsList" style="display:none;">
            <?php mysqli_data_seek($hymns_result, 0);
            while ($hymn = mysqli_fetch_assoc($hymns_result)):
                $audio_path = '/uploads/hymns/'.$hymn['audio_file'];
                $cover_file_exists = (!empty($hymn['cover_image']) && $hymn['cover_image'] !== 'default-cover.png' && file_exists(UPLOAD_BASE . 'covers/' . $hymn['cover_image']));
                $cover_path = $cover_file_exists ? '/uploads/covers/'.$hymn['cover_image'] : $default_cover_uri;
            ?>
                <?php if ($is_music_manager): ?>
                <form method="POST" style="display:none;" id="editForm_<?php echo $hymn['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="edit_hymn" value="1">
                    <input type="hidden" name="hymn_id" value="<?php echo $hymn['id']; ?>">
                    <input type="hidden" name="edit_hymn_number" value="<?php echo htmlspecialchars($hymn['hymn_number']); ?>">
                    <input type="hidden" name="edit_title" value="<?php echo htmlspecialchars($hymn['title']); ?>">
                    <input type="hidden" name="edit_description" value="<?php echo htmlspecialchars($hymn['description']); ?>">
                    <input type="hidden" name="edit_lyrics" value="<?php echo htmlspecialchars($hymn['lyrics']); ?>">
                </form>
                <?php endif; ?>
                <div class="hymn-item" data-id="<?php echo $hymn['id']; ?>" data-title="<?php echo htmlspecialchars($hymn['title']); ?>" data-number="<?php echo htmlspecialchars($hymn['hymn_number']); ?>" data-audio="<?php echo $audio_path; ?>" data-lyrics="<?php echo htmlspecialchars($hymn['lyrics']); ?>" data-cover="<?php echo htmlspecialchars($cover_path); ?>" data-uploaded="<?php echo htmlspecialchars($hymn['uploaded_by']); ?>" data-plays="<?php echo $hymn['play_count']; ?>" data-downloads="<?php echo $hymn['download_count']; ?>">
                    <div class="play-btn" onclick="playHymn(this.parentElement)"><i class="fas fa-play"></i></div>
                    <div class="info">
                        <span class="number">#<?php echo htmlspecialchars($hymn['hymn_number']); ?></span>
                        <h4><?php echo htmlspecialchars($hymn['title']); ?></h4>
                        <div class="meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($hymn['uploaded_by']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($hymn['created_at'])); ?></span>
                            <span><i class="fas fa-play"></i> <?php echo number_format($hymn['play_count']); ?></span>
                            <span><i class="fas fa-download"></i> <?php echo number_format($hymn['download_count']); ?></span>
                        </div>
                    </div>
                    <div class="actions">
                        <?php if ($is_music_manager): ?>
                            <button onclick="editHymn(<?php echo $hymn['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this hymn permanently?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="delete_hymn" value="1">
                                <input type="hidden" name="hymn_id" value="<?php echo $hymn['id']; ?>">
                                <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:60px 20px;color:#adb5bd;">
            <i class="fas fa-music" style="font-size:4rem;color:rgba(255,255,255,0.1);margin-bottom:20px;display:block;"></i>
            <h3 style="color:white;margin-bottom:10px;">No Hymns Yet</h3>
            <p>Hymns will appear here once they are uploaded.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Mini Player Bar -->
<div class="music-player" id="musicPlayer">
    <div class="player-inner">
        <div class="cover-small">
            <img id="playerCover" src="<?php echo $default_cover_uri; ?>" alt="Cover">
        </div>
        <div class="song-info">
            <div class="title" id="playerTitle">Select a hymn</div>
            <div class="artist" id="playerArtist">Church Music</div>
        </div>
        <div class="controls">
            <button onclick="previousHymn()"><i class="fas fa-step-backward"></i></button>
            <button class="play-btn" id="playPauseBtn" onclick="togglePlayPause()"><i class="fas fa-play"></i></button>
            <button onclick="nextHymn()"><i class="fas fa-step-forward"></i></button>
            <button onclick="toggleLyrics()"><i class="fas fa-align-left"></i></button>
        </div>
        <div class="progress-container">
            <span class="time" id="currentTime">0:00</span>
            <div class="progress-bar" id="progressBar" onclick="seekAudio(event)"><div class="progress-fill" id="progressFill"></div></div>
            <span class="time" id="totalTime">0:00</span>
        </div>
        <div class="right-controls">
            <button onclick="togglePlaylist()"><i class="fas fa-list-ul"></i></button>
            <button id="expandPlayerBtn" onclick="openFullscreenPlayer()" title="Full screen"><i class="fas fa-expand"></i></button>
            <button class="download-btn" id="downloadBtn" onclick="downloadCurrentHymn()"><i class="fas fa-download"></i></button>
            <button onclick="closePlayer()"><i class="fas fa-times"></i></button>
        </div>
    </div>
</div>

<!-- Full‑Screen Player Overlay -->
<div class="fullscreen-player" id="fullscreenPlayer">
    <span class="close-fs" onclick="closeFullscreenPlayer()">&times;</span>
    <img id="fsCover" class="cover-large" src="<?php echo $default_cover_uri; ?>" alt="Cover">
    <h2 id="fsTitle" class="song-title">Title</h2>
    <p id="fsArtist" class="artist">Artist</p>
    <div class="progress-large" id="fsProgressBar" onclick="seekAudioFS(event)">
        <div class="progress-fill-large" id="fsProgressFill"></div>
    </div>
    <div class="time-large">
        <span id="fsCurrentTime">0:00</span>
        <span id="fsTotalTime">0:00</span>
    </div>
    <div class="controls-large">
        <i class="fas fa-step-backward" onclick="previousHymn()"></i>
        <i id="fsPlayPauseBtn" class="fas fa-pause" onclick="togglePlayPause()"></i>
        <i class="fas fa-step-forward" onclick="nextHymn()"></i>
        <i class="fas fa-download" onclick="downloadCurrentHymn()"></i>
    </div>
    <div class="lyrics-panel" id="fsLyrics"></div>
</div>

<!-- Lyrics Modal -->
<div class="lyrics-modal" id="lyricsModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeLyrics()">&times;</button>
        <h2 id="lyricsTitle">Lyrics</h2>
        <div class="lyrics-text" id="lyricsText">No lyrics available.</div>
    </div>
</div>

<!-- ===== ALL MUSIC JAVASCRIPT ===== -->
<script>
const DEFAULT_COVER = "<?php echo $default_cover_uri; ?>";
let currentAudio = null;
let currentHymnId = null;
let currentHymnData = {};
let playlist = [];
let currentIndex = -1;
let isPlaying = false;
let viewMode = 'grid';

window.addEventListener('beforeunload', function() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }
});

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function playHymn(element) {
    const data = element.dataset;
    if (!data.audio || data.audio === '') {
        alert('Audio file not found.');
        return;
    }
    currentHymnData = {
        id: data.id,
        title: data.title,
        number: data.number,
        audio: data.audio,
        lyrics: data.lyrics || 'No lyrics available.',
        cover: data.cover || DEFAULT_COVER,
        uploaded: data.uploaded || 'Unknown',
        plays: parseInt(data.plays) || 0,
        downloads: parseInt(data.downloads) || 0
    };
    currentHymnId = data.id;
    buildPlaylist();
    currentIndex = playlist.findIndex(item => item.id == data.id);

    document.getElementById('playerTitle').textContent = data.title + ' (#' + data.number + ')';
    document.getElementById('playerCover').src = data.cover || DEFAULT_COVER;
    document.getElementById('playerArtist').textContent = 'Uploaded by: ' + (data.uploaded || 'Unknown');
    showPlayerBar();

    document.getElementById('fsTitle').textContent = data.title;
    document.getElementById('fsArtist').textContent = '#' + data.number + ' – ' + (data.uploaded || 'Unknown');
    document.getElementById('fsCover').src = data.cover || DEFAULT_COVER;
    document.getElementById('fsLyrics').innerHTML = escapeHtml(data.lyrics).replace(/\n/g, '<br>');

    if (currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }

    currentAudio = new Audio(data.audio);
    currentAudio.addEventListener('timeupdate', updateProgress);
    currentAudio.addEventListener('loadedmetadata', function() {
        document.getElementById('totalTime').textContent = formatTime(this.duration);
        document.getElementById('fsTotalTime').textContent = formatTime(this.duration);
    });
    currentAudio.addEventListener('ended', function() {
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
        nextHymn();
    });

    currentAudio.play().then(() => {
        isPlaying = true;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-pause';
        incrementPlayCount(currentHymnId);
    }).catch(() => {
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
    });
}

function togglePlayPause() {
    if (!currentAudio) return;
    if (isPlaying) {
        currentAudio.pause();
        isPlaying = false;
        document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('fsPlayPauseBtn').className = 'fas fa-play';
    } else {
        currentAudio.play().then(() => {
            isPlaying = true;
            document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
            document.getElementById('fsPlayPauseBtn').className = 'fas fa-pause';
        }).catch(() => {});
    }
}

function updateProgress() {
    if (!currentAudio) return;
    const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
    document.getElementById('progressFill').style.width = progress + '%';
    document.getElementById('currentTime').textContent = formatTime(currentAudio.currentTime);
    const fsFill = document.getElementById('fsProgressFill');
    if (fsFill) fsFill.style.width = progress + '%';
    const fsCur = document.getElementById('fsCurrentTime');
    if (fsCur) fsCur.textContent = formatTime(currentAudio.currentTime);
}

function seekAudio(event) {
    if (!currentAudio) return;
    const bar = event.currentTarget;
    const rect = bar.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    currentAudio.currentTime = x * currentAudio.duration;
}

function seekAudioFS(event) {
    if (!currentAudio) return;
    const bar = document.getElementById('fsProgressBar');
    const rect = bar.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    currentAudio.currentTime = x * currentAudio.duration;
}

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return mins + ':' + (secs < 10 ? '0' : '') + secs;
}

function buildPlaylist() {
    const items = document.querySelectorAll('.hymn-card, .hymn-item');
    playlist = [];
    items.forEach(item => {
        const data = item.dataset;
        if (data.audio && data.audio !== '') {
            playlist.push({
                id: data.id,
                title: data.title,
                number: data.number,
                audio: data.audio,
                lyrics: data.lyrics || 'No lyrics available.',
                cover: data.cover || DEFAULT_COVER,
                uploaded: data.uploaded || 'Unknown',
                element: item
            });
        }
    });
}

function nextHymn() {
    if (playlist.length === 0) return;
    currentIndex = (currentIndex + 1) % playlist.length;
    const next = playlist[currentIndex];
    if (next && next.element) playHymn(next.element);
}

function previousHymn() {
    if (playlist.length === 0) return;
    currentIndex = (currentIndex - 1 + playlist.length) % playlist.length;
    const prev = playlist[currentIndex];
    if (prev && prev.element) playHymn(prev.element);
}

function togglePlaylist() {
    if (playlist.length === 0) {
        alert('No hymns in playlist.');
        return;
    }
    let message = '📋 Playlist (' + playlist.length + ' hymns):\n\n';
    playlist.forEach((item, index) => {
        message += (index === currentIndex ? '▶ ' : '  ') + '#' + item.number + ' ' + item.title + '\n';
    });
    alert(message);
}

function closePlayer() {
    if (currentAudio) currentAudio.pause();
    isPlaying = false;
    document.getElementById('musicPlayer').classList.remove('active');
    closeFullscreenPlayer();
}

function openFullscreenPlayer() {
    const fs = document.getElementById('fullscreenPlayer');
    if (!fs) return;
    fs.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (currentAudio) {
        document.getElementById('fsPlayPauseBtn').className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
        updateProgress();
    }
}

function closeFullscreenPlayer() {
    const fs = document.getElementById('fullscreenPlayer');
    if (fs) {
        fs.classList.remove('active');
    }
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeFullscreenPlayer();
});

window.openFullscreenPlayer = openFullscreenPlayer;
window.closeFullscreenPlayer = closeFullscreenPlayer;

function toggleLyrics() {
    if (!currentHymnData.lyrics || currentHymnData.lyrics === 'No lyrics available.') {
        alert('No lyrics available for this hymn.');
        return;
    }
    document.getElementById('lyricsTitle').textContent = currentHymnData.title + ' (#' + currentHymnData.number + ')';
    document.getElementById('lyricsText').innerHTML = escapeHtml(currentHymnData.lyrics).replace(/\n/g, '<br>');
    document.getElementById('lyricsModal').classList.add('active');
}

function closeLyrics() {
    document.getElementById('lyricsModal').classList.remove('active');
}

function downloadCurrentHymn() {
    if (!currentHymnData.audio) {
        alert('No audio file to download.');
        return;
    }
    const link = document.createElement('a');
    link.href = currentHymnData.audio;
    link.download = currentHymnData.title + ' - Hymn ' + currentHymnData.number + '.mp3';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    incrementDownloadCount(currentHymnId);
    const btn = document.getElementById('downloadBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check" style="color:#2ecc71;"></i>';
    setTimeout(() => btn.innerHTML = orig, 2000);
}

function incrementPlayCount(hymnId) {
    if (!hymnId) return;
    fetch('music-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=play&id=' + hymnId
    }).catch(() => {});
}

function incrementDownloadCount(hymnId) {
    if (!hymnId) return;
    fetch('music-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=download&id=' + hymnId
    }).catch(() => {});
}

function setView(view) {
    viewMode = view;
    document.getElementById('hymnsGrid').style.display = view === 'grid' ? 'grid' : 'none';
    document.getElementById('hymnsList').style.display = view === 'list' ? 'block' : 'none';
    document.querySelectorAll('.view-toggle button').forEach((btn, i) => {
        btn.classList.toggle('active', i === (view === 'grid' ? 0 : 1));
    });
}

function filterHymns() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const sort = document.getElementById('sortSelect').value;
    const cards = document.querySelectorAll('.hymn-card, .hymn-item');
    let visible = [];
    cards.forEach(item => {
        const title = (item.dataset.title || '').toLowerCase();
        const number = (item.dataset.number || '').toLowerCase();
        const match = title.includes(query) || number.includes(query);
        item.style.display = match ? '' : 'none';
        if (match) visible.push(item);
    });
    if (visible.length > 1 && sort !== 'newest') {
        const parent = viewMode === 'grid' ? document.getElementById('hymnsGrid') : document.getElementById('hymnsList');
        visible.sort((a, b) => {
            switch(sort) {
                case 'title': return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                case 'number': return parseInt(a.dataset.number) - parseInt(b.dataset.number);
                case 'popular': return (parseInt(b.dataset.plays) || 0) - (parseInt(a.dataset.plays) || 0);
                case 'oldest': return parseInt(a.dataset.id) - parseInt(b.dataset.id);
                default: return parseInt(b.dataset.id) - parseInt(a.dataset.id);
            }
        });
        visible.forEach(item => parent.appendChild(item));
    }
}

function editHymn(id) {
    const form = document.getElementById('editForm_' + id);
    if (!form) return;
    const title = prompt('Edit Title:', form.edit_title.value);
    if (title === null) return;
    const number = prompt('Edit Hymn Number:', form.edit_hymn_number.value);
    if (number === null) return;
    const lyrics = prompt('Edit Lyrics:', form.edit_lyrics.value || '');
    if (lyrics === null) return;
    form.edit_title.value = title;
    form.edit_hymn_number.value = number;
    form.edit_lyrics.value = lyrics;
    form.submit();
}

document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    switch(e.key) {
        case ' ': e.preventDefault(); togglePlayPause(); break;
        case 'ArrowRight': e.preventDefault(); nextHymn(); break;
        case 'ArrowLeft': e.preventDefault(); previousHymn(); break;
        case 'l': case 'L': toggleLyrics(); break;
        case 'Escape': closePlayer(); closeLyrics(); break;
    }
});

function showPlayerBar() {
    const all = Array.from(document.querySelectorAll('#musicPlayer'));
    if (!all.length) return null;
    const p = all[all.length - 1];
    all.slice(0, -1).forEach(el => el.remove());
    p.classList.add('active');
    if (getComputedStyle(p).display === 'none') {
        p.style.cssText = 'display:block !important;position:fixed !important;left:0;right:0;bottom:0;z-index:2147482000;background:#0c1a2b;border-top:1px solid rgba(255,255,255,.12);color:#fff;padding:10px 16px';
    }
    return p;
}
window.showPlayerBar = showPlayerBar;
</script>