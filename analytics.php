<?php
$conn = new mysqli("localhost", "root", "", "petrol_tracker");
$month_order = ["January","February","March","April","May","June","July","August","September","October","November","December"];
$colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6', '#f97316', '#6366f1'];

// 1. DATA: Monthly Expenditure Per Vehicle
$car_list = $conn->query("SELECT id, plate_number FROM cars");
$stack_datasets = []; $idx = 0;
while ($car = $car_list->fetch_assoc()) {
    $usage = $conn->query("SELECT month, SUM(amount) as total FROM usage_records WHERE car_id = {$car['id']} GROUP BY month");
    $monthly_map = []; while($row = $usage->fetch_assoc()) $monthly_map[$row['month']] = $row['total'];
    $data_points = []; foreach ($month_order as $m) $data_points[] = $monthly_map[$m] ?? 0;
    
    $stack_datasets[] = [
        'label' => $car['plate_number'],
        'data' => $data_points,
        'borderColor' => $colors[$idx % count($colors)],
        'backgroundColor' => $colors[$idx % count($colors)] . '15',
        'borderWidth' => 3,
        'fill' => false,
        'tension' => 0.3,
        'pointBackgroundColor' => $colors[$idx % count($colors)],
        'pointBorderColor' => '#ffffff',
        'pointBorderWidth' => 2,
        'pointRadius' => 4,
        'pointHoverRadius' => 6
    ];
    $idx++;
}

// 2. DATA: Summary Statistics
$summary_query = $conn->query("
    SELECT 
        c.plate_number, 
        c.custodian,
        SUM(u.amount) as total_rm, 
        SUM(u.liter) as total_liter,
        SUM(u.returned - u.predrive) as total_km,
        CASE WHEN SUM(u.liter) > 0 THEN SUM(u.returned - u.predrive)/SUM(u.liter) ELSE 0 END as kml
    FROM cars c 
    LEFT JOIN usage_records u ON c.id = u.car_id 
    GROUP BY c.id
");
$car_lbls = []; $car_rm = []; $car_eff = []; $car_km = [];
while ($row = $summary_query->fetch_assoc()) {
    $car_lbls[] = $row['plate_number'];
    $car_rm[] = round($row['total_rm'] ?? 0, 2);
    $car_eff[] = round($row['kml'] ?? 0, 2);
    $car_km[] = round($row['total_km'] ?? 0, 0);
}

// Calculate overall totals
$total_fleet_cost = array_sum($car_rm);
$avg_fleet_efficiency = count(array_filter($car_eff)) > 0 ? round(array_sum($car_eff) / count(array_filter($car_eff)), 2) : 0;
$total_fleet_km = array_sum($car_km);

// 3. DATA: Monthly Fleet Total (for trend card)
$monthly_fleet = $conn->query("SELECT month, SUM(amount) as total FROM usage_records GROUP BY month");
$fleet_monthly = array_fill_keys($month_order, 0);
while($row = $monthly_fleet->fetch_assoc()) {
    $fleet_monthly[$row['month']] = round($row['total'], 2);
}
$current_month = date('F');
$current_month_spend = $fleet_monthly[$current_month] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics · Petrol Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .stat-card {
            transition: all 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.02);
        }
    </style>
</head>
<body class="bg-[#fafbfc] antialiased">

<div class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-xl border-b border-gray-200/60 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center shadow-md">
                        <span class="text-white text-xl">📊</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Fleet<span class="text-indigo-600">Analytics</span></h1>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider -mt-0.5">Performance & Insights</p>
                    </div>
                </div>
                <a href="index.php" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all border border-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Fleet Vehicles</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= count($car_lbls) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">🚗</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Fleet Cost</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">RM <?= number_format($total_fleet_cost, 0) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">💰</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Avg Efficiency</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $avg_fleet_efficiency ?> km/L</p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">⛽</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">This Month Spend</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">RM <?= number_format($current_month_spend, 0) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">📅</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 gap-8">
            
            <!-- Monthly Expenditure Trend -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Monthly Expenditure per Vehicle</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Track spending patterns across your fleet</p>
                    </div>
                    <div class="flex gap-1 text-xs">
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full font-medium">RM (MYR)</span>
                    </div>
                </div>
                <div class="h-[400px]">
                    <canvas id="perVehicleChart"></canvas>
                </div>
            </div>

            <!-- Bottom Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Total Cost Bar Chart -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-800">Total Cost by Vehicle</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Year-to-date expenditure</p>
                    </div>
                    <div class="h-[300px]">
                        <canvas id="costChart"></canvas>
                    </div>
                </div>

                <!-- Fuel Efficiency Horizontal Bar -->
                <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-gray-800">Fuel Efficiency Ranking</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Kilometers per liter (KM/L)</p>
                    </div>
                    <div class="h-[300px]">
                        <canvas id="effChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Insights Card -->
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm">
                        <span class="text-xl">💡</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">Fleet Insights</h3>
                        <p class="text-sm text-gray-600">
                            <?php
                            $most_efficient = !empty($car_eff) ? $car_lbls[array_search(max($car_eff), $car_eff)] : 'N/A';
                            $highest_cost = !empty($car_rm) ? $car_lbls[array_search(max($car_rm), $car_rm)] : 'N/A';
                            ?>
                            <span class="font-semibold text-indigo-700"><?= $most_efficient ?></span> is your most fuel-efficient vehicle, while 
                            <span class="font-semibold text-amber-700"><?= $highest_cost ?></span> has the highest total expenditure.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Enhanced Chart Configurations
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    
    // 1. Monthly Expenditure Line Chart
    new Chart(document.getElementById('perVehicleChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($month_order) ?>,
            datasets: <?= json_encode($stack_datasets) ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            weight: '500',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#f3f4f6',
                    bodyColor: '#d1d5db',
                    borderColor: '#374151',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e5e7eb',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // 2. Total Cost Bar Chart
    new Chart(document.getElementById('costChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($car_lbls) ?>,
            datasets: [{
                label: 'Total Expenditure (RM)',
                data: <?= json_encode($car_rm) ?>,
                backgroundColor: [
                    '#4f46e5', '#6366f1', '#818cf8', '#a5b4fc',
                    '#10b981', '#34d399', '#6ee7b7',
                    '#f59e0b', '#fbbf24', '#fcd34d',
                    '#ef4444', '#f87171', '#fca5a5'
                ],
                borderRadius: 8,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    callbacks: {
                        label: function(context) {
                            return 'Total: RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e5e7eb',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // 3. Fuel Efficiency Horizontal Bar Chart
    new Chart(document.getElementById('effChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($car_lbls) ?>,
            datasets: [{
                label: 'KM/L',
                data: <?= json_encode($car_eff) ?>,
                backgroundColor: function(context) {
                    const value = context.raw;
                    if (value >= 15) return '#10b981'; // Excellent - green
                    if (value >= 10) return '#f59e0b'; // Good - amber
                    return '#ef4444'; // Needs attention - red
                },
                borderRadius: 8,
                barPercentage: 0.7,
                categoryPercentage: 0.9
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    callbacks: {
                        label: function(context) {
                            return context.parsed.x.toFixed(2) + ' KM/L';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: '#e5e7eb',
                        drawBorder: false
                    },
                    title: {
                        display: true,
                        text: 'Kilometers per Liter (KM/L)',
                        color: '#6b7280',
                        font: {
                            weight: '500',
                            size: 11
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>
</body>
</html>