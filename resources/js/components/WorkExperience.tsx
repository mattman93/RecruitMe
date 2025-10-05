import { Calendar } from 'lucide-react';
import React from 'react';

interface WorkExperienceItem {
    id: number;
    job_title: string;
    company: string;
    location?: string;
    date_range: string;
    description?: string;
    achievements?: string[];
    is_current: boolean;
}

interface WorkExperienceProps {
    workExperience: WorkExperienceItem[];
}

export default function WorkExperience({ workExperience }: WorkExperienceProps) {
    if (!workExperience || workExperience.length === 0) {
        return (
            <div className="mt-8">
                <h2 className="text-xl font-semibold text-gray-900 mb-6">Work History</h2>
                <div className="text-center py-8 text-gray-500">
                    <p className="text-sm">No work experience found.</p>
                    <p className="text-sm mt-2">Upload your resume to automatically extract your work history.</p>
                </div>
            </div>
        );
    }

    const formatDescription = (description?: string, achievements?: string[]) => {
        if (achievements && achievements.length > 0) {
            return (
                <ul className="list-disc list-inside space-y-1 text-xs text-gray-900">
                    {achievements.map((achievement, index) => (
                        <li key={index}>{achievement}</li>
                    ))}
                </ul>
            );
        }

        if (description) {
            return <p className="text-xs text-gray-900 leading-relaxed">{description}</p>;
        }

        return null;
    };

    return (
        <div className="mt-8">
            {/* <h2 className="text-xl font-semibold text-gray-900 mb-6">Work Experience</h2> */}
             <h2 className="font-semibold text-foreground mb-4">Work History</h2>
            <div className="space-y-4">
                 {workExperience.map((job, index) => (
                    <div key={index} className="pl-4 pb-4" style={{ borderLeft: '2px solid #d1d5db' }}>
                      <h4 className="font-semibold text-foreground text-sm mb-1">{job.job_title}</h4>
                      <p className="text-sm text-muted-foreground mb-2">{job.company}</p>
                      <div className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Calendar size={12} />
                        <span>{job.date_range}</span>
                      </div>
                    </div>
                  ))}
            </div>
        </div>
    );
}