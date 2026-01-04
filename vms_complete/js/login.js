function loginAsCoach() {
  const userSession = {
    user_id: 1,
    full_name: "Juan Dela Cruz",
    role: "Coach",
    sport_id: 1,
    sport_name: "Volleyball",
    is_logged_in: true
  };

  sessionStorage.setItem("userSession", JSON.stringify(userSession));
  routeByRole();
}

function loginAsAdmin() {
  const userSession = {
    user_id: 99,
    full_name: "System Admin",
    role: "System Administrator",
    sport_id: 1,
    sport_name: "Volleyball",
    is_logged_in: true
  };

  sessionStorage.setItem("userSession", JSON.stringify(userSession));
  routeByRole();
}
