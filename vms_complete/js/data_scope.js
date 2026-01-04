const session = JSON.parse(sessionStorage.getItem("userSession"));

function filterBySport(data) {
  return data.filter(row => row.sport_id === session.sport_id);
}
