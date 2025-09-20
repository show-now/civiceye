 <?php
// Database configuration
include("db.php");

// Function to get municipality performance data
function getMunicipalityPerformance($conn) {
    $query = "
        SELECT
            m.id,
            m.name AS municipality_name,
            COUNT(c.id) AS total_complaints,
            SUM(CASE WHEN c.status = 'Closed' THEN 1 ELSE 0 END) AS resolved_complaints,
            AVG(CASE WHEN c.status = 'Closed' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.closed_at) ELSE NULL END) AS avg_resolution_hours
        FROM municipalities m
        LEFT JOIN complaints c ON m.id = c.municipality_id
        GROUP BY m.id, m.name
        HAVING total_complaints > 0
        ORDER BY avg_resolution_hours ASC, resolved_complaints DESC
    ";

    $result = mysqli_query($conn, $query);
    $municipalities = [];

    while($row = mysqli_fetch_assoc($result)) {
        $resolution_rate = $row['total_complaints'] > 0
            ? ($row['resolved_complaints'] / $row['total_complaints']) * 100
            : 0;

        $municipalities[] = [
            'id' => $row['id'],
            'name' => $row['municipality_name'],
            'total_complaints' => $row['total_complaints'],
            'resolved_complaints' => $row['resolved_complaints'],
            'resolution_rate' => round($resolution_rate, 1),
            'avg_resolution_hours' => $row['avg_resolution_hours'] ? round($row['avg_resolution_hours'], 1) : 'N/A'
        ];
    }

    return $municipalities;
}

// Get performance data
$municipalities = getMunicipalityPerformance($conn);

// Separate into Hall of Fame (top performers) and Hall of Shame (poor performers)
$hall_of_fame = array_slice($municipalities, 0, min(3, count($municipalities)));
$hall_of_shame = count($municipalities) > 3 ? array_slice($municipalities, -3, 3) : [];
$other_municipalities = count($municipalities) > 6 ? array_slice($municipalities, 3, count($municipalities) - 6) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CivicEye - Municipal Performance Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>  <!-- ADD THIS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --success-light: #34d399;
            --warning-color: #f59e0b;
            --warning-light: #fbbf24;
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --gray-color: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 30px 0;
            text-align: center;
            border-radius: 16px;
            margin-bottom: 40px;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
            position: relative;
            overflow: hidden;
        }

        header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        h1 {
            font-size: 2.8rem;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.5px;
            position: relative;
        }

        .subtitle {
            font-size: 1.3rem;
            opacity: 0.95;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (min-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            padding: 25px;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fame-header {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .shame-header {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .card-body {
            padding: 25px;
        }

        .municipality-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 15px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }

        .municipality-item:hover {
            background-color: #f8fafc;
        }

        .municipality-item:last-child {
            border-bottom: none;
        }

        .municipality-info {
            flex: 1;
        }

        .municipality-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .municipality-stats {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
        }

        .stat {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 80px;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--gray-color);
        }

        .resolution-rate {
            color: var(--success-color);
        }

        .resolution-time {
            color: var(--primary-color);
        }

        .rank {
            font-size: 1.4rem;
            font-weight: 700;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-right: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .rank-1 {
            background: linear-gradient(135deg, #fcd34d, #f59e0b);
            color: white;
        }

        .rank-2 {
            background: linear-gradient(135deg, #e5e7eb, #9ca3af);
            color: var(--dark-color);
        }

        .rank-3 {
            background: linear-gradient(135deg, #b45309, #d97706);
            color: white;
        }

        .shame-rank {
            background: linear-gradient(135deg, var(--danger-color), #b91c1c);
            color: white;
        }

        .other-municipalities {
            margin-top: 50px;
        }

        .other-header {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
            padding: 20px 25px;
            font-size: 1.3rem;
            font-weight: 600;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .other-list {
            background: white;
            border-radius: 0 0 16px 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .other-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            padding: 18px 15px;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .other-item:last-child {
            border-bottom: none;
        }

        .other-item-header {
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            background-color: #f8fafc;
            border-radius: 8px 8px 0 0;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 40px;
            flex-wrap: wrap;
            padding: 25px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .legend-color {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }

        .legend-resolved {
            background: var(--success-color);
        }

        .legend-time {
            background: var(--primary-color);
        }

        .legend-rate {
            background: var(--warning-color);
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-color);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-data p {
            font-size: 1.1rem;
        }

        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 0.95rem;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .connection-success {
            background: var(--success-color);
            color: white;
        }

        .connection-error {
            background: var(--danger-color);
            color: white;
        }

        .performance-indicator {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 10px;
        }

        .performance-good {
            background: var(--success-light);
            color: #065f46;
        }

        .performance-average {
            background: var(--warning-light);
            color: #92400e;
        }

        .performance-poor {
            background: var(--danger-light);
            color: #991b1b;
        }

        .data-refresh {
            text-align: center;
            margin-top: 30px;
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .municipality-stats {
                flex-direction: column;
                gap: 8px;
            }
            
            .other-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .legend {
                flex-direction: column;
                gap: 15px;
            }
            
            h1 {
                font-size: 2.2rem;
            }
            
            .subtitle {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
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

    <div class="container">
        <header>
            <h1>CivicEye Performance Dashboard</h1>
            <p class="subtitle">Comprehensive analysis of municipal efficiency in civic issue resolution</p>
        </header>

        <div class="dashboard">
            <!-- Hall of Fame -->
            <div class="card">
                <div class="card-header fame-header">
                    <i class="fas fa-trophy"></i>
                    <h2>Hall of Fame</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($hall_of_fame)): ?>
                        <?php foreach ($hall_of_fame as $index => $municipality): ?>
                            <div class="municipality-item">
                                <div class="rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                                <div class="municipality-info">
                                    <div class="municipality-name"><?php echo htmlspecialchars($municipality['name']); ?></div>
                                    <div class="municipality-stats">
                                        <div class="stat">
                                            <span class="stat-value resolution-rate"><?php echo $municipality['resolution_rate']; ?>%</span>
                                            <span class="stat-label">Resolution Rate</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-value resolution-time"><?php echo $municipality['avg_resolution_hours']; ?>h</span>
                                            <span class="stat-label">Avg. Time</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-value"><?php echo $municipality['resolved_complaints']; ?>/<?php echo $municipality['total_complaints']; ?></span>
                                            <span class="stat-label">Completed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-line"></i>
                            <p>No performance data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hall of Shame -->
            <div class="card">
                <div class="card-header shame-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hall Of Shame!</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($hall_of_shame)): ?>
                        <?php foreach (array_reverse($hall_of_shame) as $index => $municipality): ?>
                            <div class="municipality-item">
                                <div class="rank shame-rank"><?php echo count($municipalities) - $index; ?></div>
                                <div class="municipality-info">
                                    <div class="municipality-name"><?php echo htmlspecialchars($municipality['name']); ?></div>
                                    <div class="municipality-stats">
                                        <div class="stat">
                                            <span class="stat-value" style="color: <?php echo $municipality['resolution_rate'] < 50 ? 'var(--danger-color)' : 'var(--warning-color)'; ?>">
                                                <?php echo $municipality['resolution_rate']; ?>%
                                            </span>
                                            <span class="stat-label">Resolution Rate</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-value" style="color: var(--danger-color);">
                                                <?php echo $municipality['avg_resolution_hours'] === 'N/A' ? 'N/A' : $municipality['avg_resolution_hours'] . 'h'; ?>
                                            </span>
                                            <span class="stat-label">Avg. Time</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-value"><?php echo $municipality['resolved_complaints']; ?>/<?php echo $municipality['total_complaints']; ?></span>
                                            <span class="stat-label">Completed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-line"></i>
                            <p>No performance data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Other Municipalities -->
        <?php if (!empty($other_municipalities)): ?>
        <div class="other-municipalities">
            <div class="other-header">
                <i class="fas fa-list-alt"></i>
                <h2>Other Municipalities Performance</h2>
            </div>
            <div class="other-list">
                <div class="other-item other-item-header">
                    <div>Municipality Name</div>
                    <div>Resolution Rate</div>
                    <div>Avg. Resolution Time</div>
                </div>
                <?php foreach ($other_municipalities as $municipality): 
                    $performanceClass = '';
                    if ($municipality['resolution_rate'] >= 80) {
                        $performanceClass = 'performance-good';
                    } elseif ($municipality['resolution_rate'] >= 50) {
                        $performanceClass = 'performance-average';
                    } else {
                        $performanceClass = 'performance-poor';
                    }
                ?>
                    <div class="other-item">
                        <div>
                            <?php echo htmlspecialchars($municipality['name']); ?>
                            <span class="performance-indicator <?php echo $performanceClass; ?>">
                                <?php 
                                if ($municipality['resolution_rate'] >= 80) echo 'Excellent';
                                elseif ($municipality['resolution_rate'] >= 50) echo 'Average';
                                else echo 'Needs Attention';
                                ?>
                            </span>
                        </div>
                        <div><strong><?php echo $municipality['resolution_rate']; ?>%</strong></div>
                        <div><strong><?php echo $municipality['avg_resolution_hours']; ?> hours</strong></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-color legend-resolved"></div>
                <span>High Resolution Rate (Good Performance)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-time"></div>
                <span>Average Resolution Time (Efficiency)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-rate"></div>
                <span>Performance Rating Indicator</span>
            </div>
        </div>

        <div class="data-refresh">
            <i class="fas fa-sync-alt"></i> Data updated automatically. Last refresh: <?php echo date('M j, Y g:i A'); ?>
        </div>
    </div>

    <script>
        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease, transform 0.6s ease ${index * 0.2}s`;

                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });

            // Animate municipality items
            const items = document.querySelectorAll('.municipality-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                item.style.transition = `opacity 0.4s ease, transform 0.4s ease ${index * 0.1}s`;

                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 300);
            });

            // Add hover effects to stats
            const stats = document.querySelectorAll('.stat');
            stats.forEach(stat => {
                stat.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                stat.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>
<?php
// Close the database connection
mysqli_close($conn);
?