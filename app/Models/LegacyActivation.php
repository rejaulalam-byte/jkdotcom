<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * পুরনো সাইট থেকে আনা কর্মীর সক্রিয় করার নিয়ম: অ্যাডমিন ফোনে জানিয়ে "ফোন করা হয়েছে" টিক দিলে ১৫ দিন
 * একটাই কমন প্রাথমিক পাসওয়ার্ডে লগইন চলে; লগইনের পর কর্মী নিজের পাসওয়ার্ড বসালে (/account/setup) তিনি "সক্রিয়"।
 * কমন পাসওয়ার্ডের শুধু হ্যাশ রাখা হয়। ডাটাবেস না থাকলে storage/demo-legacy-password.json।
 */
final class LegacyActivation
{
    /** টিক দেওয়ার পর কমন পাসওয়ার্ড যত দিন কাজ করে। */
    public const DAYS = 15;

    private const DEMO_FILE = '/storage/demo-legacy-password.json';

    public const STATES = [
        'todo' => 'ফোন বাকি',
        'called' => 'ফোন দেওয়া হয়েছে',
        'expired' => 'মেয়াদ শেষ',
        'active' => 'সক্রিয়',
    ];

    /**
     * কর্মীর সক্রিয় করার অবস্থা: todo | called | expired | active; পুরনো সাইটের কর্মী না হলে খালি লেখা।
     *
     * @param array<string,mixed> $user
     */
    public static function state(array $user): string
    {
        if ((string) ($user['legacy_id'] ?? '') === '') {
            return '';
        }
        if ((int) ($user['must_change_password'] ?? 0) !== 1) {
            return 'active';
        }
        $deadline = (string) ($user['activation_deadline'] ?? '');
        if ($deadline === '') {
            return 'todo';
        }

        return strtotime($deadline) >= time() ? 'called' : 'expired';
    }

    /** মেয়াদ শেষ হতে আরও কত দিন (আংশিক দিনও ১ গোনা); মেয়াদ না থাকলে ০। @param array<string,mixed> $user */
    public static function daysLeft(array $user): int
    {
        $deadline = (string) ($user['activation_deadline'] ?? '');
        if ($deadline === '' || strtotime($deadline) < time()) {
            return 0;
        }

        return (int) ceil((strtotime($deadline) - time()) / 86400);
    }

    /**
     * এই কর্মীকে এই পাসওয়ার্ডে ঢুকতে দেওয়া যায় কি: পুরনো সাইটের কর্মী, এখনও নিজের পাসওয়ার্ড বসাননি,
     * ফোনের টিকের মেয়াদ চলছে এবং পাসওয়ার্ড কমন পাসওয়ার্ডের সমান।
     *
     * @param array<string,mixed> $user
     */
    public static function accepts(array $user, string $password): bool
    {
        if (self::state($user) !== 'called') {
            return false;
        }
        $hash = self::commonHash();

        return $hash !== '' && password_verify($password, $hash);
    }

    private static function commonHash(): string
    {
        $row = self::commonRow();

        return $row === null ? '' : (string) $row['password_hash'];
    }

    /** কমন পাসওয়ার্ড কখন ও কে বসিয়েছেন (পাসওয়ার্ড নিজে নয়); বসানো না থাকলে null। @return array{updated_at:string,updated_by:string}|null */
    public static function info(): ?array
    {
        $row = self::commonRow();

        return $row === null ? null : ['updated_at' => (string) $row['updated_at'], 'updated_by' => (string) $row['updated_by']];
    }

    /** @return array<string,mixed>|null */
    private static function commonRow(): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $row = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            return is_array($row) && isset($row['password_hash']) ? $row : null;
        }

        try {
            $stmt = $pdo->query('SELECT password_hash, updated_by, updated_at FROM legacy_common_password WHERE id = 1');
            $row = $stmt === false ? false : $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            Logger::error('LegacyActivation::commonRow failed: ' . $e->getMessage());

            return null;
        }
    }

    /** নতুন কমন পাসওয়ার্ডের হ্যাশ বসায় (আগেরটা বাতিল হয়)। সফল হলে true। */
    public static function setCommon(string $hash, string $by): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $row = ['password_hash' => $hash, 'updated_by' => $by, 'updated_at' => date('Y-m-d H:i:s')];

            return @file_put_contents($file, json_encode($row), LOCK_EX) !== false;
        }

        try {
            $pdo->prepare(
                'INSERT INTO legacy_common_password (id, password_hash, updated_by, updated_at) VALUES (1, :h, :b, NOW())
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), updated_by = VALUES(updated_by), updated_at = NOW()'
            )->execute([':h' => $hash, ':b' => mb_substr($by, 0, 100)]);

            return true;
        } catch (Throwable $e) {
            Logger::error('LegacyActivation::setCommon failed: ' . $e->getMessage());

            return false;
        }
    }
}
