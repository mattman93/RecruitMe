<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class LeadCacheService
{
    /**
     * Cache TTL in seconds (10 minutes)
     */
    const CACHE_TTL = 600;

    /**
     * Generate a cache key for leads with session-based invalidation
     *
     * @param int $userId
     * @param bool $relevant
     * @param int $limit
     * @return string
     */
    public function generateCacheKey(int $userId, bool $relevant = false, int $limit = 20): string
    {
        // Get session ID and create a short hash (first 8 chars)
        $sessionId = Session::getId();
        $sessionHash = substr(md5($sessionId), 0, 8);

        // Build cache key
        $key = sprintf(
            'leads:user:%d:session:%s:relevant:%s:limit:%d',
            $userId,
            $sessionHash,
            $relevant ? 'true' : 'false',
            $limit
        );

        return $key;
    }

    /**
     * Get leads from cache
     *
     * @param int $userId
     * @param bool $relevant
     * @param int $limit
     * @return array|null
     */
    public function getLeads(int $userId, bool $relevant = false, int $limit = 20): ?array
    {
        $key = $this->generateCacheKey($userId, $relevant, $limit);
        return Cache::get($key);
    }

    /**
     * Store leads in cache
     *
     * @param int $userId
     * @param array $data
     * @param bool $relevant
     * @param int $limit
     * @return bool
     */
    public function putLeads(int $userId, array $data, bool $relevant = false, int $limit = 20): bool
    {
        $key = $this->generateCacheKey($userId, $relevant, $limit);
        return Cache::put($key, $data, self::CACHE_TTL);
    }

    /**
     * Invalidate all cached leads for a user
     * Useful when user changes preferences, updates resume, etc.
     *
     * @param int $userId
     * @return void
     */
    public function invalidateUserCache(int $userId): void
    {
        // Get all cache keys matching this user
        // Note: This requires Redis SCAN which is more efficient than KEYS
        $pattern = sprintf('appliflow_cache:leads:user:%d:*', $userId);

        try {
            $redis = Cache::getStore()->connection();
            $cursor = null;

            do {
                [$cursor, $keys] = $redis->scan($cursor ?? 0, [
                    'MATCH' => $pattern,
                    'COUNT' => 100
                ]);

                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } while ($cursor !== 0 && $cursor !== '0');

        } catch (\Exception $e) {
            \Log::warning('Failed to invalidate user cache', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if cache is available (Redis is up)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            Cache::get('health_check');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
