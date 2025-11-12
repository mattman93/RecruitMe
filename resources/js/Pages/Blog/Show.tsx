import { useState, useEffect } from "react";
import { Navbar } from "../../components/Navbar";
import { Footer } from "../../components/Footer";
import { Badge } from "../../components/ui/badge";
import { Button } from "../../components/ui/button";
import { Calendar, Clock, ArrowLeft, User } from "lucide-react";

interface Author {
  name: string;
  profile_image?: string;
  bio?: string;
}

interface Tag {
  name: string;
  slug: string;
}

interface Post {
  id: string;
  slug: string;
  title: string;
  html: string;
  feature_image?: string;
  published_at: string;
  updated_at?: string;
  reading_time?: number;
  authors?: Author[];
  tags?: Tag[];
  excerpt?: string;
}

interface BlogShowProps {
  post: Post;
}

export default function BlogShow({ post }: BlogShowProps) {
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

      <div className="max-w-4xl mx-auto px-6 py-12">
        {/* Back Button */}
        <Button
          variant="ghost"
          className="mb-8"
          onClick={() => (window.location.href = "/blog")}
        >
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Blog
        </Button>

        {/* Feature Image */}
        {post.feature_image && (
          <div className="relative w-full h-96 mb-8 rounded-lg overflow-hidden">
            <img
              src={post.feature_image}
              alt={post.title}
              className="w-full h-full object-cover"
            />
          </div>
        )}

        {/* Post Header */}
        <article className="prose prose-lg max-w-none">
          <div className="mb-8">
            {/* Tags */}
            {post.tags && post.tags.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-4">
                {post.tags.map((tag) => (
                  <Badge key={tag.slug} variant="secondary">
                    {tag.name}
                  </Badge>
                ))}
              </div>
            )}

            {/* Title */}
            <h1 className="text-5xl font-bold text-gray-900 mb-4">{post.title}</h1>

            {/* Excerpt */}
            {post.excerpt && (
              <p className="text-xl text-gray-600 mb-6">{post.excerpt}</p>
            )}

            {/* Meta Info */}
            <div className="flex flex-wrap items-center gap-4 text-gray-600 border-b border-gray-200 pb-6">
              <div className="flex items-center gap-2">
                <Calendar className="w-4 h-4" />
                <span>{formatDate(post.published_at)}</span>
              </div>
              {post.reading_time && (
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4" />
                  <span>{post.reading_time} min read</span>
                </div>
              )}
              {post.authors && post.authors.length > 0 && (
                <div className="flex items-center gap-2">
                  <User className="w-4 h-4" />
                  <span>By {post.authors.map((a) => a.name).join(", ")}</span>
                </div>
              )}
            </div>
          </div>

          {/* Post Content */}
          <div
            className="blog-content"
            dangerouslySetInnerHTML={{ __html: post.html }}
          />

          {/* Author Info */}
          {post.authors && post.authors.length > 0 && (
            <div className="mt-12 pt-8 border-t border-gray-200">
              <h3 className="text-2xl font-bold mb-4">About the Author</h3>
              {post.authors.map((author, index) => (
                <div key={index} className="flex items-start gap-4 mb-6">
                  {author.profile_image && (
                    <img
                      src={author.profile_image}
                      alt={author.name}
                      className="w-16 h-16 rounded-full object-cover"
                    />
                  )}
                  <div>
                    <h4 className="text-xl font-semibold mb-1">{author.name}</h4>
                    {author.bio && <p className="text-gray-600">{author.bio}</p>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </article>
      </div>

      <Footer onContactUs={() => {}} isPrelaunch={false} />

      {/* Custom styles for blog content */}
      <style>{`
        .blog-content {
          line-height: 1.8;
        }
        .blog-content h2 {
          font-size: 2rem;
          font-weight: 700;
          margin-top: 2rem;
          margin-bottom: 1rem;
          color: #1f2937;
        }
        .blog-content h3 {
          font-size: 1.5rem;
          font-weight: 600;
          margin-top: 1.5rem;
          margin-bottom: 0.75rem;
          color: #374151;
        }
        .blog-content p {
          margin-bottom: 1.25rem;
          color: #4b5563;
        }
        .blog-content a {
          color: #2563eb;
          text-decoration: underline;
        }
        .blog-content a:hover {
          color: #1d4ed8;
        }
        .blog-content ul,
        .blog-content ol {
          margin-bottom: 1.25rem;
          padding-left: 2rem;
        }
        .blog-content li {
          margin-bottom: 0.5rem;
        }
        .blog-content img {
          border-radius: 0.5rem;
          margin: 2rem 0;
        }
        .blog-content blockquote {
          border-left: 4px solid #e5e7eb;
          padding-left: 1rem;
          font-style: italic;
          color: #6b7280;
          margin: 1.5rem 0;
        }
        .blog-content code {
          background-color: #f3f4f6;
          padding: 0.2rem 0.4rem;
          border-radius: 0.25rem;
          font-size: 0.9em;
          color: #1f2937;
        }
        .blog-content pre {
          background-color: #1f2937;
          color: #f9fafb;
          padding: 1rem;
          border-radius: 0.5rem;
          overflow-x: auto;
          margin: 1.5rem 0;
        }
        .blog-content pre code {
          background-color: transparent;
          color: inherit;
          padding: 0;
        }
      `}</style>
    </div>
  );
}
