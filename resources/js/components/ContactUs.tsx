import { useState } from "react";
import { Button } from "./ui/button";
import { ArrowRight, Mail, User } from "lucide-react";

interface ContactUsProps {
  onClose?: () => void;
}

export function ContactUs({ onClose }: ContactUsProps) {
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError("");

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/mailing-list/subscribe', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({
          email,
          name: name || null,
        }),
      });

      if (response.ok) {
        setIsSubmitted(true);
      } else {
        const data = await response.json();
        setError(data.message || 'Something went wrong. Please try again.');
      }
    } catch (error) {
      console.error('Subscription failed:', error);
      setError('Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isSubmitted) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center p-8">
        <div className="max-w-md w-full text-center space-y-6">
          <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto">
            <Mail className="h-8 w-8 text-[#2D5BFF]" />
          </div>
          <h2 className="text-2xl font-bold text-[#1A1A1A]">You're On The List!</h2>
          <p className="text-[#4A4A4A]">
            Thanks for your interest in AppliFlow. We'll be in touch soon with updates about our launch and your early access benefits.
          </p>
          {onClose && (
            <Button onClick={onClose} className="bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white">
              Continue
            </Button>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white flex items-center justify-center p-8">
      <div className="max-w-md w-full space-y-8">
        <div className="text-center">
          <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto mb-6">
            <Mail className="h-8 w-8 text-[#2D5BFF]" />
          </div>
          <h2 className="text-3xl font-bold text-[#1A1A1A] mb-4">Join Our Early Access</h2>
          <p className="text-[#4A4A4A] leading-relaxed">
            Join our early user list to be among the first job seekers on the platform.
            This will give you higher visibility to employers, higher FlowRank, lifetime access to paid features,
            and early access to beta features.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-[#1A1A1A] mb-2">
              Email Address *
            </label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-[#7A7A7A]" />
              <input
                type="email"
                id="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full pl-10 pr-4 py-3 border border-[#E6E9ED] rounded-lg focus:ring-2 focus:ring-[#2D5BFF] focus:border-transparent transition-colors"
                placeholder="Enter your email"
              />
            </div>
          </div>

          <div>
            <label htmlFor="name" className="block text-sm font-medium text-[#1A1A1A] mb-2">
              Name (Optional)
            </label>
            <div className="relative">
              <User className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-[#7A7A7A]" />
              <input
                type="text"
                id="name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full pl-10 pr-4 py-3 border border-[#E6E9ED] rounded-lg focus:ring-2 focus:ring-[#2D5BFF] focus:border-transparent transition-colors"
                placeholder="Enter your name"
              />
            </div>
          </div>

          {error && (
            <div className="p-3 bg-[#FEF2F2] border border-[#FECACA] rounded-lg">
              <p className="text-sm text-[#DC2626]">{error}</p>
            </div>
          )}

          <Button
            type="submit"
            disabled={isSubmitting || !email}
            className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white py-3 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2"></div>
                Joining...
              </>
            ) : (
              <>
                Join Early Access
                <ArrowRight className="ml-2 h-4 w-4" />
              </>
            )}
          </Button>
        </form>

        {onClose && (
          <div className="text-center">
            <button
              onClick={onClose}
              className="text-sm text-[#7A7A7A] hover:text-[#4A4A4A] transition-colors"
            >
              ← Back
            </button>
          </div>
        )}
      </div>
    </div>
  );
}