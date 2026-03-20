const loginForm = document.getElementById("loginForm");

if (loginForm) {
  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value.trim();

    if (username === "" || password === "") {
      alert("Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.");
      return;
    }

    // Day 1 chỉ demo giao diện
    window.location.href = "dashboard.html";
  });
}