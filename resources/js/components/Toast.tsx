import { useEffect } from 'react';
import { X, CheckCircle2, XCircle, AlertCircle } from 'lucide-react';

export type ToastVariant = 'success' | 'error' | 'info';

export interface ToastProps {
  id: string;
  message: string;
  variant: ToastVariant;
  duration?: number;
  onClose: (id: string) => void;
}

const variantStyles = {
  success: {
    bgColor: '#dcfce7',
    borderColor: '#86efac',
    textColor: '#14532d',
    iconColor: '#15803d',
    icon: <CheckCircle2 className="h-5 w-5" />,
  },
  error: {
    bgColor: '#fee2e2',
    borderColor: '#fca5a5',
    textColor: '#7f1d1d',
    iconColor: '#b91c1c',
    icon: <XCircle className="h-5 w-5" />,
  },
  info: {
    bgColor: '#f3e8ff',
    borderColor: '#d8b4fe',
    textColor: '#581c87',
    iconColor: '#7e22ce',
    icon: <AlertCircle className="h-5 w-5" />,
  },
};

export function Toast({ id, message, variant, duration = 5000, onClose }: ToastProps) {
  const styles = variantStyles[variant];

  useEffect(() => {
    const timer = setTimeout(() => {
      onClose(id);
    }, duration);

    return () => clearTimeout(timer);
  }, [id, duration, onClose]);

  return (
    <div
      className="flex items-start gap-3 p-4 rounded-lg border shadow-lg animate-in slide-in-from-right-full duration-300"
      style={{
        minWidth: '320px',
        maxWidth: '500px',
        backgroundColor: styles.bgColor,
        borderColor: styles.borderColor,
        color: styles.textColor,
        opacity: 1,
        zIndex: 9999,
        position: 'relative'
      }}
    >
      <span style={{ color: styles.iconColor }}>{styles.icon}</span>
      <p className="flex-1 text-sm font-medium" style={{ color: styles.textColor }}>{message}</p>
      <button
        onClick={() => onClose(id)}
        className="flex-shrink-0 hover:opacity-70 transition-opacity"
        style={{ color: styles.textColor }}
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}

export function ToastContainer({ toasts, onClose }: { toasts: ToastProps[]; onClose: (id: string) => void }) {
  return (
    <div className="fixed top-4 right-4 flex flex-col gap-2" style={{ zIndex: 10000 }}>
      {toasts.map((toast) => (
        <Toast key={toast.id} {...toast} onClose={onClose} />
      ))}
    </div>
  );
}
