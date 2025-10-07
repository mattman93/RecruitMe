import { X, MapPin, DollarSign, Briefcase, ExternalLink } from 'lucide-react';
import { Button } from './ui/button';

interface ViewJobDescriptionProps {
  isOpen: boolean;
  onClose: () => void;
  jobTitle: string;
  company: string;
  salary?: string;
  location?: string;
  description: string;
  jobUrl?: string;
}

export function ViewJobDescription({
  isOpen,
  onClose,
  jobTitle,
  company,
  salary,
  location,
  description,
  jobUrl
}: ViewJobDescriptionProps) {
  if (!isOpen) return null;

  // Decode HTML entities (like &nbsp;)
  // Note: Most descriptions in DB are plain text without HTML tags
  const decodeHtmlEntities = (text: string): string => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return doc.body.innerHTML;
  };

  // Format plain text with automatic paragraph breaks
  const formatPlainText = (text: string): string => {
    let formatted = decodeHtmlEntities(text);

    // Temporarily replace common abbreviations with placeholders
    formatted = formatted.replace(/e\.g\./gi, 'EG_PLACEHOLDER');
    formatted = formatted.replace(/i\.e\./gi, 'IE_PLACEHOLDER');
    formatted = formatted.replace(/\beg\./gi, 'EG2_PLACEHOLDER');
    formatted = formatted.replace(/\bie\./gi, 'IE2_PLACEHOLDER');

    // Split on common section headers (ALL CAPS followed by colon)
    formatted = formatted.replace(/([A-Z][A-Z\s]{3,}:)/g, '<h4 class="font-semibold text-foreground mt-4 mb-2">$1</h4>');

    // Split on patterns like "RESPONSIBILITIES:" or "QUALIFICATIONS:"
    formatted = formatted.replace(/\n([A-Z][A-Z\s]{3,}:)/g, '<h4 class="font-semibold text-foreground mt-4 mb-2">$1</h4>');

    // Handle ALL CAPS section headers without colons (e.g., "NICE TO HAVE", "REQUIRED SKILLS")
    // Matches multiple ALL CAPS words followed immediately by a capital letter starting a new word
    formatted = formatted.replace(/([A-Z]{2,}(?:\s+[A-Z]{2,})+)([A-Z][a-z])/g, '<h4 class="font-semibold text-foreground mt-4 mb-2">$1</h4>$2');

    // Add line break after every period followed by any character (letter or number)
    formatted = formatted.replace(/\.([a-zA-Z0-9])/g, '.<br><br>$1');

    // Restore abbreviations
    formatted = formatted.replace(/EG_PLACEHOLDER/g, 'e.g.');
    formatted = formatted.replace(/IE_PLACEHOLDER/g, 'i.e.');
    formatted = formatted.replace(/EG2_PLACEHOLDER/g, 'eg.');
    formatted = formatted.replace(/IE2_PLACEHOLDER/g, 'ie.');

    return formatted;
  };

  const formattedDescription = formatPlainText(description);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-start justify-between p-6 border-b border-border">
          <div className="flex-1 pr-4">
            <h2 className="text-2xl font-semibold text-foreground mb-2">{jobTitle}</h2>
            <p className="text-lg text-muted-foreground">{company}</p>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground transition-colors"
          >
            <X size={24} />
          </button>
        </div>

        {/* Job Details */}
        <div className="px-6 py-4 border-b border-border bg-muted/20">
          <div className="flex flex-wrap gap-4">
            {salary && (
              <div className="flex items-center gap-2">
                <DollarSign size={18} className="text-primary" />
                <span className="text-sm font-medium">{salary}</span>
              </div>
            )}
            {location && (
              <div className="flex items-center gap-2">
                <MapPin size={18} className="text-blue-600" />
                <span className="text-sm">{location}</span>
              </div>
            )}
            {jobUrl && (
              <a
                href={jobUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 text-primary hover:text-primary/80 transition-colors"
              >
                <ExternalLink size={18} />
                <span className="text-sm font-medium">View on company site</span>
              </a>
            )}
          </div>
        </div>

        {/* Description */}
        <div className="overflow-y-auto p-6" style={{ maxHeight: '500px' }}>
          <h3 className="text-lg font-semibold text-foreground mb-4 sticky top-0 bg-white pb-2 -mt-6 pt-6">Job Description</h3>
          <div
            className="prose prose-sm max-w-none text-muted-foreground leading-relaxed"
            style={{ whiteSpace: 'pre-wrap' }}
            dangerouslySetInnerHTML={{ __html: formattedDescription }}
          />
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-border flex justify-end gap-3">
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </div>
  );
}
