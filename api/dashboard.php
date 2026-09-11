<?php
/**
 * Bhardwaj Gurukul - Admin Dashboard
 * Main management interface for notices
 */
session_start();
require_once __DIR__ . '/config.php';

// Check authentication
if (empty($_SESSION['admin_id']) || empty($_SESSION['session_token'])) {
    header('Location: /dashboard/login.php');
    exit;
}

$db = new SupabaseDB();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Bhardwaj Gurukul</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Forum&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/Bhardwaj-logo.ico">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        saffron: { 50: '#fff7ed', 100: '#ffedd5', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
                        leaf: { 50: '#f0fdf4', 100: '#dcfce7', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534' },
                        crimson: { 50: '#fef2f2', 100: '#fee2e2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
                        ink: { 900: '#1c1917', 800: '#292524', 700: '#44403c' },
                        paper: '#fffbf2'
                    },
                    fontFamily: {
                        sans: ['Forum', 'Poppins', 'system-ui', 'serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Forum', 'Poppins', system-ui, serif; background: #f8f6f1; }
        .spinner {
            width: 18px; height: 18px;
            border: 3px solid #fff3;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .notice-card {
            transition: all 0.2s ease;
        }
        .notice-card:hover {
            transform: translateY(-2px);
        }
        /* Priority badges */
        .priority-urgent { @apply bg-crimson-100 text-crimson-700 border-crimson-200; }
        .priority-high { @apply bg-saffron-100 text-saffron-700 border-saffron-200; }
        .priority-normal { @apply bg-leaf-100 text-leaf-700 border-leaf-200; }
        .priority-low { @apply bg-gray-100 text-gray-700 border-gray-200; }

        /* Status toggle */
        .status-active { @apply bg-leaf-500; }
        .status-inactive { @apply bg-gray-300; }

        /* Sidebar nav */
        .nav-item {
            @apply flex items-center gap-3 px-4 py-3 rounded-xl text-ink-700 hover:bg-saffron-50 hover:text-saffron-600 transition font-medium text-sm;
        }
        .nav-item.active {
            @apply bg-saffron-500 text-white shadow-pop;
        }
        .nav-item svg { @apply w-5 h-5; }

        /* Modal */
        .modal-overlay {
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
        }

        /* Toast */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast {
            animation: slideIn 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-saffron-100 flex flex-col hidden lg:flex fixed h-full z-30">
        <!-- Brand -->
        <div class="p-5 border-b border-saffron-100">
            <div class="flex items-center gap-3">
                <img src="/logo.png" alt="Logo" class="w-10 h-10 object-contain">
                <div>
                    <div class="font-bold text-ink-900 text-sm">Bhardwaj Gurukul</div>
                    <div class="text-xs text-ink-600">Admin Panel</div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 p-3 space-y-1">
            <a href="#" class="nav-item active" data-page="dashboard" onclick="switchPage('dashboard', this)">
                <i data-lucide="layout-dashboard"></i> Dashboard
            </a>
            <a href="#" class="nav-item" data-page="notices" onclick="switchPage('notices', this)">
                <i data-lucide="file-text"></i> All Notices
            </a>
            <a href="#" class="nav-item" data-page="create" onclick="switchPage('create', this)">
                <i data-lucide="plus-circle"></i> Add Notice
            </a>
        </nav>

        <!-- User -->
        <div class="p-4 border-t border-saffron-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-saffron-100 flex items-center justify-center">
                    <i data-lucide="user" class="w-5 h-5 text-saffron-600"></i>
                </div>
                <div class="text-sm">
                    <div class="font-bold text-ink-900"><?= sanitize($_SESSION['full_name'] ?? 'Admin') ?></div>
                    <div class="text-xs text-ink-600"><?= sanitize($_SESSION['username'] ?? '') ?></div>
                </div>
            </div>
            <button onclick="logout()" class="flex items-center gap-2 text-sm text-crimson-600 hover:text-crimson-700 font-medium">
                <i data-lucide="log-out" class="w-4 h-4"></i> Logout
            </button>
        </div>
    </aside>

    <!-- Mobile header -->
    <div class="lg:hidden fixed top-0 inset-x-0 z-30 bg-white border-b border-saffron-100 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <img src="/logo.png" alt="Logo" class="w-8 h-8 object-contain">
            <span class="font-bold text-ink-900 text-sm">Bhardwaj Gurukul</span>
        </div>
        <button onclick="toggleMobileMenu()" class="p-2 hover:bg-saffron-50 rounded-lg">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Mobile menu overlay -->
    <div id="mobileMenu" class="lg:hidden fixed inset-0 z-40 bg-black/40 hidden" onclick="toggleMobileMenu()">
        <div class="w-64 h-full bg-white p-4" onclick="event.stopPropagation()">
            <nav class="space-y-1">
                <a href="#" class="nav-item active flex" data-page="dashboard" onclick="switchPage('dashboard', this); toggleMobileMenu()">
                    <i data-lucide="layout-dashboard"></i> Dashboard
                </a>
                <a href="#" class="nav-item flex" data-page="notices" onclick="switchPage('notices', this); toggleMobileMenu()">
                    <i data-lucide="file-text"></i> All Notices
                </a>
                <a href="#" class="nav-item flex" data-page="create" onclick="switchPage('create', this); toggleMobileMenu()">
                    <i data-lucide="plus-circle"></i> Add Notice
                </a>
            </nav>
            <hr class="my-4">
            <button onclick="logout(); toggleMobileMenu()" class="flex items-center gap-2 text-sm text-crimson-600 font-medium">
                <i data-lucide="log-out" class="w-4 h-4"></i> Logout
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 lg:ml-64 pt-14 lg:pt-0">

        <!-- ===== DASHBOARD PAGE ===== -->
        <div id="page-dashboard" class="page-content">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-ink-900">Dashboard</h1>
                    <p class="text-sm text-ink-600 mt-1">Overview of all notices and recent activity</p>
                </div>

                <!-- Stats -->
                <div id="statsGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Loaded by JS -->
                </div>

                <!-- Recent Notices -->
                <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-ink-900">Recent Notices</h2>
                        <button onclick="switchPage('notices', document.querySelector('[data-page=notices]'))" class="text-sm text-saffron-600 hover:text-saffron-700 font-semibold">
                            View All →
                        </button>
                    </div>
                    <div id="recentNotices">
                        <div class="flex items-center justify-center py-8">
                            <div class="spinner border-saffron-500" style="width:32px;height:32px;border-width:4px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ALL NOTICES PAGE ===== -->
        <div id="page-notices" class="page-content hidden">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-ink-900">All Notices</h1>
                        <p class="text-sm text-ink-600 mt-1">Manage all uploaded notices</p>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-400"></i>
                            <input type="text" id="searchInput" placeholder="Search notices..."
                                class="pl-9 pr-4 py-2 rounded-xl border border-saffron-100 text-sm focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none w-48 sm:w-64"
                                oninput="filterNotices()">
                        </div>
                        <button onclick="switchPage('create', document.querySelector('[data-page=create]'))"
                            class="flex items-center gap-2 bg-saffron-500 hover:bg-saffron-600 text-white px-4 py-2 rounded-xl font-bold text-sm transition">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Notice
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 overflow-hidden">
                    <!-- Notices list -->
                    <div id="noticesList">
                        <div class="flex items-center justify-center py-12">
                            <div class="spinner border-saffron-500" style="width:32px;height:32px;border-width:4px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== CREATE NOTICE PAGE ===== -->
        <div id="page-create" class="page-content hidden">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-ink-900">Add New Notice</h1>
                    <p class="text-sm text-ink-600 mt-1">Upload a PDF, image, or create a text notice</p>
                </div>

                <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-6 sm:p-8">
                    <form id="createNoticeForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <!-- Notice Type Tabs -->
                        <div class="flex gap-2 p-1 bg-saffron-50 rounded-xl w-fit">
                            <button type="button" class="type-tab active flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition"
                                data-type="text" onclick="switchNoticeType('text')">
                                <i data-lucide="file-text" class="w-4 h-4"></i> Text Notice
                            </button>
                            <button type="button" class="type-tab flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition"
                                data-type="file" onclick="switchNoticeType('file')">
                                <i data-lucide="file-upload" class="w-4 h-4"></i> Upload File
                            </button>
                        </div>

                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-bold text-ink-800 mb-2">Notice Title *</label>
                            <input type="text" name="title" required
                                class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition"
                                placeholder="e.g. Annual Exam Schedule 2026">
                        </div>

                        <!-- Text Content -->
                        <div id="textContent">
                            <label class="block text-sm font-bold text-ink-800 mb-2">Notice Content</label>
                            <textarea name="content" rows="8"
                                class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition resize-y"
                                placeholder="Type your notice content here..."></textarea>
                        </div>

                        <!-- File Upload -->
                        <div id="fileUpload" class="hidden">
                            <label class="block text-sm font-bold text-ink-800 mb-2">Upload Notice File</label>
                            <div class="border-2 border-dashed border-saffron-200 rounded-xl p-8 text-center hover:border-saffron-400 transition cursor-pointer"
                                onclick="document.getElementById('noticeFile').click()"
                                ondragover="this.classList.add('border-saffron-500', 'bg-saffron-50'); event.preventDefault();"
                                ondragleave="this.classList.remove('border-saffron-500', 'bg-saffron-50');"
                                ondrop="handleFileDrop(event)">
                                <input type="file" id="noticeFile" name="notice_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.txt" class="hidden" onchange="handleFileSelect(event)">
                                <i data-lucide="upload-cloud" class="w-12 h-12 text-saffron-400 mx-auto mb-3"></i>
                                <p class="text-sm text-ink-700 font-medium">Click to upload or drag & drop</p>
                                <p class="text-xs text-ink-500 mt-1">PDF, JPG, PNG, DOC, DOCX, TXT — Max 50MB</p>
                                <div id="fileInfo" class="hidden mt-3 text-sm text-leaf-700 font-medium"></div>
                            </div>
                        </div>

                        <!-- Priority & Status -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-ink-800 mb-2">Priority</label>
                                <select name="priority"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition bg-white">
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-ink-800 mb-2">Status</label>
                                <select name="is_active"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition bg-white">
                                    <option value="1">Active (Published)</option>
                                    <option value="0">Inactive (Draft)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button type="submit" id="submitBtn"
                                class="flex-1 flex items-center justify-center gap-2 bg-saffron-500 hover:bg-saffron-600 text-white font-bold py-3 px-4 rounded-xl shadow-pop transition">
                                <i data-lucide="save" class="w-5 h-5"></i>
                                <span>Publish Notice</span>
                            </button>
                            <button type="button" onclick="switchPage('dashboard', document.querySelector('[data-page=dashboard]'))"
                                class="px-6 py-3 rounded-xl border-2 border-saffron-200 text-ink-700 font-bold hover:bg-saffron-50 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== EDIT NOTICE MODAL ===== -->
        <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="modal-overlay absolute inset-0" onclick="closeEditModal()"></div>
            <div class="relative bg-white rounded-2xl shadow-pop max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 sm:p-8 z-10">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-ink-900">Edit Notice</h2>
                    <button onclick="closeEditModal()" class="p-2 hover:bg-saffron-50 rounded-lg">
                        <i data-lucide="x" class="w-5 h-5 text-ink-600"></i>
                    </button>
                </div>
                <form id="editNoticeForm" class="space-y-4">
                    <input type="hidden" name="id" id="editNoticeId">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div>
                        <label class="block text-sm font-bold text-ink-800 mb-2">Title</label>
                        <input type="text" name="title" id="editTitle" required
                            class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-ink-800 mb-2">Content</label>
                        <textarea name="content" id="editContent" rows="6"
                            class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition resize-y"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-ink-800 mb-2">Priority</label>
                            <select name="priority" id="editPriority"
                                class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 bg-white focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-ink-800 mb-2">Status</label>
                            <select name="is_active" id="editStatus"
                                class="w-full px-4 py-3 rounded-xl border-2 border-saffron-100 bg-white focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" id="editSubmitBtn"
                            class="flex-1 flex items-center justify-center gap-2 bg-saffron-500 hover:bg-saffron-600 text-white font-bold py-3 rounded-xl transition">
                            <i data-lucide="save" class="w-5 h-5"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeEditModal()"
                            class="px-6 py-3 rounded-xl border-2 border-saffron-200 text-ink-700 font-bold hover:bg-saffron-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-50 space-y-3"></div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        // API base URL - use absolute paths
        const API_BASE = '/api/';
        const ADMIN_TOKEN = '<?= $_SESSION["session_token"] ?? "" ?>';

        // --- Nav ---
        function switchPage(page, el) {
            document.querySelectorAll('.page-content').forEach(p => p.classList.add('hidden'));
            document.getElementById('page-' + page).classList.remove('hidden');
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            if (el) el.classList.add('active');

            if (page === 'dashboard') loadDashboard();
            if (page === 'notices') loadNotices();
        }

        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        }

        // --- API Requests ---
        async function api(method, action, body = null, files = null) {
            const opts = {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Session-Token': ADMIN_TOKEN
                }
            };

            if (body && files) {
                const formData = new FormData();
                Object.keys(body).forEach(key => formData.append(key, body[key]));
                Object.keys(files).forEach(key => formData.append(key, files[key]));
                opts.body = formData;
                delete opts.headers['Content-Type'];
            } else if (body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }

            const res = await fetch(API_BASE + 'notices.php?action=' + action, opts);
            const data = await res.json();
            return data;
        }

        // --- Dashboard ---
        async function loadDashboard() {
            // Load stats
            const statsRes = await api('GET', 'stats');
            if (statsRes.success) {
                const d = statsRes.data;
                document.getElementById('statsGrid').innerHTML = `
                    <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-saffron-100 text-saffron-600 grid place-items-center"><i data-lucide="file-text" class="w-6 h-6"></i></div>
                            <div><div class="text-2xl font-extrabold text-ink-900">${d.total_notices}</div><div class="text-xs text-ink-600 font-medium">Total Notices</div></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-leaf-100 text-leaf-600 grid place-items-center"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
                            <div><div class="text-2xl font-extrabold text-ink-900">${d.active_notices}</div><div class="text-xs text-ink-600 font-medium">Active</div></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-crimson-100 text-crimson-600 grid place-items-center"><i data-lucide="pause-circle" class="w-6 h-6"></i></div>
                            <div><div class="text-2xl font-extrabold text-ink-900">${d.inactive_notices}</div><div class="text-xs text-ink-600 font-medium">Drafts</div></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-saffron-50 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-600 grid place-items-center"><i data-lucide="file" class="w-6 h-6"></i></div>
                            <div><div class="text-2xl font-extrabold text-ink-900">${d.pdf_notices}</div><div class="text-xs text-ink-600 font-medium">PDF Files</div></div>
                        </div>
                    </div>
                `;
                lucide.createIcons();
            }

            // Load recent
            const recentRes = await api('GET', 'list');
            if (recentRes.success) {
                const notices = recentRes.data.notices.slice(0, 5);
                if (notices.length === 0) {
                    document.getElementById('recentNotices').innerHTML = `
                        <div class="text-center py-8 text-ink-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-saffron-300"></i>
                            <p>No notices yet. Create your first one!</p>
                        </div>
                    `;
                } else {
                    document.getElementById('recentNotices').innerHTML = notices.map(n => `
                        <div class="notice-card flex items-center gap-4 p-4 rounded-xl border border-saffron-50 hover:border-saffron-100 hover:bg-saffron-50/50">
                            <div class="w-10 h-10 rounded-xl ${n.file_url ? 'bg-crimson-100 text-crimson-600' : 'bg-leaf-100 text-leaf-600'} grid place-items-center shrink-0">
                                <i data-lucide="${n.file_url ? 'file' : 'file-text'}" class="w-5 h-5"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-ink-900 truncate">${escapeHtml(n.title)}</div>
                                <div class="text-xs text-ink-600">${n.created_at} · <span class="priority-${n.priority} px-2 py-0.5 rounded-full text-xs border">${n.priority}</span></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full ${n.is_active ? 'bg-leaf-500' : 'bg-gray-300'}"></span>
                            </div>
                        </div>
                    `).join('');
                }
                lucide.createIcons();
            }
        }

        // --- All Notices ---
        let allNotices = [];

        async function loadNotices() {
            const res = await api('GET', 'list');
            if (res.success) {
                allNotices = res.data.notices;
                renderNotices(allNotices);
            } else {
                document.getElementById('noticesList').innerHTML = `
                    <div class="text-center py-12 text-crimson-600">Failed to load notices.</div>
                `;
            }
        }

        function renderNotices(notices) {
            const container = document.getElementById('noticesList');
            if (notices.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4 text-saffron-300"></i>
                        <p class="text-ink-600 font-medium">No notices found</p>
                        <p class="text-sm text-ink-400 mt-1">Create a new notice to get started</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            container.innerHTML = notices.map(n => `
                <div class="notice-card flex flex-col sm:flex-row sm:items-center gap-3 p-4 border-b border-saffron-50 last:border-b-0 hover:bg-saffron-50/50 transition">
                    <div class="w-10 h-10 rounded-xl ${n.file_url ? 'bg-crimson-100 text-crimson-600' : 'bg-leaf-100 text-leaf-600'} grid place-items-center shrink-0">
                        <i data-lucide="${n.file_url ? 'file' : 'file-text'}" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-ink-900">${escapeHtml(n.title)}</span>
                            <span class="priority-${n.priority} px-2 py-0.5 rounded-full text-xs border">${n.priority}</span>
                        </div>
                        <div class="text-xs text-ink-600 mt-0.5">
                            ${n.created_at}
                            ${n.file_url ? '· <i data-lucide="paperclip" class="w-3 h-3 inline"></i> <a href="' + escapeHtml(n.file_url) + '" target="_blank" class="text-saffron-600 hover:underline">View file</a>' : ''}
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button onclick="toggleStatus('${n.id}', ${n.is_active})"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold ${n.is_active ? 'bg-leaf-50 text-leaf-700 hover:bg-leaf-100' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'} transition">
                            <span class="w-2 h-2 rounded-full ${n.is_active ? 'bg-leaf-500' : 'bg-gray-400'}"></span>
                            ${n.is_active ? 'Active' : 'Draft'}
                        </button>
                        <button onclick="openEditModal('${n.id}')"
                            class="p-2 hover:bg-saffron-100 rounded-lg transition text-ink-600 hover:text-saffron-600">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button onclick="confirmDelete('${n.id}')"
                            class="p-2 hover:bg-crimson-50 rounded-lg transition text-ink-600 hover:text-crimson-600">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        function filterNotices() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allNotices.filter(n =>
                n.title.toLowerCase().includes(q) ||
                (n.content && n.content.toLowerCase().includes(q))
            );
            renderNotices(filtered);
        }

        // --- Create Notice ---
        let selectedFile = null;

        function switchNoticeType(type) {
            document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active', 'bg-white', 'shadow-sm'));
            document.querySelector(`.type-tab[data-type="${type}"]`).classList.add('active', 'bg-white', 'shadow-sm');

            if (type === 'text') {
                document.getElementById('textContent').classList.remove('hidden');
                document.getElementById('fileUpload').classList.add('hidden');
            } else {
                document.getElementById('textContent').classList.add('hidden');
                document.getElementById('fileUpload').classList.remove('hidden');
            }
        }
        // Init first tab
        switchNoticeType('text');

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) showFileInfo(file);
        }

        function handleFileDrop(e) {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (file) showFileInfo(file);
        }

        function showFileInfo(file) {
            selectedFile = file;
            document.getElementById('fileInfo').classList.remove('hidden');
            document.getElementById('fileInfo').innerHTML = `
                <i data-lucide="file-check" class="w-4 h-4 inline mr-1"></i>
                ${escapeHtml(file.name)} (${formatBytes(file.size)})
            `;
            lucide.createIcons();
        }

        function formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
            return (bytes/1048576).toFixed(1) + ' MB';
        }

        document.getElementById('createNoticeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner"></div><span>Publishing...</span>';

            const formData = new FormData(this);
            const body = {
                title: formData.get('title'),
                content: formData.get('content'),
                priority: formData.get('priority'),
                is_active: formData.get('is_active') === '1'
            };

            const files = {};
            if (selectedFile) files.notice_file = selectedFile;

            try {
                const res = await api('POST', 'create', body, Object.keys(files).length > 0 ? files : null);
                if (res.success) {
                    showToast('Notice published successfully!', 'success');
                    this.reset();
                    selectedFile = null;
                    document.getElementById('fileInfo').classList.add('hidden');
                    document.getElementById('fileInfo').innerHTML = '';
                    switchPage('dashboard', document.querySelector('[data-page=dashboard]'));
                } else {
                    showToast(res.data.message || 'Failed to create notice.', 'error');
                }
            } catch(err) {
                showToast('Network error. Please try again.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i><span>Publish Notice</span>';
            lucide.createIcons();
        });

        // --- Edit Modal ---
        async function openEditModal(id) {
            const res = await api('GET', 'get', null);
            // We need a direct get, use fetch directly
            const noticeRes = await fetch(API_BASE + 'notices.php?action=get&id=' + id, {
                headers: { 'X-Session-Token': ADMIN_TOKEN }
            });
            const data = await noticeRes.json();

            if (data.success) {
                document.getElementById('editNoticeId').value = data.data.notice.id;
                document.getElementById('editTitle').value = data.data.notice.title;
                document.getElementById('editContent').value = data.data.notice.content || '';
                document.getElementById('editPriority').value = data.data.notice.priority;
                document.getElementById('editStatus').value = data.data.notice.is_active ? '1' : '0';
                document.getElementById('editModal').classList.remove('hidden');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        document.getElementById('editNoticeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('editSubmitBtn');
            btn.disabled = true;

            const id = document.getElementById('editNoticeId').value;
            const body = {
                title: document.getElementById('editTitle').value,
                content: document.getElementById('editContent').value,
                priority: document.getElementById('editPriority').value,
                is_active: document.getElementById('editStatus').value === '1'
            };

            const res = await api('POST', 'update&id=' + id, body);
            if (res.success) {
                showToast('Notice updated successfully!', 'success');
                closeEditModal();
                loadNotices();
            } else {
                showToast(res.data.message || 'Failed to update notice.', 'error');
            }
            btn.disabled = false;
        });

        // --- Toggle Status ---
        async function toggleStatus(id, current) {
            const res = await api('POST', 'toggle&id=' + id);
            if (res.success) {
                loadNotices();
                showToast('Notice status updated.', 'success');
            }
        }

        // --- Delete ---
        async function confirmDelete(id) {
            if (!confirm('Are you sure you want to delete this notice? This action cannot be undone.')) return;
            const res = await api('POST', 'delete&id=' + id);
            if (res.success) {
                loadNotices();
                showToast('Notice deleted.', 'success');
            } else {
                showToast(res.data.message || 'Failed to delete.', 'error');
            }
        }

        // --- Logout ---
        async function logout() {
            await fetch(API_BASE + 'auth.php?action=logout', {
                method: 'POST',
                headers: { 'X-Session-Token': ADMIN_TOKEN }
            });
            window.location.href = '/dashboard/login.php';
        }

        // --- Toast ---
        function showToast(message, type = 'info') {
            const colors = {
                success: 'bg-leaf-600',
                error: 'bg-crimson-600',
                info: 'bg-saffron-600'
            };
            const el = document.createElement('div');
            el.className = `toast ${colors[type]} text-white px-4 py-3 rounded-xl shadow-lg text-sm font-medium flex items-center gap-2`;
            el.innerHTML = `
                <i data-lucide="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}" class="w-5 h-5"></i>
                ${escapeHtml(message)}
            `;
            document.getElementById('toastContainer').appendChild(el);
            lucide.createIcons();
            setTimeout(() => el.remove(), 4000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // --- Init ---
        lucide.createIcons();
        loadDashboard();
    </script>
</body>
</html>
