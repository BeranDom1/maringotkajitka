const navbar = document.querySelector(".navbar");
const navLinks = document.querySelectorAll(".nav-link");
const bookingForm = document.querySelector("#bookingForm");
const formStatus = document.querySelector("#formStatus");
const galleryGrid = document.querySelector("#galleryGrid");
const photoLightbox = document.querySelector("#photoLightbox");
const photoLightboxImage = document.querySelector("#photoLightboxImage");
const photoLightboxCaption = document.querySelector("#photoLightboxCaption");
const photoLightboxLink = document.querySelector("#photoLightboxLink");
const photoLightboxClose = document.querySelector(".photo-lightbox-close");

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

function escapeText(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function renderGallery(items) {
  if (!galleryGrid) return;

  if (!items.length) {
    galleryGrid.innerHTML = '<div class="col-12"><p class="gallery-empty">Galerie zatím čeká na první fotky.</p></div>';
    return;
  }

  galleryGrid.innerHTML = items
    .map((item, index) => {
      const isWide = index % 5 === 3 || index % 5 === 4;
      const columnClass = isWide
        ? index % 5 === 3
          ? "col-md-6 col-lg-7"
          : "col-md-12 col-lg-5"
        : "col-md-6 col-lg-4";
      const caption = escapeText(item.caption || "");
      const image = escapeText(item.image);

      return `
        <div class="${columnClass}">
          <figure class="gallery-tile${isWide ? " is-wide" : ""}">
            <img src="${image}" alt="${caption || "Fotka z galerie Maringotky u vody"}" loading="lazy">
            ${caption ? `<figcaption class="gallery-caption">${caption}</figcaption>` : ""}
          </figure>
        </div>
      `;
    })
    .join("");
}

if (galleryGrid) {
  fetch("data/gallery.json", { cache: "no-store" })
    .then((response) => {
      if (!response.ok) throw new Error("Gallery data not found");
      return response.json();
    })
    .then((items) => renderGallery(Array.isArray(items) ? items : []))
    .catch(() => {
      galleryGrid.innerHTML = '<div class="col-12"><p class="gallery-empty">Galerii se nepodařilo načíst.</p></div>';
    });
}

function openPhotoLightbox(image) {
  if (!photoLightbox || !photoLightboxImage) return;

  const source = image.currentSrc || image.src;
  const caption = image.alt || "";

  photoLightboxImage.src = source;
  photoLightboxImage.alt = caption;
  photoLightboxCaption.textContent = caption;
  photoLightboxCaption.hidden = !caption;
  photoLightboxLink.href = source;
  photoLightbox.classList.add("is-open");
  photoLightbox.setAttribute("aria-hidden", "false");
  document.body.classList.add("lightbox-open");
  photoLightboxClose.focus();
}

function closePhotoLightbox() {
  if (!photoLightbox || !photoLightboxImage) return;

  photoLightbox.classList.remove("is-open");
  photoLightbox.setAttribute("aria-hidden", "true");
  document.body.classList.remove("lightbox-open");
  photoLightboxImage.src = "";
}

document.addEventListener("click", (event) => {
  const image = event.target.closest("main img:not(.hero-logo)");

  if (image && !image.closest(".hero-corner-logo")) {
    openPhotoLightbox(image);
    return;
  }

  if (event.target === photoLightbox || event.target === photoLightboxClose) {
    closePhotoLightbox();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && photoLightbox?.classList.contains("is-open")) {
    closePhotoLightbox();
  }
});

bookingForm.addEventListener("submit", (event) => {
  const arrival = document.querySelector("#arrival").value;
  const departure = document.querySelector("#departure").value;

  if (arrival && departure && departure <= arrival) {
    event.preventDefault();
    formStatus.textContent = "Zkontrolujte prosím termín, odjezd musí být později než příjezd.";
    return;
  }

  formStatus.textContent = "Odesíláme poptávku na e-mail.";
});
