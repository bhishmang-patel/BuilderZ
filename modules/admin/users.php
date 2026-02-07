<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin']);

$db = Database::getInstance();
$page_title = 'User Management';
$current_page = 'users';

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Security token expired. Please try again.');
        redirect('modules/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $data = [
            'username' => sanitize($_POST['username']),
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'full_name' => sanitize($_POST['full_name']),
            'email' => sanitize($_POST['email']),
            'role' => $_POST['role'],
            'status' => 'active'
        ];
        
        $id = $db->insert('users', $data);
        logAudit('create', 'users', $id, null, ['username' => $data['username']]);
        setFlashMessage('success', 'User created successfully');
        redirect('modules/admin/users.php');
        
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $data = [
            'full_name' => sanitize($_POST['full_name']),
            'email' => sanitize($_POST['email']),
            'role' => $_POST['role'],
            'status' => $_POST['status']
        ];
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $db->update('users', $data, 'id = ?', ['id' => $id]);
        logAudit('update', 'users', $id, null, $data);
        setFlashMessage('success', 'User updated successfully');
        redirect('modules/admin/users.php');
        
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Prevent deleting own account
        if ($id == $_SESSION['user_id']) {
            setFlashMessage('error', 'Cannot delete your own account');
        } else {
            $db->delete('users', 'id = ?', [$id]);
            logAudit('delete', 'users', $id);
            setFlashMessage('success', 'User deleted successfully');
        }
        redirect('modules/admin/users.php');
    }
}

// Fetch users
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Calculate Stats
$total_users = count($users);
$active_users = count(array_filter($users, function($u) { return $u['status'] === 'active'; }));
$admins = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">
<style>
/* Local overrides for consistency */
.avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    margin-right: 12px;
}
.role-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
<style>
/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card-modern {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}
.stat-card-modern::after {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    width: 6px;
    height: 100%;
}
.stat-card-modern.blue::after { background: #3b82f6; }
.stat-card-modern.green::after { background: #10b981; }
.stat-card-modern.purple::after { background: #a855f7; }

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}
.stat-icon.blue { background: #eff6ff; color: #3b82f6; }
.stat-icon.green { background: #ecfdf5; color: #10b981; }
.stat-icon.purple { background: #f3e8ff; color: #a855f7; }

.stat-info h4 { margin: 0; font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-info .value { font-size: 28px; font-weight: 800; color: #1e293b; margin-top: 4px; letter-spacing: -0.5px; }

/* Custom Modals */
.custom-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.custom-modal.active {
    display: flex;
}
.modal-content {
    background-color: #fff;
    padding: 0;
    border: none;
    width: 90%;
    max-width: 500px;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideIn 0.3s ease-out;
}
.modal-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; transition: color 0.2s; }
.close-btn:hover { color: #ef4444; }
.modal-body { padding: 24px; }
.modal-footer {
    padding: 20px 24px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-radius: 0 0 16px 16px;
}
@keyframes modalSlideIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Local overrides */
.avatar-circle {
    width: 38px;
    height: 38px;
    margin-right: 12px;
}
.av-green { background-color: #10b981 !important; color: white !important; }
.av-blue { background-color: #3b82f6 !important; color: white !important; }
.av-purple { background-color: #a855f7 !important; color: white !important; }
.av-orange { background-color: #f59e0b !important; color: white !important; }

.badge-pill.purple {
    background: #f3e8ff;
    color: #a855f7;
}

.modern-btn.gray {
    background: #e2e8f0;
    color: #475569;
}
.modern-btn.gray:hover {
    background: #cbd5e1;
    color: #1e293b;
}
</style>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="stat-card-modern blue">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h4>Total Users</h4>
            <div class="value"><?= $total_users ?></div>
        </div>
    </div>
    <div class="stat-card-modern green">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <h4>Active Users</h4>
            <div class="value"><?= $active_users ?></div>
        </div>
    </div>
    <div class="stat-card-modern purple">
        <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
        <div class="stat-info">
            <h4>Administrators</h4>
            <div class="value"><?= $admins ?></div>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="chart-card-custom" style="height: fit-content;">
    <div class="chart-header-custom" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div class="chart-icon-box blue" style="width:40px; height:40px;"><i class="fas fa-users-cog"></i></div>
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">System Users</h3>
                <div style="font-size:13px; color:#64748b; margin-top:2px;">Manage user access and roles</div>
            </div>
        </div>
        <button class="modern-btn blue small" onclick="openAddModal()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: auto; height: 44px; font-size: 14px; padding: 0 24px;">
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User Profile</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $colors = ['av-green', 'av-blue', 'av-purple', 'av-orange'];
                $idx = 0;
                foreach ($users as $user): 
                    // Generate avatar initials
                    $initials = strtoupper(substr($user['username'], 0, 1));
                    if (!empty($user['full_name'])) {
                        $parts = explode(' ', $user['full_name']);
                        if (count($parts) > 1) {
                            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                        }
                    }
                    $color = $colors[$idx % 4];
                    $idx++;
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <div class="avatar-circle <?= $color ?>"><?= $initials ?></div>
                            <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($user['username']) ?></div>
                        </div>
                    </td>
                    <td style="color:#64748b; font-weight:500;"><?= htmlspecialchars($user['full_name']) ?></td>
                    <td style="color:#64748b;"><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <?php
                            $role_class = match($user['role']) {
                                'admin' => 'badge-pill red',
                                'project_manager' => 'badge-pill blue',
                                'accountant' => 'badge-pill purple',
                                default => 'badge-pill gray'
                            };
                        ?>
                        <span class="<?= $role_class ?>">
                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-pill <?= $user['status'] === 'active' ? 'green' : 'gray' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </td>
                    <td style="color:#64748b; font-size:13px;">
                        <?= isset($user['last_login']) && $user['last_login'] ? date('M d, h:i A', strtotime($user['last_login'])) : 'Never' ?>
                    </td>
                    <td style="text-align:right;">
                        <button class="action-btn edit" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" title="Edit">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="action-btn delete" onclick="openDeleteModal(<?= $user['id'] ?>)" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="custom-modal">
    <div class="modal-content" style="width: 600px; padding: 0; border: none; overflow: hidden;">
        <div class="modal-header" style="background: #fff; padding: 24px 28px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="chart-icon-box blue" style="width: 40px; height: 40px; font-size: 16px;"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Add New User</h3>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #64748b;">Create functionality access for team members</p>
                </div>
            </div>
            <button class="close-btn" onclick="closeModalById('addUserModal')" style="font-size: 24px; color: #94a3b8; background: transparent; border: none; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="modal-body" style="padding: 28px;">
                <input type="hidden" name="action" value="create">
                
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Username *</label>
                            <div style="position: relative;">
                                <input type="text" name="username" class="modern-input" required placeholder="john_doe" style="padding-left: 38px;">
                                <i class="fas fa-at" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Full Name *</label>
                            <div style="position: relative;">
                                <input type="text" name="full_name" class="modern-input" required placeholder="John Doe" style="padding-left: 38px;">
                                <i class="fas fa-user" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Email Address</label>
                    <div style="position: relative;">
                        <input type="email" name="email" class="modern-input" placeholder="john.doe@company.com" style="padding-left: 38px;">
                        <i class="fas fa-envelope" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Role Assignment *</label>
                             <div style="position: relative;" >
                                <select name="role" class="modern-select" required style="padding-left: 38px;" >
                                    <option value="">Select Role</option>
                                    <option value="project_manager">Project Manager</option>
                                    <option value="accountant">Accountant</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <i class="fas fa-user-shield" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Password *</label>
                            <div style="position: relative;">
                                <input type="password" name="password" class="modern-input" required minlength="6" placeholder="******" style="padding-left: 38px;">
                                <i class="fas fa-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: #3b82f6; margin-top: 2px;"></i>
                    <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                        New users will receive their login credentials via email if configured. Default status is <strong>Active</strong>.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 28px; background: #fff; border-top: 1px solid #f1f5f9;">
                <button type="button" class="modern-btn gray" onclick="closeModalById('addUserModal')" style="background: white; border: 1px solid #e2e8f0;">Cancel</button>
                <button type="submit" class="modern-btn blue" style="min-width: 140px; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);">
                    <i class="fas fa-plus-circle" style="margin-right: 8px;"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="custom-modal">
    <div class="modal-content" style="width: 500px;">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="close-btn" onclick="closeModalById('editUserModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" id="edit_username" class="modern-input" disabled style="background:#f1f5f9; cursor:not-allowed;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" class="modern-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="modern-input">
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="modern-select" required>
                                <option value="project_manager">Project Manager</option>
                                <option value="accountant">Accountant</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Status *</label>
                            <select name="status" id="edit_status" class="modern-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="padding-top:15px; border-top:1px solid #f1f5f9;">
                    <label class="form-label" style="color:#64748b;">Change Password (Optional)</label>
                    <input type="password" name="password" class="modern-input" minlength="6" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modern-btn gray" onclick="closeModalById('editUserModal')">Cancel</button>
                <button type="submit" class="modern-btn blue">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="custom-modal">
    <div class="modal-content" style="width: 400px; text-align: center;">
        <div style="padding: 30px;">
            <div style="width: 60px; height: 60px; background: #fef2f2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 30px; color: #ef4444;"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; color: #1e293b; font-size: 20px; font-weight: 700;">Confirm Deletion</h3>
            <p style="color: #64748b; font-size: 14px; margin: 0 0 25px 0; line-height: 1.5;">
                Are you sure you want to delete this user?<br>This action cannot be undone.
            </p>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div style="display: flex; gap: 12px; justify-content: center; margin-top: 10px;">
                    <button type="button" class="modern-btn gray" style="padding: 10px 24px; min-width: 120px; border-radius: 8px; font-weight: 600;" onclick="closeModalById('deleteConfirmModal')">Cancel</button>
                    <button type="submit" class="modern-btn red" style="padding: 10px 24px; min-width: 120px; border-radius: 8px; font-weight: 600; background: #ef4444; color: white; border: none;">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addUserModal').classList.add('active');
}

function closeModalById(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('editUserModal').classList.add('active');
}

function openDeleteModal(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteConfirmModal').classList.add('active');
}


// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('custom-modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
