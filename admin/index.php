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

        foreach ($items as &$item) {
            $id = (string) ($item['id'] ?? '');
            if (isset($captions[$id])) {
                $item['caption'] = trim((string) $captions[$id]);
            }
        }
        unset($item);

        write_gallery($items);
        $message = 'Popisky byly ulozene.';
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

?>
<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrace galerie | Maringotka u vody</title>
    <link rel="stylesheet" href="admin.css">
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
          <p class="hint">Můžete vybrat více fotek najednou z počítače i mobilu. Popisky doplníte po nahrání níže.</p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload">
            <label for="photos">Fotky</label>
            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <p class="hint">Povolené formáty: JPG, PNG, WebP. Limit jedné fotky: 8 MB.</p>
            <div class="actions">
              <button class="button" type="submit">Nahrát fotky</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2>Upravit popisky</h2>
          <?php if (!$items): ?>
            <p class="hint">Galerie zatím nemá žádné fotky.</p>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save">
              <div class="grid">
                <?php foreach ($items as $item): ?>
                  <?php
                    $id = (string) ($item['id'] ?? '');
                    $image = (string) ($item['image'] ?? '');
                    $caption = (string) ($item['caption'] ?? '');
                  ?>
                  <article class="photo-card">
                    <img src="<?= e(image_src_for_admin($image)) ?>" alt="">
                    <div class="photo-card-body">
                      <label for="caption-<?= e($id) ?>">Popisek</label>
                      <textarea id="caption-<?= e($id) ?>" name="captions[<?= e($id) ?>]" placeholder="Popisek může zůstat prázdný."><?= e($caption) ?></textarea>
                      <button class="button danger" type="submit" form="delete-<?= e($id) ?>">Smazat fotku</button>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <div class="actions">
                <button class="button" type="submit">Uložit popisky</button>
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
