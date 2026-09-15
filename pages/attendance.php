<?php
// pages/attendance.php - Full page reload version with member date filter (fixed date comparison)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);

// ---- Get the church ID for the logged‑in user ----
$church_stmt = mysqli_prepare($conn, "SELECT church_id FROM users WHERE id = ?");
if ($church_stmt) {
    mysqli_stmt_bind_param($church_stmt, "i", $user_id);
    mysqli_stmt_execute($church_stmt);
    $church_result = mysqli_stmt_get_result($church_stmt);
    $church_row = mysqli_fetch_assoc($church_result);
    $church_id = $church_row['church_id'] ?? 0;
    mysqli_stmt_close($church_stmt);
} else {
    $church_id = 0;
    error_log("Attendance page: failed to prepare church query: " . mysqli_error($conn));
}

if ($church_id === 0) {
    echo '<div class="error-message">No church assigned to your account. Please contact the administrator.</div>';
    exit();
}

// ---- CSRF protection ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---- Messages from redirect ----
$error_message = '';
$success_message = '';
$selected_event = null;

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success_message = 'Changes saved successfully!';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $success_message = 'Event deleted successfully!';
}

// ---- Handle form submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($posted_csrf) || !hash_equals($csrf_token, $posted_csrf)) {
        $error_message = 'Your session expired. Please refresh the page and try again.';
    } elseif (isset($_POST['delete_event'])) {
        // ---------------- Delete event ----------------
        $event_id = intval($_POST['event_id'] ?? 0);
        $password = (string)($_POST['delete_password'] ?? '');

        // Verify the user's own password
        $user_stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
        if (!$user_stmt) {
            $error_message = 'Unable to verify your password. Database error.';
        } else {
            mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user_row = $user_result ? mysqli_fetch_assoc($user_result) : null;
            mysqli_stmt_close($user_stmt);

            $stored_password = $user_row['password'] ?? '';
            if (!$user_row || !$stored_password || !password_verify($password, $stored_password)) {
                $error_message = 'Incorrect password. The event was not deleted.';
            } else {
                // Check event ownership by church_id
                $event_stmt = mysqli_prepare($conn, 'SELECT id FROM events WHERE id = ? AND church_id = ? LIMIT 1');
                if ($event_stmt) {
                    mysqli_stmt_bind_param($event_stmt, 'ii', $event_id, $church_id);
                    mysqli_stmt_execute($event_stmt);
                    mysqli_stmt_store_result($event_stmt);
                    $owns_event = mysqli_stmt_num_rows($event_stmt) === 1;
                    mysqli_stmt_close($event_stmt);

                    if (!$owns_event) {
                        $error_message = 'Event not found or you do not have permission to delete it.';
                    } else {
                        mysqli_begin_transaction($conn);
                        try {
                            // Delete attendance records for this event & church
                            $del_att = mysqli_prepare($conn, 'DELETE FROM attendance WHERE event_id = ? AND church_id = ?');
                            if ($del_att) {
                                mysqli_stmt_bind_param($del_att, 'ii', $event_id, $church_id);
                                if (!mysqli_stmt_execute($del_att)) {
                                    throw new Exception('Attendance deletion failed: ' . mysqli_stmt_error($del_att));
                                }
                                mysqli_stmt_close($del_att);
                            } else {
                                throw new Exception('Failed to prepare attendance deletion');
                            }

                            // Delete offerings
                            $del_off = mysqli_prepare($conn, 'DELETE FROM offerings WHERE event_id = ? AND church_id = ?');
                            if ($del_off) {
                                mysqli_stmt_bind_param($del_off, 'ii', $event_id, $church_id);
                                if (!mysqli_stmt_execute($del_off)) {
                                    throw new Exception('Offering deletion failed: ' . mysqli_stmt_error($del_off));
                                }
                                mysqli_stmt_close($del_off);
                            } else {
                                throw new Exception('Failed to prepare offerings deletion');
                            }

                            // Delete the event itself
                            $del_ev = mysqli_prepare($conn, 'DELETE FROM events WHERE id = ? AND church_id = ?');
                            if ($del_ev) {
                                mysqli_stmt_bind_param($del_ev, 'ii', $event_id, $church_id);
                                if (!mysqli_stmt_execute($del_ev) || mysqli_stmt_affected_rows($del_ev) !== 1) {
                                    throw new Exception('Event deletion failed: ' . (mysqli_stmt_error($del_ev) ?: 'Event may no longer exist.'));
                                }
                                mysqli_stmt_close($del_ev);
                            } else {
                                throw new Exception('Failed to prepare event deletion');
                            }

                            mysqli_commit($conn);
                            // Full page reload after deletion
                            echo '<script>window.location.href = "index.php?page=attendance&deleted=1";</script>';
                            exit();
                        } catch (Throwable $exception) {
                            mysqli_rollback($conn);
                            $error_message = 'Unable to delete the event. No records were changed.';
                            error_log("Delete event error: " . $exception->getMessage());
                        }
                    }
                } else {
                    $error_message = 'Database error while checking event ownership.';
                }
            }
        }
    } elseif (isset($_POST['create_event'])) {
        // ---------------- Create new event ----------------
        $event_name = trim($_POST['event_name'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $event_type = trim($_POST['event_type'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($event_name) || empty($event_date)) {
            $error_message = "Event name and date are required.";
        } else {
            $insert_query = "INSERT INTO events (church_id, event_name, event_date, event_type, description) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "issss", $church_id, $event_name, $event_date, $event_type, $description);
                if (mysqli_stmt_execute($stmt)) {
                    $event_id = mysqli_insert_id($conn);

                    // If an offering amount is provided, create an offering record
                    if (isset($_POST['offering_amount']) && floatval($_POST['offering_amount']) > 0) {
                        $off_amount = floatval($_POST['offering_amount']);
                        $off_notes = trim($_POST['offering_notes'] ?? '');
                        $off_stmt = mysqli_prepare($conn, "INSERT INTO offerings (church_id, event_id, amount, notes) VALUES (?, ?, ?, ?)");
                        if ($off_stmt) {
                            mysqli_stmt_bind_param($off_stmt, "iids", $church_id, $event_id, $off_amount, $off_notes);
                            mysqli_stmt_execute($off_stmt);
                            mysqli_stmt_close($off_stmt);
                        }
                    }

                    mysqli_stmt_close($stmt);
                    // Full page reload to the new event
                    echo '<script>window.location.href = "index.php?page=attendance&event_id=' . $event_id . '&success=1";</script>';
                    exit();
                } else {
                    $error_message = "Error creating event: " . mysqli_stmt_error($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                $error_message = "Database error: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['mark_attendance'])) {
        // ---------------- Mark attendance ----------------
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id <= 0) {
            $error_message = "Invalid event ID.";
        } else {
            // Verify the event belongs to this church
            $check_ev = mysqli_prepare($conn, "SELECT id FROM events WHERE id = ? AND church_id = ?");
            if ($check_ev) {
                mysqli_stmt_bind_param($check_ev, "ii", $event_id, $church_id);
                mysqli_stmt_execute($check_ev);
                mysqli_stmt_store_result($check_ev);
                $event_exists = mysqli_stmt_num_rows($check_ev) > 0;
                mysqli_stmt_close($check_ev);
                if (!$event_exists) {
                    $error_message = "Event not found or does not belong to your church.";
                }
            } else {
                $error_message = "Database error while verifying event.";
            }

            if (empty($error_message)) {
                // Delete existing attendance for this event/church
                $del_att = mysqli_prepare($conn, "DELETE FROM attendance WHERE church_id = ? AND event_id = ?");
                if ($del_att) {
                    mysqli_stmt_bind_param($del_att, "ii", $church_id, $event_id);
                    mysqli_stmt_execute($del_att);
                    mysqli_stmt_close($del_att);
                }

                // Insert new attendance records
                if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
                    $attendance_count = 0;
                    $att_insert = "INSERT INTO attendance (church_id, event_id, member_id, attendance_status) VALUES (?, ?, ?, ?)";
                    $att_stmt = mysqli_prepare($conn, $att_insert);
                    if ($att_stmt) {
                        foreach ($_POST['attendance'] as $member_id => $status) {
                            $member_id = intval($member_id);
                            $status = trim($status);
                            if (!empty($status)) {
                                mysqli_stmt_bind_param($att_stmt, "iiis", $church_id, $event_id, $member_id, $status);
                                if (mysqli_stmt_execute($att_stmt)) {
                                    $attendance_count++;
                                }
                            }
                        }
                        mysqli_stmt_close($att_stmt);
                        $success_message = "Attendance marked for $attendance_count members!";
                    } else {
                        $error_message = "Failed to prepare attendance insert.";
                    }
                }

                // Handle offering update (if provided)
                if (isset($_POST['offering_amount']) && $_POST['offering_amount'] !== '') {
                    $off_amount = floatval($_POST['offering_amount']);
                    $off_notes = trim($_POST['offering_notes'] ?? '');

                    $check_query = "SELECT id FROM offerings WHERE church_id = ? AND event_id = ? LIMIT 1";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    if ($check_stmt) {
                        mysqli_stmt_bind_param($check_stmt, "ii", $church_id, $event_id);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        $exists = ($check_result && mysqli_num_rows($check_result) > 0);
                        mysqli_stmt_close($check_stmt);

                        if ($exists) {
                            $update_query = "UPDATE offerings SET amount = ?, notes = ? WHERE church_id = ? AND event_id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_query);
                            if ($update_stmt) {
                                mysqli_stmt_bind_param($update_stmt, "dsii", $off_amount, $off_notes, $church_id, $event_id);
                                if (mysqli_stmt_execute($update_stmt)) {
                                    $success_message .= " Offering updated successfully!";
                                } else {
                                    $error_message = "Error updating offering: " . mysqli_stmt_error($update_stmt);
                                }
                                mysqli_stmt_close($update_stmt);
                            } else {
                                $error_message = "Database error preparing offering update.";
                            }
                        } else {
                            $insert_off = "INSERT INTO offerings (church_id, event_id, amount, notes) VALUES (?, ?, ?, ?)";
                            $insert_stmt = mysqli_prepare($conn, $insert_off);
                            if ($insert_stmt) {
                                mysqli_stmt_bind_param($insert_stmt, "iids", $church_id, $event_id, $off_amount, $off_notes);
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $success_message .= " Offering recorded successfully!";
                                } else {
                                    $error_message = "Error recording offering: " . mysqli_stmt_error($insert_stmt);
                                }
                                mysqli_stmt_close($insert_stmt);
                            } else {
                                $error_message = "Database error preparing offering insert.";
                            }
                        }
                    } else {
                        $error_message = "Database error checking offering record.";
                    }
                }

                // If no errors, full page reload to the same event with success message
                if (empty($error_message)) {
                    echo '<script>window.location.href = "index.php?page=attendance&event_id=' . $event_id . '&success=1";</script>';
                    exit();
                }
            }
        }
    }
}

// ---- Fetch all events for this church ----
$events_query = "SELECT * FROM events WHERE church_id = ? ORDER BY event_date DESC";
$events_stmt = mysqli_prepare($conn, $events_query);
if ($events_stmt) {
    mysqli_stmt_bind_param($events_stmt, "i", $church_id);
    mysqli_stmt_execute($events_stmt);
    $events_result = mysqli_stmt_get_result($events_stmt);
} else {
    $events_result = false;
    $error_message = "Failed to load events.";
}

// ---- Selected event handling ----
$selected_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($selected_event_id > 0) {
    $selected_event_query = "SELECT * FROM events WHERE id = ? AND church_id = ?";
    $selected_event_stmt = mysqli_prepare($conn, $selected_event_query);
    if ($selected_event_stmt) {
        mysqli_stmt_bind_param($selected_event_stmt, "ii", $selected_event_id, $church_id);
        mysqli_stmt_execute($selected_event_stmt);
        $selected_event_result = mysqli_stmt_get_result($selected_event_stmt);
        $selected_event = $selected_event_result ? mysqli_fetch_assoc($selected_event_result) : null;
        mysqli_stmt_close($selected_event_stmt);
    }
}

// Auto-select most recent event if none chosen
if ($selected_event_id == 0 && $events_result && mysqli_num_rows($events_result) > 0) {
    mysqli_data_seek($events_result, 0);
    $first_event = mysqli_fetch_assoc($events_result);
    $selected_event_id = $first_event['id'];
    $selected_event = $first_event;
    mysqli_data_seek($events_result, 0);
}

// ---- Fetch members – with date filter (DATE() comparison fixed) ----
$members_result = false;
$filter_note = '';

if ($selected_event && !empty($selected_event['event_date'])) {
    // Check if created_at column exists
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM members LIKE 'created_at'");
    $has_created_at = ($col_check && mysqli_num_rows($col_check) > 0);

    if ($has_created_at) {
        // Use DATE() to compare date portion only
        $members_query = "SELECT * FROM members WHERE church_id = ? AND DATE(created_at) <= ? ORDER BY name ASC";
        $members_stmt = mysqli_prepare($conn, $members_query);
        if ($members_stmt) {
            mysqli_stmt_bind_param($members_stmt, "is", $church_id, $selected_event['event_date']);
            mysqli_stmt_execute($members_stmt);
            $members_result = mysqli_stmt_get_result($members_stmt);
        }

        // If no members returned, fallback to all church members
        if (!$members_result || mysqli_num_rows($members_result) === 0) {
            $fallback_query = "SELECT * FROM members WHERE church_id = ? ORDER BY name ASC";
            $fb_stmt = mysqli_prepare($conn, $fallback_query);
            if ($fb_stmt) {
                mysqli_stmt_bind_param($fb_stmt, "i", $church_id);
                mysqli_stmt_execute($fb_stmt);
                $members_result = mysqli_stmt_get_result($fb_stmt);
            }
            $filter_note = 'All members are shown. Date filtering could not be applied (member join dates may be missing or after this event).';
        }
    } else {
        // No created_at column at all, just show all members
        $members_query = "SELECT * FROM members WHERE church_id = ? ORDER BY name ASC";
        $members_stmt = mysqli_prepare($conn, $members_query);
        if ($members_stmt) {
            mysqli_stmt_bind_param($members_stmt, "i", $church_id);
            mysqli_stmt_execute($members_stmt);
            $members_result = mysqli_stmt_get_result($members_stmt);
        }
        $filter_note = 'Member join dates not available; showing all members.';
    }
} else {
    // No event selected – show all members
    $members_query = "SELECT * FROM members WHERE church_id = ? ORDER BY name ASC";
    $members_stmt = mysqli_prepare($conn, $members_query);
    if ($members_stmt) {
        mysqli_stmt_bind_param($members_stmt, "i", $church_id);
        mysqli_stmt_execute($members_stmt);
        $members_result = mysqli_stmt_get_result($members_stmt);
    }
}
if (!isset($members_stmt) || !$members_stmt) {
    $members_result = false;
    $error_message = "Failed to load members.";
}

// ---- Attendance data for selected event ----
$attendance_data = [];
if ($selected_event_id > 0) {
    $att_query = "SELECT a.*, m.name as member_name 
                  FROM attendance a 
                  JOIN members m ON a.member_id = m.id 
                  WHERE a.church_id = ? AND a.event_id = ?";
    $att_stmt = mysqli_prepare($conn, $att_query);
    if ($att_stmt) {
        mysqli_stmt_bind_param($att_stmt, "ii", $church_id, $selected_event_id);
        mysqli_stmt_execute($att_stmt);
        $att_result = mysqli_stmt_get_result($att_stmt);
        while ($row = mysqli_fetch_assoc($att_result)) {
            $attendance_data[$row['member_id']] = $row;
        }
        mysqli_stmt_close($att_stmt);
    }
}

// ---- Offering for selected event ----
$offering_amount = 0;
$offering_notes = '';
if ($selected_event_id > 0) {
    $off_query = "SELECT * FROM offerings WHERE church_id = ? AND event_id = ? LIMIT 1";
    $off_stmt = mysqli_prepare($conn, $off_query);
    if ($off_stmt) {
        mysqli_stmt_bind_param($off_stmt, "ii", $church_id, $selected_event_id);
        mysqli_stmt_execute($off_stmt);
        $off_result = mysqli_stmt_get_result($off_stmt);
        if ($off_result && mysqli_num_rows($off_result) > 0) {
            $off_data = mysqli_fetch_assoc($off_result);
            $offering_amount = $off_data['amount'];
            $offering_notes = $off_data['notes'] ?? '';
        }
        mysqli_stmt_close($off_stmt);
    }
}

// ---- Total offerings for the church ----
$total_offering = 0;
$offering_count = 0;
try {
    $tot_query = "SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total FROM offerings WHERE church_id = ?";
    $tot_stmt = mysqli_prepare($conn, $tot_query);
    if ($tot_stmt) {
        mysqli_stmt_bind_param($tot_stmt, "i", $church_id);
        mysqli_stmt_execute($tot_stmt);
        $tot_result = mysqli_stmt_get_result($tot_stmt);
        if ($tot_result) {
            $tot_data = mysqli_fetch_assoc($tot_result);
            $total_offering = $tot_data['total'] ?? 0;
            $offering_count = $tot_data['cnt'] ?? 0;
        }
        mysqli_stmt_close($tot_stmt);
    }
} catch (Exception $e) {
    $total_offering = 0;
    $offering_count = 0;
}
?>
<!-- ===== PAGE CONTENT ===== -->
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/attendance.css">

<div class="header-section">
    <h1><i class="fas fa-calendar-check"></i> Attendance Tracking</h1>
    <p class="subtitle">Track member attendance and offerings for church events and services</p>
    <a href="index.php?page=dashboard" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
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

<?php if (!empty($filter_note)): ?>
    <div class="info-message" style="background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px;">
        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($filter_note); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <div>
            <h3>Success</h3>
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="attendance-container">
    <!-- Events Sidebar -->
    <div class="events-sidebar">
        <h2><i class="fas fa-calendar-alt"></i> Events</h2>
        <div class="event-list">
            <?php if ($events_result && mysqli_num_rows($events_result) > 0):
                $has_events = true;
                while ($event = mysqli_fetch_assoc($events_result)):
                    $is_active = ($selected_event_id == $event['id']);
            ?>
                <div class="event-item <?php echo $is_active ? 'active' : ''; ?>"
                     onclick="window.location.href='index.php?page=attendance&event_id=<?php echo $event['id']; ?>'">
                    <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                    <div class="event-date">
                        <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
                    </div>
                    <div class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></div>
                </div>
            <?php endwhile;
            else: ?>
                <p style="color: #adb5bd; text-align: center; padding: 20px;">
                    <i class="fas fa-calendar-plus" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                    No events found.<br>Create your first event below.
                </p>
            <?php endif; ?>
        </div>

        <!-- Create Event Form -->
        <div class="create-event-form">
            <h3><i class="fas fa-plus-circle"></i> Create New Event</h3>
            <form method="POST" action="" id="createEventForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="create_event" value="1">
                <div class="form-group">
                    <label for="event_name">Event Name <span style="color: #e74c3c;">*</span></label>
                    <input type="text" id="event_name" name="event_name" required placeholder="e.g., Sunday Service" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Event Date <span style="color: #e74c3c;">*</span></label>
                        <input type="date" id="event_date" name="event_date" required value="<?php echo date('Y-m-d'); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="event_type">Event Type</label>
                        <select id="event_type" name="event_type" class="form-control">
                            <option value="Sunday Service">Sunday Service</option>
                            <option value="Bible Study">Bible Study</option>
                            <option value="Prayer Meeting">Prayer Meeting</option>
                            <option value="Special Event">Special Event</option>
                            <option value="Wedding">Wedding</option>
                            <option value="Funeral">Funeral</option>
                            <option value="Baptism">Baptism</option>
                            <option value="Communion">Communion</option>
                            <option value="Youth Service">Youth Service</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description <span style="color: #6c757d;">(Optional)</span></label>
                    <textarea id="description" name="description" rows="2" placeholder="Event description..." class="form-control"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="offering_amount">Offering Amount (₵)</label>
                        <input type="number" id="offering_amount" name="offering_amount" step="0.01" min="0" placeholder="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="offering_notes">Offering Notes</label>
                        <input type="text" id="offering_notes" name="offering_notes" placeholder="e.g. Thanksgiving offering" class="form-control">
                    </div>
                </div>
                <button type="submit" class="submit-btn" style="width: 100%;">
                    <i class="fas fa-calendar-plus"></i> Create Event
                </button>
            </form>
        </div>
    </div>

    <!-- Attendance Main Area -->
    <div class="attendance-main">
        <?php if ($selected_event_id > 0 && $selected_event): ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="color: white; margin: 0 0 5px 0;"><?php echo htmlspecialchars($selected_event['event_name']); ?></h2>
                    <p style="color: #adb5bd; margin: 0;">
                        <i class="far fa-calendar"></i> <?php echo date('l, F j, Y', strtotime($selected_event['event_date'])); ?>
                         • <?php echo htmlspecialchars($selected_event['event_type']); ?>
                    </p>
                </div>
                <div class="event-actions">
                    <a href="index.php?page=attendance" class="back-btn" style="font-size: 13px;">
                        <i class="fas fa-times"></i> Clear Selection
                    </a>
                    <button type="button" class="delete-event-btn" id="openDeleteModal">
                        <i class="fas fa-trash-alt"></i> Delete Event
                    </button>
                </div>
            </div>

            <?php if (!empty($selected_event['description'])): ?>
                <div style="background: rgba(255, 255, 255, 0.05); padding: 12px 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="color: #adb5bd; margin: 0; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($selected_event['description']); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($members_result && mysqli_num_rows($members_result) > 0):
                $total_members = mysqli_num_rows($members_result);
                $present_count = 0; $absent_count = 0; $late_count = 0; $excused_count = 0;
                mysqli_data_seek($members_result, 0);
                while ($member = mysqli_fetch_assoc($members_result)) {
                    if (isset($attendance_data[$member['id']])) {
                        $status = $attendance_data[$member['id']]['attendance_status'];
                        switch ($status) {
                            case 'Present': $present_count++; break;
                            case 'Absent': $absent_count++; break;
                            case 'Late': $late_count++; break;
                            case 'Excused': $excused_count++; break;
                        }
                    }
                }
                $attendance_rate = $total_members > 0 ? round(($present_count / $total_members) * 100) : 0;
            ?>
                <div class="attendance-stats">
                    <div class="stat-card"><div class="stat-label">Total Members</div><div class="stat-number"><?php echo $total_members; ?></div></div>
                    <div class="stat-card"><div class="stat-label">Present</div><div class="stat-number status-present"><?php echo $present_count; ?></div></div>
                    <div class="stat-card"><div class="stat-label">Absent</div><div class="stat-number status-absent"><?php echo $absent_count; ?></div></div>
                    <div class="stat-card"><div class="stat-label">Late</div><div class="stat-number status-late"><?php echo $late_count; ?></div></div>
                    <div class="stat-card"><div class="stat-label">Attendance Rate</div><div class="stat-number"><?php echo $attendance_rate; ?>%</div></div>
                    <div class="stat-card"><div class="stat-label">Event Offering</div><div class="stat-number offering-stat">₵<?php echo number_format($offering_amount, 2); ?></div></div>
                    <div class="stat-card"><div class="stat-label">Total Offerings</div><div class="stat-number offering-stat">₵<?php echo number_format($total_offering, 2); ?><small style="display: block; font-size: 11px; color: #6c757d;">(<?php echo $offering_count; ?> records)</small></div></div>
                </div>

                <!-- Offering Update Section -->
                <div class="offering-section">
                    <div class="offering-header"><i class="fas fa-hand-holding-usd"></i><h3>Update Event Offering</h3></div>
                    <div class="offering-input-group">
                        <div>
                            <label for="event_offering_amount"><i class="fas fa-edit"></i> Amount (₵) <small>Current: ₵<?php echo number_format($offering_amount, 2); ?></small></label>
                            <input type="number" id="event_offering_amount" value="<?php echo number_format($offering_amount, 2); ?>" step="0.01" min="0" class="form-control" placeholder="Enter new amount">
                        </div>
                        <div>
                            <label for="event_offering_notes"><i class="fas fa-sticky-note"></i> Notes <small>Current: <?php echo htmlspecialchars($offering_notes ?: 'No notes'); ?></small></label>
                            <input type="text" id="event_offering_notes" value="<?php echo htmlspecialchars($offering_notes); ?>" placeholder="e.g., Updated total, Additional collection" class="form-control">
                        </div>
                    </div>
                    <div style="background: rgba(52, 152, 219, 0.08); padding: 10px 15px; border-radius: 5px; margin-top: 10px;">
                        <p style="color: #3498db; font-size: 13px; margin: 0;"><i class="fas fa-info-circle"></i> Updating the amount here will update the offering record for this event.</p>
                    </div>
                </div>

                <!-- Attendance Form -->
                <form method="POST" action="" id="attendanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="mark_attendance" value="1">
                    <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0 10px 0;">
                        <h3 style="color: white; margin: 0;"><i class="fas fa-users"></i> Member Attendance <small style="color: #6c757d; font-weight: normal; font-size: 13px;">(<?php echo $total_members; ?> members)</small></h3>
                        <button type="button" onclick="setAllAttendance('Present')" class="back-btn" style="font-size: 12px;"><i class="fas fa-check"></i> All Present</button>
                    </div>

                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Member Name</th>
                                <th style="width: 25%;">Contact</th>
                                <th style="width: 25%;">Attendance Status</th>
                                <th style="width: 15%;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            mysqli_data_seek($members_result, 0);
                            while ($member = mysqli_fetch_assoc($members_result)):
                                $current_status = isset($attendance_data[$member['id']]) ? $attendance_data[$member['id']]['attendance_status'] : 'Present';
                                $notes = isset($attendance_data[$member['id']]['notes']) ? $attendance_data[$member['id']]['notes'] : '';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($member['name']); ?></strong><br><small style="color: #6c757d;">ID: <?php echo $member['id']; ?></small></td>
                                <td><?php echo htmlspecialchars($member['contact'] ?: 'N/A'); ?></td>
                                <td>
                                    <select name="attendance[<?php echo $member['id']; ?>]" class="status-select status-<?php echo strtolower($current_status); ?>">
                                        <option value="Present" <?php echo ($current_status == 'Present') ? 'selected' : ''; ?>>✅ Present</option>
                                        <option value="Absent" <?php echo ($current_status == 'Absent') ? 'selected' : ''; ?>>❌ Absent</option>
                                        <option value="Late" <?php echo ($current_status == 'Late') ? 'selected' : ''; ?>>⏰ Late</option>
                                        <option value="Excused" <?php echo ($current_status == 'Excused') ? 'selected' : ''; ?>>📝 Excused</option>
                                    </select>
                                </td>
                                <td><small style="color: #6c757d;"><?php echo !empty($notes) ? htmlspecialchars($notes) : '—'; ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <input type="hidden" name="offering_amount" id="hidden_offering_amount" value="<?php echo $offering_amount; ?>">
                    <input type="hidden" name="offering_notes" id="hidden_offering_notes" value="<?php echo htmlspecialchars($offering_notes); ?>">

                    <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button type="submit" class="submit-btn" id="saveAttendanceBtn"><i class="fas fa-save"></i> Save Attendance & Offering</button>
                        <button type="button" onclick="resetAttendance()" class="back-btn"><i class="fas fa-undo"></i> Reset to Default</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="no-event-selected">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Members Found</h3>
                    <p>You need to add members before tracking attendance.</p>
                    <a href="index.php?page=add-member" class="add-btn" style="margin-top: 20px; display: inline-block;"><i class="fas fa-user-plus"></i> Add Members First</a>
                </div>
            <?php endif; ?>

            <!-- Delete Event Modal -->
            <div class="delete-modal" id="deleteEventModal" hidden>
                <div class="delete-modal-backdrop" data-close-delete-modal></div>
                <div class="delete-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
                    <button type="button" class="delete-modal-close" data-close-delete-modal aria-label="Close">&times;</button>
                    <i class="fas fa-triangle-exclamation delete-warning-icon"></i>
                    <h3 id="deleteModalTitle">Delete this event?</h3>
                    <p>This permanently removes the event, its attendance, and its offering record. Enter your account password to continue.</p>
                    <form method="POST" action="" id="deleteEventForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="delete_event" value="1">
                        <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                        <label for="delete_password">Account password</label>
                        <input type="password" id="delete_password" name="delete_password" required autocomplete="current-password" class="form-control">
                        <div class="delete-modal-actions">
                            <button type="button" class="back-btn" data-close-delete-modal>Cancel</button>
                            <button type="submit" class="delete-confirm-btn" id="confirmDeleteBtn"><i class="fas fa-trash-alt"></i> Delete permanently</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="no-event-selected">
                <i class="fas fa-calendar-alt"></i>
                <h3>Select an Event</h3>
                <p>Choose an event from the sidebar to view and mark attendance.</p>
                <p style="font-size: 14px; color: #6c757d; margin-top: 5px;">Or create a new event to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Set max date to today
    document.getElementById('event_date').max = new Date().toISOString().split('T')[0];

    // Update select colors
    function updateSelectColor(select) {
        select.className = 'status-select';
        switch (select.value) {
            case 'Present': select.classList.add('status-present'); break;
            case 'Absent': select.classList.add('status-absent'); break;
            case 'Late': select.classList.add('status-late'); break;
            case 'Excused': select.classList.add('status-excused'); break;
        }
    }
    document.querySelectorAll('.status-select').forEach(function(s) {
        updateSelectColor(s);
        s.addEventListener('change', function() { updateSelectColor(this); });
    });

    window.setAllAttendance = function(status) {
        if (confirm('Set all members to "' + status + '"?')) {
            document.querySelectorAll('.status-select').forEach(function(s) {
                s.value = status; updateSelectColor(s);
            });
        }
    };
    window.resetAttendance = function() {
        if (confirm('Reset all attendance to "Present"?')) {
            document.querySelectorAll('.status-select').forEach(function(s) {
                s.value = 'Present'; updateSelectColor(s);
            });
        }
    };

    // Hidden offering sync
    const offeringAmt = document.getElementById('event_offering_amount');
    const offeringNotes = document.getElementById('event_offering_notes');
    const hiddenAmt = document.getElementById('hidden_offering_amount');
    const hiddenNotes = document.getElementById('hidden_offering_notes');
    if (offeringAmt && hiddenAmt) {
        offeringAmt.addEventListener('input', function() { hiddenAmt.value = this.value || 0; });
    }
    if (offeringNotes && hiddenNotes) {
        offeringNotes.addEventListener('input', function() { hiddenNotes.value = this.value; });
    }

    // Confirm offering change
    const saveBtn = document.getElementById('saveAttendanceBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            const currentAmt = parseFloat(<?php echo $offering_amount; ?>) || 0;
            const newAmt = parseFloat(offeringAmt?.value || '0') || 0;
            if (Math.abs(newAmt - currentAmt) > 0.01) {
                if (!confirm('You are changing the offering amount from ₵' + currentAmt.toFixed(2) + ' to ₵' + newAmt.toFixed(2) + '.\n\nAre you sure you want to update the offering record?')) {
                    e.preventDefault();
                    offeringAmt?.focus();
                    return false;
                }
            }
            if (offeringAmt && hiddenAmt) hiddenAmt.value = offeringAmt.value || 0;
            if (offeringNotes && hiddenNotes) hiddenNotes.value = offeringNotes.value;
            return true;
        });
    }

    const attendanceForm = document.getElementById('attendanceForm');
    if (attendanceForm && saveBtn) {
        attendanceForm.addEventListener('submit', function() {
            saveBtn.innerHTML = '<span class="spinner"></span> Saving...';
            saveBtn.disabled = true;
        });
    }

    // Delete modal
    const deleteModal = document.getElementById('deleteEventModal');
    const openDeleteBtn = document.getElementById('openDeleteModal');
    const deletePwd = document.getElementById('delete_password');
    if (deleteModal && openDeleteBtn) {
        const closeModal = function() {
            deleteModal.hidden = true;
            document.body.classList.remove('modal-open');
        };
        openDeleteBtn.addEventListener('click', function() {
            deleteModal.hidden = false;
            document.body.classList.add('modal-open');
            setTimeout(function() { deletePwd?.focus(); }, 0);
        });
        deleteModal.querySelectorAll('[data-close-delete-modal]').forEach(function(btn) {
            btn.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !deleteModal.hidden) closeModal();
        });
    }

    const deleteForm = document.getElementById('deleteEventForm');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (deleteForm && confirmDeleteBtn) {
        deleteForm.addEventListener('submit', function() {
            confirmDeleteBtn.innerHTML = '<span class="spinner"></span> Deleting...';
            confirmDeleteBtn.disabled = true;
        });
    }

    // Auto-hide messages
    document.querySelectorAll('.success-message, .error-message, .info-message').forEach(function(msg) {
        setTimeout(function() {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(function() { msg.style.display = 'none'; }, 500);
        }, 5000);
    });

    // Format currency inputs on blur
    document.querySelectorAll('input[type="number"][step="0.01"]').forEach(function(inp) {
        inp.addEventListener('blur', function() {
            if (this.value) this.value = parseFloat(this.value).toFixed(2);
        });
    });

    // Prevent double submission
    document.querySelectorAll('form').forEach(function(form) {
        let submitted = false;
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
            setTimeout(function() { submitted = false; }, 3000);
        });
    });
</script>