<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * ফর্মে সুপার অ্যাডমিনের যোগ করা নতুন ঘর (মাইগ্রেশন 022) ও দর্শকের পূরণ করা মান।
 * তিন ফর্ম: worker / agent (মান users.id ধরে) এবং post (মান job_posts.id ধরে)। নতুন ঘরগুলো ফর্মে সম্মতির টিকের
 * ঠিক আগে, অ্যাডমিনের সাজানো ক্রমে বসে (partials/custom-fields.php)। সাইটের আসল ঘর এখানে নয় (সেগুলো FormFields)।
 * ডাটাবেস না থাকলে (লোকাল প্রিভিউ) storage/demo-custom-fields.json ও demo-custom-values.json।
 */
final class CustomFields
{
    private const DEMO_FIELDS = '/storage/demo-custom-fields.json';
    private const DEMO_VALUES = '/storage/demo-custom-values.json';

    /** ঘরের ধরন => অ্যাডমিনকে দেখানো নাম */
    public const TYPES = [
        'text'     => 'এক লাইনের লেখা',
        'textarea' => 'কয়েক লাইনের লেখা',
        'number'   => 'সংখ্যা',
        'date'     => 'তারিখ',
        'select'   => 'ড্রপডাউন (নিজের দেওয়া বিকল্প)',
        'checkbox' => 'হ্যাঁ/না টিক',
    ];

    public const MAX_LABEL = 200;
    private const MAX_TEXT = 300;
    private const MAX_TEXTAREA = 2000;

    /**
     * একটা ফর্মের সব নতুন ঘর, ক্রম অনুযায়ী।
     *
     * @return list<array{id:int,form_key:string,label:string,field_type:string,options:list<string>,is_required:bool,sort_order:int}>
     */
    public static function forForm(string $form): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(self::DEMO_FIELDS), static fn (array $r): bool => ($r['form_key'] ?? '') === $form));
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM form_custom_fields WHERE form_key = :f ORDER BY sort_order ASC, id ASC');
                $stmt->execute([':f' => $form]);
                $rows = $stmt->fetchAll();
            } catch (Throwable $e) {
                Logger::error('CustomFields::forForm failed: ' . $e->getMessage());

                return [];
            }
        }
        usort($rows, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

        return array_map([self::class, 'shape'], $rows);
    }

    /** @return array{id:int,form_key:string,label:string,field_type:string,options:list<string>,is_required:bool,sort_order:int}|null */
    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead(self::DEMO_FIELDS) as $r) {
                if ((int) $r['id'] === $id) {
                    return self::shape($r);
                }
            }

            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT * FROM form_custom_fields WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return $row === false ? null : self::shape($row);
        } catch (Throwable $e) {
            Logger::error('CustomFields::find failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * নতুন ঘর (তালিকার শেষে) অথবা আগের ঘরের বদল। ঠিক না থাকলে বাংলায় ভুলের বার্তা ফেরত দেয়, নইলে null।
     *
     * @param array{label:string,field_type:string,options:string,is_required:bool} $data
     */
    public static function save(string $form, ?int $id, array $data): ?string
    {
        $label = trim($data['label']);
        if ($label === '' || mb_strlen($label) > self::MAX_LABEL) {
            return 'ঘরের নাম লিখুন (সর্বোচ্চ ২০০ অক্ষর)।';
        }
        if (!isset(self::TYPES[$data['field_type']])) {
            return 'ঘরের ধরন বাছুন।';
        }
        $options = self::parseOptions($data['options']);
        if ($data['field_type'] === 'select' && count($options) < 2) {
            return 'ড্রপডাউনের জন্য অন্তত দুটি বিকল্প লিখুন (প্রতি লাইনে একটি)।';
        }
        $optionsText = $data['field_type'] === 'select' ? implode("\n", $options) : null;
        $required = $data['is_required'] ? 1 : 0;

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead(self::DEMO_FIELDS);
            if ($id === null) {
                $rows[] = ['id' => (int) max(array_column($rows, 'id') ?: [0]) + 1, 'form_key' => $form, 'label' => $label, 'field_type' => $data['field_type'],
                    'options' => $optionsText, 'is_required' => $required, 'sort_order' => self::nextOrder($form), 'created_at' => date('Y-m-d H:i:s')];
            } else {
                foreach ($rows as &$r) {
                    if ((int) $r['id'] === $id) {
                        $r = array_merge($r, ['label' => $label, 'field_type' => $data['field_type'], 'options' => $optionsText, 'is_required' => $required]);
                    }
                }
                unset($r);
            }

            return self::demoWrite(self::DEMO_FIELDS, $rows) ? null : 'সংরক্ষণ করা যায়নি।';
        }
        try {
            if ($id === null) {
                $pdo->prepare(
                    'INSERT INTO form_custom_fields (form_key, label, field_type, options, is_required, sort_order, created_at)
                     VALUES (:f, :l, :t, :o, :r, :s, NOW())'
                )->execute([':f' => $form, ':l' => $label, ':t' => $data['field_type'], ':o' => $optionsText, ':r' => $required, ':s' => self::nextOrder($form)]);
            } else {
                $pdo->prepare('UPDATE form_custom_fields SET label = :l, field_type = :t, options = :o, is_required = :r WHERE id = :id')
                    ->execute([':l' => $label, ':t' => $data['field_type'], ':o' => $optionsText, ':r' => $required, ':id' => $id]);
            }

            return null;
        } catch (Throwable $e) {
            Logger::error('CustomFields::save failed: ' . $e->getMessage());

            return 'সংরক্ষণ করা যায়নি। (ডাটাবেসে মাইগ্রেশন 022 চালানো হয়েছে তো?)';
        }
    }

    /** ঘর মোছা; তার সব পূরণ করা মানও মুছে যায়। */
    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $fields = array_values(array_filter(self::demoRead(self::DEMO_FIELDS), static fn (array $r): bool => (int) $r['id'] !== $id));
            $values = array_values(array_filter(self::demoRead(self::DEMO_VALUES), static fn (array $r): bool => (int) $r['field_id'] !== $id));

            return self::demoWrite(self::DEMO_FIELDS, $fields) && self::demoWrite(self::DEMO_VALUES, $values);
        }
        try {
            return $pdo->prepare('DELETE FROM form_custom_fields WHERE id = :id')->execute([':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('CustomFields::delete failed: ' . $e->getMessage());

            return false;
        }
    }

    /** ক্রমে এক ধাপ ওপরে ($step = -1) বা নিচে (+1): পাশের ঘরের সঙ্গে জায়গা বদল। */
    public static function move(int $id, int $step): bool
    {
        $field = self::find($id);
        if ($field === null) {
            return false;
        }
        $list = self::forForm($field['form_key']);
        $ids = array_column($list, 'id');
        $pos = array_search($id, $ids, true);
        $to = $pos === false ? -1 : $pos + $step;
        if ($to < 0 || $to >= count($ids)) {
            return true;
        }
        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];

        return self::writeOrder($ids);
    }

    /**
     * ফর্ম থেকে আসা মান (cf[<id>]) পড়ে যাচাই করে। ফেরত: [ঘর-id => মান] (খালি মান বাদ) ও ভুলের বার্তা।
     *
     * @param array<mixed> $posted
     * @return array{0:array<int,string>,1:list<string>}
     */
    public static function read(string $form, array $posted): array
    {
        $values = [];
        $errors = [];
        foreach (self::forForm($form) as $f) {
            $raw = $posted[$f['id']] ?? '';
            $raw = is_string($raw) ? $raw : '';
            $value = match ($f['field_type']) {
                'textarea' => clean_input($raw, self::MAX_TEXTAREA, true),
                'checkbox' => $raw !== '' ? 'হ্যাঁ' : '',
                // বাংলা অঙ্ক ইংরেজিতে; অন্য অক্ষর থাকলে নিচের যাচাই "সঠিক নয়" বলে
                'number' => strtr(clean_input($raw, 20), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']),
                default => clean_input($raw, self::MAX_TEXT),
            };
            if ($value === '') {
                if ($f['is_required']) {
                    $errors[] = '"' . $f['label'] . '" ঘরটি পূরণ করুন।';
                }
                continue;
            }
            $bad = match ($f['field_type']) {
                'number' => preg_match('/^-?[0-9]{1,15}(\.[0-9]{1,4})?$/', $value) !== 1,
                'date' => \DateTime::createFromFormat('Y-m-d', $value) === false || \DateTime::createFromFormat('Y-m-d', $value)->format('Y-m-d') !== $value,
                'select' => !in_array($value, $f['options'], true),
                default => false,
            };
            if ($bad) {
                $errors[] = '"' . $f['label'] . '" ঘরের মান সঠিক নয়।';
                continue;
            }
            $values[$f['id']] = $value;
        }

        return [$values, $errors];
    }

    /** @param array<int,string> $values */
    public static function saveValues(string $form, int $recordId, array $values): void
    {
        if ($values === [] || $recordId <= 0) {
            return;
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead(self::DEMO_VALUES);
            foreach ($values as $fieldId => $value) {
                $rows[] = ['field_id' => $fieldId, 'form_key' => $form, 'record_id' => $recordId, 'value' => $value, 'created_at' => date('Y-m-d H:i:s')];
            }
            self::demoWrite(self::DEMO_VALUES, $rows);

            return;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO form_custom_values (field_id, form_key, record_id, value, created_at) VALUES (:f, :k, :r, :v, NOW())');
            foreach ($values as $fieldId => $value) {
                $stmt->execute([':f' => $fieldId, ':k' => $form, ':r' => $recordId, ':v' => $value]);
            }
        } catch (Throwable $e) {
            Logger::error('CustomFields::saveValues failed: ' . $e->getMessage());
        }
    }

    /**
     * একজন সদস্য বা একটা বিজ্ঞপ্তির পূরণ করা নতুন ঘরগুলো (ঘর মোছা হলে সেটা আর আসে না), ঘরের ক্রমে।
     *
     * @return list<array{label:string,value:string}>
     */
    public static function valuesFor(string $form, int $recordId): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $byField = [];
            foreach (self::demoRead(self::DEMO_VALUES) as $r) {
                if ($r['form_key'] === $form && (int) $r['record_id'] === $recordId) {
                    $byField[(int) $r['field_id']] = (string) $r['value'];
                }
            }
        } else {
            try {
                $stmt = $pdo->prepare('SELECT field_id, value FROM form_custom_values WHERE form_key = :k AND record_id = :r');
                $stmt->execute([':k' => $form, ':r' => $recordId]);
                $byField = [];
                foreach ($stmt->fetchAll() as $r) {
                    $byField[(int) $r['field_id']] = (string) $r['value'];
                }
            } catch (Throwable $e) {
                Logger::error('CustomFields::valuesFor failed: ' . $e->getMessage());

                return [];
            }
        }
        $out = [];
        foreach (self::forForm($form) as $f) {
            if (isset($byField[$f['id']])) {
                $out[] = ['label' => $f['label'], 'value' => $byField[$f['id']]];
            }
        }

        return $out;
    }

    /** একজন সদস্য বা একটা বিজ্ঞপ্তি চিরতরে মুছলে তার পূরণ করা নতুন ঘরের মানও মুছে যায়। */
    public static function deleteValuesFor(string $form, int $recordId): void
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            self::demoWrite(self::DEMO_VALUES, array_values(array_filter(self::demoRead(self::DEMO_VALUES),
                static fn (array $r): bool => !($r['form_key'] === $form && (int) $r['record_id'] === $recordId))));

            return;
        }
        try {
            $pdo->prepare('DELETE FROM form_custom_values WHERE form_key = :k AND record_id = :r')->execute([':k' => $form, ':r' => $recordId]);
        } catch (Throwable $e) {
            Logger::error('CustomFields::deleteValuesFor failed: ' . $e->getMessage());
        }
    }

    /** প্রতি লাইনে একটি বিকল্প; খালি ও একই বিকল্প দুবার বাদ, সর্বোচ্চ ৫০টি। @return list<string> */
    public static function parseOptions(string $text): array
    {
        $lines = array_map(static fn (string $l): string => clean_input($l, 150), preg_split('/\R/u', $text) ?: []);

        return array_slice(array_values(array_unique(array_filter($lines, static fn (string $l): bool => $l !== ''))), 0, 50);
    }

    /** @param array<string,mixed> $r */
    private static function shape(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'form_key' => (string) $r['form_key'],
            'label' => (string) $r['label'],
            'field_type' => (string) $r['field_type'],
            'options' => self::parseOptions((string) ($r['options'] ?? '')),
            'is_required' => (bool) $r['is_required'],
            'sort_order' => (int) $r['sort_order'],
        ];
    }

    private static function nextOrder(string $form): int
    {
        $list = self::forForm($form);

        return $list === [] ? 1 : max(array_column($list, 'sort_order')) + 1;
    }

    /** @param list<int> $ids নতুন ক্রমে */
    private static function writeOrder(array $ids): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead(self::DEMO_FIELDS);
            foreach ($rows as &$r) {
                $pos = array_search((int) $r['id'], $ids, true);
                if ($pos !== false) {
                    $r['sort_order'] = $pos + 1;
                }
            }
            unset($r);

            return self::demoWrite(self::DEMO_FIELDS, $rows);
        }
        try {
            $stmt = $pdo->prepare('UPDATE form_custom_fields SET sort_order = :s WHERE id = :id');
            foreach ($ids as $pos => $id) {
                $stmt->execute([':s' => $pos + 1, ':id' => $id]);
            }

            return true;
        } catch (Throwable $e) {
            Logger::error('CustomFields::writeOrder failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    private static function demoRead(string $name): array
    {
        $file = BASE_PATH . $name;
        $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function demoWrite(string $name, array $rows): bool
    {
        return @file_put_contents(BASE_PATH . $name, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
