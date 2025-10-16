<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize report parameters
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'borrowing';
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Get all categories for filter
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result();

// Report data (initialize empty)
$report_data = [];
$chart_data = [];
$report_title = '';
$report_description = '';

// Generate report based on type
switch ($report_type) {
    case 'borrowing':
        $report_title = 'Borrowing Statistics Report';
        $report_description = 'Overview of book borrowing activities during the selected period.';
        
        // Query to get borrowing stats by date
        $query = "
            SELECT 
                DATE(b.borrow_date) as date,
                COUNT(*) as borrow_count
            FROM borrows b
            WHERE b.borrow_date BETWEEN ? AND ?
        ";
        
        $params = [$start_date, $end_date];
        $types = "ss";
        
        if ($status) {
            if ($status == 'returned') {
                $query .= " AND b.return_date IS NOT NULL";
            } elseif ($status == 'active') {
                $query .= " AND b.return_date IS NULL AND b.due_date >= CURDATE()";
            } elseif ($status == 'overdue') {
                $query .= " AND b.return_date IS NULL AND b.due_date < CURDATE()";
            }
        }
        
        $query .= " GROUP BY DATE(b.borrow_date) ORDER BY date";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
            $chart_labels[] = formatDate($row['date']);
            $chart_values[] = $row['borrow_count'];
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Borrowings by Date'
        ];
        
        // Get summary statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_borrows,
                SUM(CASE WHEN return_date IS NULL THEN 1 ELSE 0 END) as active_borrows,
                SUM(CASE WHEN return_date IS NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_borrows,
                COUNT(DISTINCT user_id) as unique_borrowers
            FROM borrows
            WHERE borrow_date BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        break;
        
    case 'books':
        $report_title = 'Book Usage Report';
        $report_description = 'Analysis of the most borrowed books during the selected period.';
        
        $query = "
            SELECT 
                b.book_id,
                bk.title,
                bk.author,
                bk.isbn,
                c.name as category,
                COUNT(*) as borrow_count,
                AVG(CASE 
                    WHEN b.return_date IS NOT NULL 
                    THEN DATEDIFF(b.return_date, b.borrow_date) 
                    ELSE NULL 
                END) as avg_days_kept
            FROM borrows b
            JOIN books bk ON b.book_id = bk.id
            LEFT JOIN categories c ON bk.category_id = c.id
            WHERE b.borrow_date BETWEEN ? AND ?
        ";
        
        $params = [$start_date, $end_date];
        $types = "ss";
        
        if ($category_id > 0) {
            $query .= " AND bk.category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
        
        $query .= " GROUP BY b.book_id, bk.title ORDER BY borrow_count DESC LIMIT 20";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Build chart data for top 10 books
        $top_books = array_slice($report_data, 0, 10);
        $chart_labels = [];
        $chart_values = [];
        
        foreach ($top_books as $book) {
            $chart_labels[] = $book['title'];
            $chart_values[] = $book['borrow_count'];
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Most Popular Books'
        ];
        
        break;
        
    case 'users':
        $report_title = 'User Activity Report';
        $report_description = 'Analysis of user borrowing patterns during the selected period.';
        
        $query = "
            SELECT 
                b.user_id,
                u.name,
                u.email,
                COUNT(*) as borrow_count,
                SUM(CASE WHEN b.return_date IS NULL AND b.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
                MAX(b.borrow_date) as last_borrow_date
            FROM borrows b
            JOIN users u ON b.user_id = u.id
            WHERE b.borrow_date BETWEEN ? AND ?
            GROUP BY b.user_id, u.name
            ORDER BY borrow_count DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate summary statistics
        $total_users = count($report_data);
        $active_users = 0;
        $users_with_overdue = 0;
        
        foreach ($report_data as $user) {
            if ($user['borrow_count'] > 0) $active_users++;
            if ($user['overdue_count'] > 0) $users_with_overdue++;
        }
        
        // Build chart data - user activity distribution
        $activity_ranges = [
            '1 book' => 0,
            '2-5 books' => 0,
            '6-10 books' => 0,
            '11+ books' => 0
        ];
        
        foreach ($report_data as $user) {
            if ($user['borrow_count'] == 1) {
                $activity_ranges['1 book']++;
            } elseif ($user['borrow_count'] >= 2 && $user['borrow_count'] <= 5) {
                $activity_ranges['2-5 books']++;
            } elseif ($user['borrow_count'] >= 6 && $user['borrow_count'] <= 10) {
                $activity_ranges['6-10 books']++;
            } else {
                $activity_ranges['11+ books']++;
            }
        }
        
        $chart_labels = array_keys($activity_ranges);
        $chart_values = array_values($activity_ranges);
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'User Activity Distribution'
        ];
        
        break;
        
    case 'overdue':
        $report_title = 'Overdue Books Report';
        $report_description = 'List of currently overdue books and associated users.';
        
        $query = "
            SELECT 
                b.id as borrow_id,
                b.book_id,
                bk.title,
                bk.author,
                b.user_id,
                u.name as user_name,
                u.email as user_email,
                b.borrow_date,
                b.due_date,
                DATEDIFF(CURDATE(), b.due_date) as days_overdue
            FROM borrows b
            JOIN books bk ON b.book_id = bk.id
            JOIN users u ON b.user_id = u.id
            WHERE b.return_date IS NULL 
              AND b.due_date < CURDATE()
              AND b.borrow_date BETWEEN ? AND ?
            ORDER BY days_overdue DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Group overdue books by days overdue
        $overdue_ranges = [
            '1-7 days' => 0,
            '8-14 days' => 0,
            '15-30 days' => 0,
            '31+ days' => 0
        ];
        
        foreach ($report_data as $item) {
            if ($item['days_overdue'] <= 7) {
                $overdue_ranges['1-7 days']++;
            } elseif ($item['days_overdue'] <= 14) {
                $overdue_ranges['8-14 days']++;
            } elseif ($item['days_overdue'] <= 30) {
                $overdue_ranges['15-30 days']++;
            } else {
                $overdue_ranges['31+ days']++;
            }
        }
        
        $chart_labels = array_keys($overdue_ranges);
        $chart_values = array_values($overdue_ranges);
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Overdue Books by Duration'
        ];
        
        break;
}

// Include header
$pageTitle = "Reports";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Reports &amp; Statistics</h2>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <div class="col-md-3 form-group">
                <label for="type">Report Type</label>
                <select name="type" id="type" class="form-control">
                    <option value="borrowing" <?= $report_type == 'borrowing' ? 'selected' : '' ?>>Borrowing Statistics</option>
                    <option value="books" <?= $report_type == 'books' ? 'selected' : '' ?>>Book Usage</option>
                    <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>User Activity</option>
                    <option value="overdue" <?= $report_type == 'overdue' ? 'selected' : '' ?>>Overdue Books</option>
                </select>
            </div>
            
            <div class="col-md-3 form-group">
                <label for="date-range">Date Range</label>
                <div class="input-group">
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                    <div class="input-group-prepend input-group-append">
                        <span class="input-group-text">to</span>
                    </div>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
            </div>
            
            <div class="col-md-3 form-group">
                <?php if ($report_type == 'books'): ?>
                    <label for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="form-control">
                        <option value="0">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                <?php elseif ($report_type == 'borrowing'): ?>
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Borrows</option>
                        <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="returned" <?= $status == 'returned' ? 'selected' : '' ?>>Returned</option>
                        <option value="overdue" <?= $status == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                <?php else: ?>
                    <label>&nbsp;</label>
                    <div class="form-control bg-light" style="visibility: hidden;">
                        Placeholder
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-3 form-group">
                <label>&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" id="export-report" class="btn btn-success ml-2">Export</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0"><?= $report_title ?></h4>
    </div>
    <div class="card-body">
        <p class="lead"><?= $report_description ?></p>
        
        <!-- Summary Statistics -->
        <?php if ($report_type == 'borrowing' && !empty($summary)): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Borrows</h6>
                            <h3><?= $summary['total_borrows'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Active Borrows</h6>
                            <h3><?= $summary['active_borrows'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Overdue Books</h6>
                            <h3><?= $summary['overdue_borrows'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Unique Borrowers</h6>
                            <h3><?= $summary['unique_borrowers'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Chart Visualization -->
        <?php if (!empty($chart_data)): ?>
            <div class="chart-container mb-4" style="position: relative; height:400px;">
                <canvas id="reportChart"></canvas>
            </div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <div class="table-responsive">
            <?php if ($report_type == 'borrowing'): ?>
                <table class="table table-striped table-bordered" id="reportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Number of Borrows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= formatDate($row['date']) ?></td>
                                <td><?= $row['borrow_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'books'): ?>
                <table class="table table-striped table-bordered" id="reportTable">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Times Borrowed</th>
                            <th>Avg. Days Kept</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['author']) ?></td>
                                <td><?= htmlspecialchars($row['isbn']) ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td><?= $row['borrow_count'] ?></td>
                                <td><?= $row['avg_days_kept'] ? round($row['avg_days_kept'], 1) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'users'): ?>
                <table class="table table-striped table-bordered" id="reportTable">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Borrows</th>
                            <th>Overdue Books</th>
                            <th>Last Borrow Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= $row['borrow_count'] ?></td>
                                <td>
                                    <?php if ($row['overdue_count'] > 0): ?>
                                        <span class="text-danger"><?= $row['overdue_count'] ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($row['last_borrow_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'overdue'): ?>
                <table class="table table-striped table-bordered" id="reportTable">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>User</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td>
                                    <?= htmlspecialchars($row['user_name']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['user_email']) ?></small>
                                </td>
                                <td><?= formatDate($row['borrow_date']) ?></td>
                                <td><?= formatDate($row['due_date']) ?></td>
                                <td>
                                    <span class="badge badge-danger"><?= $row['days_overdue'] ?> days</span>
                                </td>
                                <td>
                                    <a href="process_return.php?id=<?= $row['borrow_id'] ?>" class="btn btn-sm btn-primary">Return</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Chart Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($chart_data)): ?>
    // Chart initialization
    const ctx = document.getElementById('reportChart').getContext('2d');
    const reportChart = new Chart(ctx, {
        type: <?= $report_type == 'users' || $report_type == 'overdue' ? "'pie'" : "'bar'" ?>,
        data: {
            labels: <?= json_encode($chart_data['labels']) ?>,
            datasets: [{
                label: <?= json_encode($chart_data['title']) ?>,
                data: <?= json_encode($chart_data['values']) ?>,
                backgroundColor: [
                    'rgba(214, 40, 57, 0.8)',
                    'rgba(76, 201, 240, 0.8)',
                    'rgba(42, 157, 143, 0.8)',
                    'rgba(244, 162, 97, 0.8)',
                    'rgba(231, 111, 81, 0.8)',
                    'rgba(74, 78, 105, 0.8)',
                    'rgba(245, 107, 127, 0.8)',
                    'rgba(38, 70, 83, 0.8)',
                    'rgba(189, 61, 83, 0.8)',
                    'rgba(45, 149, 150, 0.8)'
                ],
                borderColor: [
                    'rgba(214, 40, 57, 1)',
                    'rgba(76, 201, 240, 1)',
                    'rgba(42, 157, 143, 1)',
                    'rgba(244, 162, 97, 1)',
                    'rgba(231, 111, 81, 1)',
                    'rgba(74, 78, 105, 1)',
                    'rgba(245, 107, 127, 1)',
                    'rgba(38, 70, 83, 1)',
                    'rgba(189, 61, 83, 1)',
                    'rgba(45, 149, 150, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    display: <?= $report_type == 'users' || $report_type == 'overdue' ? 'false' : 'true' ?>
                },
                x: {
                    display: <?= $report_type == 'users' || $report_type == 'overdue' ? 'false' : 'true' ?>
                }
            },
            plugins: {
                legend: {
                    display: <?= $report_type == 'users' || $report_type == 'overdue' ? 'true' : 'false' ?>,
                    position: 'top',
                }
            }
        }
    });
    <?php endif; ?>
    
    // Export functionality
    document.getElementById('export-report').addEventListener('click', function() {
        // Create a CSV
        const table = document.getElementById('reportTable');
        let csv = [];
        
        // Get headers
        let headers = [];
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            headers.push('"' + cell.textContent.trim() + '"');
        });
        csv.push(headers.join(','));
        
        // Get rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let rowData = [];
            cells.forEach(cell => {
                rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        // Create a hidden link to download the CSV
        const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `${<?= json_encode($report_type) ?>}_report_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>

<?php include('../includes/footer.php'); ?>
