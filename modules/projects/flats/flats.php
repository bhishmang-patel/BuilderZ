<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/MasterService.php';
require_once __DIR__ . '/../../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAuth();
checkPermission(['admin', 'project_manager']);

$db            = Database::getInstance();
$page_title    = 'Flats';
$current_page  = 'flats';
$masterService = new MasterService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die('CSRF Token verification failed');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $flatNo = sanitize($_POST['flat_no']);
            if (!empty($_POST['flat_prefix_single'])) $flatNo = sanitize($_POST['flat_prefix_single']) . '-' . $flatNo;
            $data = [
                'project_id'   => intval($_POST['project_id']),
                'flat_no'      => $flatNo,
                'floor'        => intval($_POST['floor']),
                'unit_type'    => $_POST['unit_type'] ?? 'flat',
                'bhk'          => $_POST['bhk'],
                'rera_area'    => floatval($_POST['rera_area']),
                'sellable_area'=> floatval($_POST['sellable_area']),
                'usable_area'  => floatval($_POST['usable_area']),
                'area_sqft'    => floatval($_POST['sellable_area']),
                'total_value'  => floatval($_POST['flat_value']),
                'rate_per_sqft'=> (floatval($_POST['sellable_area']) > 0) ? (floatval($_POST['flat_value']) / floatval($_POST['sellable_area'])) : 0,
                'status'       => $_POST['status'],
            ];
            $masterService->createFlat($data);
            setFlashMessage('success', 'Unit created successfully');
        } elseif ($action === 'bulk_create') {
            $projectId  = intval($_POST['project_id']);
            $floorCount = intval($_POST['floor_count']);
            $prefix     = sanitize($_POST['flat_prefix']);
            $flatMix    = json_decode($_POST['flat_mix'], true);
            if (!$projectId || !$floorCount || empty($flatMix)) throw new Exception('Invalid bulk configuration');
            $proj = $masterService->getProjectById($projectId);
            if (!$proj) throw new Exception('Project not found');
            if (!$proj['has_multiple_towers']) $prefix = '';
            if ($floorCount >= 2) {
                if (empty($flatMix['typical_floor']['units'])) throw new Exception("Typical floor configuration is required for projects with 2+ floors.");
                foreach ($flatMix['typical_floor']['units'] as $u) if ($u['unit_type'] !== 'flat') throw new Exception("Typical floors create FLATS only.");
            }
            $db->beginTransaction();
            $count = 0;
            try {
                $createUnit = function($floor, $unitData, $index) use ($masterService, $projectId, $prefix) {
                    $sellableArea = floatval($unitData['sellable_area']);
                    $flatValue    = floatval($unitData['flat_value']);
                    $bhk          = $unitData['bhk'] ?? '';
                    $unitType     = $unitData['unit_type'];
                    if ($sellableArea <= 0) throw new Exception("Sellable Area must be > 0 (Row $index)");
                    if ($flatValue <= 0)    throw new Exception("Flat Value must be > 0 (Row $index)");
                    if (!empty($unitData['usable_area']) && $unitData['usable_area'] > $sellableArea) throw new Exception("Usable area cannot exceed sellable area (Row $index)");
                    if ($unitType === 'flat' && empty($bhk)) throw new Exception("BHK is required for Flats (Floor $floor, Row $index)");
                    if ($unitType === 'shop' && !empty($bhk)) throw new Exception("Shops cannot have a BHK value (Floor $floor, Row $index)");
                    $rate   = $flatValue / $sellableArea;
                    $seqStr = str_pad($index, 2, '0', STR_PAD_LEFT);
                    $flatNo = $prefix . $floor . $seqStr;
                    $masterService->createFlat(['project_id' => $projectId, 'flat_no' => $flatNo, 'floor' => $floor, 'unit_type' => $unitType, 'bhk' => ($unitType === 'shop') ? null : $bhk, 'rera_area' => floatval($unitData['rera_area']), 'sellable_area' => $sellableArea, 'usable_area' => floatval($unitData['usable_area']), 'area_sqft' => $sellableArea, 'total_value' => $flatValue, 'rate_per_sqft' => $rate, 'status' => 'available']);
                };
                if (!empty($flatMix['ground_floor']))    foreach ($flatMix['ground_floor'] as $idx => $unit) { $createUnit(0, $unit, $idx+1); $count++; }
                if (!empty($flatMix['first_floor']))     foreach ($flatMix['first_floor']  as $idx => $unit) { $createUnit(1, $unit, $idx+1); $count++; }
                if (!empty($flatMix['typical_floor']['units']) && $floorCount >= 2) {
                    $startFloor = max(2, intval($flatMix['typical_floor']['start_floor']));
                    for ($f = $startFloor; $f <= $floorCount - 1; $f++) foreach ($flatMix['typical_floor']['units'] as $idx => $unit) { $createUnit($f, $unit, $idx+1); $count++; }
                }
                $db->commit();
                setFlashMessage('success', "$count units created successfully");
            } catch (Exception $ex) { $db->rollBack(); throw $ex; }
        } elseif ($action === 'update') {
            $data = ['flat_no' => sanitize($_POST['flat_no']), 'floor' => intval($_POST['floor']), 'unit_type' => $_POST['unit_type'], 'bhk' => $_POST['bhk'], 'rera_area' => floatval($_POST['rera_area']), 'sellable_area' => floatval($_POST['sellable_area']), 'usable_area' => floatval($_POST['usable_area']), 'area_sqft' => floatval($_POST['sellable_area']), 'total_value' => floatval($_POST['flat_value']), 'rate_per_sqft' => (floatval($_POST['sellable_area']) > 0) ? (floatval($_POST['flat_value']) / floatval($_POST['sellable_area'])) : 0, 'status' => $_POST['status']];
            $masterService->updateFlat(intval($_POST['id']), $data);
            setFlashMessage('success', 'Unit updated successfully');
        } elseif ($action === 'delete') {
            $masterService->deleteFlat(intval($_POST['id']));
            setFlashMessage('success', 'Flat deleted successfully');
        } elseif ($action === 'bulk_delete') {
            $ids   = json_decode($_POST['ids'], true);
            $count = $masterService->bulkDeleteFlats($ids);
            setFlashMessage('success', "$count flats deleted successfully");
        }
    } catch (Exception $e) { setFlashMessage('error', $e->getMessage()); }
    redirect('modules/projects/flats/flats.php');
}

$filters  = ['project_id' => $_GET['project'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''];
$flats    = $masterService->getAllFlats($filters);
$projects = $masterService->getAllProjects();

$detailedStats = $masterService->getDetailedStats();
$counts = ['flat' => ['total'=>0,'available'=>0], 'shop' => ['total'=>0,'available'=>0], 'office' => ['total'=>0,'available'=>0], 'all' => ['total'=>0,'available'=>0]];
foreach ($detailedStats as $row) {
    $type  = strtolower($row['unit_type'] ?? 'flat');
    if (!isset($counts[$type])) $type = 'flat';
    $cnt   = intval($row['count']);
    $counts[$type]['total'] += $cnt;
    $counts['all']['total'] += $cnt;
    if ($row['status'] === 'available') { $counts[$type]['available'] += $cnt; $counts['all']['available'] += $cnt; }
}
$has_filters = $filters['project_id'] || $filters['status'] || $filters['search'];

include __DIR__ . '/../../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --ink:        #1a1714; --ink-soft:  #6b6560; --ink-mute:  #9e9690;
    --cream:      #f5f3ef; --surface:   #ffffff; --border:    #e8e3db; --border-lt: #f0ece5;
    --accent:     #2a58b5; --accent-lt: #eff4ff; --accent-md: #c7d9f9; --accent-bg: #f0f5ff; --accent-dk: #1e429f;
    --green:      #059669; --green-lt:  #d1fae5;
    --orange:     #d97706; --orange-lt: #fef3c7;
    --red:        #dc2626; --red-lt:    #fee2e2;
    --purple:     #7c3aed; --purple-lt: #ede9fe;
}
body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }
.pw  { max-width: 1380px; margin: 2.5rem auto; padding: 0 1.5rem 5rem; }

@keyframes hdrIn  { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeUp { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
@keyframes rowIn  { from { opacity:0; transform:translateX(-7px);  } to { opacity:1; transform:translateX(0); } }

/* ── Header ───────────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    margin-bottom: 2.25rem; padding-bottom: 1.5rem; border-bottom: 1.5px solid var(--border);
    opacity: 0; animation: hdrIn 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}
.eyebrow { font-size:0.67rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:var(--accent); margin-bottom:0.28rem; }
.page-header h1 { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; color:var(--ink); margin:0; line-height:1.1; }
.page-header h1 em { font-style:italic; color:var(--accent); }
.hdr-btns { display:flex; gap:0.6rem; flex-wrap:wrap; }
.btn-new {
    display:inline-flex; align-items:center; gap:0.45rem;
    padding:0.6rem 1.3rem; background:var(--ink); color:white; border:1.5px solid var(--ink);
    border-radius:8px; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:0.875rem; font-weight:600;
    text-decoration:none; transition:all 0.18s;
}
.btn-new:hover { background:var(--accent); border-color:var(--accent); transform:translateY(-1px); box-shadow:0 4px 14px rgba(42,88,181,.3); color:white; text-decoration:none; }
.btn-new.sec { background:white; color:var(--ink-soft); border-color:var(--border); }
.btn-new.sec:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); box-shadow:none; transform:none; }
.btn-new.danger { background:var(--red-lt); color:var(--red); border-color:#fca5a5; }
.btn-new.danger:hover { background:var(--red); color:white; border-color:var(--red); transform:none; box-shadow:none; }

/* ── Stats ────────────────────────── */
.stat-row {
    display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; margin-bottom:1.5rem;
}
@media(max-width:1100px){ .stat-row{grid-template-columns:repeat(3,1fr);} }
@media(max-width:680px) { .stat-row{grid-template-columns:repeat(2,1fr);} }

.stat-card {
    background:var(--surface); border:1.5px solid var(--border); border-radius:12px;
    padding:1.1rem 1.25rem; display:flex; align-items:center; gap:0.9rem;
    box-shadow:0 1px 4px rgba(26,23,20,.04); transition:transform .2s,box-shadow .2s;
    opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) both;
}
.stat-card.s1{animation-delay:.07s} .stat-card.s2{animation-delay:.11s} .stat-card.s3{animation-delay:.15s}
.stat-card.s4{animation-delay:.19s} .stat-card.s5{animation-delay:.23s}
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(26,23,20,.08); }
.sc-ic { width:38px; height:38px; border-radius:9px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:0.85rem; }
.sc-ic.green  { background:var(--green-lt);  color:var(--green); }
.sc-ic.blue   { background:var(--accent-lt); color:var(--accent); }
.sc-ic.purple { background:var(--purple-lt); color:var(--purple); }
.sc-ic.orange { background:var(--orange-lt); color:var(--orange); }
.sc-ic.gray   { background:var(--cream);     color:var(--ink-mute); border:1px solid var(--border); }
.sc-lbl { font-size:0.63rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--ink-mute); margin-bottom:0.22rem; }
.sc-val { font-family:'Fraunces',serif; font-size:1.4rem; font-weight:700; color:var(--ink); line-height:1; }
.sc-frac { font-family:'DM Sans',sans-serif; font-size:0.8rem; font-weight:500; color:var(--ink-mute); margin-left:2px; }

/* ── Panel ────────────────────────── */
.panel { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden; opacity:0; animation:fadeUp .42s cubic-bezier(.22,1,.36,1) .28s both; }
.panel-toolbar { display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; padding:1.05rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff; }
.pt-icon { width:30px; height:30px; background:var(--accent-lt); color:var(--accent); border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.75rem; flex-shrink:0; }
.pt-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); flex:1; display:flex; align-items:center; gap:0.5rem; }
.count-tag { font-size:.62rem; font-weight:800; padding:.15rem .55rem; border-radius:20px; background:var(--cream); color:var(--ink-mute); border:1px solid var(--border); font-family:'DM Sans',sans-serif; }

/* ── Filter bar ───────────────────── */
.filter-bar { display:none; padding:1rem 1.5rem; gap:0.65rem; flex-wrap:wrap; align-items:flex-end; border-bottom:1.5px solid var(--border-lt); background:#fdfcfa; }
.filter-bar.show { display:flex; }
.fi-grp { display:flex; flex-direction:column; gap:.28rem; }
.fi-grp label { font-size:.62rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.fi { height:38px; padding:0 .8rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.82rem; color:var(--ink); background:white; outline:none; transition:border-color .15s,box-shadow .15s; -webkit-appearance:none; appearance:none; }
.fi:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.1); }
.fi-sel { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .65rem center; padding-right:2rem; min-width:150px; }
.fi-search { min-width:200px; }
.btn-apply { height:38px; padding:0 1.1rem; background:var(--ink); color:white; border:none; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.38rem; transition:background .18s; }
.btn-apply:hover { background:var(--accent); }
.btn-clr { height:38px; padding:0 1rem; background:var(--red-lt); color:var(--red); border:none; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.38rem; text-decoration:none; }
.btn-clr:hover { background:#fecaca; text-decoration:none; }
.btn-sm-filter { display:inline-flex; align-items:center; gap:.38rem; padding:.5rem .9rem; border:1.5px solid var(--border); background:white; color:var(--ink-soft); border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .18s; }
.btn-sm-filter:hover,.btn-sm-filter.active { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.filter-dot { display:none; width:6px; height:6px; border-radius:50%; background:var(--accent); }
.btn-sm-filter.active .filter-dot { display:inline-block; }

/* ── Flats table ──────────────────── */
.flat-table { width:100%; border-collapse:collapse; font-size:.855rem; }
.flat-table thead tr { background:#eef2fb; border-bottom:1.5px solid var(--border); }
.flat-table thead th { padding:.65rem 1rem; font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft); text-align:left; white-space:nowrap; }
.flat-table thead th.al-c { text-align:center; }
.flat-table thead th.al-r { text-align:right; }
.flat-table tbody tr { border-bottom:1px solid var(--border-lt); transition:background .12s; }
.flat-table tbody tr:last-child { border-bottom:none; }
.flat-table tbody tr:hover { background:#f4f7fd; }
.flat-table tbody tr.row-in { animation:rowIn .24s cubic-bezier(.22,1,.36,1) forwards; }
.flat-table td { padding:.75rem 1rem; vertical-align:middle; }
.flat-table td.al-c { text-align:center; }
.flat-table td.al-r { text-align:right; }

/* checkbox / lock */
.chk { accent-color:var(--accent); width:15px; height:15px; cursor:pointer; }
.lock-ic { color:var(--border); font-size:.72rem; }

/* flat no pill */
.flat-pill { display:inline-block; padding:.2rem .65rem; border-radius:20px; font-size:.7rem; font-weight:800; letter-spacing:.04em; background:var(--accent-lt); color:var(--accent-dk); border:1px solid var(--accent-md); }

/* floor */
.floor-val { font-size:.82rem; font-weight:700; color:var(--ink-soft); }

/* BHK / type */
.bhk-pill { display:inline-block; padding:.2rem .65rem; border-radius:20px; font-size:.68rem; font-weight:700; background:var(--purple-lt); color:var(--purple); border:1px solid #c4b5fd; }

/* area / rate / amount */
.num-cell { font-family:'Fraunces',serif; font-weight:700; color:var(--ink-soft); font-size:.88rem; }
.num-cell.green { color:var(--green); }
.num-cell.muted { color:var(--ink-mute); }
.rate-est { font-family:'Courier New',monospace; font-size:.75rem; color:var(--ink-mute); }

/* status */
.status-pill { display:inline-flex; align-items:center; gap:.28rem; padding:.2rem .65rem; border-radius:20px; font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
.status-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.status-pill.available { background:var(--green-lt);  color:var(--green); border:1px solid #6ee7b7; }
.status-pill.booked    { background:var(--orange-lt); color:var(--orange); border:1px solid #fcd34d; }
.status-pill.sold      { background:var(--accent-lt); color:var(--accent); border:1px solid var(--accent-md); }

/* action btns */
.act-grp { display:flex; gap:.35rem; justify-content:flex-end; }
.act-btn { width:28px; height:28px; border-radius:6px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; cursor:pointer; transition:all .15s; }
.act-btn:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.act-btn.del:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); }

/* empty */
.empty-state { text-align:center; padding:4rem 1.5rem; }
.empty-state .es-icon { font-size:2.5rem; display:block; margin-bottom:.75rem; color:var(--accent); opacity:.18; }
.empty-state h4 { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink-soft); margin:0 0 .35rem; }
.empty-state p  { font-size:.82rem; color:var(--ink-mute); margin:0; }

/* ── Modal system ─────────────────── */
.m-backdrop { display:none; position:fixed; inset:0; z-index:10000; background:rgba(26,23,20,.45); backdrop-filter:blur(3px); align-items:center; justify-content:center; padding:1rem; }
.m-backdrop.open { display:flex; }
.m-box { background:white; border-radius:14px; overflow:hidden; width:100%; box-shadow:0 24px 48px rgba(26,23,20,.18); animation:mIn .28s cubic-bezier(.22,1,.36,1); max-height:92vh; display:flex; flex-direction:column; }
.m-box.sm { max-width:420px; }
.m-box.md { max-width:620px; }
.m-box.lg { max-width:900px; }
@keyframes mIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }

.m-head { display:flex; align-items:center; justify-content:space-between; padding:1.1rem 1.5rem; border-bottom:1.5px solid var(--border-lt); background:#fafbff; flex-shrink:0; }
.m-head-l { display:flex; align-items:center; gap:.6rem; }
.m-hic { width:28px; height:28px; border-radius:7px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.72rem; }
.m-hic.blue   { background:var(--accent-lt); color:var(--accent); }
.m-hic.green  { background:var(--green-lt);  color:var(--green); }
.m-hic.orange { background:var(--orange-lt); color:var(--orange); }
.m-hic.purple { background:var(--purple-lt); color:var(--purple); }
.m-head h3 { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin:0; }
.m-head p  { font-size:.72rem; color:var(--ink-mute); margin:2px 0 0; }
.m-close { width:26px; height:26px; border-radius:5px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.85rem; transition:all .15s; }
.m-close:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); }
.m-body { padding:1.4rem 1.5rem; overflow-y:auto; flex:1; }
.m-foot { display:flex; justify-content:flex-end; gap:.65rem; padding:1rem 1.5rem; border-top:1.5px solid var(--border-lt); background:#fafbff; flex-shrink:0; }

/* section label in modal */
.m-sec { font-size:.62rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-mute); margin:1.1rem 0 .65rem; padding-bottom:.35rem; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; gap:.35rem; }
.m-sec:first-child { margin-top:0; }

/* fields */
.mf { display:flex; flex-direction:column; gap:.28rem; margin-bottom:.9rem; }
.mf label { font-size:.63rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-mute); }
.mf label .req { color:var(--red); margin-left:2px; }
.mf input,.mf select,.mf textarea { width:100%; height:40px; padding:0 .85rem; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:.875rem; color:var(--ink); background:#fdfcfa; outline:none; transition:border-color .18s,box-shadow .18s; -webkit-appearance:none; appearance:none; }
.mf select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .85rem center; padding-right:2.2rem; }
.mf input:focus,.mf select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(42,88,181,.11); background:white; }
.mf input[readonly] { background:var(--cream); color:var(--ink-mute); cursor:not-allowed; }
.mf-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.mf-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.8rem; }
@media(max-width:500px) { .mf-row-2,.mf-row-3{grid-template-columns:1fr;} }

/* modal buttons */
.btn-modal { display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1.3rem; border-radius:7px; font-family:'DM Sans',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; border:1.5px solid transparent; transition:all .18s; }
.btn-modal-ghost  { background:white; border-color:var(--border); color:var(--ink-soft); }
.btn-modal-ghost:hover  { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.btn-modal-submit { background:var(--ink); color:white; border-color:var(--ink); }
.btn-modal-submit:hover { background:var(--accent); border-color:var(--accent); transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,88,181,.3); }
.btn-modal-danger { background:var(--red); color:white; border-color:var(--red); }
.btn-modal-danger:hover { background:#b91c1c; }
.btn-modal-sec { background:white; color:var(--ink-soft); border-color:var(--border); }
.btn-modal-sec:hover { background:var(--cream); }

/* delete modal center */
.del-inner { padding:2rem 1.5rem; text-align:center; }
.del-icon { width:56px; height:56px; border-radius:50%; background:var(--red-lt); display:flex; align-items:center; justify-content:center; margin:0 auto 1.1rem; font-size:1.25rem; color:var(--red); }

/* tabs for bulk create */
.m-tabs { display:flex; gap:0; border-bottom:1.5px solid var(--border-lt); margin:0 -1.5rem; padding:0 1.5rem; background:#fafbff; }
.m-tab { padding:.65rem 1.2rem; font-size:.78rem; font-weight:700; cursor:pointer; border:none; background:none; color:var(--ink-mute); border-bottom:2.5px solid transparent; transition:all .18s; margin-bottom:-1.5px; }
.m-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.m-tab-panel { display:none; padding-top:1.25rem; }
.m-tab-panel.active { display:block; }

/* info box */
.info-box { display:flex; align-items:center; gap:.5rem; padding:.65rem 1rem; background:var(--accent-lt); border:1px solid var(--accent-md); border-radius:8px; font-size:.8rem; color:var(--accent); margin-bottom:1rem; }

/* mix table */
.mix-table { width:100%; border-collapse:collapse; font-size:.8rem; }
.mix-table th { padding:.5rem .65rem; font-size:.6rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-soft); text-align:left; border-bottom:1.5px solid var(--border-lt); background:#eef2fb; }
.mix-table td { padding:.4rem .5rem; border-bottom:1px solid var(--border-lt); vertical-align:middle; }
.mix-table tr:last-child td { border-bottom:none; }
.mix-table input,.mix-table select { height:34px; padding:0 .6rem; border:1.5px solid var(--border); border-radius:6px; font-size:.78rem; font-family:'DM Sans',sans-serif; color:var(--ink); background:#fdfcfa; width:100%; outline:none; -webkit-appearance:none; }
.mix-table input:focus,.mix-table select:focus { border-color:var(--accent); }
.mix-table input[readonly] { background:var(--cream); }
.mix-table select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .5rem center; padding-right:1.6rem; }
.btn-add-row { display:inline-flex; align-items:center; gap:.35rem; margin-top:.75rem; padding:.45rem 1rem; border:1.5px dashed var(--border); border-radius:7px; font-size:.78rem; font-weight:600; color:var(--ink-soft); background:white; cursor:pointer; transition:all .18s; }
.btn-add-row:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-bg); }
.btn-rm-row { width:26px; height:26px; border-radius:5px; border:1.5px solid var(--border); background:white; color:var(--ink-mute); display:flex; align-items:center; justify-content:center; font-size:.6rem; cursor:pointer; transition:all .15s; }
.btn-rm-row:hover { border-color:var(--red); color:var(--red); background:var(--red-lt); }
</style>

<div class="pw">

    <!-- ── Header ──────────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Projects &rsaquo; Inventory</div>
            <h1>Unit <em>Registry</em></h1>
        </div>
        <div class="hdr-btns">
            <button class="btn-new sec" onclick="fShowM('bulkModal')">
                <i class="fas fa-layer-group"></i> Bulk Create
            </button>
            <button class="btn-new" onclick="fShowM('addModal')">
                <i class="fas fa-plus"></i> Add Unit
            </button>
        </div>
    </div>

    <!-- ── Stats ───────────────────── -->
    <div class="stat-row">
        <div class="stat-card s1">
            <div class="sc-ic green"><i class="fas fa-building"></i></div>
            <div>
                <div class="sc-lbl">Flats</div>
                <div class="sc-val"><?= $counts['flat']['available'] ?><span class="sc-frac"> / <?= $counts['flat']['total'] ?></span></div>
            </div>
        </div>
        <div class="stat-card s2">
            <div class="sc-ic blue"><i class="fas fa-store"></i></div>
            <div>
                <div class="sc-lbl">Shops</div>
                <div class="sc-val"><?= $counts['shop']['available'] ?><span class="sc-frac"> / <?= $counts['shop']['total'] ?></span></div>
            </div>
        </div>
        <div class="stat-card s3">
            <div class="sc-ic purple"><i class="fas fa-briefcase"></i></div>
            <div>
                <div class="sc-lbl">Offices</div>
                <div class="sc-val"><?= $counts['office']['available'] ?><span class="sc-frac"> / <?= $counts['office']['total'] ?></span></div>
            </div>
        </div>
        <div class="stat-card s4">
            <div class="sc-ic orange"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="sc-lbl">Available</div>
                <div class="sc-val"><?= $counts['all']['available'] ?></div>
            </div>
        </div>
        <div class="stat-card s5">
            <div class="sc-ic gray"><i class="fas fa-layer-group"></i></div>
            <div>
                <div class="sc-lbl">Total Units</div>
                <div class="sc-val"><?= $counts['all']['total'] ?></div>
            </div>
        </div>
    </div>

    <!-- ── Panel ───────────────────── -->
    <div class="panel">
        <div class="panel-toolbar">
            <div class="pt-icon"><i class="fas fa-layer-group"></i></div>
            <div class="pt-title">
                All Units
                <span class="count-tag"><?= count($flats) ?></span>
            </div>
            <button id="bulkDelBtn" class="btn-new danger" onclick="fConfirmBulkDel()" style="display:none; padding:.45rem 1rem; font-size:.78rem;">
                <i class="fas fa-trash-alt"></i> Delete (<span id="selCount">0</span>)
            </button>
            <button class="btn-sm-filter <?= $has_filters ? 'active' : '' ?>" onclick="toggleFilters()" id="filterBtn">
                <span class="filter-dot"></span>
                <i class="fas fa-filter"></i> Filters
            </button>
        </div>

        <!-- Filter bar -->
        <form method="GET" id="filterForm">
            <div class="filter-bar <?= $has_filters ? 'show' : '' ?>" id="filterBar">
                <div class="fi-grp">
                    <label>Project</label>
                    <select name="project" class="fi fi-sel">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filters['project_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fi-grp">
                    <label>Status</label>
                    <select name="status" class="fi fi-sel">
                        <option value="">All Status</option>
                        <option value="available" <?= $filters['status']==='available' ? 'selected':'' ?>>Available</option>
                        <option value="booked"    <?= $filters['status']==='booked'    ? 'selected':'' ?>>Booked</option>
                        <option value="sold"      <?= $filters['status']==='sold'      ? 'selected':'' ?>>Sold</option>
                    </select>
                </div>
                <div class="fi-grp">
                    <label>Search</label>
                    <input type="text" name="search" class="fi fi-search" placeholder="Flat number…" value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <button type="submit" class="btn-apply"><i class="fas fa-search"></i> Apply</button>
                <?php if ($has_filters): ?>
                    <a href="flats.php" class="btn-clr"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <div style="overflow-x:auto;">
            <form id="bulkForm" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="ids" id="bulkIds">
                <table class="flat-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selAll" class="chk" onclick="fToggleAll(this)"></th>
                            <th>Project</th>
                            <th>Unit No</th>
                            <th class="al-c">Floor</th>
                            <th class="al-c">BHK / Type</th>
                            <th class="al-r">Area (sqft)</th>
                            <th class="al-r">Rate / sqft</th>
                            <th class="al-r">Ag. Amount</th>
                            <th class="al-c">Status</th>
                            <th class="al-r" style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($flats)): ?>
                            <tr><td colspan="10">
                                <div class="empty-state">
                                    <span class="es-icon"><i class="fas fa-building"></i></span>
                                    <h4>No units found</h4>
                                    <p>Start by adding units to your projects.</p>
                                </div>
                            </td></tr>
                        <?php else:
                            foreach ($flats as $i => $flat):
                                $statusClass = $flat['status'] === 'available' ? 'available' : ($flat['status'] === 'booked' ? 'booked' : 'sold');
                                $bhkLabel = !empty($flat['bhk']) ? htmlspecialchars(str_replace('BHK',' BHK',$flat['bhk'])) : (!empty($flat['unit_type']) && strtolower($flat['unit_type']) !== 'flat' ? ucfirst(htmlspecialchars($flat['unit_type'])) : '—');
                        ?>
                            <tr class="row-in" style="animation-delay:<?= $i*20 ?>ms;">
                                <td class="al-c">
                                    <?php if ($flat['status'] === 'available'): ?>
                                        <input type="checkbox" class="chk flat-chk" value="<?= $flat['id'] ?>" onclick="fUpdateBulk()">
                                    <?php else: ?>
                                        <i class="fas fa-lock lock-ic"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= renderProjectBadge($flat['project_name'], $flat['project_id']) ?></td>
                                <td><span class="flat-pill"><?= htmlspecialchars($flat['flat_no']) ?></span></td>
                                <td class="al-c"><span class="floor-val"><?= $flat['floor'] ?></span></td>
                                <td class="al-c"><span class="bhk-pill"><?= $bhkLabel ?></span></td>
                                <td class="al-r"><span class="num-cell"><?= number_format($flat['area_sqft'],2) ?></span></td>
                                <td class="al-r">
                                    <?php if (!empty($flat['booked_rate'])): ?>
                                        <span class="num-cell"><?= formatCurrency($flat['booked_rate']) ?></span>
                                    <?php else: ?>
                                        <span class="rate-est"><?= formatCurrency($flat['rate_per_sqft']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="al-r">
                                    <?php if (!empty($flat['booked_amount'])): ?>
                                        <span class="num-cell green"><?= formatCurrency($flat['booked_amount']) ?></span>
                                    <?php else: ?>
                                        <span class="num-cell muted"><?= formatCurrency($flat['total_value']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="al-c"><span class="status-pill <?= $statusClass ?>"><?= ucfirst($flat['status']) ?></span></td>
                                <td>
                                    <div class="act-grp">
                                        <button type="button" class="act-btn" onclick="fViewFlat(<?= $flat['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                                        <button type="button" class="act-btn" onclick="fEditFlat(<?= htmlspecialchars(json_encode($flat)) ?>)" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                        <?php if ($flat['status']==='available'): ?>
                                            <button type="button" class="act-btn del" onclick="fDelFlat(<?= $flat['id'] ?>,'<?= htmlspecialchars($flat['flat_no']) ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>

<!-- ════════════ MODALS ════════════ -->

<!-- Add Modal -->
<div class="m-backdrop" id="addModal">
    <div class="m-box md">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="m-head">
                <div class="m-head-l">
                    <div class="m-hic green"><i class="fas fa-plus"></i></div>
                    <div><h3>Add Single Unit</h3><p>Create a new flat, shop or office</p></div>
                </div>
                <button type="button" class="m-close" onclick="fCloseM('addModal')">×</button>
            </div>
            <div class="m-body">
                <div class="m-sec"><i class="fas fa-map-marker-alt"></i> Location</div>
                <div class="mf-row-3">
                    <div class="mf" style="grid-column:span 2;">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" required onchange="fTogglePrefix(this,'add')">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" data-multi="<?= $p['has_multiple_towers']??0 ?>" data-floors="<?= $p['total_floors']??0 ?>">
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mf" id="addPrefixBox" style="display:none">
                        <label>Tower <span class="req">*</span></label>
                        <input type="text" name="flat_prefix_single" id="addPrefix" placeholder="e.g. A">
                    </div>
                </div>
                <div class="mf-row-2">
                    <div class="mf">
                        <label>Unit No <span class="req">*</span></label>
                        <input type="text" name="flat_no" required placeholder="e.g. 101">
                    </div>
                    <div class="mf">
                        <label>Floor <span class="req">*</span></label>
                        <input type="number" name="floor" required>
                    </div>
                </div>
                <div class="m-sec"><i class="fas fa-home"></i> Unit Details</div>
                <div class="mf-row-3">
                    <div class="mf">
                        <label>Type <span class="req">*</span></label>
                        <select name="unit_type" required>
                            <option value="flat">Flat</option>
                            <option value="shop">Shop</option>
                            <option value="office">Office</option>
                        </select>
                    </div>
                    <div class="mf">
                        <label>BHK</label>
                        <select name="bhk">
                            <option value="">—</option>
                            <option value="1BHK">1 BHK</option>
                            <option value="2BHK">2 BHK</option>
                            <option value="3BHK">3 BHK</option>
                        </select>
                    </div>
                    <div class="mf">
                        <label>Status</label>
                        <select name="status">
                            <option value="available">Available</option>
                            <option value="booked">Booked</option>
                            <option value="sold">Sold</option>
                        </select>
                    </div>
                </div>
                <div class="m-sec"><i class="fas fa-ruler-combined"></i> Areas &amp; Pricing</div>
                <div class="mf-row-3">
                    <div class="mf"><label>RERA Area</label><input type="number" name="rera_area" step="0.01" placeholder="0.00"></div>
                    <div class="mf"><label>Usable Area</label><input type="number" name="usable_area" step="0.01" placeholder="0.00"></div>
                    <div class="mf"><label>Sellable Area <span class="req">*</span></label><input type="number" name="sellable_area" id="addSell" step="0.01" required placeholder="0.00" oninput="fCalcRate('add')"></div>
                </div>
                <div class="mf-row-2">
                    <div class="mf"><label>Total Value <span class="req">*</span></label><input type="number" name="flat_value" id="addVal" step="0.01" required placeholder="0.00" oninput="fCalcRate('add')"></div>
                    <div class="mf"><label>Rate / sqft (auto)</label><input type="text" id="addRate" readonly placeholder="—"></div>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn-modal btn-modal-ghost" onclick="fCloseM('addModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-submit"><i class="fas fa-save"></i> Save Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="m-backdrop" id="editModal">
    <div class="m-box md">
        <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="editId">
            <div class="m-head">
                <div class="m-head-l">
                    <div class="m-hic blue"><i class="fas fa-pencil-alt"></i></div>
                    <div><h3>Edit Unit</h3><p>Update unit details and pricing</p></div>
                </div>
                <button type="button" class="m-close" onclick="fCloseM('editModal')">×</button>
            </div>
            <div class="m-body">
                <div class="mf-row-3">
                    <div class="mf"><label>Unit No <span class="req">*</span></label><input type="text" name="flat_no" id="editNo" required></div>
                    <div class="mf"><label>Floor <span class="req">*</span></label><input type="number" name="floor" id="editFloor" required></div>
                    <div class="mf"><label>Status</label><select name="status" id="editStatus"><option value="available">Available</option><option value="booked">Booked</option><option value="sold">Sold</option></select></div>
                </div>
                <div class="mf-row-3">
                    <div class="mf"><label>Type <span class="req">*</span></label><select name="unit_type" id="editType" required><option value="flat">Flat</option><option value="shop">Shop</option><option value="office">Office</option></select></div>
                    <div class="mf"><label>BHK</label><select name="bhk" id="editBhk"><option value="">—</option><option value="1BHK">1 BHK</option><option value="2BHK">2 BHK</option><option value="3BHK">3 BHK</option></select></div>
                    <div class="mf"><label>Rate / sqft (auto)</label><input type="text" id="editRate" readonly placeholder="—"></div>
                </div>
                <div class="m-sec"><i class="fas fa-ruler-combined"></i> Areas &amp; Pricing</div>
                <div class="mf-row-3">
                    <div class="mf"><label>RERA Area</label><input type="number" name="rera_area" id="editRera" step="0.01"></div>
                    <div class="mf"><label>Usable Area</label><input type="number" name="usable_area" id="editUsable" step="0.01"></div>
                    <div class="mf"><label>Sellable Area <span class="req">*</span></label><input type="number" name="sellable_area" id="editSell" step="0.01" required oninput="fCalcRate('edit')"></div>
                </div>
                <div class="mf"><label>Total Value <span class="req">*</span></label><input type="number" name="flat_value" id="editVal" step="0.01" required oninput="fCalcRate('edit')"></div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn-modal btn-modal-ghost" onclick="fCloseM('editModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-submit"><i class="fas fa-check"></i> Update Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- View Modal -->
<div class="m-backdrop" id="viewModal">
    <div class="m-box md">
        <div class="m-head">
            <div class="m-head-l">
                <div class="m-hic purple"><i class="fas fa-eye"></i></div>
                <div><h3>Unit Details</h3><p>Full unit profile</p></div>
            </div>
            <button type="button" class="m-close" onclick="fCloseM('viewModal')">×</button>
        </div>
        <div class="m-body" id="viewContent" style="min-height:200px;">
            <div style="text-align:center;padding:3rem;color:var(--ink-mute);">
                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;display:block;margin-bottom:.75rem;"></i>
                Loading details…
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="m-backdrop" id="delModal">
    <div class="m-box sm">
        <div class="del-inner">
            <div class="del-icon"><i class="fas fa-trash-alt"></i></div>
            <h4 style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;margin:0 0 .6rem;">Delete <span id="delNo"></span>?</h4>
            <p style="font-size:.85rem;color:var(--ink-soft);line-height:1.6;margin:0 0 1.5rem;">
                Are you sure? <strong style="color:var(--red);">This action cannot be undone.</strong>
            </p>
            <form method="POST">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId">
                <div style="display:flex;gap:.75rem;justify-content:center;">
                    <button type="button" class="btn-modal btn-modal-ghost" onclick="fCloseM('delModal')">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Create Modal -->
<div class="m-backdrop" id="bulkModal">
    <div class="m-box lg">
        <form method="POST" id="bulkForm2">
            <?= csrf_field() ?><input type="hidden" name="action" value="bulk_create"><input type="hidden" name="flat_mix" id="flatMix">
            <div class="m-head">
                <div class="m-head-l">
                    <div class="m-hic orange"><i class="fas fa-layer-group"></i></div>
                    <div><h3>Bulk Create Flats</h3><p>Configure Ground, First &amp; Typical floors</p></div>
                </div>
                <button type="button" class="m-close" onclick="fCloseM('bulkModal')">×</button>
            </div>
            <div class="m-body">
                <div class="mf-row-3" style="margin-bottom:.5rem;">
                    <div class="mf" style="grid-column:span 2;">
                        <label>Project <span class="req">*</span></label>
                        <select name="project_id" required onchange="fTogglePrefix(this,'bulk')">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" data-multi="<?= $p['has_multiple_towers']??0 ?>" data-floors="<?= $p['total_floors']??0 ?>">
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mf"><label>Total Floors <span class="req">*</span></label><input type="number" name="floor_count" id="floorCnt" min="1" required placeholder="e.g. 10"></div>
                </div>
                <div class="mf" id="bulkPrefixBox" style="display:none;margin-bottom:1rem;">
                    <label>Flat Prefix</label>
                    <input type="text" name="flat_prefix" value="A-" placeholder="e.g. A-">
                    <span style="font-size:.7rem;color:var(--ink-mute);margin-top:3px;">e.g. "A-" → A-101, A-102</span>
                </div>
                <div class="m-tabs" id="bulkTabs">
                    <button type="button" class="m-tab active" onclick="fSwitchTab('ground')">Ground Floor (G)</button>
                    <button type="button" class="m-tab" onclick="fSwitchTab('first')">First Floor (1)</button>
                    <button type="button" class="m-tab" onclick="fSwitchTab('typical')">Typical Floors (2+)</button>
                </div>
                <!-- Ground -->
                <div class="m-tab-panel active" id="panelGround">
                    <div class="info-box"><i class="fas fa-store"></i> Define Shops / Offices / Flats for Ground Floor.</div>
                    <table class="mix-table">
                        <thead><tr><th>Type</th><th>RERA</th><th>Sellable</th><th>Usable</th><th>Value</th><th>Rate</th><th></th></tr></thead>
                        <tbody id="tbGround"></tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="fAddRow('ground')"><i class="fas fa-plus"></i> Add Unit</button>
                </div>
                <!-- First -->
                <div class="m-tab-panel" id="panelFirst">
                    <div class="info-box"><i class="fas fa-layer-group"></i> Define mixed units for First Floor.</div>
                    <table class="mix-table">
                        <thead><tr><th>Type</th><th>BHK</th><th>RERA</th><th>Sellable</th><th>Usable</th><th>Value</th><th>Rate</th><th></th></tr></thead>
                        <tbody id="tbFirst"></tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="fAddRow('first')"><i class="fas fa-plus"></i> Add Unit</button>
                </div>
                <!-- Typical -->
                <div class="m-tab-panel" id="panelTypical">
                    <div class="info-box"><i class="fas fa-info-circle"></i> <strong>Floors 2+: Only "Flat" type allowed.</strong></div>
                    <table class="mix-table">
                        <thead><tr><th>Type</th><th>BHK</th><th>RERA</th><th>Sellable</th><th>Usable</th><th>Value</th><th>Rate</th><th></th></tr></thead>
                        <tbody id="tbTypical"></tbody>
                    </table>
                    <button type="button" class="btn-add-row" onclick="fAddRow('typical')"><i class="fas fa-plus"></i> Add Flat</button>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn-modal btn-modal-ghost" onclick="fCloseM('bulkModal')">Cancel</button>
                <button type="button" class="btn-modal btn-modal-submit" onclick="fSubmitBulk()"><i class="fas fa-bolt"></i> Generate Flats</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Modal ─────────────────────────── */
function fShowM(id) { const el=document.getElementById(id); if(el){el.classList.add('open');document.body.style.overflow='hidden';} }
function fCloseM(id){ const el=document.getElementById(id); if(el){el.classList.remove('open');document.body.style.overflow='';} }
document.querySelectorAll('.m-backdrop').forEach(bd => bd.addEventListener('click', e => { if(e.target===bd) fCloseM(bd.id); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.m-backdrop.open').forEach(m => fCloseM(m.id)); });

/* ── Filter toggle ─────────────────── */
function toggleFilters() { document.getElementById('filterBar').classList.toggle('show'); document.getElementById('filterBtn').classList.toggle('active'); }

/* ── Rate calc ─────────────────────── */
function fCalcRate(p) {
    const sell=parseFloat(document.getElementById(p+'Sell')?.value)||0;
    const val =parseFloat(document.getElementById(p+'Val')?.value)||0;
    const rate=document.getElementById(p+'Rate');
    if(rate) rate.value=(sell>0&&val>0)?(val/sell).toFixed(2):'0.00';
}

/* ── Prefix toggle ─────────────────── */
function fTogglePrefix(sel,pfx) {
    const opt=sel.options[sel.selectedIndex];
    const multi=parseInt(opt.getAttribute('data-multi'))===1;
    const floors=opt.getAttribute('data-floors')||'';
    if(pfx==='add'){
        const box=document.getElementById('addPrefixBox'),inp=document.getElementById('addPrefix');
        if(box) box.style.display=multi?'block':'none';
        if(inp){inp.required=multi;if(!multi)inp.value='';}
    } else {
        const box=document.getElementById('bulkPrefixBox');
        if(box) box.style.display=multi?'block':'none';
        const fc=document.getElementById('floorCnt');
        if(fc&&floors) fc.value=floors;
    }
}

/* ── Edit / Delete / View ──────────── */
function fEditFlat(f) {
    ['editId','editNo','editFloor','editType','editBhk','editStatus','editRera','editUsable'].forEach(id=>{
        const map={'editId':'id','editNo':'flat_no','editFloor':'floor','editType':'unit_type','editBhk':'bhk','editStatus':'status','editRera':'rera_area','editUsable':'usable_area'};
        const el=document.getElementById(id); if(el) el.value=f[map[id]]||'';
    });
    document.getElementById('editSell').value=f.sellable_area||f.area_sqft||'';
    document.getElementById('editVal').value=f.total_value||0;
    fCalcRate('edit'); fShowM('editModal');
}
function fDelFlat(id,no){ document.getElementById('delId').value=id; document.getElementById('delNo').textContent=no; fShowM('delModal'); }
function fViewFlat(id){
    fShowM('viewModal');
    const c=document.getElementById('viewContent');
    c.innerHTML='<div style="text-align:center;padding:3rem;color:var(--ink-mute);"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;display:block;margin-bottom:.75rem;"></i>Loading…</div>';
    fetch('<?= BASE_URL ?>modules/projects/flats/get_flat_details.php?id='+id).then(r=>r.text()).then(h=>c.innerHTML=h).catch(()=>{c.innerHTML='<div style="text-align:center;padding:2rem;color:var(--red);">Failed to load.</div>';});
}

/* ── Bulk delete ───────────────────── */
function fToggleAll(src){ document.querySelectorAll('.flat-chk').forEach(cb=>cb.checked=src.checked); fUpdateBulk(); }
function fUpdateBulk(){
    const chks=document.querySelectorAll('.flat-chk:checked');
    const btn=document.getElementById('bulkDelBtn');
    document.getElementById('selCount').textContent=chks.length;
    btn.style.display=chks.length>0?'inline-flex':'none';
}
function fConfirmBulkDel(){
    const chks=document.querySelectorAll('.flat-chk:checked'); if(!chks.length) return;
    if(!confirm('Delete '+chks.length+' flats?')) return;
    document.getElementById('bulkIds').value=JSON.stringify(Array.from(chks).map(cb=>cb.value));
    document.getElementById('bulkForm').submit();
}

/* ── Tabs ──────────────────────────── */
function fSwitchTab(name){
    document.querySelectorAll('.m-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.m-tab-panel').forEach(p=>p.classList.remove('active'));
    const map={ground:0,first:1,typical:2};
    document.querySelectorAll('.m-tab')[map[name]]?.classList.add('active');
    document.getElementById('panel'+name.charAt(0).toUpperCase()+name.slice(1))?.classList.add('active');
}

/* ── Add row ───────────────────────── */
function fAddRow(sec){
    const tb=document.getElementById('tb'+sec.charAt(0).toUpperCase()+sec.slice(1));
    if(!tb) return;
    const tr=document.createElement('tr');
    const isTypical=sec==='typical', isGround=sec==='ground';
    let typeCell=isTypical
        ?`<td><input type="hidden" class="unit-type" value="flat"><span class="flat-pill">FLAT</span></td>`
        :`<td><select class="unit-type" onchange="fToggleBhk(this)"><option value="shop">Shop</option><option value="office">Office</option><option value="flat">Flat</option></select></td>`;
    let bhkCell=isGround?'':`<td><select class="bhk" ${isTypical?'':'disabled style="background:var(--cream);cursor:not-allowed"'}><option value="">—</option><option value="1BHK">1 BHK</option><option value="2BHK">2 BHK</option><option value="3BHK">3 BHK</option></select></td>`;
    tr.innerHTML=`${typeCell}${bhkCell}<td><input type="number" class="rera" placeholder="RERA" step="0.01"></td><td><input type="number" class="sell" placeholder="Sellable" step="0.01" oninput="fUpdateRate(this)"></td><td><input type="number" class="usable" placeholder="Usable" step="0.01"></td><td><input type="number" class="fval" placeholder="Value" step="0.01" oninput="fUpdateRate(this)"></td><td><input type="text" class="rate" readonly placeholder="—"></td><td><button type="button" class="btn-rm-row" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>`;
    tb.appendChild(tr);
    if(!isTypical&&!isGround){ const s=tr.querySelector('.unit-type'); if(s) fToggleBhk(s); }
}
function fToggleBhk(sel){
    const bhk=sel.closest('tr').querySelector('.bhk'); if(!bhk) return;
    const isFlat=sel.value==='flat', isOffice=sel.value==='office';
    bhk.disabled=!(isFlat||isOffice); bhk.style.background=(isFlat||isOffice)?'white':'var(--cream)'; bhk.style.cursor=(isFlat||isOffice)?'default':'not-allowed';
    if(!isFlat&&!isOffice) bhk.value='';
}
function fUpdateRate(inp){
    const tr=inp.closest('tr');
    const sell=parseFloat(tr.querySelector('.sell').value)||0, val=parseFloat(tr.querySelector('.fval').value)||0;
    const rate=tr.querySelector('.rate'); if(rate) rate.value=(sell>0&&val>0)?(val/sell).toFixed(2):'';
}
function getRows(sec){
    const rows=[], tb=document.getElementById('tb'+sec.charAt(0).toUpperCase()+sec.slice(1));
    if(!tb) return [];
    tb.querySelectorAll('tr').forEach(tr=>{
        const bhk=tr.querySelector('.bhk'); rows.push({unit_type:tr.querySelector('.unit-type').value,bhk:bhk?bhk.value:'',rera_area:tr.querySelector('.rera').value,sellable_area:tr.querySelector('.sell').value,usable_area:tr.querySelector('.usable').value,flat_value:tr.querySelector('.fval').value});
    });
    return rows;
}
function fSubmitBulk(){
    const g=getRows('ground'),f=getRows('first'),t=getRows('typical'),all=[...g,...f,...t],fc=parseInt(document.getElementById('floorCnt').value)||0;
    const err=msg=>Swal.fire({icon:'warning',title:'Validation Error',text:msg,confirmButtonText:'Okay',buttonsStyling:false,customClass:{popup:'premium-swal-popup',title:'premium-swal-title',htmlContainer:'premium-swal-content',confirmButton:'premium-swal-confirm'}});
    for(let i=0;i<all.length;i++){
        const r=all[i];
        if(parseFloat(r.sellable_area)<=0||isNaN(parseFloat(r.sellable_area))){err('Sellable Area must be > 0 for all units.');return;}
        if(parseFloat(r.flat_value)<=0||isNaN(parseFloat(r.flat_value))){err('Flat Value must be > 0 for all units.');return;}
        if(r.unit_type==='flat'&&!r.bhk){err('BHK is required for all Flats.');return;}
    }
    if(fc>=2&&t.length===0){
        Swal.fire({title:'Missing Typical Floor?',text:`You have ${fc} floors but no Typical Floor config. Only Ground/First floor units will be created.`,icon:'question',showCancelButton:true,confirmButtonText:'Continue Anyway',cancelButtonText:'Go Back',buttonsStyling:false,reverseButtons:true,customClass:{popup:'premium-swal-popup',title:'premium-swal-title',confirmButton:'premium-swal-confirm',cancelButton:'premium-swal-cancel'}}).then(r=>{if(r.isConfirmed) submit();});
        return;
    }
    submit();
    function submit(){
        document.getElementById('flatMix').value=JSON.stringify({ground_floor:g,first_floor:f,typical_floor:{start_floor:2,units:t}});
        document.getElementById('bulkForm2').submit();
    }
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>