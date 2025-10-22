import { useEffect, useState } from 'react';
import { Card } from './ui/card';
import { Button } from './ui/button';
import { CheckCircle2, Loader2 } from 'lucide-react';

interface SubscribeSuccessProps {
  onReturnToDashboard: () => void;
  onRegister?: () => void;
  isAuthenticated?: boolean;
}

export function SubscribeSuccess({ onReturnToDashboard, onRegister, isAuthenticated = false }: SubscribeSuccessProps) {
  const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
  const [customerEmail, setCustomerEmail] = useState<string>('');
  const [sessionId, setSessionId] = useState<string>('');

  useEffect(() => {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    const sid = urlParams.get('session_id');

    if (sid) {
      setSessionId(sid);
      // Store session_id in localStorage for unauthenticated users
      if (!isAuthenticated) {
        localStorage.setItem('stripe_session_id', sid);
      }

      fetch(`/api/stripe/session-status?session_id=${sid}`, {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          setStatus(data.status === 'complete' ? 'success' : 'error');
          setCustomerEmail(data.customer_email || '');
        })
        .catch(() => {
          setStatus('error');
        });
    } else {
      setStatus('error');
    }
  }, [isAuthenticated]);

  if (status === 'loading') {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Card className="p-8 max-w-md w-full text-center">
          <Loader2 className="h-16 w-16 animate-spin text-primary mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-foreground mb-2">Processing...</h2>
          <p className="text-muted-foreground">Please wait while we confirm your subscription</p>
        </Card>
      </div>
    );
  }

  if (status === 'error') {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Card className="p-8 max-w-md w-full text-center">
          <div className="text-destructive text-6xl mb-4">⚠️</div>
          <h2 className="text-2xl font-bold text-foreground mb-2">Something went wrong</h2>
          <p className="text-muted-foreground mb-6">
            We couldn't verify your subscription. Please contact support if you were charged.
          </p>
          <Button onClick={onReturnToDashboard}>Return to Dashboard</Button>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <Card className="p-8 max-w-md w-full text-center">
        <CheckCircle2 className="h-16 w-16 text-green-600 mx-auto mb-4" />
        <h2 className="text-2xl font-bold text-foreground mb-2">
          {isAuthenticated ? 'Welcome to AppliFlow Pro!' : 'Payment Successful!'}
        </h2>
        <p className="text-muted-foreground mb-4">
          {isAuthenticated
            ? 'Your subscription is now active. You have unlimited tokens to apply to as many jobs as you want!'
            : 'Create your account to start applying to unlimited jobs with your new subscription!'
          }
        </p>
        {customerEmail && (
          <p className="text-sm text-muted-foreground mb-6">
            A confirmation email has been sent to <strong>{customerEmail}</strong>
          </p>
        )}
        {isAuthenticated ? (
          <Button onClick={onReturnToDashboard} className="w-full">
            Go to Dashboard
          </Button>
        ) : (
          <Button onClick={onRegister} className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC]">
            Create Your Account
          </Button>
        )}
      </Card>
    </div>
  );
}
