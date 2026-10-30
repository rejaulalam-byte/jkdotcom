<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** ফুটারের ইমেইল-বাক্সে জমা পড়া ইমেইল (subscribers টেবিল): অ্যাডমিনের তালিকা, CSV ও মোছা। ডাটাবেস না থাকলে খালি। */
final class SubscriberRepository
{
    /**
     * নতুন আগে। $q = ইমেইলের অংশ। $limit < 0 হলে সব (CSV-র জন্য, সর্বোচ্চ ২০০০০)।
     *
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function adminList(string $q, int $limit, int $offset): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return ['rows' => [], 'total' => 0];
        }
        try {
            $where = $q !== '' ? 'WHERE email LIKE :q' : '';
            $params = $q !== '' ? [':q' => '%' . addcslashes($q, '%_\\') . '%'] : [];
            $count = $pdo->prepare("SELECT COUNT(*) FROM subscribers $where");
            $count->execute($params);
            $limit = $limit < 0 ? 20000 : $limit;
            $stmt = $pdo->prepare("SELECT id, email, created_at FROM subscribers $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('SubscriberRepository::adminList failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT id, email, created_at FROM subscribers WHERE id = :id');
            $stmt->execute([':id' => $id]);

            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            Logger::error('SubscriberRepository::find failed: ' . $e->getMessage());

            return null;
        }
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return false;
        }
        try {
            $pdo->prepare('DELETE FROM subscribers WHERE id = :id')->execute([':id' => $id]);

            return true;
        } catch (Throwable $e) {
            Logger::error('SubscriberRepository::delete failed: ' . $e->getMessage());

            return false;
        }
    }
}
