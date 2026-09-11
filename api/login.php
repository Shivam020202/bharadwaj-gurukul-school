<?php
/**
 * Bhardwaj Gurukul - Admin Dashboard Login
 */
session_start();
require_once __DIR__ . '/config.php';

$error = '';
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
    <link href="https://fonts.googleapis.com/css2?family=Forum&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/Bhardwaj-logo.png">
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
                    boxShadow: {
                        soft: '0 6px 24px -8px rgba(0,0,0,0.15)',
                        pop: '0 12px 30px -10px rgba(180,83,9,0.35)',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Forum', 'Poppins', system-ui, serif;
            background: #f8f6f1;
        }

        .login-bg {
            background: linear-gradient(135deg, #f59e0b15 0%, #22c55e10 50%, #ef444405 100%);
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
        }

        /* Loader */
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #fff3;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Floating shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
            pointer-events: none;
        }
    </style>
</head>

<body class="login-bg min-h-screen flex items-center justify-center px-4 py-12 relative overflow-hidden">

    <!-- Decorative shapes -->
    <div class="shape w-96 h-96 bg-saffron-500 -top-48 -left-48"></div>
    <div class="shape w-72 h-72 bg-leaf-500 -bottom-36 -right-36"></div>
    <div class="shape w-48 h-48 bg-crimson-500 top-1/4 right-1/4"></div>

    <div class="w-full max-w-md z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-8 flex flex-col items-center justify-center">
            <div class="inline-flex items-center gap-3 bg-white px-5 py-3 rounded-2xl shadow-soft mb-2">
                <img src="/logo.png" alt="Bhardwaj Gurukul Logo" class="h-16 w-auto object-contain">
                <div class="text-left">
                    <h1 class="text-2xl font-bold text-ink-900 leading-tight">Bhardwaj Gurukul</h1>
                    <p class="text-xs text-ink-600 font-semibold">Admin Dashboard</p>
                </div>
            </div>
        </div>

        <!-- Login Card -->
        <div class="login-card rounded-3xl shadow-pop p-8 border border-saffron-100">
            <h2 class="text-xl font-bold text-ink-900 mb-1">Welcome back</h2>
            <p class="text-sm text-ink-700 mb-6">Sign in to manage notices and updates</p>

            <div id="errorAlert" class="mb-5 p-3 rounded-xl bg-crimson-50 border border-crimson-200 text-crimson-700 text-sm flex items-center gap-2 hidden">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                <span id="errorText"></span>
            </div>

            <form method="POST" action="" id="loginForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div>
                    <label class="block text-sm font-bold text-ink-800 mb-2" for="username">Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-ink-500"></i>
                        <input type="text" id="username" name="username" required autocomplete="username"
                            class="w-full pl-10 pr-4 py-3 rounded-xl border-2 border-saffron-100 bg-white focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition text-ink-900"
                            placeholder="Enter your username">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-ink-800 mb-2" for="password">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-ink-500"></i>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full pl-10 pr-12 py-3 rounded-xl border-2 border-saffron-100 bg-white focus:border-saffron-500 focus:ring-2 focus:ring-saffron-200 outline-none transition text-ink-900"
                            placeholder="Enter your password">
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-700">
                            <i data-lucide="eye" id="eyeIcon" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full flex items-center justify-center gap-2 bg-saffron-500 hover:bg-saffron-600 text-white font-bold py-3 px-4 rounded-xl shadow-pop transition-all active:scale-[0.98]">
                    <span id="btnText">Sign In</span>
                    <div id="btnLoader" class="spinner hidden"></div>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-saffron-100">
                <div class="flex items-center gap-2 text-xs text-ink-600">
                    <i data-lucide="shield-check" class="w-4 h-4 text-leaf-600"></i>
                    <span>Secure login with session management</span>
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-ink-600 mt-6">
            &copy; 2026 Bhardwaj Gurukul. All rights reserved.
        </p>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();

        // Toggle password visibility
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        // Form submission with loading state and AJAX request
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const errorAlert = document.getElementById('errorAlert');
            const errorText = document.getElementById('errorText');

            errorAlert.classList.add('hidden');
            btn.disabled = true;
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            btnText.textContent = 'Signing in...';
            btnLoader.classList.remove('hidden');

            const formData = new FormData(this);
            const credentials = {
                username: formData.get('username'),
                password: formData.get('password'),
                csrf_token: formData.get('csrf_token')
            };

            try {
                const res = await fetch('/api/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(credentials)
                });
                const result = await res.json();

                if (result.success) {
                    if (result.data?.session?.token) {
                        document.cookie = `session_token=${result.data.session.token}; path=/; max-age=86400; SameSite=Lax`;
                        document.cookie = `admin_id=${result.data.admin.id}; path=/; max-age=86400; SameSite=Lax`;
                    }
                    window.location.href = '/dashboard/dashboard.php';
                } else {
                    errorText.textContent = result.data?.message || 'Login failed.';
                    errorAlert.classList.remove('hidden');
                    btn.disabled = false;
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');
                    btnText.textContent = 'Sign In';
                    btnLoader.classList.add('hidden');
                }
            } catch (err) {
                errorText.textContent = 'Connection error. Please try again.';
                errorAlert.classList.remove('hidden');
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed');
                btnText.textContent = 'Sign In';
                btnLoader.classList.add('hidden');
            }
        });

        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>
