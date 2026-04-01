<?php
$base_path = $base_path ?? '../';
?>
<nav class="navbar navbar-expand-lg navbar-dark user-navbar fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $base_path; ?>user/home.php">
            <span class="brand-dot me-2"></span>
            FLEXZONE
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar" aria-controls="userNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNavbar">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#about">About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#pricing">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#gallery">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/home.php#trainers">Trainers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>user/contact.php">Contact</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <a href="#" class="social-link" aria-label="Facebook">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" class="social-link" aria-label="Instagram">
                    <i class="bi bi-instagram"></i>
                </a>
            </div>
        </div>
    </div>
</nav>