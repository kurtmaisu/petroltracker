<?php
$conn = new mysqli("localhost", "root", "", "petrol_tracker");

$preview_data = null;
$duplicate_entries = [];
$processing = false;
$extracted_count = 0;

// Array for months
$months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

if (isset($_POST['process_pdf'])) {
    $processing = true;
    $car_id = $_POST['car_id'];
    $selected_month = $_POST['month'];
    
    // Get month number for date generation (1-12)
    $month_num = array_search($selected_month, $months) + 1;
    $year = date('Y');
    
    // Simulated Extraction Logic - DYNAMIC BASED ON SELECTED MONTH
    // In production, replace this with actual OCR/Gemini API that returns an array of records
    $preview_data = [];
    
    // Generate 3-8 sample records for the selected month
    $num_records = rand(4, 8); // Simulate variable number of records
    
    $base_odo = rand(40000, 50000);
    $current_date = 1;
    
    for ($i = 0; $i < $num_records; $i++) {
        // Generate dates within the selected month
        $day = min($current_date + rand(3, 7), 28); // Max 28th to avoid month boundary issues
        $current_date = $day + 1;
        
        $date_str = sprintf('%04d-%02d-%02d', $year, $month_num, $day);
        
        $predrive = $base_odo;
        $distance = rand(80, 250);
        $returned = $base_odo + $distance;
        $liter = round($distance / rand(10, 15), 2); // Random efficiency 10-15 km/L
        $amount = round($liter * 2.05, 2); // RM 2.05 per liter
        
        $preview_data[] = [
            'date_recorded' => $date_str,
            'predrive' => (string)$predrive,
            'returned' => (string)$returned,
            'amount' => (string)$amount,
            'liter' => (string)$liter
        ];
        
        $base_odo = $returned;
    }
    
    $extracted_count = count($preview_data);
    
    // Check for duplicates for each extracted record
    foreach ($preview_data as $index => $record) {
        $check = $conn->query("SELECT id FROM usage_records WHERE car_id = $car_id AND date_recorded = '{$record['date_recorded']}'");
        if ($check->num_rows > 0) {
            $duplicate_entries[$index] = $check->fetch_assoc()['id'];
        }
    }
}

// Handle batch save
if (isset($_POST['save_all_records'])) {
    $car_id = $_POST['car_id'];
    $month = $_POST['month'];
    $record_count = $_POST['record_count'];
    
    $saved = 0;
    for ($i = 0; $i < $record_count; $i++) {
        if (isset($_POST["include_$i"]) && $_POST["include_$i"] == '1') {
            $date_rec = $_POST["date_recorded_$i"];
            $predrive = $_POST["predrive_$i"];
            $returned = $_POST["returned_$i"];
            $amount = $_POST["amount_$i"];
            $liter = $_POST["liter_$i"];
            $existing_id = $_POST["existing_id_$i"] ?? '';
            
            if (!empty($existing_id)) {
                // Update existing record
                $conn->query("UPDATE usage_records SET 
                    month='$month', 
                    date_recorded='$date_rec', 
                    predrive='$predrive', 
                    returned='$returned', 
                    amount='$amount', 
                    liter='$liter' 
                    WHERE id=$existing_id");
            } else {
                // Insert new record
                $conn->query("INSERT INTO usage_records (car_id, month, date_recorded, predrive, returned, amount, liter) 
                              VALUES ('$car_id', '$month', '$date_rec', '$predrive', '$returned', '$amount', '$liter')");
            }
            $saved++;
        }
    }
    
    header("Location: index.php?car_id=$car_id&month=$month&saved=$saved");
    exit();
}

// Get car count for display
$car_count_result = $conn->query("SELECT COUNT(*) as count FROM cars");
$car_count = $car_count_result->fetch_assoc()['count'];

// Get selected month for display
$selected_month_display = $_POST['month'] ?? date('F');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan PDF · Petrol Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        .upload-area {
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #6366f1;
            background: linear-gradient(to bottom right, #fafafa, #ffffff);
        }
        input[type="file"] {
            display: none;
        }
        .record-row {
            transition: background-color 0.2s ease;
        }
        .record-row:hover {
            background-color: #f9fafb;
        }
        /* Scrollable table for many records */
        .records-container {
            max-height: 500px;
            overflow-y: auto;
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
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-pink-600 rounded-lg flex items-center justify-center shadow-md">
                        <span class="text-white text-xl">📄</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Scan<span class="text-purple-600">Processor</span></h1>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider -mt-0.5">OCR Document Import</p>
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
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Info Banner -->
        <?php if ($car_count == 0): ?>
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-semibold text-amber-800">No vehicles registered yet</p>
                <p class="text-sm text-amber-700 mt-0.5">Please add at least one vehicle in the dashboard before scanning logs.</p>
                <a href="index.php" class="inline-block mt-2 text-sm font-medium text-amber-900 hover:text-amber-700 underline">Go to Dashboard →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upload Card -->
        <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-purple-50/50 to-pink-50/50">
                <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                    <span class="w-6 h-6 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-sm">📤</span>
                    Upload Document for Processing
                </h2>
                <p class="text-sm text-gray-500 mt-1 ml-8">Upload a scanned PDF or image of your monthly petrol log for automatic data extraction (up to 10 records)</p>
            </div>
            
            <div class="p-6">
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Select Vehicle</label>
                            <select name="car_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition" required <?= $car_count == 0 ? 'disabled' : '' ?>>
                                <option value="">— Choose a vehicle —</option>
                                <?php 
                                if ($car_count > 0) {
                                    $cl = $conn->query("SELECT * FROM cars ORDER BY plate_number"); 
                                    while($c = $cl->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['plate_number'] ?> — <?= $c['custodian'] ?: 'No custodian' ?></option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Billing Month</label>
                            <select name="month" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl font-medium focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition">
                                <?php foreach($months as $m): ?>
                                    <option value="<?= $m ?>" <?= (date('F') == $m) ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Upload Area -->
                    <div class="upload-area border-2 border-dashed border-gray-200 rounded-2xl p-10 text-center bg-gray-50/30 mb-6 cursor-pointer" onclick="document.getElementById('file_input').click()">
                        <input type="file" name="pdf_log" id="file_input" accept=".pdf,image/*" onchange="updateFileName(this)">
                        <div class="max-w-sm mx-auto">
                            <div class="w-20 h-20 bg-gradient-to-br from-purple-100 to-pink-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                            <p class="font-semibold text-gray-700 mb-1">Click to upload or drag and drop</p>
                            <p class="text-sm text-gray-500 mb-3" id="file_name_display">PDF, JPG, or PNG (Max 10MB)</p>
                            <span class="inline-block px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 shadow-sm">
                                Browse Files
                            </span>
                        </div>
                    </div>

                    <button type="submit" name="process_pdf" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold py-3.5 rounded-xl hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?= $car_count == 0 ? 'disabled' : '' ?>>
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Process & Extract Data
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Processing Indicator -->
        <?php if ($processing && !$preview_data): ?>
        <div class="mt-6 bg-white rounded-2xl border border-gray-200/60 shadow-sm p-8 text-center">
            <div class="w-16 h-16 border-4 border-purple-200 border-t-purple-600 rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-gray-600 font-medium">Processing document...</p>
            <p class="text-sm text-gray-400 mt-1">Extracting data using OCR</p>
        </div>
        <?php endif; ?>

        <!-- Multiple Records Preview -->
        <?php if ($preview_data): ?>
        <div class="mt-6 bg-white rounded-2xl border-2 <?= count($duplicate_entries) > 0 ? 'border-amber-400' : 'border-green-400' ?> shadow-lg overflow-hidden animate-fade-in">
            <div class="px-6 py-4 <?= count($duplicate_entries) > 0 ? 'bg-amber-50' : 'bg-green-50' ?> border-b <?= count($duplicate_entries) > 0 ? 'border-amber-200' : 'border-green-200' ?>">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 <?= count($duplicate_entries) > 0 ? 'bg-amber-100' : 'bg-green-100' ?> rounded-xl flex items-center justify-center">
                            <span class="text-xl"><?= count($duplicate_entries) > 0 ? '⚠️' : '✅' ?></span>
                        </div>
                        <div>
                            <h3 class="font-bold <?= count($duplicate_entries) > 0 ? 'text-amber-800' : 'text-green-800' ?>">
                                <?= $extracted_count ?> Records Extracted for <?= $selected_month_display ?>
                            </h3>
                            <p class="text-sm <?= count($duplicate_entries) > 0 ? 'text-amber-600' : 'text-green-600' ?>">
                                <?= count($duplicate_entries) > 0 ? count($duplicate_entries) . ' record(s) already exist and will be updated' : 'Review and select records to save' ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="toggleAllRecords(true)" class="text-xs px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">Select All</button>
                        <button type="button" onclick="toggleAllRecords(false)" class="text-xs px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">Deselect All</button>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <form action="ocr_upload.php" method="POST" id="batchSaveForm">
                    <input type="hidden" name="car_id" value="<?= $_POST['car_id'] ?>">
                    <input type="hidden" name="month" value="<?= $_POST['month'] ?>">
                    <input type="hidden" name="record_count" value="<?= $extracted_count ?>">
                    <input type="hidden" name="saved_count" id="saved_count" value="0">
                    
                    <!-- Table Header -->
                    <div class="grid grid-cols-12 gap-2 px-4 py-3 bg-gray-50 rounded-t-xl text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <div class="col-span-1">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllRecords(this.checked)" class="rounded border-gray-300">
                        </div>
                        <div class="col-span-2">Date</div>
                        <div class="col-span-2">Odometer Range</div>
                        <div class="col-span-1">Distance</div>
                        <div class="col-span-2">Amount (RM)</div>
                        <div class="col-span-2">Litres</div>
                        <div class="col-span-1">Efficiency</div>
                        <div class="col-span-1">Status</div>
                    </div>
                    
                    <!-- Scrollable Records List -->
                    <div class="records-container border border-gray-200 border-t-0 rounded-b-xl">
                        <?php 
                        $total_distance = 0;
                        $total_amount = 0;
                        $total_liters = 0;
                        
                        foreach ($preview_data as $index => $record): 
                            $distance = $record['returned'] - $record['predrive'];
                            $efficiency = $distance / max(0.01, $record['liter']);
                            $is_duplicate = isset($duplicate_entries[$index]);
                            
                            $total_distance += $distance;
                            $total_amount += $record['amount'];
                            $total_liters += $record['liter'];
                        ?>
                        <div class="record-row grid grid-cols-12 gap-2 px-4 py-3 items-center bg-white hover:bg-gray-50/50 transition border-b border-gray-100 last:border-b-0">
                            <div class="col-span-1">
                                <input type="checkbox" name="include_<?= $index ?>" value="1" class="record-checkbox rounded border-gray-300" checked onchange="updateSaveCount()">
                                <input type="hidden" name="existing_id_<?= $index ?>" value="<?= $duplicate_entries[$index] ?? '' ?>">
                            </div>
                            <div class="col-span-2">
                                <input type="date" name="date_recorded_<?= $index ?>" value="<?= $record['date_recorded'] ?>" 
                                       class="w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500/20 outline-none">
                            </div>
                            <div class="col-span-2">
                                <div class="flex items-center gap-1">
                                    <input type="number" name="predrive_<?= $index ?>" value="<?= $record['predrive'] ?>" 
                                           class="w-16 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500/20 outline-none">
                                    <span class="text-gray-400 text-xs">→</span>
                                    <input type="number" name="returned_<?= $index ?>" value="<?= $record['returned'] ?>" 
                                           class="w-16 px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500/20 outline-none">
                                </div>
                            </div>
                            <div class="col-span-1">
                                <span class="font-medium text-gray-700 text-sm"><?= number_format($distance) ?> km</span>
                            </div>
                            <div class="col-span-2">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">RM</span>
                                    <input type="number" step="0.01" name="amount_<?= $index ?>" value="<?= $record['amount'] ?>" 
                                           class="w-full pl-8 pr-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-green-700 font-medium focus:ring-2 focus:ring-purple-500/20 outline-none">
                                </div>
                            </div>
                            <div class="col-span-2">
                                <div class="relative">
                                    <input type="number" step="0.01" name="liter_<?= $index ?>" value="<?= $record['liter'] ?>" 
                                           class="w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500/20 outline-none">
                                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">L</span>
                                </div>
                            </div>
                            <div class="col-span-1">
                                <span class="text-xs font-medium <?= $efficiency > 12 ? 'text-green-600' : ($efficiency > 8 ? 'text-amber-600' : 'text-red-600') ?>">
                                    <?= number_format($efficiency, 1) ?> km/L
                                </span>
                            </div>
                            <div class="col-span-1">
                                <?php if ($is_duplicate): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                                        Update
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                        New
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Summary Footer -->
                    <div class="grid grid-cols-12 gap-2 px-4 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border border-gray-200 rounded-xl mt-3">
                        <div class="col-span-1"></div>
                        <div class="col-span-2 text-sm font-semibold text-gray-700">Totals:</div>
                        <div class="col-span-2"></div>
                        <div class="col-span-1">
                            <span class="font-bold text-gray-900"><?= number_format($total_distance) ?> km</span>
                        </div>
                        <div class="col-span-2">
                            <span class="font-bold text-green-700">RM <?= number_format($total_amount, 2) ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="font-bold text-gray-900"><?= number_format($total_liters, 2) ?> L</span>
                        </div>
                        <div class="col-span-1">
                            <span class="font-bold <?= ($total_distance / max(0.01, $total_liters)) > 12 ? 'text-green-600' : 'text-amber-600' ?>">
                                <?= number_format($total_distance / max(0.01, $total_liters), 1) ?> km/L
                            </span>
                        </div>
                        <div class="col-span-1"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="submit" name="save_all_records" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold py-3 rounded-xl shadow-sm hover:shadow-lg transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save <span id="selectedCountDisplay"><?= $extracted_count ?></span> Selected Records
                        </button>
                        <a href="ocr_upload.php" class="px-6 py-3 bg-gray-100 text-gray-600 font-medium rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Help Card -->
        <?php if (!$preview_data && !$processing): ?>
        <div class="mt-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl border border-purple-100 p-5">
            <div class="flex gap-3">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                    <span class="text-purple-600 text-lg">💡</span>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 mb-1">How it works</h4>
                    <p class="text-sm text-gray-600">
                        Upload a scanned petrol log (PDF or image). Our OCR system will extract all entries (up to 10 records) 
                        including date, odometer readings, amount paid, and litres pumped. Review each record and save them to your database.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
    function updateFileName(input) {
        const display = document.getElementById('file_name_display');
        if (input.files && input.files[0]) {
            display.textContent = input.files[0].name;
            display.className = 'text-sm font-medium text-purple-600 mb-3';
        } else {
            display.textContent = 'PDF, JPG, or PNG (Max 10MB)';
            display.className = 'text-sm text-gray-500 mb-3';
        }
    }

    function toggleAllRecords(checked) {
        document.querySelectorAll('.record-checkbox').forEach(cb => cb.checked = checked);
        document.getElementById('selectAllCheckbox').checked = checked;
        document.getElementById('selectAllCheckbox').indeterminate = false;
        updateSaveCount();
    }

    function updateSaveCount() {
        const checked = document.querySelectorAll('.record-checkbox:checked').length;
        document.getElementById('selectedCountDisplay').textContent = checked;
        document.getElementById('saved_count').value = checked;
    }

    // Initialize save count and checkbox states
    document.addEventListener('DOMContentLoaded', function() {
        updateSaveCount();
        
        // Sync select all checkbox
        const checkboxes = document.querySelectorAll('.record-checkbox');
        const selectAll = document.getElementById('selectAllCheckbox');
        
        function updateSelectAll() {
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            if (checkedCount === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.indeterminate = true;
            }
        }
        
        checkboxes.forEach(cb => cb.addEventListener('change', function() {
            updateSaveCount();
            updateSelectAll();
        }));
        
        updateSelectAll();
    });

    // Drag and drop functionality
    const uploadArea = document.querySelector('.upload-area');
    const fileInput = document.getElementById('file_input');
    
    if (uploadArea && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('border-purple-400', 'bg-purple-50/50');
            });
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('border-purple-400', 'bg-purple-50/50');
            });
        });
        
        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                updateFileName(fileInput);
            }
        });
    }
</script>

</body>
</html>