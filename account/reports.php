<?php
$pageTitle = 'Reports & Analytics';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require user to be logged in
requireLogin();

$userId = $_SESSION['user_id'];
$user = getUserById($userId);

// Check subscription status
if (!isSubscriptionActive($user)) {
    $_SESSION['message'] = 'Your subscription is inactive. Please subscribe to access reports.';
    $_SESSION['message_type'] = 'error';
    redirect(SITE_URL . '/account/billing.php');
}

// Get widget_id for this user
$widgetId = isset($user['widget_id']) ? $user['widget_id'] : null;

// Set default date range (last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Handle date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    
    // Validate dates
    if (!validateDate($startDate) || !validateDate($endDate)) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
    }
}

// Function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Report data
$visitorCount = 0;
$messageCount = 0;
$averageResponseTime = 0;
$conversationCount = 0;
$dailyStats = [];
$topPages = [];
$visitorSources = [];
$conversionRate = 0;

try {
    // Total visitor count
    $visitorQuery = $db->fetch(
        "SELECT COUNT(*) as count FROM visitors WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date",
        [
            'user_id' => $userId,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        ]
    );
    $visitorCount = $visitorQuery ? $visitorQuery['count'] : 0;
    
    // Total message count
    $messageQuery = $db->fetch(
        "SELECT COUNT(*) as count FROM messages WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date",
        [
            'user_id' => $userId,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        ]
    );
    $messageCount = $messageQuery ? $messageQuery['count'] : 0;
    
    // Get conversation count (visitors with messages)
    $conversationQuery = $db->fetch(
        "SELECT COUNT(DISTINCT visitor_id) as count FROM messages 
         WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date",
        [
            'user_id' => $userId,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        ]
    );
    $conversationCount = $conversationQuery ? $conversationQuery['count'] : 0;
    
    // Calculate conversion rate (visitors who sent messages)
    if ($visitorCount > 0) {
        $conversionRate = round(($conversationCount / $visitorCount) * 100, 2);
    }
    
    // Calculate average response time
    // This is complex as we need to find pairs of visitor and agent messages
    $responseTimeQuery = $db->query(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, v.created_at, a.created_at)) as avg_response_time
         FROM messages v
         JOIN messages a ON v.visitor_id = a.visitor_id AND v.user_id = a.user_id
         WHERE v.user_id = ? 
         AND v.sender_type = 'visitor' 
         AND a.sender_type = 'agent'
         AND a.created_at > v.created_at
         AND v.created_at BETWEEN ? AND ?
         AND NOT EXISTS (
             SELECT 1 FROM messages m
             WHERE m.visitor_id = v.visitor_id
             AND m.user_id = v.user_id
             AND m.created_at > v.created_at
             AND m.created_at < a.created_at
         )",
        [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    
    $avgResponseResult = $responseTimeQuery->fetch(PDO::FETCH_ASSOC);
    $averageResponseTime = $avgResponseResult && $avgResponseResult['avg_response_time'] ? $avgResponseResult['avg_response_time'] : 0;
    
    // Get daily stats for chart
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    $daysCount = ceil(($endTimestamp - $startTimestamp) / 86400);
    
    // Initialize dates array with all dates in range
    $dates = [];
    for ($i = 0; $i < $daysCount; $i++) {
        $currentDate = date('Y-m-d', strtotime("+{$i} days", $startTimestamp));
        $dates[$currentDate] = [
            'date' => $currentDate,
            'visitors' => 0,
            'messages' => 0
        ];
    }
    
    // Get visitor counts by day
    $visitorsByDayQuery = $db->query(
        "SELECT DATE(created_at) as date, COUNT(*) as count 
         FROM visitors 
         WHERE user_id = ? 
         AND created_at BETWEEN ? AND ? 
         GROUP BY DATE(created_at)",
        [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    
    while ($row = $visitorsByDayQuery->fetch(PDO::FETCH_ASSOC)) {
        if (isset($dates[$row['date']])) {
            $dates[$row['date']]['visitors'] = (int)$row['count'];
        }
    }
    
    // Get message counts by day
    $messagesByDayQuery = $db->query(
        "SELECT DATE(created_at) as date, COUNT(*) as count 
         FROM messages 
         WHERE user_id = ? 
         AND created_at BETWEEN ? AND ? 
         GROUP BY DATE(created_at)",
        [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    
    while ($row = $messagesByDayQuery->fetch(PDO::FETCH_ASSOC)) {
        if (isset($dates[$row['date']])) {
            $dates[$row['date']]['messages'] = (int)$row['count'];
        }
    }
    
    // Convert dates array to indexed array for chart
    $dailyStats = array_values($dates);
    
    // Get top pages visitors were on
    $topPagesQuery = $db->query(
        "SELECT url, COUNT(*) as count 
         FROM visitors 
         WHERE user_id = ? 
         AND created_at BETWEEN ? AND ? 
         GROUP BY url 
         ORDER BY count DESC 
         LIMIT 5",
        [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    
    $topPages = $topPagesQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Get visitor sources (referrers)
    // This uses a subquery to extract domain from referrer URLs
    $visitorSourcesQuery = $db->query(
        "SELECT 
            CASE 
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                WHEN referrer LIKE '%google.com%' THEN 'Google'
                WHEN referrer LIKE '%facebook.com%' THEN 'Facebook'
                WHEN referrer LIKE '%twitter.com%' THEN 'Twitter'
                WHEN referrer LIKE '%instagram.com%' THEN 'Instagram'
                WHEN referrer LIKE '%linkedin.com%' THEN 'LinkedIn'
                WHEN referrer LIKE '%bing.com%' THEN 'Bing'
                WHEN referrer LIKE '%yahoo.com%' THEN 'Yahoo'
                ELSE 'Other'
            END AS source,
            COUNT(*) as count
         FROM visitors 
         WHERE user_id = ? 
         AND created_at BETWEEN ? AND ?
         GROUP BY source
         ORDER BY count DESC",
        [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
    );
    
    $visitorSources = $visitorSourcesQuery->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error generating reports: " . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<style>
.reports-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.reports-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}

.reports-header h1 {
    margin: 0;
    font-size: 1.8rem;
    color: #333;
}

.date-range-form {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 0.85rem;
    margin-bottom: 5px;
    color: #555;
}

.form-control {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background-color: #3498db;
    color: white;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn:hover {
    background-color: #2980b9;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 20px;
    text-align: center;
}

.stat-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: #ebf5fb;
    color: #3498db;
    font-size: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    color: #777;
    font-size: 0.9rem;
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

.chart-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 20px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.chart-title {
    font-size: 1.2rem;
    color: #333;
    margin: 0;
}

.chart-container {
    position: relative;
    height: 300px;
}

.table-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 20px;
    margin-bottom: 30px;
}

.table-header {
    margin-bottom: 15px;
}

.table-title {
    font-size: 1.2rem;
    color: #333;
    margin: 0;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th, table td {
    padding: 10px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #555;
}

.progress-bar {
    height: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-fill {
    height: 100%;
    background-color: #3498db;
}

.url-cell {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 768px) {
    .reports-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .date-range-form {
        flex-direction: column;
        width: 100%;
    }
    
    .form-group {
        width: 100%;
    }
    
    .chart-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="reports-container">
    <div class="reports-header">
        <h1>Reports & Analytics</h1>
        
        <form method="get" class="date-range-form">
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo htmlspecialchars($endDate); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <button type="submit" class="btn">Apply Filter</button>
        </form>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($visitorCount); ?></div>
            <div class="stat-label">Total Visitors</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-value"><?php echo number_format($messageCount); ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="stat-value"><?php echo number_format($conversationCount); ?></div>
            <div class="stat-label">Conversations</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-value"><?php echo $conversionRate; ?>%</div>
            <div class="stat-label">Conversion Rate</div>
        </div>
    </div>
    
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h2 class="chart-title">Visitors & Messages Over Time</h2>
            </div>
            <div class="chart-container">
                <canvas id="dailyStatsChart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <h2 class="chart-title">Visitor Sources</h2>
            </div>
            <div class="chart-container">
                <canvas id="sourcesChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="table-card">
        <div class="table-header">
            <h2 class="table-title">Top Pages</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Visitors</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topPages)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No data available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topPages as $page): ?>
                            <tr>
                                <td class="url-cell" title="<?php echo htmlspecialchars($page['url']); ?>"><?php echo htmlspecialchars($page['url']); ?></td>
                                <td><?php echo number_format($page['count']); ?></td>
                                <td>
                                    <?php 
                                    $percentage = $visitorCount > 0 ? round(($page['count'] / $visitorCount) * 100, 1) : 0;
                                    echo $percentage . '%';
                                    ?>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h2 class="chart-title">Additional Metrics</h2>
        </div>
        <div class="table-container">
            <table>
                <tbody>
                    <tr>
                        <th style="width: 50%;">Average Response Time</th>
                        <td>
                            <?php
                            if ($averageResponseTime > 0) {
                                $minutes = floor($averageResponseTime / 60);
                                $seconds = $averageResponseTime % 60;
                                
                                if ($minutes > 0) {
                                    echo $minutes . ' min ' . $seconds . ' sec';
                                } else {
                                    echo $seconds . ' seconds';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Messages per Conversation</th>
                        <td><?php echo $conversationCount > 0 ? round($messageCount / $conversationCount, 1) : 0; ?></td>
                    </tr>
                    <tr>
                        <th>Messages per Visitor</th>
                        <td><?php echo $visitorCount > 0 ? round($messageCount / $visitorCount, 1) : 0; ?></td>
                    </tr>
                    <tr>
                        <th>Busiest Day</th>
                        <td>
                            <?php
                            $busiestDay = null;
                            $maxVisitors = 0;
                            
                            foreach ($dailyStats as $day) {
                                if ($day['visitors'] > $maxVisitors) {
                                    $maxVisitors = $day['visitors'];
                                    $busiestDay = $day['date'];
                                }
                            }
                            
                            if ($busiestDay) {
                                echo date('F j, Y', strtotime($busiestDay)) . ' (' . $maxVisitors . ' visitors)';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize daily stats chart
    const dailyStatsCtx = document.getElementById('dailyStatsChart').getContext('2d');
    const dailyStatsData = <?php echo json_encode($dailyStats); ?>;
    
    const dailyStatsChart = new Chart(dailyStatsCtx, {
        type: 'line',
        data: {
            labels: dailyStatsData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Visitors',
                    data: dailyStatsData.map(item => item.visitors),
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.2,
                    pointRadius: 3
                },
                {
                    label: 'Messages',
                    data: dailyStatsData.map(item => item.messages),
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 2,
                    tension: 0.2,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
    
    // Initialize sources chart
    const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
    const sourcesData = <?php echo json_encode($visitorSources); ?>;
    
    const sourcesChart = new Chart(sourcesCtx, {
        type: 'doughnut',
        data: {
            labels: sourcesData.map(item => item.source),
            datasets: [
                {
                    data: sourcesData.map(item => item.count),
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#f1c40f',
                        '#e74c3c',
                        '#9b59b6',
                        '#1abc9c',
                        '#34495e',
                        '#95a5a6'
                    ],
                    borderWidth: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>