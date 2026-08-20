<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setFlashMessage('error', 'Please login to access the admin panel.');
    header('Location: ../login.php');
    exit;
}

// Check if user has admin role
$role = $_SESSION['user_role'] ?? 'guest';
if ($role !== 'admin' && $role !== 'editor') {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ../index.php');
    exit;
}

// Get dashboard statistics
$stats = getDashboardStats($pdo);

// Get sales data for chart (last 30 days)
$salesData = getSalesData($pdo, 30);

// Get top products
$topProducts = getTopProducts($pdo, 5);

// Get recent orders
$recentOrders = getRecentOrders($pdo, 10);

// Get user registrations (last 7 days)
$userRegistrations = getUserRegistrations($pdo, 7);

// Get category sales
$categorySales = getCategorySales($pdo);

// Format chart data
$chartLabels = array_reverse(array_column($salesData, 'date'));
$chartOrders = array_reverse(array_column($salesData, 'orders'));
$chartRevenue = array_reverse(array_column($salesData, 'revenue'));

// Format for JSON
$chartLabelsJson = json_encode($chartLabels);
$chartOrdersJson = json_encode($chartOrders);
$chartRevenueJson = json_encode($chartRevenue);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Luminé Glow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<section class="admin-section">
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= sanitize($_SESSION['user_name']) ?>!</p>
            </div>
            <div class="admin-actions">
                <a href="../logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?= number_format($stats['total_customers'] ?? 0) ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3><?= number_format($stats['total_orders'] ?? 0) ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3><?= formatPrice($stats['total_revenue'] ?? 0) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3><?= number_format($stats['pending_orders'] ?? 0) ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛍️</div>
                <div class="stat-info">
                    <h3><?= number_format($stats['total_products'] ?? 0) ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <!-- Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Sales Overview</h3>
                    <p>Last 30 days</p>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Category Sales -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Sales by Category</h3>
                    <p>Top categories</p>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="bottom-row">
            <!-- Top Products -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Top Selling Products</h3>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($topProducts): ?>
                                <?php foreach ($topProducts as $index => $product): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= sanitize($product['product_name']) ?></td>
                                    <td><?= formatPrice($product['price']) ?></td>
                                    <td><?= $product['total_quantity'] ?? 0 ?></td>
                                    <td><?= formatPrice($product['total_revenue'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--muted);">No sales data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Recent Orders</h3>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentOrders): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?= sanitize($order['order_number']) ?></td>
                                    <td><?= sanitize($order['customer_name'] ?? 'Guest') ?></td>
                                    <td><?= formatPrice($order['total_amount']) ?></td>
                                    <td><?= getStatusBadge($order['order_status']) ?></td>
                                    <td><?= formatDate($order['order_date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--muted);">No orders found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- User Registrations -->
        <div class="table-card">
            <div class="card-header">
                <h3>Recent User Registrations</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($userRegistrations): ?>
                            <?php foreach ($userRegistrations as $user): ?>
                            <tr>
                                <td><?= sanitize($user['name']) ?></td>
                                <td><?= sanitize($user['email']) ?></td>
                                <td><?= getStatusBadge($user['role']) ?></td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td><?= getStatusBadge($user['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--muted);">No recent registrations</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
// Sales Chart
const ctx1 = document.getElementById('salesChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= $chartLabelsJson ?: '[]' ?>,
        datasets: [
            {
                label: 'Revenue (Rs.)',
                data: <?= $chartRevenueJson ?: '[]' ?>,
                borderColor: '#b76d78',
                backgroundColor: 'rgba(183, 109, 120, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Orders',
                data: <?= $chartOrdersJson ?: '[]' ?>,
                borderColor: '#c9a46c',
                backgroundColor: 'rgba(201, 164, 108, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rs. ' + value.toLocaleString();
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Category Chart
const categoryData = <?= json_encode($categorySales) ?>;
const ctx2 = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(item => item.category_name || 'Uncategorized'),
        datasets: [{
            data: categoryData.map(item => item.total_sales || 0),
            backgroundColor: [
                '#b76d78',
                '#c9a46c',
                '#e7d5b9',
                '#f2d9d9',
                '#766c6f',
                '#d4a0a0'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            }
        },
        cutout: '60%'
    }
});
</script>

<?php include '../includes/footer.php'; ?>