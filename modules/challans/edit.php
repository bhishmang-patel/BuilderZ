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
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setFlashMessage('error', 'Invalid Challan ID');
    redirect('modules/challans/material.php');
}

// Fetch existing data
$challan = $db->query("SELECT * FROM challans WHERE id = ? AND challan_type = 'material'", [$id])->fetch();
if (!$challan) {
    setFlashMessage('error', 'Challan not found');
    redirect('modules/challans/material.php');
}

// Fetch existing items
$items = $db->query("SELECT ci.*, m.material_name, m.unit 
                     FROM challan_items ci 
                     JOIN materials m ON ci.material_id = m.id 
                     WHERE ci.challan_id = ?", [$id])->fetchAll();

// Pre-process items for JS
$js_items = [];
foreach ($items as $item) {
    $js_items[] = [
        'material_id' => $item['material_id'],
        'material_name' => $item['material_name'],
        'unit' => $item['unit'],
        'quantity' => floatval($item['quantity']),
        'rate' => floatval($item['rate']),
        'total' => floatval($item['quantity']) * floatval($item['rate'])
    ];
}

$page_title = 'Edit Delivery Challan';
$current_page = 'material_challan';

// Fetch lists
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
$vendors = $db->query("SELECT id, name, mobile, email, address, gst_number FROM parties WHERE party_type = 'vendor' ORDER BY name")->fetchAll();
$materials = $db->query("SELECT id, material_name, unit, default_rate FROM materials ORDER BY material_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $vendor_id = $_POST['vendor_id'];
        $vendor_name = trim($_POST['vendor_name']);
        
        // Handle new vendor if typed manually
        if (empty($vendor_id)) {
            $existing_vendor = $db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='vendor'", [$vendor_name])->fetch();
            if ($existing_vendor) {
                $vendor_id = $existing_vendor['id'];
            } else {
                $vendor_data = [
                    'party_type' => 'vendor',
                    'name' => $vendor_name,
                    'mobile' => sanitize($_POST['mobile']),
                    'address' => sanitize($_POST['address']),
                    'gst_number' => sanitize($_POST['gst_number']),
                    'email' => sanitize($_POST['email'])
                ];
                $vendor_id = $db->insert('parties', $vendor_data);
            }
        } 

        $project_id = intval($_POST['project_id']);
        $challan_date = $_POST['challan_date'];
        $vehicle_no = sanitize($_POST['vehicle_no'] ?? '');
        $challan_no = sanitize($_POST['challan_no'] ?? '');
        
        if (empty($challan_no)) {
            throw new Exception("Challan Number is required");
        }

        // Duplicate check (exclude current ID)
        $dup = $db->query("SELECT id FROM challans WHERE challan_no = ? AND id != ?", [$challan_no, $id])->fetch();
        if ($dup) {
            throw new Exception("Challan Number '$challan_no' already used.");
        }

        $materials_list = json_decode($_POST['materials_json'], true);
        if (empty($materials_list)) {
            throw new Exception("Please add at least one material");
        }

        $total_amount = 0;
        foreach ($materials_list as $material) {
            $total_amount += floatval($material['quantity']) * floatval($material['rate']);
        }

        // 1. Revert Stock for OLD items
        foreach ($items as $old_item) {
            updateMaterialStock($old_item['material_id'], $old_item['quantity'], false); // Subtract/Revert logic? 
            // Wait, updateMaterialStock($id, $qty, $add=true). 
            // Originally when created, we did $add=true (stock += qty).
            // So to revert, we should do $add=false (stock -= qty).
            updateMaterialStock($old_item['material_id'], $old_item['quantity'], false);
        }

        // 2. Delete OLD items
        $db->delete('challan_items', 'challan_id = ?', [$id]);

        // 3. Update Challan
        $update_data = [
            'challan_no' => $challan_no,
            'party_id' => $vendor_id,
            'project_id' => $project_id,
            'challan_date' => $challan_date,
            'vehicle_no' => $vehicle_no,
            'total_amount' => $total_amount,
            // Keep status same unless logic dictates otherwise
        ];
        $db->update('challans', $update_data, 'id = ?', ['id' => $id]);

        // 4. Insert NEW items and Add Stock
        foreach ($materials_list as $item) {
            $material_id = isset($item['material_id']) && $item['material_id'] ? intval($item['material_id']) : null;
            $material_name = trim($item['material_name']);
            $unit = $item['unit'];
            $rate = floatval($item['rate']);
            
            if (!$material_id) {
                // New material logic
                $existing = $db->query("SELECT id FROM materials WHERE LOWER(material_name) = LOWER(?)", [$material_name])->fetch();
                if ($existing) {
                    $material_id = $existing['id'];
                } else {
                    $new_material_data = [
                        'material_name' => $material_name,
                        'unit' => $unit,
                        'default_rate' => $rate,
                        'current_stock' => 0
                    ];
                    $material_id = $db->insert('materials', $new_material_data);
                }
            }

            $item_data = [
                'challan_id' => $id,
                'material_id' => $material_id,
                'quantity' => floatval($item['quantity']),
                'rate' => $rate
            ];
            
            $db->insert('challan_items', $item_data);
            
            // Add to stock
            updateMaterialStock($material_id, $item['quantity'], true);
        }

        logAudit('update', 'challans', $id, $challan, $update_data);
        $db->commit();
        setFlashMessage('success', "Challan updated successfully");
        redirect('modules/challans/material.php');

    } catch (Exception $e) {
        $db->rollback();
        setFlashMessage('error', $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* Same CSS as Create Page */
.create-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; overflow: hidden; max-width: 1100px; margin: 0 auto; }
.create-header { background: linear-gradient(135deg, #0f172a 0%, #334155 100%); padding: 1.5rem 2rem; color: white; display: flex; justify-content: space-between; align-items: center; }
.create-title { font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 12px; }
.create-body { padding: 2.5rem; }
.section-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9; margin-top: 1rem; }
.section-title:first-child { margin-top: 0; }
.form-grid-modern { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
.input-group-modern label { display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem; }
.input-modern { width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; color: #0f172a; transition: all 0.2s; background: #f8fafc; height: 48px; }
.input-modern:focus { outline: none; border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.action-bar { background: #f8fafc; padding: 1.5rem 2.5rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 1rem; }
.btn-modern-primary { background: #0f172a; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-modern-secondary { background: white; color: #64748b; border: 1px solid #cbd5e1; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-modern-primary:hover { opacity: 0.9; }
.btn-modern-secondary:hover { background: #f1f5f9; color: #475569; }
.material-entry-box { background: #f1f5f9; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; }
.material-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 120px; gap: 1rem; align-items: end; }
.btn-add-material { background: #10b981; color: white; border: none; height: 48px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
.btn-add-material:hover { background: #059669; }
.modern-table-simple { width: 100%; border-collapse: collapse; }
.modern-table-simple th { text-align: left; padding: 1rem; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
.modern-table-simple td { padding: 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.autocomplete-wrapper { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 8px; z-index: 50; margin-top: 4px; max-height: 250px; overflow-y: auto; display: none; }
.autocomplete-list.show { display: block; }
.autocomplete-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f8fafc; }
.autocomplete-item:hover, .autocomplete-item.active { background: #f1f5f9; }
</style>

<div class="create-challan-container" style="padding-top: 0; padding-bottom: 4rem;">
    <form method="POST" id="challanForm" onsubmit="return validateForm()">
        <div class="create-card">
            
            <!-- Header -->
            <div class="create-header">
                <div>
                    <h1 class="create-title"><i class="fas fa-edit"></i> Edit Delivery Challan</h1>
                    <div style="font-size: 0.875rem; color: #cbd5e1; margin-top: 0.25rem;">Update challan details and materials</div>
                </div>
            </div>

            <div class="create-body">
                
                <!-- Section 1 -->
                <div class="section-title">Challan Information</div>
                <div class="form-grid-modern">
                    <div class="input-group-modern">
                        <label>Select Project *</label>
                        <select name="project_id" class="input-modern" required>
                            <option value="">Choose Project...</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= $project['id'] == $challan['project_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group-modern">
                        <label>Challan No *</label>
                        <input type="text" name="challan_no" class="input-modern" required value="<?= htmlspecialchars($challan['challan_no']) ?>">
                    </div>

                    <div class="input-group-modern">
                        <label>Challan Date *</label>
                        <input type="date" name="challan_date" class="input-modern" required value="<?= $challan['challan_date'] ?>">
                    </div>

                    <div class="input-group-modern">
                        <label>Vendor Name *</label>
                         <div class="autocomplete-wrapper">
                            <input type="text" name="vendor_name" id="vendor_name" class="input-modern" placeholder="Search vendor..." autocomplete="off" required>
                            <ul id="vendor_suggestions" class="autocomplete-list"></ul>
                        </div>
                        <input type="hidden" name="vendor_id" id="vendor_id" value="<?= $challan['party_id'] ?>">
                    </div>

                     <div class="input-group-modern">
                        <label>Vehicle No</label>
                        <input type="text" name="vehicle_no" class="input-modern" placeholder="e.g. MH-12-AB-1234" value="<?= htmlspecialchars($challan['vehicle_no']) ?>">
                    </div>

                    <div class="input-group-modern">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" id="vendor_mobile" class="input-modern" placeholder="Vendor mobile">
                    </div>

                     <div class="input-group-modern">
                        <label>GST Number</label>
                        <input type="text" name="gst_number" id="vendor_gst" class="input-modern" placeholder="Optional">
                    </div>
                </div>

                <div class="form-grid-modern" style="grid-template-columns: 1fr 2fr;">
                    <div class="input-group-modern">
                         <label>Email Address</label>
                        <input type="email" name="email" id="vendor_email" class="input-modern" placeholder="vendor@example.com">
                    </div>
                    <div class="input-group-modern">
                        <label>Vendor Address</label>
                        <input type="text" name="address" id="vendor_address" class="input-modern" placeholder="Full address">
                    </div>
                </div>

                <!-- Section 2: Materials -->
                <div class="section-title" style="margin-top: 2rem;">Material Items</div>
                
                <div class="material-entry-box">
                    <div class="material-grid">
                        <div class="input-group-modern">
                            <label>Material Name</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" id="material_name_input" class="input-modern" placeholder="Search material..." autocomplete="off">
                                <ul id="material_suggestions" class="autocomplete-list"></ul>
                            </div>
                            <input type="hidden" id="material_id_hidden">
                        </div>
                        <div class="input-group-modern">
                            <label>Unit</label>
                             <select id="material_unit" class="input-modern">
                                <option value="">Select</option>
                                <option value="kg">Kg</option>
                                <option value="ton">Ton</option>
                                <option value="bag">Bag</option>
                                <option value="cft">CFT</option>
                                <option value="sqft">Sqft</option>
                                <option value="nos">Nos</option>
                                <option value="ltr">Ltr</option>
                                <option value="brass">Brass</option>
                                <option value="bundle">Bundle</option>
                            </select>
                        </div>
                        <div class="input-group-modern">
                            <label>Quantity</label>
                            <input type="number" id="material_quantity" class="input-modern" placeholder="0.00" step="0.01">
                        </div>
                        <div class="input-group-modern">
                            <label>Rate (₹)</label>
                            <input type="number" id="material_rate" class="input-modern" placeholder="0.00" step="0.01">
                        </div>
                        <div>
                             <button type="button" class="btn-add-material" onclick="addMaterial()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table-simple">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Rate (₹)</th>
                                <th>Amount (₹)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="materials_tbody">
                            <tr class="empty-state-row">
                                <td colspan="6">
                                    <i class="fas fa-cubes" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                                    No items added. Add materials using the form above.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot style="background: #f8fafc; font-weight: 700;">
                            <tr>
                                <td colspan="4" style="text-align: right; border-top: 2px solid #e2e8f0;">TOTAL AMOUNT:</td>
                                <td style="color: #0f172a; font-size: 1.1rem; border-top: 2px solid #e2e8f0;" id="grand_total">₹ 0.00</td>
                                <td style="border-top: 2px solid #e2e8f0;"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                 <input type="hidden" name="materials_json" id="materials_json">

            </div> <!-- End Body -->

            <!-- Footer Actions -->
            <div class="action-bar">
                <a href="material.php" class="btn-modern-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-modern-primary">
                    <i class="fas fa-check"></i> Update Challan
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Toast Notification Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Reuse Create Page Scripts
function showToast(message, type = 'error', title = '') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    // Icons same as create.php
    const icons = {
        error: '<svg class="toast-icon" fill="currentColor" style="color: #dc3545;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
        success: '<svg class="toast-icon" fill="currentColor" style="color: #28a745;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        warning: '<svg class="toast-icon" fill="currentColor" style="color: #ffc107;" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
    };
    
    toast.innerHTML = `
        ${icons[type] || icons.error}
        <div class="toast-content">
            <div class="toast-title">${title || 'Notification'}</div>
            <p class="toast-message">${message}</p>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

const vendors = <?= json_encode($vendors) ?>;
const existingMaterials = <?= json_encode($materials) ?>;
let materialsData = <?= json_encode($js_items) ?>;

// Autocomplete and render functions same as create.php
function setupAutocomplete(inputId, listId, data, onSelect) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    let currentFocus = -1;

    function closeAllLists(elmnt) {
        if (elmnt !== input) {
            list.classList.remove('show');
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
            list.classList.remove('show');
            return;
        }
        
        matches.forEach(item => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            
            if (item.name) {
                li.innerHTML = `<strong>${item.name}</strong>`;
            } else {
                li.innerHTML = `<strong>${item.material_name}</strong> <span class='text-muted small ml-2'>(${item.unit})</span>`;
            }

            li.onclick = function() {
                onSelect(item);
                list.classList.remove('show');
            };
            list.appendChild(li);
        });
        list.classList.add('show');
    }

    function filterAndShow(val) {
        let matches = [];
        const lowerVal = val.toLowerCase();
        
        if (!val) {
            matches = data.slice(0, 15);
        } else {
            if (data[0] && data[0].name) {
                matches = data.filter(d => d.name.toLowerCase().includes(lowerVal));
            } else if (data[0]) {
                matches = data.filter(d => d.material_name.toLowerCase().includes(lowerVal));
            }
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
        if (!list.classList.contains('show')) return;
        
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

// Vendor Setup
setupAutocomplete('vendor_name', 'vendor_suggestions', vendors, function(vendor) {
    document.getElementById('vendor_name').value = vendor.name;
    document.getElementById('vendor_id').value = vendor.id;
    document.getElementById('vendor_mobile').value = vendor.mobile || '';
    document.getElementById('vendor_email').value = vendor.email || '';
    document.getElementById('vendor_address').value = vendor.address || '';
    document.getElementById('vendor_gst').value = vendor.gst_number || '';
});

// Pre-fill vendor info based on ID
const currentVendorId = document.getElementById('vendor_id').value;
if (currentVendorId) {
    const v = vendors.find(v => v.id == currentVendorId);
    if (v) {
        document.getElementById('vendor_name').value = v.name;
        document.getElementById('vendor_mobile').value = v.mobile || '';
        document.getElementById('vendor_email').value = v.email || '';
        document.getElementById('vendor_address').value = v.address || '';
        document.getElementById('vendor_gst').value = v.gst_number || '';
    }
}

document.getElementById('vendor_name').addEventListener('input', function() {
    document.getElementById('vendor_id').value = '';
});

// Material Setup
const materialNameInput = document.getElementById('material_name_input');
const materialIdInput = document.getElementById('material_id_hidden');
const unitSelect = document.getElementById('material_unit');
const rateInput = document.getElementById('material_rate');

setupAutocomplete('material_name_input', 'material_suggestions', existingMaterials, function(material) {
    materialNameInput.value = material.material_name;
    materialIdInput.value = material.id;
    unitSelect.value = material.unit;
    unitSelect.classList.add('readonly-select');
    if (!rateInput.value) rateInput.value = material.default_rate;
});

materialNameInput.addEventListener('input', function() {
    materialIdInput.value = '';
    unitSelect.classList.remove('readonly-select');
});

function addMaterial() {
    const name = materialNameInput.value.trim();
    const id = materialIdInput.value;
    const unit = unitSelect.value;
    const qty = parseFloat(document.getElementById('material_quantity').value);
    const rate = parseFloat(rateInput.value);
    
    if (!name || !unit || !qty || !rate) {
        showToast('Please fill all material fields', 'warning', 'Incomplete Information');
        return;
    }
    
    // Check duplicates in JS list
    // Note: Edit mode allows duplicates if intended (e.g. multiple bags of cement with different rates?). 
    // Usually valid to block duplicates to prevent confusion.
    if (materialsData.some(m => m.material_name.toLowerCase() === name.toLowerCase())) {
        showToast('This material has already been added', 'warning', 'Duplicate Material');
        return;
    }
    
    materialsData.push({
        material_id: id,
        material_name: name,
        unit: unit,
        quantity: qty,
        rate: rate,
        total: qty * rate
    });
    
    renderMaterials();
    
    materialNameInput.value = '';
    materialIdInput.value = '';
    unitSelect.value = '';
    unitSelect.classList.remove('readonly-select');
    document.getElementById('material_quantity').value = '';
    rateInput.value = '';
    materialNameInput.focus();
}

function renderMaterials() {
    const tbody = document.getElementById('materials_tbody');
    if (materialsData.length === 0) {
        tbody.innerHTML = `<tr>
            <td colspan="6" class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                <div style="font-size: 1.1rem;">No materials added yet</div>
            </td>
        </tr>`;
        document.getElementById('grand_total').textContent = '₹ 0.00';
        return;
    }
    
    let html = '';
    let grandTotal = 0;
    materialsData.forEach((m, idx) => {
        html += `<tr>
            <td style="font-weight: 600;">${m.material_name}</td>
            <td>${m.quantity}</td>
            <td><span class="badge badge-secondary">${m.unit.toUpperCase()}</span></td>
            <td>₹ ${m.rate.toFixed(2)}</td>
            <td style="font-weight: 600;">₹ ${m.total.toFixed(2)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeMaterial(${idx})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
        grandTotal += m.total;
    });
    tbody.innerHTML = html;
    document.getElementById('grand_total').textContent = '₹ ' + grandTotal.toFixed(2);
}

function removeMaterial(idx) {
    materialsData.splice(idx, 1);
    renderMaterials();
}

function validateForm() {
    if (materialsData.length === 0) {
        showToast('Please add at least one material item', 'error', 'No Materials Added');
        return false;
    }
    document.getElementById('materials_json').value = JSON.stringify(materialsData);
    return true;
}

// Initial render
renderMaterials();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
