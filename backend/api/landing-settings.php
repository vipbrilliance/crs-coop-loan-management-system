<?php
// backend/api/landing-settings.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET — public, no auth (landing page reads this without a session)
if ($method === 'GET') {
    $stmt = $db->query("SELECT value FROM system_settings WHERE `key` = 'landing_page_settings'");
    $row  = $stmt ? $stmt->fetch() : null;
    $data = $row ? json_decode($row['value'], true) : default_landing_settings();
    json_ok($data);
}

// POST — requires admin auth
if ($method === 'POST') {
    $user = require_auth($db);
    require_cap($db, 'ADMIN', $user);

    $action = $_GET['action'] ?? 'save';

    // --- Upload image ---
    if ($action === 'upload') {
        $slot = $_POST['slot'] ?? '';  // 'hero' | 'testimonial_0' | 'testimonial_1' | 'testimonial_2'
        if (!$slot) json_err('slot required');

        if (empty($_FILES['image'])) json_err('No file uploaded');
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) json_err('Upload error: ' . $file['error']);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) json_err('Only jpg/jpeg/png/gif/webp allowed');
        if ($file['size'] > 10 * 1024 * 1024) json_err('File too large (max 10 MB)');

        $uploadDir = __DIR__ . '/../uploads/landing/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Deterministic filename per slot so re-uploading replaces the old file
        $filename = preg_replace('/[^a-z0-9_]/', '-', $slot) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) json_err('Failed to save file');

        // URL relative to the backend api root so it works on any host
        $url = rtrim(str_replace('/api', '', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/uploads/landing/' . $filename;

        // Persist the url into system_settings
        $stmt = $db->query("SELECT value FROM system_settings WHERE `key` = 'landing_page_settings'");
        $row  = $stmt ? $stmt->fetch() : null;
        $curr = $row ? json_decode($row['value'], true) : default_landing_settings();

        if ($slot === 'hero') {
            $curr['hero_image'] = $url;
        } elseif (preg_match('/^testimonial_(\d)$/', $slot, $m)) {
            $i = (int)$m[1];
            if (!isset($curr['testimonials'][$i])) $curr['testimonials'][$i] = default_testimonial($i);
            $curr['testimonials'][$i]['photo'] = $url;
        }

        upsert_landing($db, $curr);
        audit_log($db, 'LandingSettings', 'UPDATED', 'LandingSettings', $slot, $slot, 'Uploaded image for ' . $slot, [], (int)$user['id']);

        json_ok(['url' => $url, 'settings' => $curr]);
    }

    // --- Save text settings ---
    if ($action === 'save') {
        $d = body();

        $stmt = $db->query("SELECT value FROM system_settings WHERE `key` = 'landing_page_settings'");
        $row  = $stmt ? $stmt->fetch() : null;
        $curr = $row ? json_decode($row['value'], true) : default_landing_settings();

        // Merge allowed fields
        if (isset($d['testimonials']) && is_array($d['testimonials'])) {
            foreach ($d['testimonials'] as $i => $t) {
                if (!isset($curr['testimonials'][$i])) $curr['testimonials'][$i] = default_testimonial($i);
                foreach (['name', 'role', 'initials', 'tenure', 'quote'] as $field) {
                    if (array_key_exists($field, $t)) $curr['testimonials'][$i][$field] = $t[$field];
                }
                // photo URL only set via upload action, not text save
            }
        }

        upsert_landing($db, $curr);
        audit_log($db, 'LandingSettings', 'UPDATED', 'LandingSettings', 'text', 'text', 'Updated landing page text content', [], (int)$user['id']);

        json_ok($curr);
    }
}

// ── helpers ──────────────────────────────────────────────────────────────────

function upsert_landing($db, $data) {
    $json = json_encode($data);
    $stmt = $db->prepare("
        INSERT INTO system_settings (`key`, value) VALUES ('landing_page_settings', ?)
        ON DUPLICATE KEY UPDATE value = ?
    ");
    $stmt->execute([$json, $json]);
}

function default_landing_settings() {
    return [
        'hero_image' => null,
        'testimonials' => [
            default_testimonial(0),
            default_testimonial(1),
            default_testimonial(2),
        ],
    ];
}

function default_testimonial($i) {
    $defaults = [
        ['name' => 'Jonalyn Mendoza', 'role' => 'Production Lead · CRS Holdings', 'initials' => 'JM', 'tenure' => '5 yrs', 'quote' => 'They checked my eligibility, processed it, then approved — it really was that easy. No credit-score questions, no awkward conversations.', 'photo' => null],
        ['name' => 'Renato Bautista',  'role' => 'Warehouse Supervisor · Bellshayce',  'initials' => 'RB', 'tenure' => '7 yrs', 'quote' => 'I\'ve been contributing ₱1,000 a month since 2019. Watching the share capital and dividends grow is honestly the best financial habit I\'ve built.', 'photo' => null],
        ['name' => 'Angeli Cruz',       'role' => 'HR Officer · CRS Holdings',           'initials' => 'AC', 'tenure' => '3 yrs', 'quote' => 'The portal is what sold me. I can check my balance, see my next due, and download payment receipts — all from my phone during break.', 'photo' => null],
    ];
    return $defaults[$i] ?? ['name' => 'Member ' . ($i + 1), 'role' => '', 'initials' => 'M' . ($i + 1), 'tenure' => '', 'quote' => '', 'photo' => null];
}
