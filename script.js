const sidebar = document.getElementById("mySidebar");
const overlay = document.getElementById("myOverlay");

function w3_open() {
  sidebar.style.display = "block";
  overlay.style.display = "block";
}

function w3_close() {
  sidebar.style.display = "none";
  overlay.style.display = "none";
}
