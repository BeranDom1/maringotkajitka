# Maringotka u vody
Web pro pronájem rybářské maringotky.

## Administrace galerie

Administrace je ve složce `/admin/` a slouží jen pro správu veřejné galerie.

- Výchozí heslo je v souboru `admin/config.php`.
- Před ostrým nasazením změňte hodnotu `ADMIN_PASSWORD`.
- Fotky se ukládají do `uploads/gallery/`.
- Popisky a seznam fotek se ukládají do `data/gallery.json`.
- Galerie na webu se načítá automaticky z `data/gallery.json`.
