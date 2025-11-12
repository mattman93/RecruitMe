<?php

namespace App\Http\Controllers;

use App\Services\GhostService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogController extends Controller
{
    private GhostService $ghostService;

    public function __construct(GhostService $ghostService)
    {
        $this->ghostService = $ghostService;
    }

    /**
     * Display blog listing page
     */
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $postsData = $this->ghostService->getPosts(12, $page);

        return Inertia::render('Blog/Index', [
            'posts' => $postsData['posts'] ?? [],
            'meta' => $postsData['meta'] ?? null,
        ]);
    }

    /**
     * Display a single blog post
     */
    public function show(string $slug)
    {
        $post = $this->ghostService->getPostBySlug($slug);

        if (!$post) {
            abort(404, 'Post not found');
        }

        return Inertia::render('Blog/Show', [
            'post' => $post,
        ]);
    }

    /**
     * Redirect to Ghost admin (superadmin only)
     */
    public function admin(Request $request)
    {
        // Check if user is authenticated and is superadmin
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            abort(403, 'Access denied. Only superadmins can access the blog admin.');
        }

        // Redirect to Ghost admin interface
        return redirect()->away(config('services.ghost.admin_url'));
    }
}
