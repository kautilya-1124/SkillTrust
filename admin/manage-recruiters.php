<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$currentPage = 'manage-recruiters.php';
$toastType = (string) ($_GET['toast_type'] ?? '');
$toastMsg = (string) ($_GET['toast_msg'] ?? '');

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($q !== '') { $where[] = '(company_name LIKE ? OR email LIKE ?)'; $like = '%' . $q . '%'; $types .= 'ss'; $params[] = $like; $params[] = $like; }
if (in_array($status, ['pending', 'approved', 'rejected'], true)) { $where[] = 'LOWER(status)=?'; $types .= 's'; $params[] = $status; }
$whereSql = $where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$cstmt = $conn->prepare('SELECT COUNT(*) AS c FROM recruiters' . $whereSql);
if ($cstmt) {
    if ($types !== '') { $bind = [$types]; foreach ($params as $k => $v) { $bind[] = &$params[$k]; } call_user_func_array([$cstmt, 'bind_param'], $bind); }
    $cstmt->execute();
    $total = (int) (($cstmt->get_result()->fetch_assoc()['c'] ?? 0));
    $cstmt->close();
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$rows = [];
$stmt = $conn->prepare('SELECT id, company_name, email, status FROM recruiters' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
if ($stmt) {
    $listTypes = $types . 'ii';
    $listParams = $params; $listParams[] = $perPage; $listParams[] = $offset;
    $bind = [$listTypes]; foreach ($listParams as $k => $v) { $bind[] = &$listParams[$k]; } call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
}

$baseQuery = ['q' => $q, 'status' => $status];
$qsForReturn = http_build_query(array_filter($baseQuery, static fn($v) => $v !== '' && $v !== 'all'));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Recruiters</title>
<script src="https://cdn.tailwindcss.com"></script><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin/manage-recruiters.css">    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
</head>
<body class="text-slate-300"><div id="toast" class="hidden fixed bottom-6 right-6 z-[100] px-4 py-2.5 rounded-xl text-sm font-semibold border"></div><div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div><div class="flex min-h-screen">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="flex-1 lg:ml-64 flex flex-col min-h-screen"><header class="navbar sticky top-0 z-30 px-3 sm:px-4 lg:px-8 py-2.5 lg:h-16 flex items-center justify-between"><div class="flex items-center gap-2"><button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800">â˜°</button><div><h2 class="font-display font-bold text-white text-lg">Manage Recruiters</h2><p class="text-xs text-slate-500">Approve, reject or remove recruiter requests</p></div></div></header>
<main class="flex-1 px-3 sm:px-4 lg:px-8 py-6 space-y-4">
<section class="glass rounded-2xl p-4"><form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3"><input class="field md:col-span-2" name="q" value="<?php echo e($q); ?>" placeholder="Search company or email"><select class="field" name="status"><option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option><option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option><option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved</option><option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected</option></select><div class="flex gap-2"><button class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-500/20 border border-indigo-500/30 text-indigo-300">Apply</button><a href="manage-recruiters.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300">Reset</a></div></form></section>
<section class="glass rounded-2xl overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-500 text-xs"><tr><th class="text-left px-4 py-3">Company</th><th class="text-left px-4 py-3">Email</th><th class="text-left px-4 py-3">Status</th><th class="text-right px-4 py-3">Actions</th></tr></thead><tbody class="divide-y divide-slate-700/30"><?php if($rows===[]): ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No recruiters found.</td></tr><?php else: foreach($rows as $r): $st=strtolower((string)($r['status']??'pending')); ?><tr class="hover:bg-slate-800/40"><td class="px-4 py-3 text-slate-200 font-medium"><?php echo e((string)($r['company_name']??'')); ?></td><td class="px-4 py-3 text-slate-400"><?php echo e((string)($r['email']??'')); ?></td><td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $st==='approved'?'bg-emerald-500/15 text-emerald-300 border border-emerald-500/25':($st==='rejected'?'bg-rose-500/15 text-rose-300 border border-rose-500/25':'bg-amber-500/15 text-amber-300 border border-amber-500/25'); ?>"><?php echo ucfirst($st); ?></span></td><td class="px-4 py-3"><div class="flex justify-end gap-2"><?php if($st!=='approved'): ?><form method="post" action="actions/manage-recruiters-action.php"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="recruiter_id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn!==''?'&':'') . 'page=' . $page); ?>"><button class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/15 border border-emerald-500/30 text-emerald-300">Approve</button></form><?php endif; ?><?php if($st!=='rejected'): ?><form method="post" action="actions/manage-recruiters-action.php"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="recruiter_id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn!==''?'&':'') . 'page=' . $page); ?>"><button class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-500/15 border border-amber-500/30 text-amber-300">Reject</button></form><?php endif; ?><form method="post" action="actions/manage-recruiters-action.php" onsubmit="return confirm('Delete recruiter?');"><input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="recruiter_id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="return_qs" value="<?php echo e($qsForReturn . ($qsForReturn!==''?'&':'') . 'page=' . $page); ?>"><button class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 border border-rose-500/30 text-rose-300">Delete</button></form></div></td></tr><?php endforeach; endif; ?></tbody></table></div>
<div class="px-4 py-3 border-t border-slate-700/40 flex items-center justify-between"><p class="text-xs text-slate-500">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></p><?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); $qp=array_filter(array_merge($baseQuery,['page'=>(string)$prev]),fn($v)=>$v!==''&&$v!=='all'); $qn=array_filter(array_merge($baseQuery,['page'=>(string)$next]),fn($v)=>$v!==''&&$v!=='all'); ?><div class="flex gap-2"><a href="?<?php echo e(http_build_query($qp)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 <?php echo $page<=1?'pointer-events-none opacity-40':''; ?>">Prev</a><a href="?<?php echo e(http_build_query($qn)); ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-600 text-slate-300 <?php echo $page>=$totalPages?'pointer-events-none opacity-40':''; ?>">Next</a></div></div>
</section></main></div></div><script type="application/json" id="adminManageRecruitersData"><?php echo json_encode(['toastType' => $toastType, 'toastMsg' => $toastMsg], JSON_UNESCAPED_UNICODE); ?></script><script src="../assets/js/admin/common-page.js"></script><script src="../assets/js/admin/manage-recruiters.js"></script></body></html>
