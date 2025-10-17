import { useState } from "react";
import { Button } from "./ui/button";
import { ArrowRight, Mail, X } from "lucide-react";
import { BetaAccessModal } from "./BetaAccessModal";

interface ContactUsProps {
  onClose?: () => void;
  onBetaAccessSuccess?: (email: string) => void;
}

export function ContactUs({ onClose, onBetaAccessSuccess }: ContactUsProps) {
  const [email, setEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState("");
  const [showBetaModal, setShowBetaModal] = useState(false);

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
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full text-center space-y-6 p-8 relative"
             style={{
               boxShadow: '0 0 40px rgba(147, 51, 234, 0.3), 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
             }}>
          {onClose && (
            <button
              onClick={onClose}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
            >
              <X className="h-5 w-5" />
            </button>
          )}
          <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto">
            <Mail className="h-8 w-8 text-[#2D5BFF]" />
          </div>
          <h2 className="text-2xl font-bold text-[#1A1A1A]">You're On The List!</h2>
          <p className="text-[#4A4A4A] leading-relaxed">
            Thanks for your interest in AppliFlow! We'll be in touch soon with updates about our launch and your early access benefits.
          </p>
          <div className="bg-[#F0F9FF] border border-[#BAE6FD] rounded-lg p-4 text-left">
            <p className="text-sm text-[#0369A1] font-medium">What happens next?</p>
            <ul className="text-sm text-[#0369A1] mt-2 space-y-1">
              <li>• You'll receive launch updates via email</li>
              <li>• Early access invitation when we go live</li>
              <li>• Premium features unlocked for life</li>
            </ul>
          </div>
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
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full space-y-8 p-8 relative max-h-[90vh] overflow-y-auto"
           style={{
             boxShadow: '0 0 40px rgba(147, 51, 234, 0.3), 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
           }}>
        {onClose && (
          <button
            onClick={onClose}
            className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        )}
        <div className="text-center">
          <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto mb-6">
            <Mail className="h-8 w-8 text-[#2D5BFF]" />
          </div>
          <h2 className="text-3xl font-bold text-[#1A1A1A] mb-4">Join Our Early Access</h2>
          <p className="text-[#4A4A4A] leading-relaxed text-lg">
            Join our early user list to be among the first job seekers on the platform.
          </p>
          <div className="space-y-3 text-[#6B7280] text-sm pb-6" style={{ marginTop: '5%', marginLeft: '15%' }}>
            <div className="flex items-center">
              <div className="w-2 h-2 bg-[#2D5BFF] rounded-full mr-3 flex-shrink-0"></div>
              <span>Higher visibility to employers</span>
            </div>
            <div className="flex items-center">
              <div className="w-2 h-2 bg-[#2D5BFF] rounded-full mr-3 flex-shrink-0"></div>
              <span>Increased FlowRank for better job matching</span>
            </div>
            <div className="flex items-center">
              <div className="w-2 h-2 bg-[#2D5BFF] rounded-full mr-3 flex-shrink-0"></div>
              <span>Lifetime access to premium features</span>
            </div>
            <div className="flex items-center">
              <div className="w-2 h-2 bg-[#2D5BFF] rounded-full mr-3 flex-shrink-0"></div>
              <span>Early access to beta features</span>
            </div>
          </div>
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

        {/* Beta Access Link */}
        <div className="pt-6 border-t border-gray-200 text-center">
          <p className="text-sm text-gray-600 mb-2">Already have a beta access code?</p>
          <button
            onClick={() => setShowBetaModal(true)}
            className="text-[#2D5BFF] hover:text-[#1E3FCC] font-semibold text-sm transition-colors hover:underline"
          >
            Access System with Code →
          </button>
        </div>
      </div>

      {/* Beta Access Modal */}
      <BetaAccessModal
        isOpen={showBetaModal}
        onClose={() => setShowBetaModal(false)}
        onSuccess={(betaEmail) => {
          setShowBetaModal(false);
          if (onBetaAccessSuccess) {
            onBetaAccessSuccess(betaEmail);
          }
        }}
      />
    </div>
  );
}