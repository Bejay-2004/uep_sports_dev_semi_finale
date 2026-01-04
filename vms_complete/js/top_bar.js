const session = JSON.parse(sessionStorage.getItem("userSession"));

document.getElementById("profileName").textContent = session.full_name;
document.getElementById("profileRole").textContent = session.role;
document.getElementById("profileSport").textContent = session.sport_name;

document.getElementById("logoutBtn").onclick = () => {
  sessionStorage.clear();
  window.location.href = "login.html";
};
