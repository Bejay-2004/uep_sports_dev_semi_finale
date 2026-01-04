const session = JSON.parse(sessionStorage.getItem("userSession"));

if (!session || !session.is_logged_in) {
  window.location.href = "login.html";
}
