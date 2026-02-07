<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$page_title = 'Create Labour Pay';
$current_page = 'labour_pay';

// Fetch data
$labours = $db->query("SELECT id, name, mobile FROM parties WHERE party_type = 'labour' ORDER BY name")->fetchAll();
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/masters/labour_create.php');
    }

    try {
        $db->beginTransaction();

        $labour_id = intval($_POST['labour_id']);
        $labour_name = trim($_POST['labour_name']);
        
        // Create new labour if needed
        if (empty($labour_id)) {
            $existing_labour = $db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='labour'", [$labour_name])->fetch();
            
            if ($existing_labour) {
                $labour_id = $existing_labour['id'];
            } else {
                $labour_data = [
                    'party_type' => 'labour',
                    'name' => $labour_name,
                    'mobile' => sanitize($_POST['mobile']),

                ];
                $labour_id = $db->insert('parties', $labour_data);
            }
        }

        $project_id = intval($_POST['project_id']);
        $challan_date = $_POST['challan_date'];
        $work_description = sanitize($_POST['work_description']);
        $work_from_date = $_POST['work_from_date'];
        $work_to_date = $_POST['work_to_date'];
        $total_amount = floatval($_POST['total_amount']);
        
        $challan_no = generateChallanNo('labour', $db);
        
        $challan_data = [
            'challan_no' => $challan_no,
            'challan_type' => 'labour',
            'party_id' => $labour_id,
            'project_id' => $project_id,
            'challan_date' => $challan_date,
            'work_description' => $work_description,
            'work_from_date' => $work_from_date,
            'work_to_date' => $work_to_date,
            'total_amount' => $total_amount,
            'paid_amount' => 0,
            'pending_amount' => $total_amount,
            'status' => 'pending',
            'created_by' => $_SESSION['user_id']
        ];
        
        $challan_id = $db->insert('challans', $challan_data);
        
        logAudit('create', 'challans', $challan_id, null, $challan_data);
        $db->commit();
        
        setFlashMessage('success', "Labour pay $challan_no created successfully");
        redirect('modules/masters/labour.php');

    } catch (Exception $e) {
        $db->rollback();
        setFlashMessage('error', $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS for Modern Design -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<style>
/* Page-Specific Overrides for Professional Look */
.create-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    max-width: 1200px;
    margin: 2rem auto;
}

.create-header {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    padding: 1.5rem 2rem;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.create-title {
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.create-body {
    padding: 2rem;
}

.section-title {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 1px;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.form-grid-modern {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.input-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    position: relative; /* For autocomplete */
}

.input-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}

.input-modern {
    padding: 0.75rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    color: #1e293b;
    transition: all 0.2s;
    background: #f8fafc;
}

.input-modern:focus {
    border-color: #3b82f6;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

/* Autocomplete Styling Override */
.autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 50;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: none;
    margin-top: 4px;
}

.autocomplete-item {
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
    color: #334155;
}

.autocomplete-item:hover, .autocomplete-item.active {
    background: #f8fafc;
    color: #0f172a;
}

.action-bar {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.btn-modern-primary {
    background: #0f172a;
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-modern-primary:hover {
    background: #1e293b;
    transform: translateY(-1px);
}

.btn-modern-secondary {
    background: white;
    color: #64748b;
    border: 1px solid #cbd5e1;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-modern-secondary:hover {
    background: #f8fafc;
    color: #475569;
}
</style>

<div class="row">
    <div class="col-12">
        <form method="POST" id="labourForm" onsubmit="return validateForm()">
            <?= csrf_field() ?>
            <div class="create-card">
                <div class="create-header">
                    <h2 class="create-title">
                        <i class="fas fa-hard-hat"></i>
                        New Labour Pay
                    </h2>
                </div>
                
                <div class="create-body">
                    <!-- Project & Pay Details -->
                    <div class="section-title">Payment Details</div>
                    <div class="form-grid-modern">
                        <div class="input-group-modern">
                            <label class="input-label">Project *</label>
                            <select name="project_id" class="input-modern" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Pay Date *</label>
                            <input type="date" name="challan_date" class="input-modern" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Total Amount (₹) *</label>
                            <input type="number" name="total_amount" class="input-modern" placeholder="0.00" step="0.01" required>
                        </div>
                    </div>

                    <!-- Labour Information -->
                    <div class="section-title">Labour / Contractor Information</div>
                    <div class="form-grid-modern">
                        <div class="input-group-modern">
                            <label class="input-label">Labour/Contractor Name *</label>
                            <div style="position:relative;">
                                <input type="text" name="labour_name" id="labour_name" class="input-modern" style="width:100%;" placeholder="Search or enter name" autocomplete="off" required>
                                <ul id="labour_suggestions" class="autocomplete-list"></ul>
                            </div>
                            <input type="hidden" name="labour_id" id="labour_id">
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Mobile Number</label>
                            <input type="text" name="mobile" id="labour_mobile" class="input-modern" placeholder="Enter 10-digit mobile number" pattern="\d{10}" maxlength="10" minlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Please enter exactly 10 digits">
                        </div>

                    </div>

                    <!-- Work Details -->
                    <div class="section-title">Work Period & Description</div>
                    <div class="form-grid-modern">
                        <div class="input-group-modern">
                            <label class="input-label">Work From *</label>
                            <input type="date" name="work_from_date" class="input-modern" required>
                        </div>
                        <div class="input-group-modern">
                            <label class="input-label">Work To *</label>
                            <input type="date" name="work_to_date" class="input-modern" required>
                        </div>
                        <div class="input-group-modern">
                            <!-- Empty for grid alignment -->
                        </div>
                    </div>
                    
                    <div class="input-group-modern">
                        <label class="input-label">Work Description *</label>
                        <textarea name="work_description" class="input-modern" rows="3" placeholder="Describe the work completed..." required style="resize: vertical; min-height: 80px;"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-bar">
                        <a href="<?= BASE_URL ?>modules/masters/labour.php" class="btn-modern-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-modern-primary">
                            <i class="fas fa-check"></i> Save Labour Pay
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification Container -->
<div class="toast-container" id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script>
// Logic preserved but updated for new IDs if necessary
const labours = <?= json_encode($labours) ?>;

function setupAutocomplete(inputId, listId, data, onSelect) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    let currentFocus = -1;

    function closeAllLists(elmnt) {
        if (elmnt !== input) {
            list.style.display = 'none';
        }
    }

    function addActive(items) {
        if (!items || items.length === 0) return false;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        items[currentFocus].classList.add("active");
        items[currentFocus].scrollIntoView({ block: 'nearest' });
    }

    function removeActive(items) {
        for (let i = 0; i < items.length; i++) {
            items[i].classList.remove("active");
        }
    }

    function renderList(matches) {
        list.innerHTML = '';
        if (matches.length === 0) {
            list.style.display = 'none';
            return;
        }
        
        matches.forEach(item => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.innerHTML = `<strong>${item.name}</strong>`;

            li.onclick = function() {
                onSelect(item);
                list.style.display = 'none';
            };
            list.appendChild(li);
        });
        list.style.display = 'block';
    }

    function filterAndShow(val) {
        let matches = [];
        const lowerVal = val.toLowerCase();
        
        if (!val) {
            matches = data.slice(0, 15);
        } else {
            matches = data.filter(d => d.name.toLowerCase().includes(lowerVal));
        }
        renderList(matches);
        currentFocus = -1;
    }

    input.addEventListener('input', function() {
        filterAndShow(this.value);
    });

    input.addEventListener('focus', function() {
        filterAndShow(this.value);
    });

    input.addEventListener('keydown', function(e) {
        let items = list.getElementsByClassName('autocomplete-item');
        if (list.style.display === 'none') return;
        
        if (e.keyCode == 40) {
            currentFocus++;
            addActive(items);
            e.preventDefault();
        } else if (e.keyCode == 38) {
            currentFocus--;
            addActive(items);
            e.preventDefault();
        } else if (e.keyCode == 13) {
            e.preventDefault();
            if (currentFocus > -1 && items[currentFocus]) {
                items[currentFocus].click();
            } else if (items.length === 1) {
                items[0].click();
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input) {
            closeAllLists(e.target);
        }
    });
}

setupAutocomplete('labour_name', 'labour_suggestions', labours, function(labour) {
    document.getElementById('labour_name').value = labour.name;
    document.getElementById('labour_id').value = labour.id;
    document.getElementById('labour_mobile').value = labour.mobile || '';

});

document.getElementById('labour_name').addEventListener('input', function() {
    document.getElementById('labour_id').value = '';
});

function validateForm() {
    return true;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
