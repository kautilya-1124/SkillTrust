<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
validate_csrf_or_die();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    header('Location: ../login.php');
    exit;
}

$toastType = 'error';
$toastMsg = 'Invalid request.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toastType = 'error';
            $toastMsg = 'Please provide a valid name and email.';
        } else {
            $check = $conn->prepare('SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1');
            $exists = false;
            if ($check) {
                $check->bind_param('si', $email, $adminId);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();
            }
            if ($exists) {
                $toastType = 'error';
                $toastMsg = 'Email is already used by another admin.';
            } else {
                $upd = $conn->prepare('UPDATE admins SET name = ?, email = ? WHERE id = ? LIMIT 1');
                if ($upd) {
                    $upd->bind_param('ssi', $name, $email, $adminId);
                    $ok = $upd->execute();
                    $upd->close();
                    if ($ok) {
                        $_SESSION['admin_name'] = $name;
                        $toastType = 'success';
                        $toastMsg = 'Profile updated successfully.';
                    } else {
                        $toastType = 'error';
                        $toastMsg = 'Could not update profile.';
                    }
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $toastType = 'error';
            $toastMsg = 'Please fill all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $toastType = 'error';
            $toastMsg = 'New password and confirm password do not match.';
        } elseif (strlen($newPassword) < 6) {
            $toastType = 'error';
            $toastMsg = 'New password must be at least 6 characters.';
        } else {
            $stmt = $conn->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
            $row = null;
            if ($stmt) {
                $stmt->bind_param('i', $adminId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            if (!$row || !password_verify($currentPassword, (string) ($row['password'] ?? ''))) {
                $toastType = 'error';
                $toastMsg = 'Current password is incorrect.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $conn->prepare('UPDATE admins SET password = ? WHERE id = ? LIMIT 1');
                if ($upd) {
                    $upd->bind_param('si', $hash, $adminId);
                    $ok = $upd->execute();
                    $upd->close();
                    $toastType = $ok ? 'success' : 'error';
                    $toastMsg = $ok ? 'Password changed successfully.' : 'Could not update password.';
                }
            }
        }
    }
}

$qs = http_build_query(array_filter(['toast_type' => $toastType, 'toast_msg' => $toastMsg]));
header('Location: ../admin-profile.php' . ($qs !== '' ? '?' . $qs : ''));
exit;
