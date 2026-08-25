<?php

require __DIR__ . '/library.php';

$message = '';
$error = '';

if (($_GET['logout'] ?? '') === '1') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Heslo nesouhlasi.';
}

if (is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $items = read_gallery();
    $uploadSection = normalize_gallery_section((string) ($_POST['section'] ?? ''));

    if ($action === 'upload') {
        if (!is_dir(GALLERY_UPLOAD_DIR)) {
            mkdir(GALLERY_UPLOAD_DIR, 0755, true);
        }

        $uploaded = 0;
        $files = $_FILES['photos'] ?? null;

        if ($files && is_array($files['name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            foreach ($files['name'] as $index => $name) {
                if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if (($files['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $error = 'Nektere fotky se nepodarilo nahrat.';
                    continue;
                }

                if (($files['size'][$index] ?? 0) > MAX_UPLOAD_BYTES) {
                    $error = 'Nektera fotka je vetsi nez povoleny limit 8 MB.';
                    continue;
                }

                $tmpName = $files['tmp_name'][$index];
                $mime = $finfo->file($tmpName);

                if (!isset(ALLOWED_MIME_TYPES[$mime])) {
                    $error = 'Povolene jsou jen obrazky JPG, PNG nebo WebP.';
                    continue;
                }

                $fileName = make_upload_name(ALLOWED_MIME_TYPES[$mime]);
                $target = GALLERY_UPLOAD_DIR . '/' . $fileName;

                if (!move_uploaded_file($tmpName, $target)) {
                    $error = 'Fotku se nepodarilo ulozit.';
                    continue;
                }

                $items[] = [
                    'id' => pathinfo($fileName, PATHINFO_FILENAME),
                    'image' => GALLERY_UPLOAD_URL . '/' . $fileName,
                    'caption' => '',
                    'section' => $uploadSection,
                    'createdAt' => date(DATE_ATOM),
                ];
                $uploaded++;
            }
        }

        write_gallery($items);
        if ($uploaded > 0) {
            $message = $uploaded === 1 ? 'Fotka byla nahrana.' : 'Fotky byly nahrane.';
        } elseif (!$error) {
            $error = 'Vyberte prosim alespon jednu fotku.';
        }
    }

    if ($action === 'save') {
        $captions = $_POST['captions'] ?? [];
        $orders = $_POST['orders'] ?? [];
        $sections = $_POST['sections'] ?? [];

        foreach ($items as $index => &$item) {
            $id = (string) ($item['id'] ?? '');
            if (isset($captions[$id])) {
                $item['caption'] = trim((string) $captions[$id]);
            }

            if (isset($sections[$id])) {
                $item['section'] = normalize_gallery_section((string) $sections[$id]);
            }

            $item['_order'] = isset($orders[$id]) ? (int) $orders[$id] : $index + 1;
            $item['_originalIndex'] = $index;
        }
        unset($item);

        usort($items, static function (array $left, array $right): int {
            $orderCompare = ($left['_order'] ?? 0) <=> ($right['_order'] ?? 0);

            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            return ($left['_originalIndex'] ?? 0) <=> ($right['_originalIndex'] ?? 0);
        });

        foreach ($items as &$item) {
            unset($item['_order'], $item['_originalIndex']);
        }
        unset($item);

        write_gallery($items);
        $message = 'Galerie byla ulozena.';
    }

    if ($action === 'delete') {
        $deleteId = (string) ($_POST['id'] ?? '');
        $kept = [];

        foreach ($items as $item) {
            if (($item['id'] ?? '') === $deleteId) {
                delete_local_image((string) ($item['image'] ?? ''));
                continue;
            }
            $kept[] = $item;
        }

        write_gallery($kept);
        $message = 'Fotka byla odstranena z galerie.';
    }
}

$items = is_logged_in() ? read_gallery() : [];
$gallerySections = gallery_sections();

?>
<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrace galerie | Maringotka u vody</title>
    <link rel="stylesheet" href="admin.css?v=20260825-1">
  </head>
  <body>
    <?php if (!is_logged_in()): ?>
      <main class="login-panel panel">
        <h1>Administrace galerie</h1>
        <p class="hint">Přihlášení slouží jen pro správu fotek v galerii.</p>
        <?php if ($error): ?><p class="message error"><?= e($error) ?></p><?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <label for="password">Heslo</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
          <div class="actions">
            <button class="button" type="submit">Přihlásit</button>
            <a class="button secondary" href="../">Zpět na web</a>
          </div>
        </form>
      </main>
    <?php else: ?>
      <main class="admin-shell">
        <header class="admin-topbar">
          <div>
            <h1>Galerie</h1>
            <p class="hint">Nahrávejte pouze fotky, které se mají zobrazit ve veřejné galerii.</p>
          </div>
          <div class="actions">
            <a class="button secondary" href="../#galerie">Zobrazit galerii</a>
            <a class="button secondary" href="?logout=1">Odhlásit</a>
          </div>
        </header>

        <?php if ($message): ?><p class="message"><?= e($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="message error"><?= e($error) ?></p><?php endif; ?>

        <section class="panel">
          <h2>Nahrát nové fotky</h2>
          <p class="hint">Nejdřív vyberte sekci, do které fotky patří. Můžete vybrat více fotek najednou z počítače i mobilu. Popisky doplníte po nahrání níže.</p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload">
            <label for="section">Sekce galerie</label>
            <select id="section" name="section" required>
              <?php foreach ($gallerySections as $sectionId => $sectionLabel): ?>
                <option value="<?= e($sectionId) ?>"><?= e($sectionLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="photos">Fotky</label>
            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <p class="hint">Povolené formáty: JPG, PNG, WebP. Limit jedné fotky: 8 MB.</p>
            <div class="actions">
              <button class="button" type="submit">Nahrát fotky</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2>Upravit galerii</h2>
          <?php if (!$items): ?>
            <p class="hint">Galerie zatím nemá žádné fotky.</p>
          <?php else: ?>
            <p class="hint">Pořadí určuje, jak se fotky zobrazí v dané sekci. Prvních 8 fotek v každé sekci je vidět hned, další se zobrazí po tlačítku „Zobrazit další“.</p>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save">
              <?php foreach ($gallerySections as $sectionId => $sectionLabel): ?>
                <?php
                  $sectionItems = array_values(array_filter($items, static function (array $item) use ($sectionId): bool {
                      return normalize_gallery_section((string) ($item['section'] ?? '')) === $sectionId;
                  }));
                ?>
                <section class="gallery-admin-section">
                  <h3><?= e($sectionLabel) ?></h3>
                  <?php if (!$sectionItems): ?>
                    <p class="hint">V této sekci zatím nejsou žádné fotky.</p>
                  <?php else: ?>
                    <div class="grid">
                      <?php foreach ($sectionItems as $index => $item): ?>
                        <?php
                          $id = (string) ($item['id'] ?? '');
                          $image = (string) ($item['image'] ?? '');
                          $caption = (string) ($item['caption'] ?? '');
                          $order = $index + 1;
                          $currentSection = normalize_gallery_section((string) ($item['section'] ?? ''));
                        ?>
                        <article class="photo-card">
                          <?php if ($order <= 8): ?>
                            <span class="photo-badge">Vidět hned</span>
                          <?php endif; ?>
                          <img src="<?= e(image_src_for_admin($image)) ?>" alt="">
                          <div class="photo-card-body">
                            <label for="section-<?= e($id) ?>">Sekce</label>
                            <select id="section-<?= e($id) ?>" name="sections[<?= e($id) ?>]">
                              <?php foreach ($gallerySections as $optionId => $optionLabel): ?>
                                <option value="<?= e($optionId) ?>" <?= $optionId === $currentSection ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <label for="order-<?= e($id) ?>">Pořadí v sekci</label>
                            <input type="number" id="order-<?= e($id) ?>" name="orders[<?= e($id) ?>]" min="1" step="1" value="<?= e((string) $order) ?>">
                            <label for="caption-<?= e($id) ?>">Popisek</label>
                            <textarea id="caption-<?= e($id) ?>" name="captions[<?= e($id) ?>]" placeholder="Popisek může zůstat prázdný."><?= e($caption) ?></textarea>
                            <button class="button danger" type="submit" form="delete-<?= e($id) ?>">Smazat fotku</button>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </section>
              <?php endforeach; ?>
              <div class="actions">
                <button class="button" type="submit">Uložit galerii</button>
              </div>
            </form>

            <?php foreach ($items as $item): ?>
              <?php $id = (string) ($item['id'] ?? ''); ?>
              <form id="delete-<?= e($id) ?>" method="post" onsubmit="return confirm('Opravdu smazat tuto fotku z galerie?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($id) ?>">
              </form>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </main>
    <?php endif; ?>
  </body>
</html>
