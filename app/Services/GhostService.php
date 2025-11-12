<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GhostService
{
    private string $apiUrl;
    private string $contentApiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.ghost.api_url');
        $this->contentApiKey = config('services.ghost.content_api_key');
    }

    /**
     * Get all published posts
     */
    public function getPosts(int $limit = 15, int $page = 1, array $fields = null)
    {
        $cacheKey = "ghost_posts_{$limit}_{$page}";

        return Cache::remember($cacheKey, 300, function () use ($limit, $page, $fields) {
            $params = [
                'key' => $this->contentApiKey,
                'limit' => $limit,
                'page' => $page,
                'include' => 'tags,authors',
                'order' => 'published_at DESC'
            ];

            if ($fields) {
                $params['fields'] = implode(',', $fields);
            }

            $response = Http::get("{$this->apiUrl}/ghost/api/content/posts/", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        });
    }

    /**
     * Get a single post by slug
     */
    public function getPostBySlug(string $slug)
    {
        $cacheKey = "ghost_post_{$slug}";

        return Cache::remember($cacheKey, 300, function () use ($slug) {
            $params = [
                'key' => $this->contentApiKey,
                'include' => 'tags,authors'
            ];

            $response = Http::get("{$this->apiUrl}/ghost/api/content/posts/slug/{$slug}/", $params);

            if ($response->successful()) {
                $data = $response->json();
                return $data['posts'][0] ?? null;
            }

            return null;
        });
    }

    /**
     * Get featured posts
     */
    public function getFeaturedPosts(int $limit = 3)
    {
        $cacheKey = "ghost_featured_posts_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($limit) {
            $params = [
                'key' => $this->contentApiKey,
                'limit' => $limit,
                'filter' => 'featured:true',
                'include' => 'tags,authors'
            ];

            $response = Http::get("{$this->apiUrl}/ghost/api/content/posts/", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        });
    }

    /**
     * Get posts by tag
     */
    public function getPostsByTag(string $tagSlug, int $limit = 15)
    {
        $cacheKey = "ghost_posts_tag_{$tagSlug}_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($tagSlug, $limit) {
            $params = [
                'key' => $this->contentApiKey,
                'limit' => $limit,
                'filter' => "tag:{$tagSlug}",
                'include' => 'tags,authors'
            ];

            $response = Http::get("{$this->apiUrl}/ghost/api/content/posts/", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        });
    }

    /**
     * Clear all Ghost-related cache
     */
    public function clearCache()
    {
        Cache::flush();
    }
}
