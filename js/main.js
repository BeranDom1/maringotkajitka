const navbar = document.querySelector(".navbar");
const navLinks = document.querySelectorAll(".nav-link");
const bookingForm = document.querySelector("#bookingForm");
const formStatus = document.querySelector("#formStatus");

function updateNavbar() {
  navbar.classList.toggle("is-scrolled", window.scrollY > 24);
}

window.addEventListener("scroll", updateNavbar, { passive: true });
updateNavbar();

navLinks.forEach((link) => {
  link.addEventListener("click", () => {
    const menu = document.querySelector("#mainNav");
    const instance = bootstrap.Collapse.getInstance(menu);

    if (instance) {
      instance.hide();
    }
  });
});

bookingForm.addEventListener("submit", (event) => {
  event.preventDefault();

  const data = new FormData(bookingForm);
  const name = data.get("name") || "Děkujeme";
  const arrival = data.get("arrival");
  const departure = data.get("departure");

  formStatus.textContent = `${name}, poptávka na termín ${arrival} - ${departure} je připravená k odeslání.`;
  bookingForm.reset();
});
