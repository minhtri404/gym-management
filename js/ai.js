function generateWorkoutPlan() {
  const form = document.getElementById("aiWorkoutForm");
  const resultBox = document.getElementById("workoutResult");

  if (!form || !resultBox) {
    return;
  }

  const nameInput = form.querySelector('input[type="text"]');
  const goal = document.getElementById("goal").value;
  const daysPerWeek = document.getElementById("daysPerWeek").value;
  const level = document.getElementById("level").value;
  const noteInput = form.querySelector("textarea");

  if (!goal || !daysPerWeek || !level) {
    alert("Vui lòng chọn mục tiêu, số buổi và trình độ trước khi gợi ý.");
    return;
  }

  const goalMap = {
    "weight-loss": "Giảm cân",
    "muscle-gain": "Tăng cơ",
    "maintain": "Giữ dáng",
  };

  const levelMap = {
    beginner: "Mới bắt đầu",
    intermediate: "Trung bình",
    advanced: "Nâng cao",
  };

  const focusMap = {
    "weight-loss": "Cardio + toàn thân",
    "muscle-gain": "Tăng cơ theo nhóm cơ",
    "maintain": "Toàn thân + phục hồi",
  };

  const dayPlans = {
    3: ["Toàn thân + cardio nhẹ", "Thân trên + core", "Thân dưới + HIIT"],
    4: ["Thân trên", "Thân dưới", "Cardio + core", "Toàn thân"],
    5: ["Push", "Pull", "Legs", "Cardio + core", "Toàn thân"],
    6: ["Push", "Pull", "Legs", "Cardio", "Toàn thân", "Mobility"],
  };

  const planItems = dayPlans[daysPerWeek] || [];
  const memberName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : "Hội viên";
  const note = noteInput && noteInput.value.trim() ? noteInput.value.trim() : "Không có";

  const planList = planItems
    .map((item, index) => `<li>Buổi ${index + 1}: ${item}</li>`)
    .join("");

  resultBox.innerHTML = `
    <div class="mb-3">
      <h6 class="mb-2">Kế hoạch luyện tập cho ${memberName}</h6>
      <ul class="list-unstyled mb-2">
        <li><strong>Mục tiêu:</strong> ${goalMap[goal]}</li>
        <li><strong>Số buổi / tuần:</strong> ${daysPerWeek}</li>
        <li><strong>Trình độ:</strong> ${levelMap[level]}</li>
        <li><strong>Trọng tâm:</strong> ${focusMap[goal]}</li>
        <li><strong>Ghi chú:</strong> ${note}</li>
      </ul>
    </div>
    <div>
      <h6 class="mb-2">Lịch gợi ý</h6>
      <ul class="mb-0">${planList}</ul>
    </div>
  `;
}
