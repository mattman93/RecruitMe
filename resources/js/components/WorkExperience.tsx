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
                <h2 className="text-2xl font-semibold text-gray-900 mb-6">Work Experience</h2>
                <div className="text-center py-8 text-gray-500">
                    <p className="text-lg">No work experience found.</p>
                    <p className="text-sm mt-2">Upload your resume to automatically extract your work history.</p>
                </div>
            </div>
        );
    }

    const formatDescription = (description?: string, achievements?: string[]) => {
        if (achievements && achievements.length > 0) {
            return (
                <ul className="list-disc list-inside space-y-1 text-sm text-gray-600">
                    {achievements.map((achievement, index) => (
                        <li key={index}>{achievement}</li>
                    ))}
                </ul>
            );
        }

        if (description) {
            return <p className="text-sm text-gray-600 leading-relaxed">{description}</p>;
        }

        return null;
    };

    return (
        <div className="mt-8">
            <h2 className="text-2xl font-semibold text-gray-900 mb-6">Work Experience</h2>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {workExperience.map((work) => (
                    <div
                        key={work.id}
                        className="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow duration-200"
                    >
                        {/* Job Title */}
                        <h3 className="text-lg font-bold text-gray-900 mb-1">
                            {work.job_title}
                        </h3>

                        {/* Company and Location */}
                        <div className="mb-2">
                            <p className="text-md font-medium text-gray-800">{work.company}</p>
                            {work.location && (
                                <p className="text-sm text-gray-500">{work.location}</p>
                            )}
                        </div>

                        {/* Date Range */}
                        <div className="flex items-center mb-4">
                            <p className="text-sm text-gray-600">{work.date_range}</p>
                            {work.is_current && (
                                <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    Current
                                </span>
                            )}
                        </div>

                        {/* Horizontal Line */}
                        <hr className="border-gray-200 mb-4" />

                        {/* Description/Achievements */}
                        <div>
                            {formatDescription(work.description, work.achievements)}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}