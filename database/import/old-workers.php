<?php

declare(strict_types=1);

/*
 * পুরনো সাইটের (jorurika_jk) অনুমোদিত কর্মীদের নতুন `users` টেবিলের জন্য SQL বানায়। ডাটাবেসে কিছু লেখে না।
 *
 * চালানো:  php database/import/old-workers.php <পুরনো-dump.sql> <বের-হওয়া-ফোল্ডার>
 * বের হয়:  workers-import.json (SQL-এর টুকরোর তালিকা) ও report.txt (কাকে কেন বাদ দেওয়া হলো)।
 * এই ফাইলগুলোতে ব্যক্তিগত তথ্য থাকে: কখনও git-এ রাখবেন না।
 *
 * নিয়ম:
 * - শুধু approval = 'Approved' ও reg_as = 'w' (কর্মী); প্রতিনিধি, অপেক্ষমাণ, বাতিল আমদানি হয় না।
 * - পুরনো পাসওয়ার্ড (সাদা লেখা) আনা হয় না। সবার পাসওয়ার্ড অব্যবহারযোগ্য (কেউ জানে না); অ্যাডমিন ফোন করে
 *   সক্রিয় করার সময় কমন পাসওয়ার্ড বসাবেন। must_change_password = 1: প্রথম লগইনে নতুন পাসওয়ার্ড দিতে হবে।
 * - ছবি, পুরনো HTML ও কোড আনা হয় না; লেখা প্লেইন টেক্সট করে পরিষ্কার করা হয়।
 * - মোবাইল ১১ সংখ্যা ০১[৩-৯]xxxxxxxx না হলে বাদ।
 * - একই মোবাইলে একাধিক সারি = একজন মানুষ একাধিক পেশায়: প্রথম সারি (পুরনো sl-এর ক্রমে) প্রধান পেশা ও ঠিকানা,
 *   বাকিদের পেশা worker_services-এ "আরও পেশা" (সর্বোচ্চ ৪টি; বাড়তি পেশা ও একই পেশার পুনরাবৃত্তি বাদ)।
 */

if ($argc < 3) {
    fwrite(STDERR, "ব্যবহার: php old-workers.php <dump.sql> <বের-হওয়া-ফোল্ডার>\n");
    exit(1);
}
$dump = (string) file_get_contents($argv[1]);
$outDir = rtrim($argv[2], '/\\');
$root = dirname(__DIR__, 2);
$geo = require $root . '/database/seeds/geo.php';
$services = require $root . '/database/seeds/services.php';

/** dump-এর একটা টেবিলের সব সারি (INSERT ... VALUES (...),(...);) কলামের নামসহ ফেরত দেয়। */
function dump_rows(string $sql, string $table): array
{
    if (!preg_match('/^INSERT INTO `' . $table . '` \(([^)]*)\) VALUES (.*);$/m', $sql, $m)) {
        return [];
    }
    $cols = array_map(static fn (string $c): string => trim($c, " `"), explode(',', $m[1]));
    $s = $m[2];
    $n = strlen($s);
    $i = 0;
    $out = [];
    $backslash = chr(92);
    $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0"];
    while ($i < $n) {
        if ($s[$i] !== '(') {
            $i++;
            continue;
        }
        $i++;
        $row = [];
        while ($i < $n) {
            $c = $s[$i];
            if ($c === "'") {
                $i++;
                $v = '';
                while ($i < $n) {
                    if ($s[$i] === $backslash) {
                        $v .= $escapes[$s[$i + 1]] ?? $s[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($s[$i] === "'") {
                        if (($s[$i + 1] ?? '') === "'") {
                            $v .= "'";
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $v .= $s[$i++];
                }
                $row[] = $v;
            } elseif ($c === ',') {
                $i++;
            } elseif ($c === ')') {
                $i++;
                break;
            } else {
                $j = $i;
                while ($i < $n && $s[$i] !== ',' && $s[$i] !== ')') {
                    $i++;
                }
                $t = substr($s, $j, $i - $j);
                $row[] = $t === 'NULL' ? null : $t;
            }
        }
        $out[] = array_combine($cols, $row);
    }

    return $out;
}

/** বাংলা অঙ্ককে ইংরেজি অঙ্কে বদলায়। */
function en_digits(string $s): string
{
    return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
}

/** পুরনো লেখা প্লেইন টেক্সট করে: ট্যাগ, নিয়ন্ত্রণ-অক্ষর বাদ, বাড়তি ফাঁকা এক ফাঁকা। $multiline হলে লাইন-ভাঙা থাকে। */
function clean_text(string $s, int $max, bool $multiline = false): string
{
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = (string) preg_replace('/[^\P{C}\n]+/u', '', $s);
    $s = $multiline
        ? (string) preg_replace(['/[ \t]+/u', '/\n{3,}/'], [' ', "\n\n"], $s)
        : (string) preg_replace('/\s+/u', ' ', $s);

    return mb_substr(trim($s), 0, $max);
}

/** মোবাইল নাম্বার ১১ সংখ্যায় আনে; ঠিক না হলে null। */
function clean_phone(string $raw): ?string
{
    $p = (string) preg_replace('/\D/', '', en_digits($raw));
    if (str_starts_with($p, '880')) {
        $p = substr($p, 2);
    }
    if (strlen($p) === 10 && $p[0] === '1') {
        $p = '0' . $p;
    }

    return preg_match('/^01[3-9][0-9]{8}$/', $p) === 1 ? $p : null;
}

/** পুরনো "Motijheel (মতিঝিল)" বা "Dhaka (ঢাকা জেলা)" থেকে বাংলা নাম। */
function bn_name(string $s): string
{
    if (preg_match('/\(([^)]*)\)/u', $s, $m)) {
        $s = $m[1];
    }

    // য়/ড়/ঢ় একই অক্ষর দুভাবে লেখা যায় (এক কোড বা দুই কোড); তুলনার আগে এক রূপে আনা হয়
    $s = strtr($s, ["\u{09DC}" => "\u{09A1}\u{09BC}", "\u{09DD}" => "\u{09A2}\u{09BC}", "\u{09DF}" => "\u{09AF}\u{09BC}"]);

    return trim((string) preg_replace('/\s*(জেলা|বিভাগ)\s*$/u', '', trim($s)));
}

// ---- নতুন এলাকা ও পেশার তালিকা ----
$newDistrictByName = [];
$divisionOfDistrict = [];
foreach ($geo['districts'] as $d) {
    $newDistrictByName[$d[2]] = $d[0];
    $divisionOfDistrict[$d[0]] = $d[1];
}
$newUpazila = [];
foreach ($geo['upazilas'] as $u) {
    $newUpazila[$u[1] . '|' . $u[2]] = [$u[0], $u[1]];
}
// পুরনো তালিকার বানান-ভেদ, যা নামে মেলে না
$aliases = ['নওয়াবগঞ্জ' => 'নবাবগঞ্জ', 'কেরানিগঞ্জ' => 'কেরাণীগঞ্জ'];

$oldDistrict = [];
foreach (dump_rows($dump, 'district') as $d) {
    $oldDistrict[$d['dis_id']] = bn_name($d['dis_name']);
}
$areaOf = []; // পুরনো upz_id => [নতুন upazila_id, district_id, division_id]
foreach (dump_rows($dump, 'upazila') as $u) {
    $districtId = $newDistrictByName[$oldDistrict[$u['dis_id']] ?? ''] ?? null;
    $name = bn_name($u['upazila']);
    $hit = $districtId === null ? null : ($newUpazila[$districtId . '|' . ($aliases[$name] ?? $name)] ?? null);
    if ($hit !== null) {
        $areaOf[$u['upz_id']] = [$hit[0], $hit[1], $divisionOfDistrict[$hit[1]]];
    }
}

$slugByLegacy = [];
foreach ($services as $row) {
    $slugByLegacy[(int) $row[0]] = (string) $row[2];
}
$slugByOldId = [];
foreach (dump_rows($dump, 'services') as $s) {
    $slugByOldId[$s['service_id']] = $slugByLegacy[(int) $s['sl']] ?? null;
}

// ---- কর্মী বাছাই ----
$quote = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
$workers = dump_rows($dump, 'worker_info');
usort($workers, static fn (array $a, array $b): int => (int) $a['sl'] <=> (int) $b['sl']);
$unusable = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

$legacyOfPhone = []; // মোবাইল => প্রথম সারির পুরনো id
$mainSlug = [];     // মোবাইল => প্রধান পেশার slug
$extraOf = [];      // মোবাইল => আরও পেশার slug-তালিকা
$seenPhone = [];
$seenEmail = [];
$values = [];
$skip = ['status' => [], 'phone' => [], 'same_service' => [], 'too_many' => [], 'area' => [], 'service' => [], 'name' => []];
foreach ($workers as $w) {
    $label = $w['id'] . ' ' . $w['name'] . ' ' . $w['mobile'];
    if ($w['approval'] !== 'Approved' || $w['reg_as'] !== 'w') {
        $skip['status'][] = $label . ' (' . $w['approval'] . '/' . $w['reg_as'] . ')';
        continue;
    }
    $name = clean_text($w['name'], 100);
    if (mb_strlen($name) < 2) {
        $skip['name'][] = $label;
        continue;
    }
    $phone = clean_phone($w['mobile']);
    if ($phone === null) {
        $skip['phone'][] = $label;
        continue;
    }
    if (isset($seenPhone[$phone])) {
        $other = $slugByOldId[$w['service_id']] ?? null;
        if ($other === null || $other === $mainSlug[$phone] || in_array($other, $extraOf[$phone], true)) {
            $skip['same_service'][] = $label . ' (আগে: ' . $seenPhone[$phone] . ')';
        } elseif (count($extraOf[$phone]) >= 4) {
            $skip['too_many'][] = $label . ' (আগে: ' . $seenPhone[$phone] . ')';
        } else {
            $extraOf[$phone][] = $other;
        }
        continue;
    }
    $area = $areaOf[$w['preadd_area']] ?? null;
    if ($area === null) {
        $skip['area'][] = $label . ' (এলাকা-id ' . $w['preadd_area'] . ')';
        continue;
    }
    $slug = $slugByOldId[$w['service_id']] ?? null;
    if ($slug === null) {
        $skip['service'][] = $label . ' (পেশা-id ' . $w['service_id'] . ')';
        continue;
    }
    $seenPhone[$phone] = $label;
    $legacyOfPhone[$phone] = $w['id'];
    $mainSlug[$phone] = $slug;
    $extraOf[$phone] = [];

    $email = mb_strtolower(trim($w['email']));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150 || isset($seenEmail[$email])) {
        $email = null;
    } else {
        $seenEmail[$email] = true;
    }
    $exp = (int) preg_replace('/\D/', '', en_digits($w['experience']));
    $experience = $w['experience'] !== '' && $exp <= 70 ? (string) $exp : 'NULL';
    $bio = clean_text($w['work_experience'], 2000, true);
    $created = preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $w['entry_date']) === 1 ? $w['entry_date'] : date('Y-m-d H:i:s');

    $values[] = '(' . implode(', ', [
        $quote($w['id']), "'worker'", "'approved'", $quote($name), $quote($phone), $quote($email), $quote($unusable), '1',
        $quote($slug), $experience, $bio === '' ? 'NULL' : $quote($bio),
        $quote(clean_text($w['preadd_house'], 250)), (string) $area[0], (string) $area[1], (string) $area[2],
        $quote($created), $quote($created),
    ]) . ')';
}

$head = 'INSERT INTO users (legacy_id, role, status, name, phone, email, password_hash, must_change_password, service_slug, experience_years, bio, address, upazila_id, district_id, division_id, created_at, approved_at) VALUES ';
$statements = [];
foreach (array_chunk($values, 200) as $chunk) {
    $statements[] = $head . implode(",\n", $chunk) . "\n ON DUPLICATE KEY UPDATE legacy_id = legacy_id";
}
// আরও পেশা: কর্মী আগের বিবৃতিতে ঢুকে যাওয়ার পর legacy_id ধরে তার users.id খুঁজে worker_services-এ বসে
$pairs = [];
foreach ($extraOf as $phone => $slugs) {
    foreach ($slugs as $extraSlug) {
        $pairs[] = 'SELECT ' . $quote($legacyOfPhone[$phone]) . ' AS l, ' . $quote($extraSlug) . ' AS s';
    }
}
foreach (array_chunk($pairs, 200) as $chunk) {
    $statements[] = 'INSERT IGNORE INTO worker_services (user_id, service_slug) SELECT users.id, t.s FROM ('
        . implode(" UNION ALL ", $chunk) . ') t JOIN users ON users.legacy_id = t.l COLLATE utf8mb4_unicode_ci';
}
file_put_contents($outDir . '/workers-import.json', json_encode($statements, JSON_UNESCAPED_UNICODE));

$report = 'আমদানি হবে: ' . count($values) . " জন\n";
foreach ($skip as $reason => $list) {
    $report .= "\n== বাদ ($reason): " . count($list) . "\n" . implode("\n", $list) . "\n";
}
file_put_contents($outDir . '/report.txt', $report);
echo 'আমদানি হবে: ', count($values), ' জন; বাদ: ', array_sum(array_map('count', $skip)), "\n";
foreach ($skip as $reason => $list) {
    echo '  ', $reason, ': ', count($list), "\n";
}
