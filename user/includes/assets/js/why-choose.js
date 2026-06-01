document.addEventListener("DOMContentLoaded", function () {
    const tabCards = document.querySelectorAll(".why-tab-card");
    const detailTitle = document.querySelector(".why-detail-title");
    const detailPanel = document.querySelector(".why-detail-panel");
    const sidePanel = document.querySelector(".why-side-panel");

    if (!tabCards.length || !detailPanel || !sidePanel) {
        return;
    }

    const equipmentHTML = `
        <h3 class="why-detail-title">Danh sách thiết bị nổi bật</h3>

        <div class="equipment-tabs">
            <div class="equipment-tab active" data-filter="cardio">Cardio</div>
            <div class="equipment-tab" data-filter="strength">Tăng cơ</div>
            <div class="equipment-tab" data-filter="functional">Functional</div>
        </div>

        <div class="equipment-grid">
            <div class="equipment-card" data-category="functional">
                <img src="includes/assets/images/equipment/functional-trainer.jpg" class="equipment-img" alt="Cap keo da nang">
                <div>
                    <h4>Cap keo<br>da nang</h4>
                    <div class="equipment-status">Dang hoat dong</div>
                    <p>Tap vai, lung, tay, core - linh hoat nhieu goc keo.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="cardio">
                <img src="includes/assets/images/equipment/treadmill.jfif" class="equipment-img" alt="Máy chạy bộ">
                <div>
                    <h4>Máy chạy bộ<br>Life Fitness Integrity+</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Cardio bền bỉ · Nhập khẩu từ Life Fitness.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="cardio">
                <img src="includes/assets/images/equipment/technogym-bike.jpg" class="equipment-img" alt="Xe đạp Technogym">
                <div>
                    <h4>Xe đạp<br>Technogym Bike</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Tăng sức bền · Phù hợp khởi động và cardio nhẹ.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="strength">
                <img src="includes/assets/images/equipment/chest-press.jpg" class="equipment-img" alt="Super Incline Chest Press">
                <div>
                    <h4>Super Incline<br>Chest Press</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Chinh phục vùng ngực trên hiệu quả · Hỗ trợ phát triển cơ ngực.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="strength">
                <img src="includes/assets/images/equipment/leg-press.jpg" class="equipment-img" alt="Leg Press">
                <div>
                    <h4>Leg Press<br>Cybex VR3</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Tập chân, mông, đùi · Hỗ trợ tải trọng lớn.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="strength">
                <img src="includes/assets/images/equipment/chest-press.jpg" class="equipment-img" alt="Smith Machine">
                <div>
                    <h4>Smith Machine<br>Strength Station</h4>
                    <div class="equipment-status">Dang hoat dong</div>
                    <p>Ho tro squat, bench press, shoulder press - an toan khi tap nang.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="functional">
                <img src="includes/assets/images/equipment/functional-trainer.jpg" class="equipment-img" alt="Matrix Functional Trainer">
                <div>
                    <h4>Matrix<br>Functional Trainer</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Kéo cáp toàn thân · Linh hoạt nhiều nhóm cơ.</p>
                </div>
            </div>

            <div class="equipment-card" data-category="cardio">
                <img src="includes/assets/images/equipment/elliptical.jpg" class="equipment-img" alt="Precor EFX">
                <div>
                    <h4>Precor EFX 863<br>Elliptical</h4>
                    <div class="equipment-status">Đang hoạt động</div>
                    <p>Cardio ít tác động · Phù hợp giảm mỡ và tăng sức bền.</p>
                </div>
            </div>
        </div>

        <div class="why-button-wrap">
            <a href="#" class="why-equipment-btn">
                Xem chi tiết thiết bị
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    `;

    const equipmentSideHTML = `
        <h3>Thiết bị & tiêu chuẩn</h3>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-box-seam"></i></div>
            <div>
                <h4>200+ thiết bị hiện đại</h4>
                <p>Nhiều dòng máy nhập khẩu, đáp ứng đa dạng mục tiêu tập luyện.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <h4>Bảo trì định kỳ</h4>
                <p>Kiểm tra, vệ sinh và bảo dưỡng thiết bị mỗi tuần.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-people"></i></div>
            <div>
                <h4>PT hỗ trợ tại sàn</h4>
                <p>Luôn có HLV hướng dẫn hội viên khi cần sử dụng máy.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            <div>
                <h4>5 khu vực tập luyện</h4>
                <p>Cardio, tăng cơ, functional, group X và recovery.</p>
            </div>
        </div>
    `;

    const trainerHTML = `
        <h3 class="why-detail-title">Đội ngũ HLV đồng hành</h3>

        <div class="equipment-tabs">
            <div class="equipment-tab active">PT cá nhân</div>
            <div class="equipment-tab">Tăng cơ</div>
            <div class="equipment-tab">Giảm mỡ</div>
        </div>

        <div class="equipment-grid">
            <div class="equipment-card">
                <img src="includes/assets/images/trainers/trainer-1.jpg" class="equipment-img" alt="HLV Minh Quân">
                <div>
                    <h4>HLV Minh Quân</h4>
                    <div class="equipment-status">Đang nhận lịch</div>
                    <p>Chuyên tăng cơ · 5 năm kinh nghiệm · Hỗ trợ kỹ thuật bài tập.</p>
                </div>
            </div>

            <div class="equipment-card">
                <img src="includes/assets/images/trainers/trainer-2.jpg" class="equipment-img" alt="HLV Hà Anh">
                <div>
                    <h4>HLV Hà Anh</h4>
                    <div class="equipment-status">Đang nhận lịch</div>
                    <p>Chuyên giảm mỡ · Tư vấn cardio và dinh dưỡng cơ bản.</p>
                </div>
            </div>

            <div class="equipment-card">
                <img src="includes/assets/images/trainers/trainer-3.jpg" class="equipment-img" alt="HLV Quốc Bảo">
                <div>
                    <h4>HLV Quốc Bảo</h4>
                    <div class="equipment-status">Đang nhận lịch</div>
                    <p>Strength training · Sửa form squat, deadlift, bench press.</p>
                </div>
            </div>
        </div>

        <div class="why-button-wrap">
            <a href="#" class="why-equipment-btn">
                Xem danh sách HLV
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    `;

    const trainerSideHTML = `
        <h3>Tiêu chuẩn HLV</h3>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <h4>HLV có kinh nghiệm</h4>
                <p>Theo sát hội viên trong quá trình tập luyện.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-clipboard-check"></i></div>
            <div>
                <h4>Kiểm tra kỹ thuật</h4>
                <p>Hướng dẫn form đúng để hạn chế chấn thương.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-graph-up"></i></div>
            <div>
                <h4>Theo dõi tiến độ</h4>
                <p>Đánh giá thay đổi cân nặng, số đo và sức bền.</p>
            </div>
        </div>
    `;

    const planHTML = `
        <h3 class="why-detail-title">Mẫu kế hoạch cá nhân</h3>

        <div class="equipment-tabs">
            <div class="equipment-tab active">Tăng cơ</div>
            <div class="equipment-tab">Giảm mỡ</div>
            <div class="equipment-tab">Duy trì</div>
        </div>

        <div class="equipment-grid">
            <div class="equipment-card">
                <div class="why-standard-icon"><i class="bi bi-calendar-week"></i></div>
                <div>
                    <h4>Lịch tập 4 buổi / tuần</h4>
                    <div class="equipment-status">Gợi ý</div>
                    <p>Push · Pull · Legs · Full body, phù hợp người tập đều.</p>
                </div>
            </div>

            <div class="equipment-card">
                <div class="why-standard-icon"><i class="bi bi-egg-fried"></i></div>
                <div>
                    <h4>Gợi ý dinh dưỡng</h4>
                    <div class="equipment-status">Cá nhân hóa</div>
                    <p>Ước tính calo, protein và thói quen ăn uống theo mục tiêu.</p>
                </div>
            </div>

            <div class="equipment-card">
                <div class="why-standard-icon"><i class="bi bi-activity"></i></div>
                <div>
                    <h4>Theo dõi tiến độ</h4>
                    <div class="equipment-status">Hàng tuần</div>
                    <p>Ghi nhận số buổi tập, cân nặng, sức bền và cảm nhận.</p>
                </div>
            </div>
        </div>

        <div class="why-button-wrap">
            <a href="#" class="why-equipment-btn">
                Xem mẫu kế hoạch
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    `;

    const planSideHTML = `
        <h3>Kế hoạch thông minh</h3>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-bullseye"></i></div>
            <div>
                <h4>Theo mục tiêu</h4>
                <p>Tăng cơ, giảm mỡ, cải thiện sức bền hoặc duy trì vóc dáng.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-bar-chart"></i></div>
            <div>
                <h4>Có theo dõi</h4>
                <p>Ghi nhận tiến độ để điều chỉnh lịch tập phù hợp.</p>
            </div>
        </div>

        <div class="why-standard-item">
            <div class="why-standard-icon"><i class="bi bi-shield-check"></i></div>
            <div>
                <h4>An toàn</h4>
                <p>Gợi ý mức tập phù hợp, tránh quá tải khi mới bắt đầu.</p>
            </div>
        </div>
    `;

    const contentMap = {
        equipment: {
            main: equipmentHTML,
            side: equipmentSideHTML
        },
        trainer: {
            main: trainerHTML,
            side: trainerSideHTML
        },
        plan: {
            main: planHTML,
            side: planSideHTML
        }
    };

    // Helper: ensure equipment tabs have data-filter and cards have data-category
    function setDataFilterOnTabs(root) {
        const base = root || document;
        const wrappers = base.querySelectorAll('.equipment-tabs');
        wrappers.forEach(function (wrapper) {
            wrapper.querySelectorAll('.equipment-tab').forEach(function (tab) {
                if (!tab.hasAttribute('data-filter')) {
                    const txt = tab.textContent.trim().toLowerCase();
                    if (txt.includes('cardio')) tab.setAttribute('data-filter', 'cardio');
                    else if (txt.includes('tăng cơ') || txt.includes('tang co') || txt.includes('tăngcơ')) tab.setAttribute('data-filter', 'strength');
                    else if (txt.includes('functional')) tab.setAttribute('data-filter', 'functional');
                }
            });
        });
    }

    function setDataCategoryOnCards(root) {
        const base = root || document;
        base.querySelectorAll('.equipment-card').forEach(function (card) {
            if (!card.hasAttribute('data-category')) {
                let alt = '';
                const img = card.querySelector('img.equipment-img');
                if (img) alt = (img.alt || img.getAttribute('alt') || '').toLowerCase();
                const h4 = card.querySelector('h4');
                const text = (h4 ? h4.textContent : '').toLowerCase();
                const s = (alt + ' ' + text);

                if (s.includes('treadmill') || s.includes('máy chạy') || s.includes('cardio') || s.includes('xe đạp') || s.includes('elliptical') || s.includes('technogym') || s.includes('precor')) {
                    card.setAttribute('data-category', 'cardio');
                } else if (s.includes('press') || s.includes('leg press') || s.includes('smith') || s.includes('incline') || s.includes('chest') || s.includes('cable') || s.includes('leg press')) {
                    card.setAttribute('data-category', 'strength');
                } else if (s.includes('functional') || s.includes('kéo cáp') || s.includes('matrix') || s.includes('functional trainer') || s.includes('cáp')) {
                    card.setAttribute('data-category', 'functional');
                } else {
                    // fallback to cardio if unknown
                    card.setAttribute('data-category', 'cardio');
                }
            }
        });
    }

    tabCards.forEach(function (card) {
        card.addEventListener("click", function () {
            const tab = card.getAttribute("data-tab");

            tabCards.forEach(function (item) {
                item.classList.remove("active");
            });

            card.classList.add("active");

            detailPanel.innerHTML = contentMap[tab].main;
            sidePanel.innerHTML = contentMap[tab].side;

            // normalize attributes for dynamically injected content
            try {
                setDataFilterOnTabs(detailPanel);
                setDataCategoryOnCards(detailPanel);
                const activeEquipmentTab = detailPanel.querySelector(".equipment-tab.active[data-filter]");
                if (activeEquipmentTab) {
                    applyEquipmentFilter(activeEquipmentTab);
                }
            } catch (e) {
                // ignore errors while normalizing
                console.error('Normalization error:', e);
            }
        });
    });

    setDataFilterOnTabs(detailPanel);
    setDataCategoryOnCards(detailPanel);
    const initialEquipmentTab = detailPanel.querySelector(".equipment-tab.active[data-filter]");
    if (initialEquipmentTab) {
        applyEquipmentFilter(initialEquipmentTab);
    }
});

function applyEquipmentFilter(clickedTab) {
    const filter = clickedTab.getAttribute("data-filter");
    const tabsWrapper = clickedTab.closest(".equipment-tabs");

    if (!filter || !tabsWrapper) {
        return;
    }

    const detailPanel = clickedTab.closest(".why-detail-panel");
    const equipmentGrid = detailPanel ? detailPanel.querySelector(".equipment-grid") : null;

    const tabs = tabsWrapper.querySelectorAll(".equipment-tab");
    const cards = equipmentGrid ? equipmentGrid.querySelectorAll(".equipment-card") : [];

    tabs.forEach(function (tab) {
        tab.classList.remove("active");
    });

    clickedTab.classList.add("active");

    cards.forEach(function (card) {
        const category = card.getAttribute("data-category");

        if (category === filter) {
            card.style.display = "grid";
        } else {
            card.style.display = "none";
        }
    });
}

document.addEventListener("click", function (event) {
    const clickedTab = event.target.closest(".equipment-tab");

    if (clickedTab && clickedTab.hasAttribute("data-filter")) {
        applyEquipmentFilter(clickedTab);
    }
});
