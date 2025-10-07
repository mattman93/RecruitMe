import { useState } from "react";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { ChevronDown, ChevronUp, Calendar, MapPin, DollarSign } from "lucide-react";

type ApplicationStatus = "Applied" | "No Response" | "Delivery Error";

interface ApplicationCardProps {
  title: string;
  company: string;
  salaryRange?: string;
  location?: string;
  appliedDate: string;
  status: ApplicationStatus;
  description?: string;
  applicationDetails?: {
    platform?: string;
    applicationId?: string;
    coverLetter?: boolean;
  };
}

export function ApplicationCard({
  title,
  company,
  salaryRange,
  location,
  appliedDate,
  status,
  description,
  applicationDetails
}: ApplicationCardProps) {
  const [isExpanded, setIsExpanded] = useState(false);

  const getStatusBadgeStyle = (status: ApplicationStatus) => {
    switch (status) {
      case "Applied":
        return {
          backgroundColor: '#dcfce7',
          color: '#166534',
          borderColor: '#bbf7d0'
        };
      case "No Response":
        return {
          backgroundColor: '#fef9c3',
          color: '#854d0e',
          borderColor: '#fde047'
        };
      case "Delivery Error":
        return {
          backgroundColor: '#fee2e2',
          color: '#991b1b',
          borderColor: '#fecaca'
        };
      default:
        return {
          backgroundColor: '#f3f4f6',
          color: '#374151',
          borderColor: '#d1d5db'
        };
    }
  };

  return (
    <Card className="p-4 hover:shadow-md transition-all duration-200 border border-border gap-2">
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <h3 className="font-semibold text-foreground mb-1">{title}</h3>
          <p className="text-muted-foreground">{company}</p>
        </div>
        <Badge
          variant="outline"
          className="font-medium"
          style={getStatusBadgeStyle(status)}
        >
          {status}
        </Badge>
      </div>

      {/* Application Info */}
      <div className="flex items-center text-sm text-muted-foreground gap-4 mb-2">
        <div className="flex items-center">
          <Calendar size={14} />
          <span>Applied {appliedDate}</span>
        </div>
        {location && (
          <div className="flex items-center">
            <MapPin size={14} />
            <span>{location}</span>
          </div>
        )}
        {salaryRange && (
          <div className="flex items-center">
            <DollarSign size={14} />
            <span>{salaryRange}</span>
          </div>
        )}
      </div>

      {/* Show More/Less Button */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="flex items-center gap-1 text-sm text-primary hover:text-primary/80 transition-colors duration-200 font-medium"
      >
        {isExpanded ? (
          <>
            Show less
            <ChevronUp size={14} />
          </>
        ) : (
          <>
            Show more
            <ChevronDown size={14} />
          </>
        )}
      </button>

      {/* Expanded Content */}
      {isExpanded && (
        <div className="mt-4 pt-4 border-t border-border space-y-4">
          {description && (
            <div>
              <h4 className="font-medium text-foreground mb-2 text-sm">Job Description</h4>
              <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>
            </div>
          )}
          
          {applicationDetails && (
            <div>
              <h4 className="font-medium text-foreground mb-2 text-sm">Application Details</h4>
              <div className="space-y-2 text-sm text-muted-foreground">
                {applicationDetails.platform && (
                  <div className="flex justify-between">
                    <span>Platform:</span>
                    <span className="font-medium">{applicationDetails.platform}</span>
                  </div>
                )}
                {applicationDetails.applicationId && (
                  <div className="flex justify-between">
                    <span>Application ID:</span>
                    <span className="font-medium font-mono text-xs">{applicationDetails.applicationId}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span>Cover Letter:</span>
                  <span className="font-medium">
                    {applicationDetails.coverLetter ? "Included" : "Not included"}
                  </span>
                </div>
              </div>
            </div>
          )}

          {status === "Delivery Error" && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <h4 className="font-medium text-red-800 mb-1 text-sm">Delivery Failed</h4>
              <p className="text-sm text-red-700">
                There was an issue delivering your application. We'll automatically retry in a few hours.
              </p>
            </div>
          )}

          {status === "No Response" && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
              <h4 className="font-medium text-yellow-800 mb-1 text-sm">Follow-up Recommended</h4>
              <p className="text-sm text-yellow-700">
                It's been over a week since you applied. Consider following up or checking for updates.
              </p>
            </div>
          )}
        </div>
      )}
    </Card>
  );
}