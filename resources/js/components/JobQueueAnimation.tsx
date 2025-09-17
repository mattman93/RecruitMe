import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import { useScrollAnimation } from './hooks/useScrollAnimation';

export function JobQueueAnimation() {
  const { ref, isVisible } = useScrollAnimation();
  const [visibleJobs, setVisibleJobs] = useState<number[]>([]);
  const [currentJobIndex, setCurrentJobIndex] = useState(0);
  const [animationComplete, setAnimationComplete] = useState(false);
  
  const jobs = [
    {
      id: 2,
      title: "Web Engineer", 
      company: "Google",
      salary: "$130k",
      type: "Connet",
      color: "#4285F4"
    },
    {
      id: 3,
      title: "Fullstack Engineer",
      company: "Amazon",
      salary: "$150k",
      type: "San Jose",
      color: "#FF9900"
    },
    {
      id: 4,
      title: "Frontend Engineer",
      company: "Facebook",
      salary: "$160k", 
      type: "Redmond, WA",
      color: "#1877F2"
    }
  ];

  useEffect(() => {
    if (animationComplete || !isVisible) return;

    const interval = setInterval(() => {
      if (currentJobIndex < jobs.length) {
        setVisibleJobs(prev => [...prev, jobs[currentJobIndex].id]);
        setCurrentJobIndex(prev => prev + 1);
      } else {
        // Animation complete - stop the interval
        setAnimationComplete(true);
        clearInterval(interval);
      }
    }, 1200);

    return () => clearInterval(interval);
  }, [currentJobIndex, jobs.length, animationComplete, isVisible]);

  return (
    <div ref={ref} className="bg-white rounded-2xl shadow-lg border border-[#E6E9ED] p-6 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-[#2D5BFF] rounded-lg flex items-center justify-center">
            <span className="text-white font-bold text-sm">AF</span>
          </div>
          <span className="text-xl font-semibold text-[#1A1A1A]">appliFlow</span>
        </div>
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-[#2D5BFF] rounded-full flex items-center justify-center">
            <span className="text-white text-xs font-medium">JD</span>
          </div>
          <span className="text-sm text-[#4A4A4A]">John Doe</span>
        </div>
      </div>

      <div className="grid lg:grid-cols-2 gap-8">
        {/* Left side - Resume */}
        <div>
          <h3 className="text-xl font-semibold text-[#1A1A1A] mb-4">Resume</h3>
          
          {/* Resume mockup */}
          <div className="bg-[#F7F8FA] rounded-xl p-4 mb-6">
            <div className="w-24 h-32 bg-white border-2 border-[#E6E9ED] rounded-lg p-2 mb-4">
              <div className="space-y-1">
                <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                <div className="h-1 bg-[#E6E9ED] rounded w-3/4"></div>
                <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                <div className="h-1 bg-[#E6E9ED] rounded w-2/3"></div>
                <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                <div className="h-1 bg-[#E6E9ED] rounded w-1/2"></div>
              </div>
            </div>
            
            <div className="flex items-center gap-4 text-sm text-[#7A7A7A]">
              <div className="flex items-center gap-1">
                <div className="w-4 h-4 bg-[#E6E9ED] rounded"></div>
                <span>{visibleJobs.length} Match</span>
              </div>
              <div className="flex items-center gap-1">
                <div className="w-4 h-4 bg-[#E6E9ED] rounded"></div>
                <span>10 Applicants</span>
              </div>
            </div>
          </div>

          {/* Work Experience */}
          <div>
            <h4 className="font-semibold text-[#1A1A1A] mb-4">Work Experience</h4>
            <div className="space-y-4">
              <div>
                <h5 className="font-medium text-[#1A1A1A]">Frontend Developer</h5>
                <p className="text-sm text-[#7A7A7A]">Symbl</p>
                <p className="text-sm text-[#7A7A7A]">Jun 2020 - Present</p>
                <p className="text-sm text-[#7A7A7A]">San Francisco, CA</p>
              </div>
              <div>
                <h5 className="font-medium text-[#1A1A1A]">Web Developer</h5>
                <p className="text-sm text-[#7A7A7A]">Acme</p>
                <p className="text-sm text-[#7A7A7A]">May 2018 - May 2020</p>
                <p className="text-sm text-[#7A7A7A]">San Francisco, CA</p>
              </div>
            </div>
          </div>
        </div>

        {/* Right side - Job Queue */}
        <div>
          <h3 className="text-xl font-semibold text-[#1A1A1A] mb-6">Job Queue</h3>
          
          <div className="space-y-4 min-h-[400px]">
            <AnimatePresence>
              {visibleJobs.map((jobId, index) => {
                const job = jobs.find(j => j.id === jobId);
                if (!job) return null;
                
                return (
                  <motion.div
                    key={job.id}
                    initial={{ opacity: 0, y: 20, scale: 0.95 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: -20 }}
                    transition={{ 
                      duration: 0.5,
                      ease: "easeOut"
                    }}
                    className="bg-white border border-[#E6E9ED] rounded-xl p-4 shadow-sm pointer-events-none"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h4 className="font-semibold text-[#1A1A1A] mb-1">{job.title}</h4>
                        <p className="text-[#7A7A7A] text-sm mb-2">{job.company}</p>
                        <p className="text-[#1A1A1A] font-medium text-sm">{job.salary}</p>
                      </div>
                      <div className="text-right">
                        <span className="text-sm text-[#7A7A7A]">{job.type}</span>
                      </div>
                    </div>
                  </motion.div>
                );
              })}
            </AnimatePresence>
            
            {visibleJobs.length === jobs.length && (
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.5 }}
                className="pt-4"
              >
                <div className="w-full bg-[#2D5BFF] text-white py-3 px-6 rounded-xl font-medium text-center pointer-events-none cursor-default">
                  Start Applying to {visibleJobs.length} Jobs
                </div>
              </motion.div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}