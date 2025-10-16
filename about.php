<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    startSecureSession();
}

// Include header
$pageTitle = "About Us";
include('includes/header.php');
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2>About Bataan Peninsula State University Library System</h2>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Our Mission</h4>
                </div>
                <div class="card-body">
                    <p>To develop innovative leaders and empowered communities by delivering transformative instruction, research, extension, and production through Change Drivers and responsive policies.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Our Vision</h4>
                </div>
                <div class="card-body">
                    <p>An inclusive and sustainable University recognized for its global academic excellence by 2030.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Library Services</h4>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Book borrowing and returns</li>
                        <li>Reference and research assistance</li>
                        <li>Access to digital resources and databases</li>
                        <li>Study spaces and computer facilities</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Contact Information</h4>
                </div>
                <div class="card-body">
                    <p><strong>Location:</strong> Main Campus, Bataan Peninsula State University</p>
                    <p><strong>Email:</strong> booklogbpsulibrary@gmail.com</p>
                    <p><strong>Phone:</strong> (123) 456-7890</p>
                    <p><strong>Hours:</strong> Monday to Friday, 8:00 AM to 5:00 PM</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5 mb-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Developers</h4>
                </div>
                <div class="card-body">
                    <p>Franchezka Louise S. Quiambao</p>
                    <p>Angelica A. Rubio</p>
                    <p>Angel Gwen D. Matic</p>
                </div>
            </div>
        </div>
    </div>
</div>




<?php include('includes/footer.php'); ?>
