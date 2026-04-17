<?php
// 1. DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "petrol_tracker");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ============================================
// DATABASE MIGRATION (Run once to add new columns)
// ============================================
// Check if fuel_type column exists, if not add it
$result = $conn->query("SHOW COLUMNS FROM cars LIKE 'fuel_type'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE cars ADD COLUMN fuel_type ENUM('petrol', 'diesel') NOT NULL DEFAULT 'petrol' AFTER district");
}

// Check if fuel_consumption column exists in usage_records
$result = $conn->query("SHOW COLUMNS FROM usage_records LIKE 'fuel_consumption'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usage_records ADD COLUMN fuel_consumption DECIMAL(10,2) DEFAULT NULL AFTER liter");
}

// Check if we need to add fuel_type to usage_records for historical tracking
$result = $conn->query("SHOW COLUMNS FROM usage_records LIKE 'fuel_type'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usage_records ADD COLUMN fuel_type ENUM('petrol', 'diesel') DEFAULT NULL AFTER fuel_consumption");
}

// Check if remark column exists in usage_records
$result = $conn->query("SHOW COLUMNS FROM usage_records LIKE 'remark'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usage_records ADD COLUMN remark TEXT DEFAULT NULL AFTER fuel_type");
}

// Check if is_from_current_user column exists
$result = $conn->query("SHOW COLUMNS FROM usage_records LIKE 'is_from_current_user'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usage_records ADD COLUMN is_from_current_user TINYINT(1) DEFAULT 1 AFTER remark");
}

// 2. LOGIC: REGISTER NEW CAR
if (isset($_POST['add_car'])) {
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $custodian = mysqli_real_escape_string($conn, $_POST['custodian']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $district = mysqli_real_escape_string($conn, $_POST['district']);
    $fuel_type = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    
    $conn->query("INSERT INTO cars (plate_number, custodian, section, district, fuel_type) VALUES ('$plate', '$custodian', '$section', '$district', '$fuel_type')");
    header("Location: index.php");
    exit();
}

// 3. LOGIC: EDIT CAR
if (isset($_POST['edit_car'])) {
    $car_id = (int)$_POST['car_id'];
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $custodian = mysqli_real_escape_string($conn, $_POST['custodian']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $district = mysqli_real_escape_string($conn, $_POST['district']);
    $fuel_type = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    
    $conn->query("UPDATE cars SET plate_number='$plate', custodian='$custodian', section='$section', district='$district', fuel_type='$fuel_type' WHERE id=$car_id");
    header("Location: index.php");
    exit();
}

// 4. LOGIC: DELETE CAR (WITH FOREIGN KEY HANDLING)
if (isset($_GET['delete_car_id'])) {
    $car_id = (int)$_GET['delete_car_id'];
    
    // Check if car has usage records
    $check_records = $conn->query("SELECT COUNT(*) as count FROM usage_records WHERE car_id = $car_id");
    $has_records = $check_records->fetch_assoc()['count'];
    
    if ($has_records > 0) {
        $error_msg = "Cannot delete this car because it has $has_records usage record(s). Please delete all usage records first.";
    } else {
        $conn->query("DELETE FROM cars WHERE id = $car_id");
        header("Location: index.php");
        exit();
    }
}

// 5. LOGIC: DELETE USAGE RECORD
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;
    $month = isset($_GET['month']) ? mysqli_real_escape_string($conn, $_GET['month']) : '';
    
    if ($conn->query("DELETE FROM usage_records WHERE id = $id")) {
        if ($car_id && $month) {
            header("Location: index.php?car_id=" . $car_id . "&month=" . urlencode($month));
        } else {
            header("Location: index.php");
        }
    } else {
        $error_msg = "Error deleting record: " . $conn->error;
    }
    exit();
}

// 6. LOGIC: SAVE (INSERT OR UPDATE) USAGE RECORD
if (isset($_POST['save_usage'])) {
    $car_id = (int)$_POST['car_id'];
    $month = mysqli_real_escape_string($conn, $_POST['month']);
    $date_rec = mysqli_real_escape_string($conn, $_POST['date_recorded']);
    $predrive = (int)$_POST['predrive'];
    $returned = (int)$_POST['returned'];
    $amount = (float)$_POST['amount'];
    $liter = (float)$_POST['liter'];
    $remark = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');
    $is_from_current_user = isset($_POST['is_from_current_user']) ? 1 : 0;
    
    // Calculate fuel consumption (liters per 100km)
    $mileage = $returned - $predrive;
    $fuel_consumption = ($mileage > 0) ? ($liter / $mileage * 100) : null;
    
    // Get car's fuel type for the record
    $car_info = $conn->query("SELECT fuel_type FROM cars WHERE id = $car_id")->fetch_assoc();
    $fuel_type = $car_info['fuel_type'];
    
    if (!empty($_POST['record_id'])) {
        $id = (int)$_POST['record_id'];
        $conn->query("UPDATE usage_records SET date_recorded='$date_rec', predrive='$predrive', returned='$returned', amount='$amount', liter='$liter', fuel_consumption='$fuel_consumption', fuel_type='$fuel_type', remark='$remark', is_from_current_user='$is_from_current_user' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO usage_records (car_id, month, date_recorded, predrive, returned, amount, liter, fuel_consumption, fuel_type, remark, is_from_current_user) 
                      VALUES ('$car_id', '$month', '$date_rec', '$predrive', '$returned', '$amount', '$liter', '$fuel_consumption', '$fuel_type', '$remark', '$is_from_current_user')");
    }
    header("Location: index.php?car_id=$car_id&month=" . urlencode($month));
    exit();
}

// 7. DATA FETCHING
$selected_car = isset($_GET['car_id']) ? (int)$_GET['car_id'] : null;
$selected_month = isset($_GET['month']) ? $_GET['month'] : null;
$edit_record = null;
$edit_car = null;
$error_msg = null;

// Check if editing a car
if (isset($_GET['edit_car_id'])) {
    $edit_car_id = (int)$_GET['edit_car_id'];
    $edit_car = $conn->query("SELECT * FROM cars WHERE id = $edit_car_id")->fetch_assoc();
}

if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $edit_record = $conn->query("SELECT * FROM usage_records WHERE id = $edit_id")->fetch_assoc();
}

$records = [];
if ($selected_car && $selected_month) {
    $records = $conn->query("SELECT *, (returned - predrive) AS mileage, fuel_consumption 
                             FROM usage_records 
                             WHERE car_id = $selected_car AND month = '$selected_month' 
                             ORDER BY date_recorded ASC");
}

// Get summary stats for selected car/month
$total_milage = $total_amount = $total_litre = 0;
$record_count = 0;
$avg_consumption = 0;
$consumption_sum = 0;
$consumption_count = 0;
$non_user_count = 0;

if ($records && $records->num_rows > 0) {
    $record_count = $records->num_rows;
    $records->data_seek(0);
    while($row = $records->fetch_assoc()) {
        $total_milage += $row['mileage'];
        $total_amount += $row['amount'];
        $total_litre += $row['liter'];
        
        if (!$row['is_from_current_user']) {
            $non_user_count++;
        }
        
        // Calculate average fuel consumption from records that have valid consumption data
        if ($row['fuel_consumption'] && $row['fuel_consumption'] > 0) {
            $consumption_sum += $row['fuel_consumption'];
            $consumption_count++;
        }
    }
    $records->data_seek(0);
    
    if ($consumption_count > 0) {
        $avg_consumption = $consumption_sum / $consumption_count;
    }
}

// Get car count for dashboard stat
$car_count_result = $conn->query("SELECT COUNT(*) as count FROM cars");
$car_count = $car_count_result->fetch_assoc()['count'];

// Get fuel type statistics
$fuel_stats = [];
$fuel_stats_result = $conn->query("SELECT fuel_type, COUNT(*) as count FROM cars GROUP BY fuel_type");
while($row = $fuel_stats_result->fetch_assoc()) {
    $fuel_stats[$row['fuel_type']] = $row['count'];
}

// Get selected car info for display
$selected_car_info = null;
if ($selected_car) {
    $selected_car_info = $conn->query("SELECT * FROM cars WHERE id = $selected_car")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petrol Tracker · Fuel Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.02); }
        .fuel-badge-petrol { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        .fuel-badge-diesel { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
        .badge-warning { background: linear-gradient(135deg, #fed7aa, #ffedd5); color: #9b3412; }
    </style>
</head>
<body class="bg-[#fafbfc] antialiased">

<div class="min-h-screen">
    <!-- Top Navigation -->
    <nav class="bg-white/80 backdrop-blur-xl border-b border-gray-200/60 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center shadow-md">
                        <span class="text-white text-xl">⛽</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Petrol<span class="text-blue-600">Tracker</span></h1>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider -mt-0.5">Fleet Management</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="ocr_upload.php" class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Scan PDF
                    </a>
                    <a href="analytics.php" class="flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl shadow-md hover:shadow-lg hover:scale-[1.02] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Analytics
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-5 mb-8">
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Vehicles</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $car_count ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">🚗</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Petrol Vehicles</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $fuel_stats['petrol'] ?? 0 ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">⛽</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Diesel Vehicles</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $fuel_stats['diesel'] ?? 0 ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">🛢️</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Current Month</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $selected_month ?: '—' ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">📅</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-gray-200/60 shadow-sm card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Non-User Trips</p>
                        <p class="text-3xl font-bold text-amber-600 mt-1"><?= $non_user_count ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                        <span class="text-2xl">⚠️</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display error message if any -->
        <?php if (isset($error_msg)): ?>
        <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
            <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Register/Edit Vehicle -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden sticky top-24">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-6 h-6 <?= $edit_car ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600' ?> rounded-lg flex items-center justify-center text-sm">
                                <?= $edit_car ? '✏️' : '➕' ?>
                            </span>
                            <?= $edit_car ? 'Edit Vehicle' : 'Register New Vehicle' ?>
                        </h2>
                    </div>
                    <div class="p-5">
                        <form method="POST" class="space-y-4">
                            <?php if ($edit_car): ?>
                                <input type="hidden" name="car_id" value="<?= $edit_car['id'] ?>">
                            <?php endif; ?>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Plate Number</label>
                                <input type="text" name="plate_number" value="<?= htmlspecialchars($edit_car['plate_number'] ?? '') ?>" placeholder="e.g. ABC 1234" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Custodian</label>
                                <input type="text" name="custodian" value="<?= htmlspecialchars($edit_car['custodian'] ?? '') ?>" placeholder="Full name" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Section</label>
                                    <input type="text" name="section" value="<?= htmlspecialchars($edit_car['section'] ?? '') ?>" placeholder="Dept" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">District</label>
                                    <input type="text" name="district" value="<?= htmlspecialchars($edit_car['district'] ?? '') ?>" placeholder="Location" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Fuel Type</label>
                                <select name="fuel_type" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition" required>
                                    <option value="petrol" <?= (isset($edit_car) && $edit_car['fuel_type'] == 'petrol') ? 'selected' : '' ?>>⛽ Petrol</option>
                                    <option value="diesel" <?= (isset($edit_car) && $edit_car['fuel_type'] == 'diesel') ? 'selected' : '' ?>>🛢️ Diesel</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" name="<?= $edit_car ? 'edit_car' : 'add_car' ?>" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition-all mt-2">
                                    <?= $edit_car ? 'Update Vehicle' : 'Save Vehicle' ?>
                                </button>
                                <?php if ($edit_car): ?>
                                    <a href="index.php" class="flex-1 bg-gray-100 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-200 transition-all mt-2 text-center">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Vehicle List with Edit/Delete options -->
                    <div class="border-t border-gray-100">
                        <div class="px-5 py-3 bg-gray-50/30">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Vehicle List</h3>
                        </div>
                        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            <?php
                            $all_cars = $conn->query("SELECT * FROM cars ORDER BY plate_number");
                            while($car = $all_cars->fetch_assoc()):
                                $record_check = $conn->query("SELECT COUNT(*) as count FROM usage_records WHERE car_id = " . $car['id']);
                                $has_records = $record_check->fetch_assoc()['count'] > 0;
                            ?>
                            <div class="px-5 py-3 hover:bg-gray-50 transition group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <div class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($car['plate_number']) ?></div>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full <?= $car['fuel_type'] == 'petrol' ? 'fuel-badge-petrol' : 'fuel-badge-diesel' ?> font-medium">
                                                <?= $car['fuel_type'] == 'petrol' ? '⛽ Petrol' : '🛢️ Diesel' ?>
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <?= htmlspecialchars($car['custodian'] ?: 'No custodian') ?>
                                            <?php if($car['section']): ?> • <?= htmlspecialchars($car['section']) ?><?php endif; ?>
                                        </div>
                                        <?php if($has_records): ?>
                                            <div class="text-[10px] text-amber-600 mt-1">⚠️ Has usage records</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                                        <a href="index.php?edit_car_id=<?= $car['id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-1">Edit</a>
                                        <?php if($has_records): ?>
                                            <span class="text-gray-400 text-xs font-medium px-2 py-1 cursor-not-allowed" title="Cannot delete car with usage records">Delete</span>
                                        <?php else: ?>
                                            <a href="index.php?delete_car_id=<?= $car['id'] ?>" onclick="return confirm('Delete this vehicle? This action cannot be undone.')" class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Records & Form -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Selection Card -->
                <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 text-sm">🔍</span>
                            Select Vehicle & Month
                        </h2>
                    </div>
                    <div class="p-5">
                        <form method="GET" class="flex flex-col sm:flex-row gap-4">
                            <select name="car_id" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none" onchange="this.form.submit()">
                                <option value="">— Choose Car —</option>
                                <?php
                                $car_list = $conn->query("SELECT * FROM cars ORDER BY plate_number");
                                while($c = $car_list->fetch_assoc()) {
                                    $sel = ($selected_car == $c['id']) ? 'selected' : '';
                                    echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['plate_number']) . " (" . ucfirst($c['fuel_type']) . ") — " . htmlspecialchars($c['custodian']) . "</option>";
                                }
                                ?>
                            </select>
                            <select name="month" class="sm:w-48 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none" onchange="this.form.submit()">
                                <?php 
                                $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                                foreach($months as $m) {
                                    $sel = ($selected_month == $m) ? 'selected' : '';
                                    echo "<option value='$m' $sel>$m</option>";
                                }
                                ?>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if ($selected_car && $selected_month): ?>
                
                <!-- Vehicle Info Banner -->
                <?php if ($selected_car_info): ?>
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-4 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">🚙</span>
                                <span class="font-bold text-gray-800"><?= htmlspecialchars($selected_car_info['plate_number']) ?></span>
                                <span class="text-xs px-2 py-1 rounded-full <?= $selected_car_info['fuel_type'] == 'petrol' ? 'fuel-badge-petrol' : 'fuel-badge-diesel' ?>">
                                    <?= $selected_car_info['fuel_type'] == 'petrol' ? '⛽ Petrol' : '🛢️ Diesel' ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                Custodian: <?= htmlspecialchars($selected_car_info['custodian'] ?: 'N/A') ?> 
                                <?php if($selected_car_info['section']): ?> • Section: <?= htmlspecialchars($selected_car_info['section']) ?><?php endif; ?>
                                <?php if($selected_car_info['district']): ?> • District: <?= htmlspecialchars($selected_car_info['district']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-500">Average Fuel Consumption</div>
                            <div class="text-xl font-bold <?= $selected_car_info['fuel_type'] == 'diesel' ? 'text-green-600' : 'text-amber-600' ?>">
                                <?= number_format($avg_consumption, 2) ?> L/100km
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Add/Edit Form Card -->
                <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-6 h-6 <?= $edit_record ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600' ?> rounded-lg flex items-center justify-center text-sm">
                                <?= $edit_record ? '✏️' : '📝' ?>
                            </span>
                            <?= $edit_record ? 'Edit Usage Record' : 'Add New Usage Log' ?>
                        </h2>
                    </div>
                    <div class="p-5">
                        <form method="POST">
                            <input type="hidden" name="record_id" value="<?= $edit_record['id'] ?? '' ?>">
                            <input type="hidden" name="car_id" value="<?= $selected_car ?>">
                            <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
                            
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Date</label>
                                    <input type="date" name="date_recorded" value="<?= $edit_record['date_recorded'] ?? '' ?>" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Pre-Drive (km)</label>
                                    <input type="number" name="predrive" placeholder="Odometer" value="<?= $edit_record['predrive'] ?? '' ?>" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Returned (km)</label>
                                    <input type="number" name="returned" placeholder="Odometer" value="<?= $edit_record['returned'] ?? '' ?>" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Amount (RM)</label>
                                    <input type="number" step="0.01" name="amount" placeholder="0.00" value="<?= $edit_record['amount'] ?? '' ?>" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Litre</label>
                                    <input type="number" step="0.01" name="liter" placeholder="0.00" value="<?= $edit_record['liter'] ?? '' ?>" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none" required>
                                </div>
                            </div>
                            
                            <!-- Remark Section -->
                            <div class="mt-4 pt-3 border-t border-gray-100">
                                <div class="flex items-start gap-4">
                                    <div class="flex items-center mt-1">
                                        <input type="checkbox" name="is_from_current_user" id="is_from_current_user" value="1" class="w-4 h-4 text-amber-600 bg-gray-50 border-gray-300 rounded focus:ring-amber-500" <?= (!isset($edit_record) || ($edit_record['is_from_current_user'] ?? 1) == 1) ? 'checked' : '' ?>>
                                        <label for="is_from_current_user" class="ml-2 text-sm font-medium text-gray-700">This usage is from current user (custodian)</label>
                                    </div>
                                </div>
                                
                                <div id="remark_section" class="mt-3 <?= (isset($edit_record) && $edit_record['is_from_current_user'] == 0) ? '' : 'hidden' ?>">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Remark / Driver Information</label>
                                    <textarea name="remark" rows="2" placeholder="e.g., Vehicle borrowed by Ahmad from Logistics Dept, Temporary driver: Ali bin Abu, etc." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"><?= htmlspecialchars($edit_record['remark'] ?? '') ?></textarea>
                                    <p class="text-xs text-gray-400 mt-1">Specify who used the vehicle if not the custodian</p>
                                </div>
                            </div>
                            
                            <div class="flex justify-end gap-2 mt-5 pt-2 border-t border-gray-100">
                                <?php if($edit_record): ?>
                                    <a href="index.php?car_id=<?= $selected_car ?>&month=<?= urlencode($selected_month) ?>" class="px-5 py-2 bg-gray-100 text-gray-600 font-medium rounded-lg hover:bg-gray-200 transition">Cancel</a>
                                <?php endif; ?>
                                <button type="submit" name="save_usage" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 shadow-sm transition">
                                    <?= $edit_record ? 'Update' : 'Save' ?> Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Records Table Card -->
                <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-sm">📋</span>
                            Usage Records
                        </h2>
                        <?php if ($record_count > 0): ?>
                        <span class="text-xs font-medium text-gray-500 bg-white px-3 py-1 rounded-full border border-gray-200"><?= $record_count ?> entries</span>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Odometer</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Mileage</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Litre</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Consumption</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Driver</th>
                                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($records && $records->num_rows > 0): 
                                    while($row = $records->fetch_assoc()): 
                                        $is_non_user = !$row['is_from_current_user'];
                                ?>
                                <tr class="hover:bg-gray-50/50 transition <?= $is_non_user ? 'bg-amber-50/30' : '' ?>">
                                    <td class="px-5 py-3.5 font-medium text-gray-800"><?= date("d/m/Y", strtotime($row['date_recorded'])) ?></td>
                                    <td class="px-5 py-3.5 text-sm text-gray-500 font-mono"><?= number_format($row['predrive']) ?> → <?= number_format($row['returned']) ?></td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-700"><?= number_format($row['mileage']) ?> km</td>
                                    <td class="px-5 py-3.5 font-semibold text-green-600">RM <?= number_format($row['amount'], 2) ?></td>
                                    <td class="px-5 py-3.5 text-gray-600"><?= number_format($row['liter'], 2) ?> L</td>
                                    <td class="px-5 py-3.5">
                                        <?php if ($row['fuel_consumption'] && $row['fuel_consumption'] > 0): ?>
                                            <span class="text-xs font-semibold px-2 py-1 rounded-full <?= $selected_car_info['fuel_type'] == 'diesel' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>">
                                                <?= number_format($row['fuel_consumption'], 2) ?> L/100km
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <?php if ($is_non_user): ?>
                                            <div class="flex flex-col gap-1">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full badge-warning w-fit">⚠️ Other Driver</span>
                                                <?php if ($row['remark']): ?>
                                                    <span class="text-xs text-gray-500 max-w-[200px] truncate" title="<?= htmlspecialchars($row['remark']) ?>">
                                                        📝 <?= htmlspecialchars(substr($row['remark'], 0, 40)) ?><?= strlen($row['remark']) > 40 ? '...' : '' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-green-600">✓ Custodian</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex justify-center gap-3">
                                            <a href="index.php?car_id=<?= $selected_car ?>&month=<?= urlencode($selected_month) ?>&edit_id=<?= $row['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</a>
                                            <a href="index.php?car_id=<?= $selected_car ?>&month=<?= urlencode($selected_month) ?>&delete_id=<?= $row['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this record?')" 
                                               class="text-red-500 hover:text-red-700 text-sm font-medium">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-4xl opacity-40">📭</span>
                                            <p class="text-sm">No records found for this period</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if($record_count > 0): ?>
                            <tfoot>
                                <tr class="bg-gradient-to-r from-blue-900 to-indigo-900 text-white">
                                    <td colspan="2" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider opacity-80">Monthly Totals</td>
                                    <td class="px-5 py-3 font-bold"><?= number_format($total_milage) ?> km</td>
                                    <td class="px-5 py-3 font-bold text-green-300">RM <?= number_format($total_amount, 2) ?></td>
                                    <td class="px-5 py-3 font-bold"><?= number_format($total_litre, 2) ?> L</td>
                                    <td class="px-5 py-3 font-bold">
                                        <?php if ($avg_consumption > 0): ?>
                                            <span class="text-green-300"><?= number_format($avg_consumption, 2) ?> L/100km (avg)</span>
                                        <?php else: ?>
                                            <span class="text-white/60">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 font-bold">
                                        <?php if ($non_user_count > 0): ?>
                                            <span class="text-amber-300"><?= $non_user_count ?> non-user trip(s)</span>
                                        <?php else: ?>
                                            <span class="text-white/60">All by custodian</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3"></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <?php elseif (!$selected_car && !$selected_month): ?>
                <!-- Empty state when nothing selected -->
                <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm p-12 text-center">
                    <div class="text-6xl mb-4 opacity-30">🚙</div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-1">Select a vehicle and month</h3>
                    <p class="text-sm text-gray-500">Use the dropdowns above to view or add usage records</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Toggle remark section based on checkbox
    const checkbox = document.getElementById('is_from_current_user');
    const remarkSection = document.getElementById('remark_section');
    
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                remarkSection.classList.add('hidden');
            } else {
                remarkSection.classList.remove('hidden');
            }
        });
    }
</script>

</body>
</html>