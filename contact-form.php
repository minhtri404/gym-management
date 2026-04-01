<?php
include __DIR__ . '/includes/config.php';

$base_path = '';
$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = trim($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên hệ với phòng gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f8f9fa;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="mb-1">Liên hệ với phòng gym</h3>
                        <p class="text-muted mb-0">Gửi yêu cầu tư vấn, đăng ký tập thử hoặc hỗ trợ nhanh.</p>
                    </div>

                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                Gửi liên hệ thành công. Phòng gym sẽ phản hồi cho bạn sớm nhất.
                            </div>
                        <?php endif; ?>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <h6 class="mb-2">Thông tin liên hệ</h6>
                                    <div><strong>Hotline:</strong> 0909 123 456</div>
                                    <div><strong>Email:</strong> gym@example.com</div>
                                    <div><strong>Địa chỉ:</strong> 123 Nguyễn Văn A, TP.HCM</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <h6 class="mb-2">Liên hệ nhanh</h6>
                                    <div>Zalo: 0909 123 456</div>
                                    <div>Facebook: fb.com/gymdemo</div>
                                    <div>Giờ hỗ trợ: 08:00 - 21:00</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <h6 class="mb-2">Kênh hỗ trợ</h6>
                                    <div>Tư vấn gói tập</div>
                                    <div>Tư vấn PT</div>
                                    <div>Hỗ trợ dinh dưỡng</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="<?php echo $base_path; ?>php/contact/submit-contact.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" name="full_name" class="form-control" required maxlength="100">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="text" name="phone" class="form-control" required maxlength="20">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" maxlength="100">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kênh muốn được liên hệ lại</label>
                                    <select name="preferred_contact_method" class="form-select">
                                        <option value="phone">Điện thoại</option>
                                        <option value="zalo">Zalo</option>
                                        <option value="email">Email</option>
                                        <option value="facebook">Facebook</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Chủ đề</label>
                                <input type="text" name="subject" class="form-control" required maxlength="100" placeholder="Ví dụ: Tư vấn gói tập / Đăng ký tập thử / Tư vấn PT">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nội dung</label>
                                <textarea name="message" class="form-control" rows="5" required placeholder="Nhập nội dung bạn muốn được hỗ trợ..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    Gửi yêu cầu liên hệ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mt-4 card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Bản đồ</h5>
                        <div class="ratio ratio-16x9">
                            <iframe
                                src="https://www.google.com/maps?q=10.7769,106.7009&z=15&output=embed"
                                style="border:0;"
                                allowfullscreen=""
                                loading="lazy">
                            </iframe>
                        </div>
                    </div>
                </div>

                <p class="text-center text-muted mt-3 mb-0">
                    Cảm ơn bạn đã quan tâm đến dịch vụ của phòng gym.
                </p>
            </div>
        </div>
    </div>
</body>
</html>