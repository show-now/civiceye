 <?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';

// Upload multiple images
function uploadImages($files) {
    $uploaded = [];
    $targetDir = "uploads/";
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Check if files were uploaded
    if (isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $key => $name) {
            // Skip if no file was uploaded for this field
            if ($files['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                error_log("Upload error for file $name: " . $files['error'][$key]);
                continue;
            }
            
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","gif"];
            if (!in_array($ext, $allowed)) continue;
            
            $fileName = time() . "_" . uniqid() . "." . $ext;
            $targetFile = $targetDir . $fileName;
            
            if (move_uploaded_file($files["tmp_name"][$key], $targetFile)) {
                $uploaded[] = $targetFile;
            }
        }
    }
    return $uploaded;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {

    $citizen_name    = mysqli_real_escape_string($conn, trim($_POST['citizen_name']));
    $citizen_email   = mysqli_real_escape_string($conn, trim($_POST['citizen_email']));
    $citizen_phone   = mysqli_real_escape_string($conn, trim($_POST['citizen_phone']));
    $municipality_id = intval($_POST['municipality_id']);
    $category_id     = intval($_POST['category_id']);
    $location        = mysqli_real_escape_string($conn, trim($_POST['location']));
    $description     = mysqli_real_escape_string($conn, trim($_POST['description']));
    $status          = "Pending";

    $latitude  = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : 0;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : 0;

    $_SESSION['errors'] = [];

    if (empty($citizen_name) || empty($location) || empty($description) || $municipality_id <= 0 || $category_id <= 0) {
        $_SESSION['errors'][] = "Please fill all required fields.";
    }
    if (!empty($citizen_email) && !filter_var($citizen_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['errors'][] = "Invalid email address.";
    }

    $images = [];
    // Check if files were uploaded (more robust check)
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $images = uploadImages($_FILES['images']);
    }
    $images_json = mysqli_real_escape_string($conn, json_encode($images));

    if (empty($_SESSION['errors'])) {
        $sql = "INSERT INTO complaints 
        (citizen_name, citizen_email, citizen_phone, municipality_id, category_id, location, description, image, status, latitude, longitude, created_at)
        VALUES ('$citizen_name', '$citizen_email', '$citizen_phone', $municipality_id, $category_id, '$location', '$description', '$images_json', '$status', $latitude, $longitude, NOW())";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "✅ Complaint submitted successfully!";
            $complaint_id = mysqli_insert_id($conn);
            $_SESSION['share_link'] = "http://{$_SERVER['HTTP_HOST']}/view_complaint.php?id=$complaint_id";
        } else {
            $_SESSION['errors'][] = "Database error: " . mysqli_error($conn);
        }
    }

    header("Location: index.php");
    exit();
}

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$municipalities = mysqli_query($conn, "SELECT * FROM municipalities ORDER by name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CivicEye - Public Grievance Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
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
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(120deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
        }
        .header-gradient {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .step-connector {
            position: relative;
        }
        .step-connector:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 50px;
            height: 2px;
            background: linear-gradient(to right, #22c55e, #bbf7d0);
            transform: translateY(-50%);
        }
        @media (max-width: 768px) {
            .step-connector:after {
                display: none;
            }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen relative overflow-x-hidden">
    <!-- Animated background elements -->
    <div class="fixed inset-0 overflow-hidden z-0">
        <div class="absolute -top-20 -left-20 w-80 h-80 bg-primary-200 rounded-full opacity-30 animate-float"></div>
        <div class="absolute top-1/4 -right-20 w-64 h-64 bg-primary-300 rounded-full opacity-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-0 left-1/4 w-72 h-72 bg-primary-400 rounded-full opacity-15 animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-20 right-1/4 w-60 h-60 bg-primary-500 rounded-full opacity-10 animate-float" style="animation-delay: 3s;"></div>
    </div>


    <div class="relative z-10 container mx-auto px-4 py-8">

<!-- Navbar -->
<nav class="fixed top-4 left-4 z-50">
  <button onclick=" window.location.href='index.php';" 
          class="p-2 rounded-full bg-white shadow-md hover:bg-gray-100 transition">
    <svg xmlns="http://www.w3.org/2000/svg" 
         fill="none" 
         viewBox="0 0 24 24" 
         stroke="currentColor" 
         class="w-6 h-6 text-primary-600">
      <path stroke-linecap="round" 
            stroke-linejoin="round" 
            stroke-width="2" 
            d="M15 19l-7-7 7-7" />
    </svg>
  </button>
</nav>

        <!-- Header -->
        <header class="text-center mb-12 mt-6">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-white/80 shadow-lg mb-6 border-4 border-white">
                <img src="http://manager.ct.ws/static/civiceye.png" alt="logo"/>
            </div>
                        <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-3">Civic<span class="text-primary-600">Eye</span></h1>

            <p class="text-lg text-gray-600 max-w-2xl mx-auto">Report issues in your community and help make your neighborhood a better place to live</p>
        </header>

        <!-- Steps Indicator -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-10 relative">
            <div class="flex items-center mb-6 md:mb-0">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-600 text-white font-bold text-lg shadow-md">1</div>
                <div class="ml-4">
                    <div class="font-semibold text-gray-800">Complaint Details</div>
                    <div class="text-sm text-gray-500">Enter your information</div>
                </div>
            </div>
            
            <div class="step-connector hidden md:block"></div>
            
            <div class="flex items-center mb-6 md:mb-0">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-200 text-gray-500 font-bold text-lg">2</div>
                <div class="ml-4">
                    <div class="font-semibold text-gray-500">Review & Submit</div>
                    <div class="text-sm text-gray-400">Verify your details</div>
                </div>
            </div>
            
            <div class="step-connector hidden md:block"></div>
            
            <div class="flex items-center">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-200 text-gray-500 font-bold text-lg">3</div>
                <div class="ml-4">
                    <div class="font-semibold text-gray-500">Confirmation</div>
                    <div class="text-sm text-gray-400">Get tracking info</div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if(!empty($_SESSION['errors'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-md mb-8 animate__animated animate__shakeX">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-red-800 font-semibold">Please fix the following issues:</h3>
                    <ul class="mt-2 text-red-700 list-disc list-inside">
                        <?php foreach($_SESSION['errors'] as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['errors']); endif; ?>

        <?php if(!empty($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-md mb-8 animate__animated animate__fadeIn">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-green-800 font-semibold">Success!</h3>
                    <p class="mt-2 text-green-700"><?php echo $_SESSION['success']; ?></p>
                    <?php if(isset($_SESSION['share_link'])): ?>
                    <div class="mt-4">
                        <p class="text-green-700 text-sm">Share your complaint with this link:</p>
                        <a href="<?= $_SESSION['share_link'] ?>" target="_blank" class="inline-flex items-center mt-2 px-4 py-2 bg-white border border-green-300 rounded-lg text-green-700 font-medium hover:bg-green-50 transition-colors">
                            <i class="fas fa-link mr-2 text-green-600"></i> 
                            <span class="truncate max-w-xs"><?= $_SESSION['share_link'] ?></span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success'], $_SESSION['share_link']); endif; ?>

        <!-- Complaint Form -->
        <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl overflow-hidden card-hover">
            <form action="" method="POST" enctype="multipart/form-data" class="p-6 md:p-8">
                <div class="mb-10">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-user-circle text-primary-600 mr-3"></i> Your Information
                    </h2>
                    <p class="text-gray-500 mt-1">Please provide your contact details</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" name="citizen_name" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4" placeholder="Eg: Upendra" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" name="citizen_email" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4" placeholder="Eg: vijay@example.com">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input type="text" name="citizen_phone" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4" placeholder="+91 00000 00000">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-10 mb-10">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-map-marker-alt text-primary-600 mr-3"></i> Issue Details
                    </h2>
                    <p class="text-gray-500 mt-1">Tell us about the problem you've encountered</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Municipality *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-building text-gray-400"></i>
                                </div>
                                <select name="municipality_id" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4 appearance-none" required>
                                    <option value="">Select Municipality</option>
                                    <?php while($m = mysqli_fetch_assoc($municipalities)): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-tag text-gray-400"></i>
                                </div>
                                <select name="category_id" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4 appearance-none" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    // Reset pointer for categories to use again
                                    mysqli_data_seek($categories, 0);
                                    while($c = mysqli_fetch_assoc($categories)): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                        <div class="relative">
                            <div class="extremely inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-location-dot text-gray-400"></i>
                            </div>
                            <input type="text" name="location" id="location" class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4" placeholder="Enter the location of the issue" required>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea name="description" class="w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 py-3 px-4" rows="4" placeholder="Please describe the issue in detail..." required></textarea>
                        <div class="text-right text-sm text-gray-500 mt-2"><span id="char-count">0</span> characters</div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Images</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed border-gray-300 rounded-lg hover:border-primary-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <div class="flex justify-center">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-primary-500"></i>
                                </div>
                                <div class="flex text-sm text-gray-600">
                                    <label for="image-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none">
                                        <span>Upload files</span>
                                        <input id="image-upload" name="images[]" type="file" class="sr-only" accept="image/*" multiple>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB each</p>
                            </div>
                        </div>
                        <div id="file-preview" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4"></div>
                    </div>
                    
                    <div class="mt-6 bg-primary-50 rounded-lg p-4 border border-primary-200">
                        <div class="flex items-center">
                            <i class="fas fa-map-marked-alt text-primary-600 text-xl mr-3"></i>
                            <span class="text-primary-800">Location will be detected automatically when you submit</span>
                        </div>
                    </div>
                    
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                </div>

                <div class="flex justify-center mt-8">
                    <button type="submit" name="submit_complaint" class="group relative flex justify-center items-center py-3 px-8 border border-transparent text-lg font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 shadow-md hover:shadow-lg transition-all">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Complaint
                    </button>
                </div>
            </form>
        </div>

        <footer class="text-center mt-12 mb-6 text-gray-500 text-sm">
            <p> <?php echo date('Y'); ?> CivicEye - Public Grievance Portal. Helping communities thrive.</p>
        </footer>
    </div>

    <script>
        // Character counter for description
        const descriptionTextarea = document.querySelector('textarea[name="description"]');
        const charCounter = document.getElementById('char-count');

        if (descriptionTextarea && charCounter) {
            descriptionTextarea.addEventListener('input', function() {
                charCounter.textContent = this.value.length;
            });
        }

        // File upload preview
        const fileInput = document.getElementById('image-upload');
        const filePreview = document.getElementById('file-preview');

        if (fileInput && filePreview) {
            fileInput.addEventListener('change', function() {
                filePreview.innerHTML = '';
                
                if (this.files.length > 0) {
                    for (let i = 0; i < this.files.length; i++) {
                        const file = this.files[i];
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'relative group';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = file.name;
                            img.className = 'rounded-lg h-32 w-full object-cover shadow-md';
                            
                            const removeBtn = document.createElement('button');
                            removeBtn.className = 'absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity';
                            removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
                            removeBtn.onclick = function(e) {
                                e.preventDefault();
                                previewItem.remove();
                            };
                            
                            previewItem.appendChild(img);
                            previewItem.appendChild(removeBtn);
                            filePreview.appendChild(previewItem);
                        };
                        
                        reader.readAsDataURL(file);
                    }
                }
            });
        }

        // Get user location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                },
                function(err) {
                    console.log("Location not detected:", err);
                }
            );
        }
    </script>
</body>
</html