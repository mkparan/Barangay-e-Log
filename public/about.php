<?php
$page_title = "About - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

$db = db_connect();
?>

<div class="container-box mb-4">
  <h2 class="mb-4">About Barangay e-Log System</h2>
  
  <div class="row g-4 mb-5">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h4 class="mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>System Overview</h4>
          <p class="lead">
            The Barangay e-Log System is a comprehensive digital platform designed to streamline barangay operations and improve citizen services. 
            Our system enables efficient appointment management, citizen registration, and real-time communication between barangay officials and residents.
          </p>
          
          <h5 class="mt-4 mb-3">Key Features</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-calendar-check text-primary me-3 fs-5"></i>
                <div>
                  <h6>Online Appointment Booking</h6>
                  <p class="text-muted small mb-0">Citizens can book appointments for various barangay services online, eliminating the need for long queues.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-people text-primary me-3 fs-5"></i>
                <div>
                  <h6>Citizen Management</h6>
                  <p class="text-muted small mb-0">Comprehensive citizen registration and verification system with Barangay ID tracking.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-megaphone text-primary me-3 fs-5"></i>
                <div>
                  <h6>Announcements</h6>
                  <p class="text-muted small mb-0">Real-time announcements and important updates from the barangay hall.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-clock-history text-primary me-3 fs-5"></i>
                <div>
                  <h6>Appointment History</h6>
                  <p class="text-muted small mb-0">Complete record of all appointments and transactions for accountability and tracking.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-calendar-event text-primary me-3 fs-5"></i>
                <div>
                  <h6>Availability Management</h6>
                  <p class="text-muted small mb-0">Barangay officials can set available dates and time slots for appointments.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-start">
                <i class="bi bi-graph-up text-primary me-3 fs-5"></i>
                <div>
                  <h6>Analytics & Reports</h6>
                  <p class="text-muted small mb-0">Comprehensive analytics and reporting tools for barangay officials.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h5 class="mb-3"><i class="bi bi-building me-2 text-primary"></i>Contact Information</h5>
          <p class="mb-2">
            <strong>Barangay Hall</strong><br>
            <i class="bi bi-geo-alt me-2"></i>Barangay Duangan<br>
            <i class="bi bi-telephone me-2"></i>(02) 123-4567<br>
            <i class="bi bi-envelope me-2"></i>info@barangayduangan.gov.ph
          </p>
          <hr>
          <p class="mb-0 small text-muted">
            <strong>Office Hours:</strong><br>
            Monday - Friday: 8:00 AM - 5:00 PM<br>
            Saturday: 8:00 AM - 12:00 PM<br>
            Sunday: Closed
          </p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions (FAQs)</h4>
    </div>
    <div class="card-body">
      <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
              How do I register as a citizen?
            </button>
          </h2>
          <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              To register, click on the "Register" link in the navigation menu. You'll need to provide your Barangay ID, full name, and other required information. 
              After registration, you can log in immediately, but your account will need to be verified by a barangay official before you can create appointments.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
              How do I book an appointment?
            </button>
          </h2>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Once your account is verified, log in to your dashboard and click "Create Appointment". Select the service you need, choose an available date and time slot (morning or afternoon), 
              and provide any additional details. Your appointment will be reviewed by barangay officials and you'll be notified of its status.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
              What is a Barangay ID?
            </button>
          </h2>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              A Barangay ID is your unique identification number issued by the barangay. It's used to identify you in the system and track your appointments and transactions. 
              If you don't have a Barangay ID yet, please visit the barangay hall to obtain one.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
              How long does account verification take?
            </button>
          </h2>
          <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Account verification is typically processed within 1-2 business days. Barangay officials review new registrations to ensure all information is accurate. 
              You'll receive a notification once your account has been verified, and then you can start booking appointments.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
              Can I cancel or reschedule my appointment?
            </button>
          </h2>
          <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Yes, you can cancel appointments that are still pending or approved. Go to your appointment history, find the appointment you want to cancel, and click the "Cancel" button. 
              For rescheduling, please contact the barangay hall directly or create a new appointment.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
              What services are available for appointment?
            </button>
          </h2>
          <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Available services include but are not limited to: Barangay Clearance, Business Permit, Certificate of Residency, Certificate of Indigency, 
              and other barangay-related documents and services. The exact services available may vary, so please check the appointment creation page for the current list.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
              How do I know if my appointment is approved?
            </button>
          </h2>
          <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              You can check the status of your appointments in your dashboard or appointment history. The status will show as "Pending" while under review, 
              "Approved" when confirmed, "Declined" if not approved, or "Completed" after the service has been rendered.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
              What should I bring to my appointment?
            </button>
          </h2>
          <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Please bring a valid government-issued ID, your Barangay ID, and any documents specific to the service you're requesting. 
              It's recommended to check with the barangay hall beforehand or review the service requirements when creating your appointment.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
              Can I see my appointment history?
            </button>
          </h2>
          <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Yes, you can view your complete appointment history by clicking on "History" in your dashboard menu. 
              You can filter your history by date range, service type, and status to find specific appointments.
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
              What if I forget my password?
            </button>
          </h2>
          <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              If you forget your password, please contact the barangay hall directly. For security reasons, password resets must be done in person with proper identification. 
              Barangay officials can assist you with account recovery.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

