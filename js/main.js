const navbar = document.querySelector(".navbar");
const navLinks = document.querySelectorAll("#mainNav a[href^='#']");
const heroVideo = document.querySelector(".hero-video");
const bookingForm = document.querySelector("#bookingForm");
const formStatus = document.querySelector("#formStatus");
const galleryGrid = document.querySelector("#galleryGrid");
const galleryLoadMore = document.querySelector("#galleryLoadMore");
const photoLightbox = document.querySelector("#photoLightbox");
const photoLightboxImage = document.querySelector("#photoLightboxImage");
const photoLightboxCaption = document.querySelector("#photoLightboxCaption");
const photoLightboxLink = document.querySelector("#photoLightboxLink");
const photoLightboxClose = document.querySelector(".photo-lightbox-close");
const copyEmailButtons = document.querySelectorAll("[data-copy-email]");
const copyEmailStatus = document.querySelector("#copyEmailStatus");
const galleryPageSize = 8;
let galleryItems = [];
let visibleGalleryItems = galleryPageSize;
let copyEmailStatusTimeout;

function updateNavbar() {
  navbar.classList.toggle("is-scrolled", window.scrollY > 24);
}

window.addEventListener("scroll", updateNavbar, { passive: true });
updateNavbar();

if (heroVideo) {
  const showHeroVideo = () => heroVideo.classList.add("is-loaded");

  if (heroVideo.readyState >= 2) {
    showHeroVideo();
  } else {
    heroVideo.addEventListener("loadeddata", showHeroVideo, { once: true });
  }
}

navLinks.forEach((link) => {
  link.addEventListener("click", () => {
    const menu = document.querySelector("#mainNav");

    if (window.innerWidth < 992 && menu?.classList.contains("show")) {
      bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
    }
  });
});

async function copyToClipboard(value) {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(value);
      return;
    } catch (error) {
      // Some mobile browsers expose Clipboard API but deny permission.
    }
  }

  const helper = document.createElement("textarea");
  helper.value = value;
  helper.setAttribute("readonly", "");
  helper.style.position = "fixed";
  helper.style.opacity = "0";
  document.body.appendChild(helper);
  helper.select();
  helper.setSelectionRange(0, helper.value.length);
  const copied = document.execCommand("copy");
  helper.remove();

  if (!copied) throw new Error("Kopírování není podporováno.");
}

function showCopyEmailStatus(message) {
  if (!copyEmailStatus) return;

  window.clearTimeout(copyEmailStatusTimeout);
  copyEmailStatus.textContent = message;
  copyEmailStatus.classList.add("is-visible");
  copyEmailStatusTimeout = window.setTimeout(() => {
    copyEmailStatus.classList.remove("is-visible");
  }, 2600);
}

copyEmailButtons.forEach((button) => {
  button.addEventListener("click", async () => {
    const email = button.dataset.copyEmail;

    try {
      await copyToClipboard(email);
      button.classList.add("is-copied");
      button.setAttribute("aria-label", "E-mailová adresa byla zkopírována");
      showCopyEmailStatus(`E-mail ${email} byl zkopírován.`);
      window.setTimeout(() => {
        button.classList.remove("is-copied");
        button.setAttribute("aria-label", "Zkopírovat e-mailovou adresu");
      }, 2600);
    } catch (error) {
      showCopyEmailStatus(`E-mail se nepodařilo zkopírovat: ${email}`);
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

function renderGallery() {
  if (!galleryGrid) return;

  if (!galleryItems.length) {
    galleryGrid.innerHTML = '<div class="col-12"><p class="gallery-empty">Galerie zatím čeká na první fotky.</p></div>';
    if (galleryLoadMore) galleryLoadMore.hidden = true;
    return;
  }

  const itemsToShow = galleryItems.slice(0, visibleGalleryItems);

  galleryGrid.innerHTML = itemsToShow
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

  if (galleryLoadMore) {
    const hasMoreItems = visibleGalleryItems < galleryItems.length;
    galleryLoadMore.hidden = !hasMoreItems;
    galleryLoadMore.textContent = hasMoreItems ? "Zobrazit další" : "";
  }
}

if (galleryGrid) {
  fetch("data/gallery.json", { cache: "no-store" })
    .then((response) => {
      if (!response.ok) throw new Error("Gallery data not found");
      return response.json();
    })
    .then((items) => {
      galleryItems = Array.isArray(items) ? items : [];
      visibleGalleryItems = galleryPageSize;
      renderGallery();
    })
    .catch(() => {
      galleryGrid.innerHTML = '<div class="col-12"><p class="gallery-empty">Galerii se nepodařilo načíst.</p></div>';
      if (galleryLoadMore) galleryLoadMore.hidden = true;
    });
}

if (galleryLoadMore) {
  galleryLoadMore.addEventListener("click", () => {
    visibleGalleryItems += galleryPageSize;
    renderGallery();
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

bookingForm?.addEventListener("submit", async (event) => {
  event.preventDefault();

  const arrival = document.querySelector("#arrival").value;
  const departure = document.querySelector("#departure").value;
  const submitButton = bookingForm.querySelector('button[type="submit"]');

  if (arrival && departure && departure <= arrival) {
    formStatus.className = "form-status is-error";
    formStatus.textContent = "Zkontrolujte prosím termín, odjezd musí být později než příjezd.";
    return;
  }

  submitButton.disabled = true;
  submitButton.textContent = "Odesílám...";
  formStatus.className = "form-status";
  formStatus.textContent = "Odesíláme poptávku na e-mail.";

  try {
    const response = await fetch(bookingForm.action, {
      method: "POST",
      body: new FormData(bookingForm),
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Poptávku se nepodařilo odeslat.");
    }

    bookingForm.reset();
    formStatus.className = "form-status is-success";
    formStatus.textContent = result.message;
  } catch (error) {
    formStatus.className = "form-status is-error";
    formStatus.textContent = error.message || "Poptávku se nepodařilo odeslat. Zkuste to prosím znovu.";
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Odeslat poptávku";
  }
});
