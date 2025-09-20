 <?php
session_start();
include 'db.php';

$ADMIN_USER = "admin";
$ADMIN_PASS = "admin";

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-lt-installed="true">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CivicEye Admin - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'civic-green': {
              100: '#dcfce7',
              200: '#bbf7d0',
              300: '#86efac',
              400: '#4ade80',
              500: '#22c55e',
              600: '#16a34a',
              700: '#15803d',
              800: '#166534',
              900: '#14532d',
            }
          },
          animation: {
            'float': 'float 6s ease-in-out infinite',
            'fade-in': 'fadeIn 0.5s ease-out forwards',
            'shake': 'shake 0.5s ease-in-out',
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    * {
      font-family: 'Inter', sans-serif;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .password-toggle {
      cursor: pointer;
      transition: all 0.3s;
    }

    .password-toggle:hover {
      color: #16a34a;
    }

    .particle {
      position: absolute;
      background: rgba(34, 197, 94, 0.15);
      border-radius: 50%;
      pointer-events: none;
    }

    .input-highlight {
      transition: all 0.3s;
      box-shadow: 0 0 0px 0px rgba(34, 197, 94, 0);
    }

    input:focus + .input-highlight {
      box-shadow: 0 0 15px 4px rgba(34, 197, 94, 0.3);
    }

    .theme-toggle {
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .language-selector {
      transition: all 0.3s;
    }

    .language-selector:hover {
      transform: scale(1.05);
    }

    .remember-forgot {
      transition: all 0.3s;
    }

    .floating-label {
      position: absolute;
      pointer-events: none;
      left: 2.5rem;
      top: 0.75rem;
      transition: 0.2s ease all;
      color: #9ca3af;
      font-size: 0.875rem;
    }

    input:focus ~ .floating-label,
    input:not(:placeholder-shown) ~ .floating-label {
      top: -0.5rem;
      left: 0.75rem;
      font-size: 0.75rem;
      background: linear-gradient(to bottom, rgb(255 255 255 / 0.7), white);
      padding: 0 0.5rem;
      color: #16a34a;
    }

    .dark input:focus ~ .floating-label,
    .dark input:not(:placeholder-shown) ~ .floating-label {
      background: linear-gradient(to bottom, rgb(55 65 81 / 0.7), rgb(55 65 81));
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-civic-green-200 via-white to-civic-green-100 dark:from-gray-800 dark:via-gray-900 dark:to-gray-800 transition-colors duration-500">
  <!-- Background particles -->
  <div id="particles-container" class="fixed inset-0 pointer-events-none z-0"></div>

  <!-- Theme toggle -->
  <button id="theme-toggle" class="fixed top-6 right-6 z-50 w-12 h-12 rounded-full bg-civic-green-500 dark:bg-gray-700 shadow-lg flex items-center justify-center text-white theme-toggle hover:scale-110">
    <i class="fas fa-moon"></i>
    <i class="fas fa-sun hidden"></i>
  </button>

  <!-- Language selector -->
  <div class="fixed top-6 left-6 z-50">
    <select id="language-selector" class="language-selector bg-white/80 dark:bg-gray-700/80 dark:text-white rounded-full py-2 px-4 text-sm shadow-md outline-none cursor-pointer">
      <option value="en">English</option>
      <option value="es">Kannada</option>
      <option value="fr">Tamil</option>
      <option value="de">Hindi</option>
    </select>
  </div>

  <div class="w-full max-w-sm bg-white/70 dark:bg-gray-800/70 backdrop-blur-xl rounded-3xl shadow-2xl p-8 animate-fade-in relative z-10">
    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <div class="w-20 h-20 flex items-center justify-center rounded-full shadow-lg text-white text-3xl animate-float">
        <img src="/static/civiceye.png">
      </div>
    </div>

    <!-- Title -->
    <h1 class="text-center text-2xl font-bold text-civic-green-700 dark:text-civic-green-300">CivicEye Admin</h1>
    <p class="text-center text-gray-500 dark:text-gray-300 mb-6" id="login-subtitle">Login to manage complaints</p>

    <!-- Error message -->
    <?php if ($error): ?>
      <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg text-center font-medium animate-shake">
        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form id="login-form"  method="POST" action="" class="space-y-4">
      <!-- Username -->
      <div class="relative">
        <span class="absolute left-3 top-3 text-gray-400 dark:text-gray-500"><i class="fas fa-user"></i></span>
        <input type="text" name="username" required placeholder=" " class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700/50 dark:text-white rounded-lg focus:ring-2 focus:ring-civic-green-400 focus:border-civic-green-400 outline-none transition">
        <label class="floating-label">Username</label>
        <div class="input-highlight absolute inset-0 rounded-lg pointer-events-none"></div>
      </div>

      <!-- Password -->
      <div class="relative">
        <span class="absolute left-3 top-3 text-gray-400 dark:text-gray-500"><i class="fas fa-lock"></i></span>
        <input type="password" id="password-input" name="password" required placeholder=" " class="w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700/50 dark:text-white rounded-lg focus:ring-2 focus:ring-civic-green-400 focus:border-civic-green-400 outline-none transition">
        <label class="floating-label">Password</label>
        <span class="password-toggle absolute right-3 top-3 text-gray-400 dark:text-gray-500" id="password-toggle">
          <i class="fas fa-eye"></i>
        </span>
        <div class="input-highlight absolute inset-0 rounded-lg pointer-events-none"></div>
      </div>

      <!-- Remember me & Forgot password -->
      <div class="flex items-center justify-between remember-forgot">
        <div class="flex items-center">
          <input type="checkbox" id="remember-me" name="remember" class="h-4 w-4 text-civic-green-600 focus:ring-civic-green-500 border-gray-300 rounded">
          <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Remember me</label>
        </div>
        <a href="#" class="text-sm text-civic-green-600 dark:text-civic-green-400 hover:text-civic-green-800 dark:hover:text-civic-green-300">Forgot password?</a>
      </div>

      <!-- Button -->
      <button type="submit" class="w-full py-3 bg-civic-green-600 hover:bg-civic-green-700 text-white rounded-lg font-semibold shadow-md transform transition hover:scale-105 active:scale-95 flex items-center justify-center">
        <span id="button-text">Sign In</span>
        <div id="button-spinner" class="ml-2 hidden">
          <i class="fas fa-circle-notch fa-spin"></i>
        </div>
      </button>
    </form>

    <!-- Footer -->
    <p class="mt-6 text-center text-gray-500 dark:text-gray-400 text-sm">CivicEye Tracker © <span id="current-year"></span></p>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Set current year
      document.getElementById('current-year').textContent = new Date().getFullYear();

      // Theme toggle functionality
      const themeToggleBtn = document.getElementById('theme-toggle');
      const moonIcon = themeToggleBtn.querySelector('.fa-moon');
      const sunIcon = themeToggleBtn.querySelector('.fa-sun');
      const htmlElement = document.documentElement;

      // Check for saved theme preference or respect OS preference
      const savedTheme = localStorage.getItem('theme');
      const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

      if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        htmlElement.classList.add('dark');
        moonIcon.classList.add('hidden');
        sunIcon.classList.remove('hidden');
      } else {
        htmlElement.classList.remove('dark');
        moonIcon.classList.remove('hidden');
        sunIcon.classList.add('hidden');
      }

      // Toggle theme on button click
      themeToggleBtn.addEventListener('click', () => {
        htmlElement.classList.toggle('dark');

        if (htmlElement.classList.contains('dark')) {
          localStorage.setItem('theme', 'dark');
          moonIcon.classList.add('hidden');
          sunIcon.classList.remove('hidden');
        } else {
          localStorage.setItem('theme', 'light');
          moonIcon.classList.remove('hidden');
          sunIcon.classList.add('hidden');
        }
      });

      // Password visibility toggle
      const passwordInput = document.getElementById('password-input');
      const passwordToggle = document.getElementById('password-toggle');

      passwordToggle.addEventListener('click', () => {
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          passwordToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
          passwordInput.type = 'password';
          passwordToggle.innerHTML = '<i class="fas fa-eye"></i>';
        }
      });

      // Form submission with validation
      const loginForm = document.getElementById('login-form');
      const errorMessage = document.getElementById('error-message');
      const successMessage = document.getElementById('success-message');
      const buttonText = document.getElementById('button-text');
      const buttonSpinner = document.getElementById('button-spinner');
/*
      loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const username = this.username.value.trim();
        const password = this.password.value.trim();

        // Simple validation
        if (!username || !password) {
          showError('Please fill in all fields');
          return;
        }

        // Show loading state
        buttonText.textContent = 'Signing In...';
        buttonSpinner.classList.remove('hidden');
        this.querySelector('button').disabled = true;

        // Simulate API call (replace with actual authentication)
        setTimeout(() => {
          // Demo credentials for demonstration
          if (username === 'admin' && password === 'password') {
            showSuccess('Login successful! Redirecting...');

            // Simulate redirect
            setTimeout(() => {
              // In a real application, you would redirect to the dashboard
              alert('Redirecting to dashboard...');
              buttonText.textContent = 'Sign In';
              buttonSpinner.classList.add('hidden');
              loginForm.querySelector('button').disabled = false;a
            }, 1500);
          } else {
            showError('Invalid username or password');
            loginForm.classList.add('animate-shake');
            buttonText.textContent = 'Sign In';
            buttonSpinner.classList.add('hidden');
            this.querySelector('button').disabled = false;

            // Remove shake animation after it completes
            setTimeout(() => {
              loginForm.classList.remove('animate-shake');
            }, 500);
          }
        }, 1500);
      });
*/
      function showError(message) {
        document.getElementById('error-text').textContent = message;
        errorMessage.classList.remove('hidden');
        successMessage.classList.add('hidden');
      }

      function showSuccess(message) {
        document.getElementById('success-text').textContent = message;
        successMessage.classList.remove('hidden');
        errorMessage.classList.add('hidden');
      }

      // Create background particles
      createParticles();

      function createParticles() {
        const container = document.getElementById('particles-container');
        const particleCount = 100;

        for (let i = 0; i < particleCount; i++) {
          const particle = document.createElement('div');
          particle.classList.add('particle');

          // Random size and position
          const size = Math.random() * 20 + 5;
          const posX = Math.random() * 100;
          const posY = Math.random() * 100;

          particle.style.width = `${size}px`;
          particle.style.height = `${size}px`;
          particle.style.left = `${posX}vw`;
          particle.style.top = `${posY}vh`;
          particle.style.opacity = Math.random() * 0.5 + 0.1;

          // Animation
          particle.style.animation = `float ${Math.random() * 10 + 5}s ease-in-out infinite`;
          particle.style.animationDelay = `${Math.random() * 5}s`;

          container.appendChild(particle);
        }
      }

      // Language selector (demo functionality)
      document.getElementById('language-selector').addEventListener('change', function() {
        // In a real application, this would change the language of the page
        alert(`Language changed to ${this.options[this.selectedIndex].text}`);
      });
    });
  </script>
</body>
</html