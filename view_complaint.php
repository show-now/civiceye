 <?php
include 'db.php';

if(!isset($_GET['id'])) die("Complaint ID missing.");
$id = intval($_GET['id']);

// Fetch complaint
$sql = "SELECT c.*, m.name as municipality_name, cat.name as category_name
        FROM complaints c 
        LEFT JOIN municipalities m ON c.municipality_id = m.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id=$id";
$res = $conn->query($sql);
if($res->num_rows==0) die("Complaint not found.");
$c = $res->fetch_assoc();

// Citizen images (JSON array)
$citizen_images = json_decode($c['image'], true);

// Resolver images (optional JSON array, in case multiple)
$resolver_images = [];
if(!empty($c['resolver_image'])) {
    $tmp = json_decode($c['resolver_image'], true);
    if($tmp) $resolver_images = $tmp;
    else $resolver_images[] = $c['resolver_image']; // fallback single image
}

// Format dates
$created_date = date('F j, Y, g:i a', strtotime($c['created_at']));
$updated_date = !empty($c['updated_at']) ? date('F j, Y, g:i a', strtotime($c['updated_at'])) : 'N/A';
$closed_date = !empty($c['closed_at']) ? date('F j, Y, g:i a', strtotime($c['closed_at'])) : 'N/A';

// Status badge color
$status_colors = [
    'Pending' => 'warning',
    'Open' => 'primary',
    'In Progress' => 'info',
    'Resolved' => 'success',
    'Closed' => 'secondary'
];
$status_color = $status_colors[$c['status']] ?? 'secondary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Report #<?= $c['id'] ?> - CivicEye</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
:root {
    --primary-color: #22c55e;   /* main green */
    --primary-light: #4ade80;  /* lighter accent green */
    --success-color: #16a34a;  /* deep green for success */
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --light-bg: #f0fdf4;       /* lightest green bg */
    --border-color: #bbf7d0;   /* soft green border */
    --text-dark: #064e3b;      /* dark green text */
}


        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #1e293b;
            line-height: 1.6;
            min-height: 100vh;
        }

        .report-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 15px;
        }

        .report-header {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            color: white;
            padding: 2.5rem;
            border-radius: 16px 16px 0 0;
            text-align: center;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.2);
        }

        .report-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .report-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }

        .report-body {
            background: white;
            padding: 2.5rem;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .status-badge {
            font-size: 1.1rem;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .info-card {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 2rem 0 1.5rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .description-box {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--success-color);
            line-height: 1.8;
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .gallery-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }

        .gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.75rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .map-container {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
        }

        .map-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .map-button:hover {
            background: #1d4ed8;
            color: white;
        }

        .timestamp {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .print-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        @media print {
            body {
                background: white !important;
            }
            .report-header {
                box-shadow: none !important;
            }
            .print-button {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .report-title {
                font-size: 2rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .image-gallery {
                grid-template-columns: 1fr;
            }
        }

            :root {
        --green-light: #f0fdf4;
        --green-mid: #dcfce7;
        --green-accent: #bbf7d0;
        --text-dark: #064e3b;
    }

    /* Navbar */
    .navbar-custom {
        background: var(--green-light);
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .back-btn {
        background: var(--green-accent);
        border: none;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .back-btn:hover {
        background: var(--green-mid);
    }

    .back-btn .icon {
        width: 20px;
        height: 20px;
        stroke: var(--text-dark);
    }

    /* Report Header Green Gradient */
    .report-header {
        background: linear-gradient(135deg, var(--green-mid), var(--green-accent));
        color: var(--text-dark);
    }

    .report-title {
        color: var(--text-dark);
    }

    .status-badge {
        background: var(--green-light) !important;
        color: var(--text-dark) !important;
        border: 1px solid var(--green-accent);
    }
/* Report Header */
.report-header {
    background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
    color: var(--text-dark);
}

/* Section Titles */
.section-title {
    color: var(--primary-color);
}

/* Info Cards */
.info-card {
    border-left: 4px solid var(--primary-color);
}

/* Description Box */
.description-box {
    border-left: 4px solid var(--success-color);
}

/* Map Button */
.map-button {
    background: var(--primary-color);
    color: white;
}
.map-button:hover {
    background: var(--success-color);
}

/* Print Button */
.print-button {
    background: var(--primary-color);
}
.print-button:hover {
    background: var(--success-color);
}

/* Status Badge */
.status-badge {
    background: var(--light-bg) !important;
    color: var(--text-dark) !important;
    border: 1px solid var(--border-color);
}

    </style>
</head>
<body>

<nav class="navbar-custom"><a href="index.php">
  <button onclick="" class="back-btn">
    <svg xmlns="http://www.w3.org/2000/svg" 
         fill="none" 
         viewBox="0 0 24 24" 
         stroke="currentColor" 
         class="icon">
      <path stroke-linecap="round" 
            stroke-linejoin="round" 
            stroke-width="2" 
            d="M15 19l-7-7 7-7" />
    </svg>
    </a>
  </button>
</nav>


    <div class="report-container">
        <!-- Report Header -->
        <div class="report-header">
            <h1 class="report-title">Complaint Report</h1>
            <p class="report-subtitle">CivicEye - Public Grievance Portal</p>
            <span class="status-badge bg-<?= $status_color ?>">
                <i class="fas fa-<?= $c['status'] == 'Resolved' ? 'check-circle' : 'clock' ?> me-2"></i>
                Status: <?= htmlspecialchars($c['status']) ?>
            </span>
        </div>

        <!-- Report Body -->
        <div class="report-body">
            <!-- Basic Information -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Complaint ID</span>
                    <span class="info-value">#<?= $c['id'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Report Date</span>
                    <span class="info-value"><?= $created_date ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Citizen Name</span>
                    <span class="info-value"><?= htmlspecialchars($c['citizen_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Email</span>
                    <span class="info-value"><?= !empty($c['citizen_email']) ? htmlspecialchars($c['citizen_email']) : 'Not provided' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Phone</span>
                    <span class="info-value"><?= !empty($c['citizen_phone']) ? htmlspecialchars($c['citizen_phone']) : 'Not provided' ?></span>
                </div>
            </div>

            <!-- Complaint Details -->
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Complaint Details
            </h3>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Municipality</span>
                    <span class="info-value"><?= htmlspecialchars($c['municipality_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Category</span>
                    <span class="info-value"><?= !empty($c['category_name']) ? htmlspecialchars($c['category_name']) : 'Not specified' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?= htmlspecialchars($c['location']) ?></span>
                </div>
            </div>

            <!-- Description -->
            <h3 class="section-title">
                <i class="fas fa-align-left"></i>
                Issue Description
            </h3>

            <div class="description-box">
                <?= nl2br(htmlspecialchars($c['description'])) ?>
            </div>

            <!-- Citizen Images -->
            <?php if(!empty($citizen_images)): ?>
            <h3 class="section-title">
                <i class="fas fa-camera"></i>
                Evidence Photos Submitted
            </h3>

            <div class="image-gallery">
                <?php foreach($citizen_images as $index => $img): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars($img) ?>" alt="Evidence photo <?= $index + 1 ?>">
                    <div class="gallery-caption">Photo <?= $index + 1 ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Resolver Images -->
            <?php if(!empty($resolver_images)): ?>
            <h3 class="section-title">
                <i class="fas fa-check-circle"></i>
                Resolution Documentation
            </h3>

            <div class="image-gallery">
                <?php foreach($resolver_images as $index => $img): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars($img) ?>" alt="Resolution photo <?= $index + 1 ?>">
                    <div class="gallery-caption">Resolution <?= $index + 1 ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Location -->
            <?php if(!empty($c['latitude']) && !empty($c['longitude'])): ?>
            <h3 class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Location Coordinates
            </h3>

            <div class="map-container">
                <p class="info-value mb-3"><?= $c['latitude'] ?>, <?= $c['longitude'] ?></p>
                <a href="https://maps.google.com/?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>" 
                   target="_blank" class="map-button">
                    <i class="fas fa-external-link-alt"></i>
                    View on Google Maps
                </a>
            </div>
            <?php endif; ?>

            <!-- Timestamps -->
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Timeline
            </h3>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Report Created</span>
                    <span class="info-value"><?= $created_date ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?= $updated_date ?></span>
                </div>
                <?php if(!empty($c['closed_at'])): ?>
                <div class="info-item">
                    <span class="info-label">Date Resolved</span>
                    <span class="info-value"><?= $closed_date ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Print Button -->
    <div class="print-button" onclick="window.print()">
        <i class="fas fa-print fa-lg"></i>
    </div>

    <script>
        // Add subtle animations
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.info-item, .gallery-item, .section-title');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = `opacity 0.6s ease, transform 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 100);
            });
        });
    </script>
</body>
</html