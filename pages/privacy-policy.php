<?php
// pages/privacy-policy.php - Content only for SPA
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Please login to view this page.</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
?>

<!-- ===== PAGE CONTENT (NO CSS LINKS - already in index.php) ===== -->

<div class="header-section">
    <h1><i class="fas fa-shield-alt"></i> Privacy Policy</h1>
    <p class="subtitle">How we collect, use, and protect your information</p>
    <a href="#" class="back-btn" onclick="loadPage('dashboard')">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="privacy-container">
    <div class="privacy-meta">
        <i class="fas fa-calendar-alt"></i> <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?>
    </div>

    <p>
        <strong>COC Management</strong> ("we", "our", "us") is committed to protecting the privacy and security of your personal information. 
        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our church management system.
    </p>

    <h2><i class="fas fa-database"></i> 1. Information We Collect</h2>
    <p>We collect the following types of information for church administration purposes:</p>
    <ul>
        <li><strong>Personal Identification Information:</strong> Full name, date of birth, contact number, email address, residence address, and hometown</li>
        <li><strong>Church-Related Information:</strong> Previous church affiliation, number of children, attendance records, and offerings</li>
        <li><strong>Profile Information:</strong> Optional profile photos uploaded by users</li>
        <li><strong>System Usage Data:</strong> Login times, pages visited, and actions performed within the system</li>
    </ul>

    <div class="highlight-box">
        <p><i class="fas fa-info-circle" style="color: orange; margin-right: 10px;"></i>
        <strong>Note:</strong> We only collect information that is relevant and necessary for church management. All data is provided voluntarily by members.</p>
    </div>

    <h2><i class="fas fa-cogs"></i> 2. How We Use Your Information</h2>
    <p>We use the collected information for the following purposes:</p>
    <ul>
        <li>Maintain accurate church membership records</li>
        <li>Track attendance for church services and events</li>
        <li>Communicate important church announcements and updates</li>
        <li>Manage church offerings and financial contributions</li>
        <li>Generate reports for church administration</li>
        <li>Improve our services and user experience</li>
    </ul>

    <h2><i class="fas fa-share-alt"></i> 3. Information Sharing and Disclosure</h2>
    <p>We value your privacy and do not sell, trade, or rent your personal information to third parties. Your information may be shared in the following limited circumstances:</p>
    <ul>
        <li><strong>Church Leadership:</strong> Authorized church leaders and administrators have access to member records for administrative purposes</li>
        <li><strong>Legal Requirements:</strong> If required by law, we may disclose information to comply with legal obligations</li>
        <li><strong>Service Providers:</strong> We use trusted third-party services (hosting, email) that process data on our behalf</li>
    </ul>

    <h2><i class="fas fa-lock"></i> 4. Data Security</h2>
    <p>We implement appropriate technical and organizational measures to protect your personal information:</p>
    <ul>
        <li>Secure HTTPS encryption for all data transmission</li>
        <li>Password-protected user accounts</li>
        <li>Regular security updates and monitoring</li>
        <li>Access controls limiting who can view member data</li>
        <li>Secure database storage with restricted access</li>
    </ul>

    <div class="highlight-box">
        <p><i class="fas fa-shield-alt" style="color: orange; margin-right: 10px;"></i>
        <strong>Security Commitment:</strong> We are committed to protecting your data against unauthorized access, alteration, disclosure, or destruction.</p>
    </div>

    <h2><i class="fas fa-user-check"></i> 5. Your Rights</h2>
    <p>You have the following rights regarding your personal information:</p>
    <ul>
        <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
        <li><strong>Correction:</strong> Request corrections to inaccurate or incomplete data</li>
        <li><strong>Deletion:</strong> Request deletion of your personal data (subject to church record-keeping requirements)</li>
        <li><strong>Withdraw Consent:</strong> Withdraw your consent for data processing at any time</li>
        <li><strong>Update:</strong> Update your personal information through the system</li>
    </ul>
    <p>To exercise these rights, please contact us using the information provided below.</p>

    <h2><i class="fas fa-child"></i> 6. Children's Privacy</h2>
    <p>Our system may contain information about children for church administration purposes (e.g., Sunday School, children's programs). This information is provided by parents or guardians. We do not knowingly collect personal information from children without parental consent.</p>
    <p>If you are a parent or guardian and have concerns about your child's information, please contact us.</p>

    <h2><i class="fas fa-cookie"></i> 7. Cookies and Tracking</h2>
    <p>We use cookies and similar tracking technologies to:</p>
    <ul>
        <li>Maintain user sessions and logins</li>
        <li>Remember user preferences</li>
        <li>Analyze system usage and improve performance</li>
    </ul>
    <p>You can control cookie preferences through your browser settings. However, disabling cookies may affect system functionality.</p>

    <h2><i class="fas fa-globe"></i> 8. Third-Party Services</h2>
    <p>We use the following third-party services that may process your data:</p>
    <ul>
        <li><strong>Hosting Provider:</strong> InfinityFree (or your hosting provider) - stores system data</li>
        <li><strong>Google Analytics:</strong> (if used) - analyzes system usage</li>
        <li><strong>Google AdSense:</strong> (if implemented) - displays contextual advertisements</li>
    </ul>
    <p>These third-party services have their own privacy policies and data processing practices.</p>

    <h2><i class="fas fa-envelope"></i> 9. Contact Us</h2>
    <p>If you have any questions, concerns, or requests regarding this Privacy Policy or your personal information, please contact us:</p>

    <div class="contact-info-grid">
        <div class="contact-info-item">
            <i class="fas fa-envelope"></i>
            <div class="label">Email</div>
            <div class="value">bentumderrick0@email.com</div>
        </div>
        <div class="contact-info-item">
            <i class="fas fa-phone"></i>
            <div class="label">Phone</div>
            <div class="value">+233 544 22 43 25</div>
        </div>
        <div class="contact-info-item">
            <i class="fas fa-map-marker-alt"></i>
            <div class="label">Address</div>
            <div class="value">P.O. Box TA 252</div>
        </div>
    </div>

    <h2><i class="fas fa-edit"></i> 10. Changes to This Policy</h2>
    <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
    <ul>
        <li>Posting the updated policy on this page</li>
        <li>Updating the "Last Updated" date at the top of this page</li>
        <li>Sending notifications (where appropriate)</li>
    </ul>
    <p>We encourage you to review this Privacy Policy periodically to stay informed about how we protect your information.</p>

    <div class="last-updated">
        <i class="fas fa-clock"></i> This Privacy Policy was last updated on <?php echo date('F j, Y'); ?>
    </div>
</div>