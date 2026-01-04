const session = JSON.parse(sessionStorage.getItem("userSession"));

const roleDashboards = {
  "System Administrator": "admin_dashboard.html",
  "Coach": "coach_dashboard.html"
};

function routeByRole() {
  if (!session) {
    window.location.href = "login.html";
    return;
  }

  const target = roleDashboards[session.role];
  if (!target) {
    alert("Role not allowed");
    sessionStorage.clear();
    window.location.href = "login.html";
    return;
  }

  window.location.href = target;
}
