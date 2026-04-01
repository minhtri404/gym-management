<?php
include __DIR__ . '/../includes/config.php';
$base_path = '../';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLEXZONE - Gym & Fitness</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="includes/assets/css/user.css">
</head>
<body class="user-body">

    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">
                    START YOUR <br>
                    JOURNEY <span class="accent">TODAY</span>
                </h1>
                <p class="hero-text">
                    Phòng gym hiện đại dành cho người muốn thay đổi vóc dáng, cải thiện sức khỏe và xây dựng lối sống tích cực với lộ trình tập luyện rõ ràng.
                </p>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="<?php echo $base_path; ?>user/contact.php" class="btn btn-hero-primary">Join now</a>
                    <a href="#pricing" class="btn btn-hero-outline">Xem gói tập</a>
                </div>

                <div class="brand-logos d-flex flex-wrap justify-content-center gap-5">
                    <span>GYMSHARK</span>
                    <span>ROGUE</span>
                    <span>Gympass</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section-dark" id="about">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose <span class="accent">FLEXZONE</span></h2>
                <p class="section-text mx-auto">
                    Chúng tôi không chỉ cung cấp không gian tập luyện, mà còn mang đến trải nghiệm theo dõi tiến độ, hỗ trợ dinh dưỡng và kế hoạch tập luyện phù hợp với từng hội viên.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon"><i class="bi bi-activity"></i></div>
                        <h5 class="card-title-user">Thiết bị hiện đại</h5>
                        <p class="card-text-user">Khu vực tập luyện đầy đủ máy móc, hỗ trợ cardio, tăng cơ, giảm mỡ và functional training.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon"><i class="bi bi-person-check"></i></div>
                        <h5 class="card-title-user">PT đồng hành</h5>
                        <p class="card-text-user">Huấn luyện viên hỗ trợ cá nhân hóa lịch tập, chỉnh kỹ thuật và theo dõi tiến độ cho từng mục tiêu.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-card">
                        <div class="info-icon"><i class="bi bi-stars"></i></div>
                        <h5 class="card-title-user">AI hỗ trợ kế hoạch</h5>
                        <p class="card-text-user">Gợi ý lịch tập và kế hoạch dinh dưỡng theo mục tiêu như tăng cơ, giảm cân, cải thiện thể lực.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-soft" id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Flexible <span class="accent">Pricing</span></h2>
                <p class="section-text mx-auto">
                    Chọn gói tập phù hợp với lịch sinh hoạt và mục tiêu của bạn. Linh hoạt theo tháng, quý hoặc dài hạn.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="package-card">
                        <h5 class="card-title-user">Basic Plan</h5>
                        <div class="package-price mb-3">499.000đ</div>
                        <ul class="package-list">
                            <li>Tập không giới hạn giờ hành chính</li>
                            <li>Sử dụng khu máy cơ bản</li>
                            <li>Hỗ trợ check-in nhanh</li>
                        </ul>
                        <a href="<?php echo $base_path; ?>user/contact.php" class="btn btn-hero-primary w-100">Đăng ký tư vấn</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="package-card">
                        <h5 class="card-title-user">Standard Plan</h5>
                        <div class="package-price mb-3">799.000đ</div>
                        <ul class="package-list">
                            <li>Tập toàn thời gian</li>
                            <li>Được tư vấn lộ trình cơ bản</li>
                            <li>Hỗ trợ kế hoạch tập luyện</li>
                        </ul>
                        <a href="<?php echo $base_path; ?>user/contact.php" class="btn btn-hero-primary w-100">Đăng ký tư vấn</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="package-card">
                        <h5 class="card-title-user">Premium Plan</h5>
                        <div class="package-price mb-3">1.299.000đ</div>
                        <ul class="package-list">
                            <li>Tập toàn thời gian + khu nâng cao</li>
                            <li>Ưu tiên PT và tư vấn dinh dưỡng</li>
                            <li>Hỗ trợ kế hoạch AI chuyên sâu</li>
                        </ul>
                        <a href="<?php echo $base_path; ?>user/contact.php" class="btn btn-hero-primary w-100">Đăng ký tư vấn</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-dark" id="gallery">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Our <span class="accent">Gallery</span></h2>
                <p class="section-text mx-auto">
                    Không gian tập luyện hiện đại, sạch sẽ, thiết kế mạnh mẽ và tạo động lực cho người tập mỗi ngày.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3"><div class="gallery-card"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card two"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card three"></div></div>
                <div class="col-md-6 col-lg-3"><div class="gallery-card four"></div></div>
            </div>
        </div>
    </section>

    <section class="section-soft" id="trainers">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Meet Our <span class="accent">Trainers</span></h2>
                <p class="section-text mx-auto">
                    Đội ngũ huấn luyện viên hỗ trợ học viên từ người mới bắt đầu đến người có mục tiêu nâng cao thể hình và sức bền.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="trainer-card">
                        <div class="trainer-avatar"><i class="bi bi-person-fill"></i></div>
                        <h5 class="card-title-user">Coach Minh</h5>
                        <p class="card-text-user">Chuyên tăng cơ, cải thiện thể lực, xây dựng giáo án tập cá nhân hóa.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="trainer-card">
                        <div class="trainer-avatar"><i class="bi bi-person-fill"></i></div>
                        <h5 class="card-title-user">Coach Hân</h5>
                        <p class="card-text-user">Chuyên giảm mỡ, siết dáng, hỗ trợ nữ tập gym an toàn và hiệu quả.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="trainer-card">
                        <div class="trainer-avatar"><i class="bi bi-person-fill"></i></div>
                        <h5 class="card-title-user">Coach Duy</h5>
                        <p class="card-text-user">Chuyên functional training, tăng sức bền, cải thiện phong độ vận động tổng thể.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>