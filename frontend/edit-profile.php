<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");
include_once("../backend/includes/image_paths.php");

if (!isset($_SESSION['user_id'])) {
    $_SESSION['auth_message'] = 'Please sign in to edit your profile.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

$message = '';
$userId = (int) $_SESSION['user_id'];

function editProfileColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

function ensureProfileImageColumns($conn) {
    $columns = [
        'profile_image_position_x' => "DECIMAL(5,2) DEFAULT 50",
        'profile_image_position_y' => "DECIMAL(5,2) DEFAULT 50",
        'profile_image_zoom' => "DECIMAL(4,2) DEFAULT 1"
    ];

    foreach ($columns as $column => $definition) {
        if (!editProfileColumnExists($conn, $column)) {
            $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }
}

function uploadProfileImage($file, &$error) {
    return zafiro_secure_upload($file, "../uploads/profile_images", "../uploads/profile_images", ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 2 * 1024 * 1024, "profile", $error);
}

ensureProfileImageColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $removeImage = ($_POST['remove_profile_image'] ?? '') === '1';
    $positionX = max(0, min(100, (float) ($_POST['profile_image_position_x'] ?? 50)));
    $positionY = max(0, min(100, (float) ($_POST['profile_image_position_y'] ?? 50)));
    $zoom = max(1, min(1.6, (float) ($_POST['profile_image_zoom'] ?? 1)));

    if ($fullName === '' || $username === '' || $email === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter valid profile details.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $check->bind_param("ssi", $username, $email, $userId);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $message = 'Username or email already exists.';
        } else {
            $imageError = '';
            $profileImage = uploadProfileImage($_FILES['profile_image'] ?? [], $imageError);
            if ($imageError !== '') {
                $message = $imageError;
            } elseif ($removeImage) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, gender = ?, profile_image = NULL, profile_image_position_x = 50, profile_image_position_y = 50, profile_image_zoom = 1 WHERE id = ?");
                $stmt->bind_param("sssssi", $fullName, $username, $email, $phone, $gender, $userId);
                $stmt->execute();
            } elseif ($profileImage !== '') {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, gender = ?, profile_image = ?, profile_image_position_x = ?, profile_image_position_y = ?, profile_image_zoom = ? WHERE id = ?");
                $stmt->bind_param("ssssssdddi", $fullName, $username, $email, $phone, $gender, $profileImage, $positionX, $positionY, $zoom, $userId);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, gender = ?, profile_image_position_x = ?, profile_image_position_y = ?, profile_image_zoom = ? WHERE id = ?");
                $stmt->bind_param("sssssdddi", $fullName, $username, $email, $phone, $gender, $positionX, $positionY, $zoom, $userId);
                $stmt->execute();
            }

            if ($message === '') {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $message = 'Profile updated successfully.';
            }
        }
    }
}

$stmt = $conn->prepare("SELECT full_name, username, email, phone, gender, profile_image, profile_image_position_x, profile_image_position_y, profile_image_zoom FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

include("../backend/includes/header.php");
?>

<main class="account-simple-page">
    <section class="account-card edit-profile-card">
        <div class="edit-profile-head">
            <div class="edit-profile-media">
                    <img class="profile-image-preview edit-profile-image adjustable-profile-image <?php echo empty($user['profile_image']) ? 'is-hidden' : ''; ?>" id="profileImagePreview" src="<?php echo htmlspecialchars(zafiroPublicImageUrl($user['profile_image'] ?? '')); ?>" alt="Profile image" data-profile-adjust-preview data-position-x="<?php echo htmlspecialchars($user['profile_image_position_x'] ?? '50'); ?>" data-position-y="<?php echo htmlspecialchars($user['profile_image_position_y'] ?? '50'); ?>" data-zoom="<?php echo htmlspecialchars($user['profile_image_zoom'] ?? '1'); ?>">
                <div class="account-avatar edit-profile-avatar <?php echo !empty($user['profile_image']) ? 'is-hidden' : ''; ?>" id="profileDefaultAvatar"><i class="fa-regular fa-circle-user"></i></div>
            </div>
            <div>
                <span>Zafiro Casa Account</span>
                <h1>Edit Profile</h1>
                <p>Update your personal account details.</p>
            </div>
        </div>
        <?php if ($message): ?><div class="auth-alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <form action="edit-profile.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="profile-form-grid edit-profile-form-grid">
                <input type="text" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                <input type="tel" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                <select name="gender" class="language-select">
                    <option value="">Gender (Optional)</option>
                    <?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $genderOption): ?>
                        <option value="<?php echo htmlspecialchars($genderOption); ?>" <?php echo ($user['gender'] ?? '') === $genderOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($genderOption); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="profile-photo-actions">
                    <button type="button" class="account-btn small" id="changeProfilePhotoBtn">Change Profile Photo</button>
                    <button type="button" class="account-btn small outline" id="removeProfilePhotoBtn">Remove Current Photo</button>
                    <input type="file" name="profile_image" id="profileImageInput" accept=".jpg,.jpeg,.png,.webp">
                    <input type="hidden" name="remove_profile_image" id="removeProfileImageInput" value="">
                    <input type="hidden" name="profile_image_position_x" id="profileImagePositionX" value="<?php echo htmlspecialchars($user['profile_image_position_x'] ?? '50'); ?>">
                    <input type="hidden" name="profile_image_position_y" id="profileImagePositionY" value="<?php echo htmlspecialchars($user['profile_image_position_y'] ?? '50'); ?>">
                    <input type="hidden" name="profile_image_zoom" id="profileImageZoom" value="<?php echo htmlspecialchars($user['profile_image_zoom'] ?? '1'); ?>">
                    <small>JPG, PNG, or WEBP. Max 2MB.</small>
                </div>
            </div>
            <button type="submit" class="account-btn small">Update Profile</button>
        </form>
    </section>

    <div class="profile-photo-modal" id="profilePhotoModal">
        <div class="profile-photo-modal-card">
            <h2>Adjust Profile Photo</h2>
            <div class="profile-modal-preview-wrap">
                <img id="profileModalPreview" alt="Profile photo preview">
            </div>
            <div class="profile-adjust-controls" aria-label="Profile image adjustment controls">
                <button type="button" data-profile-adjust="up">Move Up</button>
                <button type="button" data-profile-adjust="down">Move Down</button>
                <button type="button" data-profile-adjust="left">Move Left</button>
                <button type="button" data-profile-adjust="right">Move Right</button>
                <button type="button" data-profile-adjust="zoom-in">Zoom In</button>
                <button type="button" data-profile-adjust="zoom-out">Zoom Out</button>
                <button type="button" data-profile-adjust="reset">Reset</button>
            </div>
            <div class="profile-modal-actions">
                <button type="button" class="account-btn small" id="confirmProfilePhotoBtn">Confirm Photo</button>
                <button type="button" class="account-btn small outline" id="cancelProfilePhotoBtn">Cancel</button>
            </div>
        </div>
    </div>
</main>

<?php include("../backend/includes/footer.php"); ?>
