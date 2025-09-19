import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';

export function JobReviewAnimation() {
  const [currentJobIndex, setCurrentJobIndex] = useState(0);
  
  const jobs = [
    {
      id: 1,
      title: "Frontend Developer",
      company: "Upwork",
      salary: "$120k - $150k",
      type: "Full-time",
      color: "#2D5BFF"
    },
    {
      id: 2,
      title: "Web Engineer", 
      company: "Google",
      salary: "$130k",
      type: "Remote",
      color: "#4285F4"
    },
    {
      id: 3,
      title: "Fullstack Engineer",
      company: "Amazon",
      salary: "$150k",
      type: "San Jose",
      color: "#FF9900"
    }
  ];

  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentJobIndex((prevIndex) => (prevIndex + 1) % jobs.length);
    }, 3000);

    return () => clearInterval(interval);
  }, [jobs.length]);

  const currentJob = jobs[currentJobIndex];

  return (
    <div className="w-full max-w-md mx-auto pointer-events-none h-80"> {/* Fixed height container */}
      {/* Header */}
      <div className="text-center mb-6">
        <h3 className="text-xl font-semibold text-[#1A1A1A] mb-2">Job Matches</h3>
        <p className="text-sm text-[#7A7A7A]">Personalized recommendations for you</p>
      </div>

      {/* Job Card Container */}
      <div className="relative h-48 overflow-hidden rounded-xl mb-6">
        <AnimatePresence mode="wait">
          <motion.div
            key={currentJob.id}
            initial={{ x: 300, opacity: 0 }}
            animate={{ x: 0, opacity: 1 }}
            exit={{ x: -300, opacity: 0 }}
            transition={{ 
              duration: 0.6,
              ease: "easeInOut"
            }}
            className="absolute inset-0 bg-white border border-[#E6E9ED] rounded-xl p-6 shadow-sm"
          >
            <div className="flex items-start justify-between h-full">
              <div className="flex-1">
                <div className="flex items-center gap-3 mb-6">
                  <div 
                    className="w-10 h-10 rounded-lg flex items-center justify-center"
                    style={{ backgroundColor: currentJob.color }}
                  >
                    <span className="text-white font-bold text-sm">
                      {currentJob.company.charAt(0)}
                    </span>
                  </div>
                  <div>
                    <h4 className="font-semibold text-[#1A1A1A] mb-1">
                      {currentJob.title}
                    </h4>
                    <p className="text-[#7A7A7A] text-sm">
                      {currentJob.company}
                    </p>
                  </div>
                </div>
                
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-[#7A7A7A]">Salary</span>
                    <span className="text-sm font-medium text-[#1A1A1A]">
                      {currentJob.salary}
                    </span>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-[#7A7A7A]">Type</span>
                    <span className="text-sm font-medium text-[#1A1A1A]">
                      {currentJob.type}
                    </span>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-[#7A7A7A]">Match</span>
                    <span className="text-sm font-medium text-[#28A745]">
                      {97 - (currentJobIndex * 3)}% match
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Job Counter */}
      <div className="flex items-center justify-center gap-2 mb-4">
        {jobs.map((_, index) => (
          <motion.div
            key={index}
            className={`w-2 h-2 rounded-full transition-colors duration-300 ${
              index === currentJobIndex ? 'bg-[#2D5BFF]' : 'bg-[#E6E9ED]'
            }`}
            animate={{
              scale: index === currentJobIndex ? 1.2 : 1
            }}
            transition={{ duration: 0.3 }}
          />
        ))}
      </div>

      {/* Progress Text */}
      <div className="text-center">
        <p className="text-sm text-[#7A7A7A]">
          Showing {currentJobIndex + 1} of {jobs.length} matches
        </p>
      </div>
    </div>
  );
}