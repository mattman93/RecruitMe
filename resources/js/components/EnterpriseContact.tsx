import { useState } from "react";
import { Button } from "./ui/button";
import { ArrowRight, Mail, User, Building2, MessageSquare, X } from "lucide-react";

interface EnterpriseContactProps {
  onClose?: () => void;
  pricingTier?: string;
}

export function EnterpriseContact({ onClose, pricingTier }: EnterpriseContactProps) {
  const [email, setEmail] = useState("");
  const [fullName, setFullName] = useState("");
  const [organization, setOrganization] = useState("");
  const [additionalDetails, setAdditionalDetails] = useState("");
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
          name: fullName,
          organization,
          additional_data: JSON.stringify({
            additionalDetails,
            pricingTier,
            formType: 'enterprise'
          }),
        }),
      });

      if (response.ok) {
        setIsSubmitted(true);
      } else {
        const data = await response.json();
        setError(data.message || 'Something went wrong. Please try again.');
      }
    } catch (error) {
      console.error('Enterprise contact failed:', error);
      setError('Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isSubmitted) {
    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div className="bg-white rounded-xl max-w-md w-full p-8 text-center space-y-6 relative shadow-2xl" style={{ boxShadow: '0 0 40px rgba(45, 91, 255, 0.3), 0 0 80px rgba(45, 91, 255, 0.1)' }}>
          {onClose && (
            <button
              onClick={onClose}
              className="absolute top-4 right-4 text-[#7A7A7A] hover:text-[#4A4A4A]"
            >
              <X className="h-5 w-5" />
            </button>
          )}
          <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto">
            <Building2 className="h-8 w-8 text-[#2D5BFF]" />
          </div>
          <h2 className="text-2xl font-bold text-[#1A1A1A]">Thank You!</h2>
          <p className="text-[#4A4A4A]">
            We've received your enterprise inquiry. Our team will be in touch within 24 hours to discuss how AppliFlow can transform your hiring process.
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
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto relative shadow-2xl" style={{ boxShadow: '0 0 40px rgba(45, 91, 255, 0.3), 0 0 80px rgba(45, 91, 255, 0.1)' }}>
        {onClose && (
          <button
            onClick={onClose}
            className="absolute top-4 right-4 text-[#7A7A7A] hover:text-[#4A4A4A] z-10"
          >
            <X className="h-5 w-5" />
          </button>
        )}

        <div className="p-8 space-y-8">
          <div className="text-center">
            <div className="w-16 h-16 bg-[#F5F8FF] rounded-full flex items-center justify-center mx-auto mb-6">
              <Building2 className="h-8 w-8 text-[#2D5BFF]" />
            </div>
            <h2 className="text-3xl font-bold text-[#1A1A1A] mb-4">Ready to Transform Your Hiring?</h2>
            <p className="text-[#4A4A4A] leading-relaxed">
              Automate your hiring process from end-to-end. Ready to launch your business into the future of hiring? Drop us a line.
            </p>
            {pricingTier && (
              <div className="mt-4 inline-block px-3 py-1 bg-[#F5F8FF] text-[#2D5BFF] text-sm rounded-full">
                Interested in: {pricingTier} Plan
              </div>
            )}
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
                  placeholder="Enter your work email"
                />
              </div>
            </div>

            <div>
              <label htmlFor="fullName" className="block text-sm font-medium text-[#1A1A1A] mb-2">
                Full Name *
              </label>
              <div className="relative">
                <User className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-[#7A7A7A]" />
                <input
                  type="text"
                  id="fullName"
                  required
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full pl-10 pr-4 py-3 border border-[#E6E9ED] rounded-lg focus:ring-2 focus:ring-[#2D5BFF] focus:border-transparent transition-colors"
                  placeholder="Enter your full name"
                />
              </div>
            </div>

            <div>
              <label htmlFor="organization" className="block text-sm font-medium text-[#1A1A1A] mb-2">
                Organization *
              </label>
              <div className="relative">
                <Building2 className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-[#7A7A7A]" />
                <input
                  type="text"
                  id="organization"
                  required
                  value={organization}
                  onChange={(e) => setOrganization(e.target.value)}
                  className="w-full pl-10 pr-4 py-3 border border-[#E6E9ED] rounded-lg focus:ring-2 focus:ring-[#2D5BFF] focus:border-transparent transition-colors"
                  placeholder="Enter your company name"
                />
              </div>
            </div>

            <div>
              <label htmlFor="additionalDetails" className="block text-sm font-medium text-[#1A1A1A] mb-2">
                Additional Details
              </label>
              <div className="relative">
                <MessageSquare className="absolute left-3 top-3 h-5 w-5 text-[#7A7A7A]" />
                <textarea
                  id="additionalDetails"
                  value={additionalDetails}
                  onChange={(e) => setAdditionalDetails(e.target.value)}
                  rows={4}
                  className="w-full pl-10 pr-4 py-3 border border-[#E6E9ED] rounded-lg focus:ring-2 focus:ring-[#2D5BFF] focus:border-transparent transition-colors resize-none"
                  placeholder="Tell us about your hiring challenges, team size, or specific needs..."
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
              disabled={isSubmitting || !email || !fullName || !organization}
              className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white py-3 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2"></div>
                  Submitting...
                </>
              ) : (
                <>
                  Schedule Demo
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
    </div>
  );
}