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
        
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $db->update('users', $data, 'id = ?', ['id' => $id]);
        logAudit('update', 'users', $id, null, $data);
        setFlashMessage('success', 'User updated successfully');
        redirect('modules/admin/users.php');
        
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
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

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5;
        --accent-bg: #eff6ff;
        --accent-lt: #dbeafe;
    }

    /* ── Page Wrapper ────────────────────────── */
    .user-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .user-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .user-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .user-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .user-header h1 em { font-style: italic; color: var(--accent); }

    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink); cursor: pointer;
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.28); color: white; }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 920px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
        display: flex; align-items: center; gap: 1.25rem;
        position: relative; overflow: hidden;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .stat-card::before {
        content: ''; position: absolute; right: 0; top: 0; bottom: 0;
        width: 4px; opacity: 0.8;
    }
    .stat-card.blue::before { background: var(--accent); }
    .stat-card.green::before { background: #10b981; }
    .stat-card.purple::before { background: #a855f7; }

    .s-icon {
        width: 48px; height: 48px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .ico-blue { background: var(--accent-bg); color: var(--accent); }
    .ico-green { background: #ecfdf5; color: #10b981; }
    .ico-purple { background: #f3e8ff; color: #a855f7; }

    .stat-content { flex: 1; }
    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700; text-align: center;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
    }

    /* ── Main Panel ──────────────────────────── */
    .user-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: var(--accent); border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-top: 0.2rem; }

    /* ── Table ───────────────────────────────── */
    .user-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .user-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .user-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .user-table thead th.th-r { text-align: right; }

    .user-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .user-table tbody tr:last-child { border-bottom: none; }
    .user-table tbody tr:hover { background: #fdfcfa; }

    .user-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .user-table td.td-r { text-align: right; }

    /* Avatar */
    .av-circ {
        width: 38px; height: 38px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }
    .av-blue { background: var(--accent); }
    .av-green { background: #10b981; }
    .av-purple { background: #a855f7; }
    .av-orange { background: #f59e0b; }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
    }
    .pill.red { background: #fef2f2; color: #b91c1c; }
    .pill.blue { background: var(--accent-bg); color: #1e40af; }
    .pill.purple { background: #f3e8ff; color: #7c3aed; }
    .pill.green { background: #ecfdf5; color: #065f46; }
    .pill.gray { background: #f0ece5; color: var(--ink-soft); }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.del:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

    /* ── Modals ──────────────────────────────── */
    .user-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .user-modal-backdrop.open { display: flex; }

    .user-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    .user-modal.sm { max-width: 420px; }
    .user-modal.md { max-width: 580px; }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); }  }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: var(--ink); margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-head h3 i { color: var(--accent); }
    .modal-head p { font-size: 0.75rem; color: var(--ink-mute); margin: 0.25rem 0 0; }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem; }

    .modal-footer {
        display: flex; justify-content: flex-end; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    /* Form fields */
    .field {
        margin-bottom: 1.1rem; position: relative;
    }
    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
    }
    .field input.with-icon, .field select.with-icon {
        padding-left: 2.5rem;
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field select.with-icon {
        background-position: right 0.8rem center, left 0.85rem center;
    }
    .field input:focus, .field select:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(42,88,181,0.1);
    }
    .field input:disabled {
        background: var(--cream); cursor: not-allowed;
    }

    .field-icon {
        position: absolute; left: 0.85rem; top: 2.15rem;
        color: var(--ink-mute); font-size: 0.8rem;
    }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }

    .info-box {
        background: var(--accent-bg); border: 1.5px solid var(--accent-lt);
        border-radius: 10px; padding: 1rem;
        display: flex; gap: 0.75rem; align-items: flex-start;
        color: #1e40af; font-size: 0.82rem; line-height: 1.5;
        margin-top: 1.25rem;
    }
    .info-box i { font-size: 1rem; margin-top: 0.1rem; flex-shrink: 0; }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary {
        background: var(--ink); color: white; border: 1.5px solid var(--ink);
    }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }
    .btn-danger { background: #ef4444; color: white; border: 1.5px solid #ef4444; }
    .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 14px rgba(239,68,68,0.3); }

    /* Delete modal special */
    .del-modal-body {
        padding: 2.5rem 1.6rem; text-align: center;
    }
    .del-icon {
        width: 64px; height: 64px; background: #fef2f2;
        border-radius: 50%; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 1.25rem;
    }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="user-wrap">

    <!-- Header -->
    <div class="user-header">
        <div>
            <div class="eyebrow">System Administration</div>
            <h1>User <em>Management</em></h1>
        </div>
        <button class="btn-new" onclick="openAdd()">
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="s-icon ico-blue"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $total_users ?></div>
            </div>
        </div>

        <div class="stat-card green">
            <div class="s-icon ico-green"><i class="fas fa-user-check"></i></div>
            <div class="stat-content">
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?= $active_users ?></div>
            </div>
        </div>

        <div class="stat-card purple">
            <div class="s-icon ico-purple"><i class="fas fa-user-shield"></i></div>
            <div class="stat-content">
                <div class="stat-label">Administrators</div>
                <div class="stat-value"><?= $admins ?></div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="user-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-users-cog"></i></div>
                <div>
                    <div class="toolbar-title">System Users</div>
                    <div class="toolbar-subtitle">Manage user access and roles</div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>User Profile</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="th-r">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $colors = ['av-blue', 'av-green', 'av-purple', 'av-orange'];
                    $idx = 0;
                    foreach ($users as $user): 
                        $initials = strtoupper(substr($user['username'], 0, 1));
                        if (!empty($user['full_name'])) {
                            $parts = explode(' ', $user['full_name']);
                            if (count($parts) > 1) {
                                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                            }
                        }
                        $color = $colors[$idx % 4];
                        $idx++;

                        $role_class = match($user['role']) {
                            'admin' => 'red',
                            'project_manager' => 'blue',
                            'accountant' => 'purple',
                            default => 'gray'
                        };
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center">
                                <div class="av-circ <?= $color ?>"><?= $initials ?></div>
                                <span style="font-weight:600"><?= htmlspecialchars($user['username']) ?></span>
                            </div>
                        </td>
                        <td><span style="color:var(--ink-soft);font-weight:500"><?= htmlspecialchars($user['full_name']) ?></span></td>
                        <td><span style="color:var(--ink-mute);font-size:0.82rem"><?= htmlspecialchars($user['email']) ?></span></td>
                        <td>
                            <span class="pill <?= $role_class ?>">
                                <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="pill <?= $user['status'] === 'active' ? 'green' : 'gray' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="color:var(--ink-mute);font-size:0.8rem">
                                <?= isset($user['last_login']) && $user['last_login'] ? date('M d, h:i A', strtotime($user['last_login'])) : 'Never' ?>
                            </span>
                        </td>
                        <td class="td-r">
                            <div class="act-group" style="justify-content:flex-end">
                                <button class="act-btn" onclick='editUser(<?= htmlspecialchars(json_encode($user)) ?>)' title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button class="act-btn del" onclick="openDel(<?= $user['id'] ?>)" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Add User Modal -->
<div class="user-modal-backdrop" id="addModal">
    <div class="user-modal md">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                    <p>Create functionality access for team members</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">×</button>
            </div>

            <div class="modal-body">
                <div class="field-row">
                    <div class="field">
                        <label>Username *</label>
                        <i class="fas fa-at field-icon"></i>
                        <input type="text" name="username" class="with-icon" required placeholder="john_doe">
                    </div>
                    <div class="field">
                        <label>Full Name *</label>
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" name="full_name" class="with-icon" required placeholder="John Doe">
                    </div>
                </div>

                <div class="field">
                    <label>Email Address</label>
                    <i class="fas fa-envelope field-icon"></i>
                    <input type="email" name="email" class="with-icon" placeholder="john.doe@company.com">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Role Assignment *</label>
                        <i class="fas fa-user-shield field-icon"></i>
                        <select name="role" class="with-icon" required>
                            <option value="">Select Role</option>
                            <option value="project_manager">Project Manager</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Password *</label>
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" name="password" class="with-icon" required minlength="6" placeholder="******">
                    </div>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p style="margin:0">New users will receive their login credentials via email if configured. Default status is <strong>Active</strong>.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="user-modal-backdrop" id="editModal">
    <div class="user-modal md">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">

            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>

            <div class="modal-body">
                <div class="field">
                    <label>Username</label>
                    <input type="text" id="editUsername" disabled>
                </div>

                <div class="field">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail">
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Role *</label>
                        <select name="role" id="editRole" required>
                            <option value="project_manager">Project Manager</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Status *</label>
                        <select name="status" id="editStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="field" style="padding-top:1rem;border-top:1.5px solid var(--border-lt)">
                    <label>Change Password (Optional)</label>
                    <input type="password" name="password" minlength="6" placeholder="Leave blank to keep current">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="user-modal-backdrop" id="delModal">
    <div class="user-modal sm">
        <div class="del-modal-body">
            <div class="del-icon">
                <i class="fas fa-exclamation-triangle" style="font-size:1.75rem;color:#ef4444"></i>
            </div>
            <h3 style="margin:0 0 0.75rem;color:var(--ink);font-weight:700">Confirm Deletion</h3>
            <p style="color:var(--ink-soft);margin:0 0 1.5rem;line-height:1.6;font-size:0.875rem">
                Are you sure you want to delete this user?<br>This action cannot be undone.
            </p>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delId">
                
                <div style="display:flex;gap:0.75rem;justify-content:center">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAdd() { document.getElementById('addModal').classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function editUser(user) {
    document.getElementById('editId').value = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editFullName').value = user.full_name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editStatus').value = user.status;
    document.getElementById('editModal').classList.add('open');
}

function openDel(id) {
    document.getElementById('delId').value = id;
    document.getElementById('delModal').classList.add('open');
}

document.querySelectorAll('.user-modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>