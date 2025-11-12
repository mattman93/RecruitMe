import { useState, useEffect } from "react";
import { Navbar } from "../../components/Navbar";
import { Footer } from "../../components/Footer";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../components/ui/card";
import { Badge } from "../../components/ui/badge";
import { Button } from "../../components/ui/button";
import { Calendar, Clock, ArrowRight } from "lucide-react";

interface Author {
  name: string;
  profile_image?: string;
}

interface Tag {
  name: string;
  slug: string;
}

interface Post {
  id: string;
  slug: string;
  title: string;
  excerpt: string;
  feature_image?: string;
  published_at: string;
  reading_time?: number;
  authors?: Author[];
  tags?: Tag[];
}

interface BlogIndexProps {
  posts: Post[];
  meta?: {
    pagination: {
      page: number;
      limit: number;
      pages: number;
      total: number;
      next?: number;
      prev?: number;
    };
  };
}

export default function BlogIndex({ posts, meta }: BlogIndexProps) {
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const response = await fetch("/api/auth/check", {
        credentials: "include",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const data = await response.json();
      setIsAuthenticated(data.authenticated);
    } catch (error) {
      setIsAuthenticated(false);
    }
  };

  const handleLogout = async () => {
    try {
      const csrfResponse = await fetch("/api/csrf-token");
      const { token } = await csrfResponse.json();

      await fetch("/api/logout", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": token,
        },
      });
      window.location.href = "/";
    } catch (error) {
      console.error("Logout failed:", error);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  };

  const handlePostClick = (slug: string) => {
    window.location.href = `/blog/${slug}`;
  };

  const handlePageChange = (page: number) => {
    window.location.href = `/blog?page=${page}`;
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-white to-gray-50">
      <Navbar
        isAuthenticated={isAuthenticated}
        onLogout={handleLogout}
        onLogin={() => (window.location.href = "/login")}
        onHome={() => (window.location.href = "/")}
        onDashboard={() => (window.location.href = "/dashboard")}
        onEnterprise={() => (window.location.href = "/enterprise")}
        onPricing={() => (window.location.href = "/subscribe")}
        onBlog={() => (window.location.href = "/blog")}
      />

      <div className="max-w-7xl mx-auto px-6" style={{ paddingTop: '80px', paddingBottom: '80px' }}>
        {/* Header */}
        <div className="text-center mb-16">
          <h1 className="text-5xl font-bold text-gray-900 mb-4">AppliFlow Blog</h1>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Insights on job applications, career advice, and the latest in recruitment automation
          </p>
        </div>

        {/* Blog Posts Grid */}
        {posts && posts.length > 0 ? (
          <>
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
              {posts.map((post) => (
                <Card
                  key={post.id}
                  className="group cursor-pointer hover:shadow-xl transition-all duration-300 overflow-hidden"
                  onClick={() => handlePostClick(post.slug)}
                >
                  {post.feature_image && (
                    <div className="relative h-48 overflow-hidden">
                      <img
                        src={post.feature_image}
                        alt={post.title}
                        className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                      />
                    </div>
                  )}
                  <CardHeader>
                    <div className="flex items-center gap-2 text-sm text-gray-500 mb-2">
                      <Calendar className="w-4 h-4" />
                      <span>{formatDate(post.published_at)}</span>
                      {post.reading_time && (
                        <>
                          <span>•</span>
                          <Clock className="w-4 h-4" />
                          <span>{post.reading_time} min read</span>
                        </>
                      )}
                    </div>
                    <CardTitle className="text-2xl group-hover:text-blue-600 transition-colors">
                      {post.title}
                    </CardTitle>
                    <CardDescription className="text-base line-clamp-3">
                      {post.excerpt}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {post.tags && post.tags.length > 0 && (
                      <div className="flex flex-wrap gap-2 mb-4">
                        {post.tags.slice(0, 3).map((tag) => (
                          <Badge key={tag.slug} variant="secondary">
                            {tag.name}
                          </Badge>
                        ))}
                      </div>
                    )}
                    <div className="flex items-center text-blue-600 font-medium group-hover:gap-3 transition-all">
                      <span>Read more</span>
                      <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Pagination */}
            {meta?.pagination && meta.pagination.pages > 1 && (
              <div className="flex justify-center items-center gap-4">
                <Button
                  onClick={() => handlePageChange(meta.pagination.page - 1)}
                  disabled={!meta.pagination.prev}
                  variant="outline"
                >
                  Previous
                </Button>
                <span className="text-gray-600">
                  Page {meta.pagination.page} of {meta.pagination.pages}
                </span>
                <Button
                  onClick={() => handlePageChange(meta.pagination.page + 1)}
                  disabled={!meta.pagination.next}
                  variant="outline"
                >
                  Next
                </Button>
              </div>
            )}
          </>
        ) : (
          <div className="text-center py-16">
            <p className="text-xl text-gray-600">No blog posts available yet. Check back soon!</p>
          </div>
        )}
      </div>

      <Footer onContactUs={() => {}} isPrelaunch={false} />
    </div>
  );
}
