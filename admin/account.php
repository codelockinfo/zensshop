<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = getBaseUrl();
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$error = '';
$success = '';

// Handle profile image upload (before header to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile'])) {
    try {
        // Check if file was uploaded
        if (empty($_FILES['profile_image']['name']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select an image file to upload.');
        }
        
        $file = $_FILES['profile_image'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('File size exceeds 2MB limit.');
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../assets/images/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Delete old profile image if exists
        if (!empty($currentUser['profile_image'])) {
            // Normalize the URL (fix old /oecom/ paths)
            $oldImage = normalizeImageUrl($currentUser['profile_image']);
            
            // Remove base URL prefix and convert to file path
            $oldImagePath = str_replace($baseUrl . '/', '', $oldImage);
            $oldImagePath = preg_replace('#^[^/]+/#', '', $oldImagePath); // Remove any leading directory
            $oldImagePath = __DIR__ . '/../' . ltrim($oldImagePath, '/');
            $oldImagePath = str_replace('\\', '/', $oldImagePath);
            if (file_exists($oldImagePath)) {
                @unlink($oldImagePath);
            }
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $currentUser['id'] . '_' . time() . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to save uploaded file.');
        }
        
        // Update database
        $profileImagePath = $baseUrl . '/assets/images/profiles/' . $filename;
        $db->execute(
            "UPDATE users SET profile_image = ? WHERE id = ?",
            [$profileImagePath, $currentUser['id']]
        );
        
        // Redirect to refresh the page and show updated image
        header('Location: ' . url('admin/account.php?success=image_updated'));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle form submission (account info and password) - before header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['upload_profile'])) {
    try {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Update name and email
        if ($name && $email) {
            $db->execute(
                "UPDATE users SET name = ?, email = ? WHERE id = ?",
                [$name, $email, $currentUser['id']]
            );
            $success = 'Account updated successfully!';
            // Reload user data
            $currentUser = $auth->getCurrentUser();
        }
        
        // Update password if provided
        if ($newPassword && $currentPassword) {
            if ($newPassword !== $confirmPassword) {
                $error = 'New password and confirm password do not match.';
            } else {
                // Verify current password
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$currentUser['id']]);
                if (password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->execute(
                        "UPDATE users SET password = ? WHERE id = ?",
                        [$hashedPassword, $currentUser['id']]
                    );
                    $success = 'Password updated successfully!';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'image_updated') {
    $success = 'Profile image updated successfully!';
    // Reload user data to get updated profile_image
    $currentUser = $auth->getCurrentUser();
}

$pageTitle = 'Account';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Account Settings</h1>
    <p class="text-gray-600">Dashboard > Account</p>
</div>

<?php if ($error): ?>
<div class="admin-alert admin-alert-error mb-4">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success mb-4">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Profile Image -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Profile Image</h2>
        
        <div class="flex flex-col items-center mb-6">
            <div class="relative mb-4">
                <?php 
                $profileImage = $currentUser['profile_image'] ?? null;
                $imageUrl = $baseUrl . '/assets/images/default-avatar.svg';
                
                if ($profileImage) {
                    // Normalize the URL (fix old /oecom/ paths)
                    $profileImage = normalizeImageUrl($profileImage);
                    
                    // Check if it's already a full URL
                    if (strpos($profileImage, 'http://') === 0 || strpos($profileImage, 'https://') === 0) {
                        $imageUrl = $profileImage;
                    } elseif (strpos($profileImage, 'data:image') === 0) {
                        // It's a base64 data URI, use it directly
                        $imageUrl = $profileImage;
                    } elseif (strpos($profileImage, '/') === 0) {
                        // It's already a path from root
                        $imageUrl = $profileImage;
                    } else {
                        // Remove any base URL prefix and convert to file path
                        $imagePath = str_replace($baseUrl . '/', '', $profileImage);
                        $imagePath = preg_replace('#^[^/]+/#', '', $imagePath); // Remove any leading directory
                        $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
                        
                        if (file_exists($fullPath)) {
                            $imageUrl = $baseUrl . '/' . ltrim($imagePath, '/');
                        }
                    }
                }
                ?>
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                     alt="Profile" 
                     id="profilePreview"
                     class="w-32 h-32 rounded-full object-cover border-4 border-gray-200"
                     onerror="this.src='<?php echo $baseUrl; ?>/assets/images/default-avatar.svg'">
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" class="w-full">
                <input type="file" 
                       name="profile_image" 
                       id="profileImageInput"
                       accept="image/*"
                       class="hidden"
                       onchange="previewProfileImage(this)">
                <button type="button" 
                        onclick="document.getElementById('profileImageInput').click()"
                        class="admin-btn admin-btn-primary w-full mb-2">
                    <i class="fas fa-upload mr-2"></i>Upload New Image
                </button>
                <button type="submit" 
                        name="upload_profile"
                        class="admin-btn bg-green-500 text-white w-full hidden"
                        id="saveProfileBtn">
                    <i class="fas fa-save mr-2"></i>Save Image
                </button>
            </form>
        </div>
    </div>
    
    <!-- Account Information -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Account Information</h2>
        
        <form method="POST" action="">
            <div class="admin-form-group">
                <label class="admin-form-label">Full Name</label>
                <input type="text" 
                       name="name" 
                       value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>"
                       required
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Email Address</label>
                <input type="email" 
                       name="email" 
                       value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                       required
                       class="admin-form-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Role</label>
                <input type="text" 
                       value="Admin" 
                       disabled
                       class="admin-form-input bg-gray-100">
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary">
                Update Account
            </button>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="admin-card">
        <h2 class="text-xl font-bold mb-4">Change Password</h2>
        
        <form method="POST" action="">
            <div class="admin-form-group">
                <label class="admin-form-label">Current Password</label>
                <input type="password" 
                       name="current_password" 
                       class="admin-form-input"
                       placeholder="Enter current password">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">New Password</label>
                <input type="password" 
                       name="new_password" 
                       class="admin-form-input"
                       placeholder="Enter new password">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label">Confirm New Password</label>
                <input type="password" 
                       name="confirm_password" 
                       class="admin-form-input"
                       placeholder="Confirm new password">
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary">
                Update Password
            </button>
        </form>
    </div>
</div>

<script>
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
            document.getElementById('saveProfileBtn').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-submit form when Save Image button is clicked
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('saveProfileBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            const form = this.closest('form');
            if (form) {
                const fileInput = document.getElementById('profileImageInput');
                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    alert('Please select an image file first.');
                    return false;
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

