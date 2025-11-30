<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

// Allow access without login - this is a kiosk page
$db = db_connect();
$errors = [];
$success = isset($_GET['success']) && $_GET['success'] == '1';

// Services list (same as create_appointment.php)
$services = [
  'Barangay Clearance','Certificate of Residency','Certificate of Indigency','Purok Clearance','Barangay Clearance Recommendation',
  'Certificate of No Issuance','Barangay Business Permit','Complaint Blotter','Settlement/Mediation Certification','Cedula','Certificate of Tribal Membership','Others'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cin = trim($_POST['cin'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? '');
    $otherReason = trim($_POST['other_reason'] ?? '');
    
    // Validation
    if (empty($firstName) || empty($lastName)) {
        $errors[] = "First name and last name are required.";
    } elseif (empty($contactNumber)) {
        $errors[] = "Contact number is required.";
    } elseif (empty($serviceType)) {
        $errors[] = "Please select a reason for your visit.";
    } elseif ($serviceType === 'Others' && empty($otherReason)) {
        $errors[] = "Please specify the reason for your visit.";
    } else {
        // Check if citizen exists (if CIN provided)
        $citizenId = null;
        if (!empty($cin)) {
            $checkStmt = $db->prepare("SELECT citizen_id FROM citizens WHERE cin = ? LIMIT 1");
            $checkStmt->bind_param('s', $cin);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $citizenId = $row['citizen_id'];
            }
        }
        
        // If no citizen found and CIN provided, or no CIN provided, create a walk-in record
        if ($citizenId === null) {
            // Generate a temporary CIN for walk-ins
            $tempCin = 'WALKIN-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            // Check if password_hash column exists
            $checkColumn = $db->query("SHOW COLUMNS FROM citizens LIKE 'password_hash'");
            if ($checkColumn->num_rows == 0) {
                $db->query("ALTER TABLE citizens ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
            }
            
            // Create temporary citizen record
            $insertStmt = $db->prepare("INSERT INTO citizens (cin, first_name, last_name, contact_number, is_verified, is_active) VALUES (?, ?, ?, ?, 0, 1)");
            $insertStmt->bind_param('ssss', $tempCin, $firstName, $lastName, $contactNumber);
            if ($insertStmt->execute()) {
                $citizenId = $insertStmt->insert_id;
                audit_log($tempCin, null, 'walkin_citizen_created', 'citizens', $citizenId);
            } else {
                $errors[] = "Error creating walk-in record: " . $insertStmt->error;
            }
        }
        
        if ($citizenId && empty($errors)) {
            // Create walk-in appointment (status: pending, no date/time required for walk-ins)
            $serviceDisplay = $serviceType === 'Others' ? 'Others: ' . $otherReason : $serviceType;
            $details = $serviceType === 'Others' ? 'Walk-in: ' . $otherReason : 'Walk-in appointment';
            
            // Set today's date and current time
            $today = date('Y-m-d');
            $currentTime = date('H:i:s');
            
            // Insert walk-in appointment
            $apptStmt = $db->prepare("INSERT INTO appointments (citizen_id, service_type, details, preferred_date, preferred_start, status, queue_number) VALUES (?, ?, ?, ?, ?, 'pending', 0)");
            $apptStmt->bind_param('issss', $citizenId, $serviceDisplay, $details, $today, $currentTime);
            
            if ($apptStmt->execute()) {
                audit_log($cin ?: 'WALKIN', null, 'walkin_appointment_created', 'appointments', $apptStmt->insert_id);
                // Redirect to prevent form resubmission and show success message
                header('Location: walkin.php?success=1');
                exit;
            } else {
                $errors[] = "Error creating walk-in appointment: " . $apptStmt->error;
            }
        }
    }
}

$page_title = "Walk-In Kiosk - Barangay e-Log";
// Don't include header/footer for kiosk mode
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($page_title) ?></title>
    <link href="/elog_barangay/public/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Force light mode */
        body {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
        
        /* Kiosk-specific styles for touch-friendly interface */
        .kiosk-container {
            min-height: 100vh;
            padding: 2rem 1rem;
            background: #f8f9fa;
        }

.kiosk-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    padding: 2.5rem;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}

.kiosk-logo {
    max-width: 300px;
    max-height: 250px;
    width: auto;
    height: auto;
    margin-bottom: 1rem;
    object-fit: contain;
}

.kiosk-title {
    font-size: 2.5rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 0.5rem;
    text-align: center;
}

.kiosk-subtitle {
    font-size: 1.2rem;
    color: #666;
    text-align: center;
    margin-bottom: 2rem;
}

.kiosk-form-group {
    margin-bottom: 1.5rem;
}

.kiosk-label {
    font-size: 1.3rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.75rem;
    display: block;
}

.kiosk-input,
.kiosk-select,
.kiosk-textarea {
    width: 100%;
    padding: 1rem 1.25rem;
    font-size: 1.2rem;
    border: 3px solid #e0e0e0;
    border-radius: 12px;
    transition: all 0.3s;
    min-height: 60px;
}

.kiosk-input:focus,
.kiosk-select:focus,
.kiosk-textarea:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
}

/* Touch device optimizations */
.touch-device .kiosk-input,
.touch-device .kiosk-textarea,
.touch-device .kiosk-select {
    -webkit-tap-highlight-color: rgba(13, 110, 253, 0.2);
    touch-action: manipulation;
    -webkit-appearance: none;
    appearance: none;
}

.touch-device .kiosk-input:focus,
.touch-device .kiosk-textarea:focus {
    transform: scale(1.02);
    transition: transform 0.2s;
}

/* Ensure inputs are not blocked on touch devices */
.touch-device input[readonly],
.touch-device textarea[readonly] {
    pointer-events: auto;
    cursor: text;
}

.kiosk-textarea {
    min-height: 120px;
    resize: vertical;
}

.kiosk-btn {
    width: 100%;
    padding: 1.25rem 2rem;
    font-size: 1.4rem;
    font-weight: bold;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    min-height: 70px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.kiosk-btn-primary {
    background: #0d6efd;
    color: white;
}

.kiosk-btn-primary:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(13, 110, 253, 0.3);
}

.kiosk-btn-primary:active {
    transform: translateY(0);
}

.kiosk-alert {
    padding: 1.5rem;
    border-radius: 12px;
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    border: none;
}

.kiosk-success {
    background: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
}

.kiosk-error {
    background: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.kiosk-info-box {
    background: #e7f3ff;
    border: 2px solid #b3d9ff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.kiosk-info-box h5 {
    color: #004085;
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
}

.kiosk-info-box p {
    color: #004085;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.optional-badge {
    display: inline-block;
    background: #6c757d;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: normal;
    margin-left: 0.5rem;
}

/* Service Cards */
.kiosk-service-card {
    border: 3px solid #e0e0e0;
    border-radius: 15px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.kiosk-service-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
    transform: translateY(-2px);
}

.kiosk-service-card.selected {
    border-color: #0d6efd;
    background: #e7f1ff;
    box-shadow: 0 5px 20px rgba(13, 110, 253, 0.3);
}

.service-card-body {
    width: 100%;
    padding: 1.25rem;
    text-align: center;
    position: relative;
}

.service-card-body input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.service-label {
    display: block;
    cursor: pointer;
    width: 100%;
    margin: 0;
}

.service-name {
    display: block;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    line-height: 1.4;
}

.check-icon {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    font-size: 1.5rem;
    color: #0d6efd;
    opacity: 0;
    transition: opacity 0.3s;
}

.kiosk-service-card.selected .check-icon {
    opacity: 1;
}

@media (max-width: 768px) {
    .kiosk-container {
        padding: 1rem 0.5rem;
    }
    
    .kiosk-card {
        padding: 1.5rem;
        border-radius: 15px;
    }
    
    .kiosk-title {
        font-size: 2rem;
    }
    
    .kiosk-subtitle {
        font-size: 1rem;
    }
    
    .kiosk-label {
        font-size: 1.1rem;
    }
    
    .kiosk-input,
    .kiosk-select,
    .kiosk-textarea {
        font-size: 1rem;
        padding: 0.875rem 1rem;
        min-height: 55px;
    }
    
    .kiosk-btn {
        font-size: 1.2rem;
        padding: 1rem 1.5rem;
        min-height: 60px;
    }
    
    #serviceCards .col-md-4 {
        margin-bottom: 1rem;
    }
    
    .kiosk-service-card {
        min-height: 80px;
    }
    
    .service-name {
        font-size: 0.95rem;
    }
    
    /* Stack columns on mobile */
    .col-lg-5,
    .col-lg-7 {
        margin-bottom: 2rem;
    }
}
</style>
</head>
<body>

<div class="kiosk-container">
    <div class="kiosk-card">
        <div class="text-center mb-4">
            <img src="/elog_barangay/public/assets/images/logo.png" alt="Barangay Logo" class="kiosk-logo">
        </div>
        <h1 class="kiosk-title text-center">
            <i class="bi bi-building me-2"></i>Walk-In Kiosk
        </h1>
        <p class="kiosk-subtitle text-center">Please fill out the form below to register your visit</p>
        
        <?php if ($success): ?>
            <div class="kiosk-alert kiosk-success">
                <h4><i class="bi bi-check-circle-fill me-2"></i>Success!</h4>
                <p class="mb-0">Your walk-in has been registered. Please wait for assistance from the barangay staff.</p>
            </div>
            <div class="text-center mt-4">
                <button type="button" class="kiosk-btn kiosk-btn-primary" onclick="window.location.href='walkin.php'">
                    <i class="bi bi-arrow-clockwise me-2"></i>Register Another Walk-In
                </button>
            </div>
        <?php else: ?>
            <div class="kiosk-info-box">
                <h5><i class="bi bi-info-circle me-2"></i>Welcome to the Barangay Walk-In Kiosk</h5>
                <p><strong>Instructions:</strong></p>
                <ul style="margin-bottom: 0; padding-left: 1.5rem;">
                    <li>Fill out all required fields</li>
                    <li>Barangay ID is optional - leave blank if you don't have one</li>
                    <li>Select the reason for your visit</li>
                    <li>Click "Submit" when done</li>
                </ul>
            </div>
            
            <?php foreach($errors as $error): ?>
                <div class="kiosk-alert kiosk-error">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc($error) ?>
                </div>
            <?php endforeach; ?>
            
            <form method="post" id="walkinForm">
                <div class="row g-4">
                    <!-- Left Column: Form Fields -->
                    <div class="col-lg-5">
                        <div class="kiosk-form-group">
                            <label class="kiosk-label">
                                Barangay ID
                                <span class="optional-badge">Optional</span>
                            </label>
                    <input type="text" 
                           name="cin" 
                           class="kiosk-input" 
                           placeholder="Enter your Barangay ID (if you have one)"
                           value="<?= esc($_POST['cin'] ?? '') ?>"
                           autocomplete="off"
                           inputmode="text"
                           autocapitalize="none">
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="kiosk-form-group">
                                    <label class="kiosk-label">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                            <input type="text" 
                                   name="first_name" 
                                   class="kiosk-input" 
                                   placeholder="Enter your first name"
                                   value="<?= esc($_POST['first_name'] ?? '') ?>"
                                   required
                                   autocomplete="given-name"
                                   inputmode="text"
                                   autocapitalize="words">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="kiosk-form-group">
                                    <label class="kiosk-label">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                            <input type="text" 
                                   name="last_name" 
                                   class="kiosk-input" 
                                   placeholder="Enter your last name"
                                   value="<?= esc($_POST['last_name'] ?? '') ?>"
                                   required
                                   autocomplete="family-name"
                                   inputmode="text"
                                   autocapitalize="words">
                                </div>
                            </div>
                        </div>
                        
                        <div class="kiosk-form-group">
                            <label class="kiosk-label">
                                Contact Number <span class="text-danger">*</span>
                            </label>
                            <input type="tel" 
                                   name="contact_number" 
                                   class="kiosk-input" 
                                   placeholder="Enter your contact number (e.g., 09123456789)"
                                   value="<?= esc($_POST['contact_number'] ?? '') ?>"
                                   required
                                   autocomplete="tel"
                                   inputmode="tel"
                                   pattern="[0-9]*">
                        </div>
                        
                        <div class="kiosk-form-group" id="otherReasonGroup" style="display: none;">
                            <label class="kiosk-label">
                                Please specify your reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="other_reason" 
                                      class="kiosk-textarea" 
                                      placeholder="Please describe the reason for your visit..."
                                      id="otherReason"
                                      inputmode="text"
                                      autocapitalize="sentences"><?= esc($_POST['other_reason'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="kiosk-form-group mt-4">
                            <button type="submit" class="kiosk-btn kiosk-btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Submit Walk-In
                            </button>
                        </div>
                    </div>
                    
                    <!-- Right Column: Service Cards -->
                    <div class="col-lg-7">
                        <label class="kiosk-label">
                            Reason for Visit <span class="text-danger">*</span>
                        </label>
                        <div class="row g-3" id="serviceCards">
                            <?php foreach($services as $service): ?>
                                <div class="col-md-4">
                                    <div class="service-card kiosk-service-card <?= (isset($_POST['service_type']) && $_POST['service_type'] === $service) ? 'selected' : '' ?>" 
                                         data-service="<?= esc($service) ?>">
                                        <div class="service-card-body">
                                            <input type="radio" 
                                                   name="service_type" 
                                                   value="<?= esc($service) ?>" 
                                                   id="service_<?= esc(str_replace(' ', '_', $service)) ?>"
                                                   <?= (isset($_POST['service_type']) && $_POST['service_type'] === $service) ? 'checked' : '' ?>
                                                   required>
                                            <label for="service_<?= esc(str_replace(' ', '_', $service)) ?>" class="service-label">
                                                <i class="bi bi-check-circle-fill check-icon"></i>
                                                <span class="service-name"><?= esc($service) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="service_type" id="serviceTypeHidden" value="<?= esc($_POST['service_type'] ?? '') ?>" required>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="/elog_barangay/public/assets/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Service card selection
    const serviceCards = document.querySelectorAll('.kiosk-service-card');
    const serviceTypeHidden = document.getElementById('serviceTypeHidden');
    const otherReasonGroup = document.getElementById('otherReasonGroup');
    const otherReasonInput = document.getElementById('otherReason');
    
    serviceCards.forEach(function(card) {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            serviceCards.forEach(function(c) {
                c.classList.remove('selected');
                const radio = c.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            
            // Add selected class to clicked card
            this.classList.add('selected');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                const serviceValue = radio.value;
                if (serviceTypeHidden) {
                    serviceTypeHidden.value = serviceValue;
                }
                
                // Toggle "Others" reason field
                toggleOtherReason(serviceValue);
            }
        });
    });
    
    // Handle radio button change (for accessibility)
    document.querySelectorAll('input[name="service_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const serviceValue = this.value;
            if (serviceTypeHidden) {
                serviceTypeHidden.value = serviceValue;
            }
            toggleOtherReason(serviceValue);
            
            // Update card visual state
            serviceCards.forEach(function(card) {
                const cardRadio = card.querySelector('input[type="radio"]');
                if (cardRadio && cardRadio.value === serviceValue) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        });
    });
    
    function toggleOtherReason(serviceValue) {
        if (otherReasonGroup && otherReasonInput) {
            if (serviceValue === 'Others') {
                otherReasonGroup.style.display = 'block';
                otherReasonInput.setAttribute('required', 'required');
            } else {
                otherReasonGroup.style.display = 'none';
                otherReasonInput.removeAttribute('required');
                otherReasonInput.value = '';
            }
        }
    }
    
    // Check initial state
    const checkedRadio = document.querySelector('input[name="service_type"]:checked');
    if (checkedRadio) {
        toggleOtherReason(checkedRadio.value);
    }
    
    // Form validation
    const form = document.getElementById('walkinForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const selectedService = serviceTypeHidden ? serviceTypeHidden.value : '';
            if (selectedService === 'Others' && otherReasonInput && !otherReasonInput.value.trim()) {
                e.preventDefault();
                alert('Please specify the reason for your visit.');
                if (otherReasonInput) otherReasonInput.focus();
                return false;
            }
            
            if (!selectedService) {
                e.preventDefault();
                alert('Please select a reason for your visit.');
                return false;
            }
        });
    }
    
    // Touch detection and keyboard handling
    let isTouchDevice = false;
    
    // Detect touch capability
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0) {
        isTouchDevice = true;
        document.body.classList.add('touch-device');
    } else {
        document.body.classList.add('mouse-device');
    }
    
    // Force keyboard to show on touch devices when input is focused/tapped
    const allInputs = document.querySelectorAll('.kiosk-input, .kiosk-textarea, .kiosk-select');
    allInputs.forEach(function(input) {
        // Remove any readonly/disabled attributes that might prevent keyboard
        if (input.hasAttribute('readonly') && input.type !== 'text') {
            // Keep readonly for date pickers if needed, but allow focus
        }
        
        // Add touch event listeners to ensure keyboard appears
        if (isTouchDevice) {
            // Touch start - prepare for keyboard
            input.addEventListener('touchstart', function(e) {
                // Allow native behavior but ensure focus
                this.focus();
            }, { passive: true });
            
            // Touch end - trigger keyboard
            input.addEventListener('touchend', function(e) {
                e.preventDefault();
                this.focus();
                // Force keyboard by clicking and focusing
                setTimeout(() => {
                    this.click();
                    this.focus();
                    // For iOS, sometimes need to trigger a click event
                    const clickEvent = new MouseEvent('click', {
                        view: window,
                        bubbles: true,
                        cancelable: true
                    });
                    this.dispatchEvent(clickEvent);
                }, 10);
            }, { passive: false });
            
            // Ensure focus triggers keyboard
            input.addEventListener('focus', function() {
                if (isTouchDevice) {
                    // Scroll input into view for better UX
                    setTimeout(() => {
                        this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 300);
                }
            });
        }
    });
    
    // Auto-focus first input only on non-touch devices (to avoid auto-opening keyboard)
    if (!isTouchDevice) {
        const firstInput = document.querySelector('.kiosk-input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
    
    // Prevent form submission on Enter key for textareas (allow multi-line)
    const textareas = document.querySelectorAll('.kiosk-textarea');
    textareas.forEach(function(textarea) {
        textarea.addEventListener('keydown', function(e) {
            // Allow Enter for new lines in textarea
            // Form will still submit via button click
        });
    });
});
</script>
</body>
</html>

