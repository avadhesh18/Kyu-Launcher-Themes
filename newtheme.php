<?php
declare(strict_types=1);

const THEMES_FILE = __DIR__ . '/docs/themes.json';
const IMAGES_DIR = __DIR__ . '/docs/images';

function slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'theme';
}

function uniqueThemeId(string $base, array $themes): string
{
    $id = $base;
    $existing = array_column($themes, 'i');
    $n = 2;
    while (in_array($id, $existing, true)) {
        $id = $base . '-' . $n;
        $n++;
    }
    return $id;
}

function normalizeHex(string $hex): ?string
{
    $hex = trim($hex);
    if ($hex === '') {
        return null;
    }
    if ($hex[0] !== '#') {
        $hex = '#' . $hex;
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return null;
    }
    return strtolower($hex);
}

function loadThemes(): array
{
    if (!is_file(THEMES_FILE)) {
        return [];
    }
    $raw = file_get_contents(THEMES_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveThemes(array $themes): bool
{
    $dir = dirname(THEMES_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }
    $json = json_encode($themes, JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents(THEMES_FILE, $json . "\n") !== false;
}

$message = null;
$messageType = 'ok';
$values = ['name' => '', 'desc' => '', 'p' => '#6366f1', 's' => '#a855f7'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim($_POST['name'] ?? '');
    $values['desc'] = trim($_POST['desc'] ?? '');
    $values['p'] = trim($_POST['p'] ?? '');
    $values['s'] = trim($_POST['s'] ?? '');

    if ($values['name'] === '') {
        $message = 'Theme name is required.';
        $messageType = 'err';
    } elseif ($values['desc'] === '') {
        $message = 'Description is required.';
        $messageType = 'err';
    } else {
        $primary = normalizeHex($values['p']);
        $secondary = normalizeHex($values['s']);
        if ($primary === null) {
            $message = 'Primary color must be a valid 6-digit hex value.';
            $messageType = 'err';
        } elseif ($secondary === null) {
            $message = 'Secondary color must be a valid 6-digit hex value.';
            $messageType = 'err';
        } else {
            $themes = loadThemes();
            $baseId = slugify($values['name']);
            $id = uniqueThemeId($baseId, $themes);
            $imagePath = IMAGES_DIR . '/' . $id . '.png';

            if (!is_file($imagePath)) {
                $message = 'Image not found. Add docs/images/' . $id . '.png first.';
                $messageType = 'err';
            } else {
                $theme = [
                    'i' => $id,
                    'n' => $values['name'],
                    'd' => $values['desc'],
                    'm' => 'images/' . $id . '.png',
                    'p' => $primary,
                    's' => $secondary,
                ];
                array_unshift($themes, $theme);

                if (saveThemes($themes)) {
                    $message = 'Theme "' . htmlspecialchars($values['name'], ENT_QUOTES, 'UTF-8') . '" added (id: ' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . ').';
                    $messageType = 'ok';
                    $values = ['name' => '', 'desc' => '', 'p' => '#6366f1', 's' => '#a855f7'];
                } else {
                    $message = 'Could not write themes.json.';
                    $messageType = 'err';
                }
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Theme</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #0f1117;
      color: #e8eaed;
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      width: 100%;
      max-width: 480px;
      background: #1a1d27;
      border: 1px solid #2a2f3d;
      border-radius: 12px;
      padding: 28px;
    }
    h1 { margin: 0 0 6px; font-size: 1.4rem; }
    .sub { color: #8b93a7; font-size: 0.875rem; margin-bottom: 24px; }
    label { display: block; font-size: 0.8rem; color: #a9b0c0; margin-bottom: 6px; }
    input[type="text"], textarea {
      width: 100%;
      background: #0f1117;
      border: 1px solid #2a2f3d;
      border-radius: 8px;
      color: #e8eaed;
      padding: 10px 12px;
      font: inherit;
      margin-bottom: 16px;
    }
    textarea { min-height: 80px; resize: vertical; }
    .hint {
      font-size: 0.8rem;
      color: #8b93a7;
      margin: -10px 0 16px;
      line-height: 1.4;
    }
    .hint code {
      color: #c4b5fd;
      font-size: 0.78rem;
    }
    .color-row {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 16px;
    }
    .color-row input[type="color"] {
      width: 44px;
      height: 44px;
      padding: 2px;
      border: 1px solid #2a2f3d;
      border-radius: 8px;
      background: #0f1117;
      cursor: pointer;
      flex-shrink: 0;
    }
    .color-row input[type="text"] { margin-bottom: 0; flex: 1; }
    button {
      width: 100%;
      background: #6366f1;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px;
      font: inherit;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
    }
    button:hover { background: #5558e3; }
    .msg {
      padding: 10px 12px;
      border-radius: 8px;
      font-size: 0.875rem;
      margin-bottom: 16px;
    }
    .msg.ok { background: #132819; color: #6ee7a0; border: 1px solid #1f4d35; }
    .msg.err { background: #2a1515; color: #f87171; border: 1px solid #5c2020; }
  </style>
</head>
<body>
  <div class="card">
    <h1>New Theme</h1>
    <p class="sub">Adds a theme to docs/themes.json</p>

    <?php if ($message !== null): ?>
      <div class="msg <?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="name">Theme name</label>
      <input type="text" id="name" name="name" required value="<?= htmlspecialchars($values['name'], ENT_QUOTES, 'UTF-8') ?>">

      <p class="hint">Drop image at <code id="image-path">docs/images/theme.png</code> before submitting.</p>

      <label for="desc">Description</label>
      <textarea id="desc" name="desc" required><?= htmlspecialchars($values['desc'], ENT_QUOTES, 'UTF-8') ?></textarea>

      <label>Primary color</label>
      <div class="color-row">
        <input type="color" id="p-picker" value="<?= htmlspecialchars($values['p'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" id="p" name="p" required pattern="#?[0-9a-fA-F]{6}" placeholder="#6366f1" value="<?= htmlspecialchars($values['p'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <label>Secondary color</label>
      <div class="color-row">
        <input type="color" id="s-picker" value="<?= htmlspecialchars($values['s'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" id="s" name="s" required pattern="#?[0-9a-fA-F]{6}" placeholder="#a855f7" value="<?= htmlspecialchars($values['s'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <button type="submit">Add theme</button>
    </form>
  </div>

  <script>
    function slugify(name) {
      return name.toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'theme';
    }

    function bindColor(pickerId, textId) {
      const picker = document.getElementById(pickerId);
      const text = document.getElementById(textId);

      function toHex(v) {
        v = v.trim();
        if (!v.startsWith('#')) v = '#' + v;
        return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toLowerCase() : null;
      }

      picker.addEventListener('input', () => {
        text.value = picker.value;
      });

      text.addEventListener('input', () => {
        const hex = toHex(text.value);
        if (hex) picker.value = hex;
      });
    }

    const nameInput = document.getElementById('name');
    const imagePath = document.getElementById('image-path');

    function updateImagePath() {
      imagePath.textContent = 'docs/images/' + slugify(nameInput.value) + '.png';
    }

    nameInput.addEventListener('input', updateImagePath);
    updateImagePath();

    bindColor('p-picker', 'p');
    bindColor('s-picker', 's');
  </script>
</body>
</html>
