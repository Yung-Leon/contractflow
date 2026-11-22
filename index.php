<?php
session_start();
// logged in users go straight to the dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ContractFlow | Contract Management System</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body>

    <!-- logo and menu on one line -->
    <nav class="menu">
        <div class="wrap">
            <a href="index.php" class="brand">
                <img src="assets/img/logo.svg" alt="ContractFlow logo">
                <strong>ContractFlow</strong>
            </a>
            <button type="button" class="menu-toggle" id="menuToggle">&#9776;</button>
            <ul id="menuList">
                <li><a href="index.php">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#process">Approval Process</a></li>
                <li><a href="#contact">Contact Us</a></li>
                <li><a href="login.php" class="login">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- slideshow: add or remove a <div class="slide"> to change the number of pictures -->
    <section class="slideshow" id="slideshow">

        <div class="slide">
            <img src="assets/img/slides/slide1.jpg" alt="">
            <div class="caption">
                <div class="wrap">
                    <h2>Every contract, from request to signature, in one system</h2>
                    <p>Create, review, approve and store contracts with a clear record of every decision.</p>
                    <a href="#about" class="btn btn-red">Centralised Solution</a>
                    <a href="#process" class="btn btn-light">Transparent Workflow</a>
                </div>
            </div>
        </div>

        <div class="slide">
            <img src="assets/img/slides/slide2.jpg" alt="">
            <div class="caption">
                <div class="wrap">
                    <h2>A defined approval path for every department</h2>
                    <p>Procurement, Legal and the CEO each review the contract before it reaches the contractor.</p>
                    <a href="#process" class="btn btn-red">Structured Approvals</a>
                    <a href="#about" class="btn btn-light">Accountable Decisions</a>
                </div>
            </div>
        </div>

        <div class="slide">
            <img src="assets/img/slides/slide3.jpg" alt="">
            <div class="caption">
                <div class="wrap">
                    <h2>Know where a contract stands at any time</h2>
                    <p>Status tracking and notifications keep every officer informed.</p>
                    <a href="#process" class="btn btn-red">Real-Time Tracking</a>
                    <a href="#contact" class="btn btn-light">Dedicated Support</a>
                </div>
            </div>
        </div>

        <button type="button" class="arrow prev" id="prevBtn" aria-label="Previous">&#10094;</button>
        <button type="button" class="arrow next" id="nextBtn" aria-label="Next">&#10095;</button>
        <div class="dots" id="dots"></div>
    </section>

    <!-- quick links under the slideshow -->
    <section class="tiles">
        <div class="wrap">
            <a href="#process" class="tile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
                <strong>Approval Process</strong>
                <span>The stages a contract follows</span>
            </a>
            <a href="#about" class="tile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <strong>Who Uses It</strong>
                <span>Officers, management and contractors</span>
            </a>
            <a href="#contact" class="tile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <strong>Contact Us</strong>
                <span>Get help with your account</span>
            </a>
        </div>
    </section>

    <!-- about -->
    <section class="block" id="about">
        <div class="wrap two-col">
            <div>
                <h2 class="title">About ContractFlow</h2>
                <p>ContractFlow is the organisation's system for managing contracts. A department raises a
                service request, and the system routes it through Procurement, Legal and Executive approval
                until the contractor signs and the contract becomes active.</p>
                <p>Every action, document and comment is recorded against the contract, so officers can see
                who handled it, what was decided and what comes next.</p>
                <a href="login.php" class="btn btn-red">Login to continue</a>
            </div>
            <div class="panel">
                <h3>Who uses the system</h3>
                <ul>
                    <li><strong>Department Officers</strong> raise service requests</li>
                    <li><strong>Procurement Officers</strong> process the request</li>
                    <li><strong>Legal Officers and Head of Legal</strong> draft and approve the contract</li>
                    <li><strong>Chief Executive Officer</strong> gives final approval</li>
                    <li><strong>Contractors</strong> review and sign</li>
                    <li><strong>Administrator</strong> manages users and access</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- the process, in short paragraphs -->
    <section class="block grey" id="process">
        <div class="wrap narrow">
            <h2 class="title">How a Contract Is Processed</h2>
            <p>A Department Officer submits the service request with its supporting document. The Procurement
            Officer checks it and passes it to a Legal Officer, who drafts the contract. The Head of Legal
            reviews the draft and the Chief Executive Officer gives the final approval. The contractor then
            signs, and the contract becomes active.</p>
            <p>At any stage a contract can be returned for correction or rejected, and everyone involved is
            notified of the outcome.</p>
        </div>
    </section>

    <!-- contact -->
    <section class="block" id="contact">
        <div class="wrap">
            <h2 class="title">Contact Us</h2>
            <div class="contact">
                <div>
                    <h3>System Support</h3>
                    <p>For a new account, a forgotten password or access problems, contact the system administrator.</p>
                </div>
                <div>
                    <h3>Email</h3>
                    <p>admin@contractflow.com</p>
                </div>
                <div>
                    <h3>Office Hours</h3>
                    <p>Monday to Friday<br>8:00 AM - 5:00 PM</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="wrap">
            <span>&copy; <?= date('Y') ?> ContractFlow. All rights reserved.</span>
            <span><a href="#about">About</a> &nbsp;|&nbsp; <a href="#contact">Contact</a></span>
        </div>
    </footer>

    <!-- back to top -->
    <a href="#" class="to-top" id="toTop" aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
    </a>

    <script src="assets/js/slideshow.js"></script>
</body>
</html>
