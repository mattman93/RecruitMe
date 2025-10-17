import { useState } from 'react';
import { X, Key } from 'lucide-react';

// Add CSS animations
const styles = `
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  @keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }
`;

interface BetaAccessModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (email: string) => void;
}

export function BetaAccessModal({ isOpen, onClose, onSuccess }: BetaAccessModalProps) {
  const [token, setToken] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  if (!isOpen) return null;

  // Inject styles
  if (typeof document !== 'undefined' && !document.getElementById('beta-modal-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'beta-modal-styles';
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token: csrfToken } = await tokenResponse.json();

      const response = await fetch('/api/beta/activate', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ token: token.trim() }),
      });

      const data = await response.json();

      if (response.ok) {
        onSuccess(data.email);
        onClose();
      } else {
        setError(data.message || 'Invalid access code. Please try again.');
      }
    } catch (err) {
      setError('Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-fade-in"
      onClick={handleBackdropClick}
      style={{ animation: 'fadeIn 0.2s ease-out' }}
    >
      <div
        className="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative"
        style={{ animation: 'scaleIn 0.2s ease-out' }}
      >
        {/* Close button */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
          aria-label="Close"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full mb-4">
            <Key className="w-8 h-8 text-white" />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Enter Beta Access Code</h2>
          <p className="text-gray-600 text-sm">
            Check your email for your exclusive beta access code
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label htmlFor="token" className="block text-sm font-medium text-gray-700 mb-2">
              Access Code
            </label>
            <input
              id="token"
              type="text"
              value={token}
              onChange={(e) => setToken(e.target.value)}
              placeholder="Enter your 32-character code"
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all font-mono text-sm"
              maxLength={32}
              required
              autoFocus
            />
            {error && (
              <p className="mt-2 text-sm text-red-600">{error}</p>
            )}
          </div>

          <button
            type="submit"
            disabled={isSubmitting || token.trim().length !== 32}
            className="w-full text-white font-semibold py-3 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all relative z-10"
            style={{
              background: 'linear-gradient(to right, #4f46e5, #9333ea)',
              display: 'block',
              color: 'white'
            }}
            onMouseEnter={(e) => {
              if (!isSubmitting && token.trim().length === 32) {
                e.currentTarget.style.background = 'linear-gradient(to right, #4338ca, #7e22ce)';
              }
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = 'linear-gradient(to right, #4f46e5, #9333ea)';
            }}
          >
            {isSubmitting ? 'Activating...' : 'Activate'}
          </button>
        </form>

        {/* Help text */}
        <div className="mt-6 pt-6 border-t border-gray-200">
          <p className="text-sm text-gray-500 text-center">
            Don't have a code?{' '}
            <button
              onClick={onClose}
              className="text-indigo-600 hover:text-indigo-700 font-medium"
            >
              Join the waitlist
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}
