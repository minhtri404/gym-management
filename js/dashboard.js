(function () {
  if (typeof Chart === "undefined") {
    return;
  }

  const data = window.dashboardData || {};
  const labels = Array.isArray(data.checkinLabels) && data.checkinLabels.length
    ? data.checkinLabels
    : ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];
  const values = Array.isArray(data.checkinValues) && data.checkinValues.length
    ? data.checkinValues
    : [32, 41, 38, 52, 48, 60, 47];
  const memberStatus = Array.isArray(data.memberStatus) && data.memberStatus.length === 3
    ? data.memberStatus
    : [85, 12, 6];

  const lineCanvas = document.getElementById("checkinChart");
  if (lineCanvas) {
    new Chart(lineCanvas, {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Hội viên mới",
            data: values,
            borderColor: "#0d6efd",
            backgroundColor: "rgba(13, 110, 253, 0.15)",
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointBackgroundColor: "#0d6efd"
          }
        ]
      },
      options: {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 5
            }
          }
        }
      }
    });
  }

  const doughnutCanvas = document.getElementById("packageChart");
  if (doughnutCanvas) {
    new Chart(doughnutCanvas, {
      type: "doughnut",
      data: {
        labels: ["Đang hoạt động", "Hết hạn", "Ngưng hoạt động"],
        datasets: [
          {
            data: memberStatus,
            backgroundColor: ["#198754", "#ffc107", "#6c757d"],
            borderWidth: 0
          }
        ]
      },
      options: {
        plugins: {
          legend: {
            display: false
          }
        },
        cutout: "70%"
      }
    });
  }
})();
