import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import { useScrollAnimation } from './hooks/useScrollAnimation';
import { Zap, Send } from 'lucide-react';

export function ProFeatureShowcase() {
  const { ref, isVisible } = useScrollAnimation(0.3);
  const [autoApplyEnabled, setAutoApplyEnabled] = useState(false);
  const [sentResumes, setSentResumes] = useState<number[]>([]);

  const companies = [
    { id: 1, name: "Google", color: "#4285F4", delay: 0 },
    { id: 2, name: "Amazon", color: "#FF9900", delay: 0.8 },
    { id: 3, name: "Microsoft", color: "#00A4EF", delay: 1.6 },
    { id: 4, name: "Meta", color: "#0668E1", delay: 2.4 },
    { id: 5, name: "Apple", color: "#000000", delay: 3.2 },
  ];

  useEffect(() => {
    if (!isVisible) return;

    const runAnimation = async () => {
      // Reset
      setAutoApplyEnabled(false);
      setSentResumes([]);

      // Wait longer with slider off before starting
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Toggle on auto-apply
      setAutoApplyEnabled(true);

      // Wait for toggle animation
      await new Promise(resolve => setTimeout(resolve, 800));

      // Send resumes one by one
      for (let i = 0; i < companies.length; i++) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        setSentResumes(prev => [...prev, companies[i].id]);
      }

      // Keep final state visible longer
      await new Promise(resolve => setTimeout(resolve, 4000));

      // Loop animation
      runAnimation();
    };

    runAnimation();
  }, [isVisible]);

  return (
    <section className={`w-full bg-white py-20 fade-in ${isVisible ? 'visible' : ''}`} ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        {/* Header */}
        <div className="text-center mb-12">
          <div
            className="inline-flex items-center gap-2 rounded-full px-4 py-2 mb-4"
            style={{
              background: 'linear-gradient(to right, #8B5CF6, #3B82F6)'
            }}
          >
            <Zap className="h-4 w-4 text-white" />
            <span className="text-sm font-bold text-white">AppliFlow Pro</span>
          </div>
          <h2 className="text-[#1A1A1A] mb-4">
            Set it and forget it
          </h2>
          <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
            Turn on Auto-Apply and let AppliFlow Pro work 24/7 to apply to the best jobs for you
          </p>
        </div>

        {/* Animation Card */}
        <div className="bg-white rounded-2xl shadow-xl border border-[#E6E9ED] p-8 max-w-4xl mx-auto">
          {/* Headers */}
          <div className="grid lg:grid-cols-2 gap-12 mb-6">
            <h3 className="text-xl font-semibold text-[#1A1A1A]">Auto-Apply Settings</h3>
            <h3 className="text-xl font-semibold text-[#1A1A1A] text-right">Applications in Progress</h3>
          </div>

          <div className="grid lg:grid-cols-2 gap-12 items-center">
            {/* Left side - Auto-Apply Toggle */}
            <div>

              {/* Toggle Card */}
              <div className="bg-[#F7F8FA] rounded-xl p-6 border border-[#E6E9ED]">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h4 className="font-semibold text-[#1A1A1A] mb-1">Auto-Apply</h4>
                    <p className="text-sm text-[#7A7A7A]">Automatically apply to matching jobs</p>
                  </div>

                  {/* Toggle Switch */}
                  <motion.div
                    className={`relative w-14 h-8 rounded-full transition-colors duration-300 flex items-center pointer-events-none ${
                      autoApplyEnabled ? 'bg-indigo-600' : 'bg-gray-300'
                    }`}
                    animate={{
                      backgroundColor: autoApplyEnabled ? '#4F46E5' : '#D1D5DB'
                    }}
                  >
                    <motion.div
                      className="absolute w-6 h-6 bg-white rounded-full shadow-lg"
                      style={{
                        left: 4
                      }}
                      animate={{
                        x: autoApplyEnabled ? 28 : 0
                      }}
                      transition={{
                        type: "spring",
                        stiffness: 700,
                        damping: 30
                      }}
                    />
                  </motion.div>
                </div>

                {/* Status */}
                <motion.div
                  animate={{
                    opacity: autoApplyEnabled ? 1 : 0
                  }}
                  transition={{ duration: 0.3 }}
                  className="h-[72px]"
                >
                  <div className="pt-4 border-t border-[#E6E9ED]">
                    <div className="flex items-center gap-2 text-sm">
                      <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                      <span className="text-[#1A1A1A] font-medium">Active</span>
                    </div>
                    <p className="text-xs text-[#7A7A7A] mt-2">
                      AppliFlow is now applying to jobs on your behalf
                    </p>
                  </div>
                </motion.div>
              </div>

              {/* Stats */}
              <div className="mt-6 grid grid-cols-2 gap-4">
                <div className="bg-white border border-[#E6E9ED] rounded-lg p-4">
                  <p className="text-2xl font-bold text-indigo-600">{sentResumes.length}</p>
                  <p className="text-sm text-[#7A7A7A]">Applications Sent</p>
                </div>
                <div className="bg-white border border-[#E6E9ED] rounded-lg p-4">
                  <p className="text-2xl font-bold text-[#1A1A1A]">24/7</p>
                  <p className="text-sm text-[#7A7A7A]">Always Working</p>
                </div>
              </div>
            </div>

            {/* Right side - Resume Sending Animation */}
            <div>
              {/* Resume Icon in Center */}
              <div className="relative h-[450px] flex items-center justify-center">
                <motion.div
                  className="w-20 h-28 bg-white border-2 border-indigo-600 rounded-lg shadow-lg p-3 relative z-10"
                  animate={{
                    scale: autoApplyEnabled ? [1, 1.05, 1] : 1
                  }}
                  transition={{
                    duration: 2,
                    repeat: Infinity,
                    ease: "easeInOut"
                  }}
                >
                  <div className="space-y-1">
                    <div className="h-1 bg-indigo-600 rounded w-full"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-3/4"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-2/3"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-1/2"></div>
                    <div className="h-1 bg-[#E6E9ED] rounded w-full"></div>
                  </div>
                  <div className="absolute -top-2 -right-2 w-6 h-6 bg-indigo-600 rounded-full flex items-center justify-center">
                    <Zap className="h-3 w-3 text-white" />
                  </div>
                </motion.div>

                {/* Company Cards with Flying Resume Animation */}
                <AnimatePresence>
                  {companies.map((company, index) => {
                    const angle = (index / companies.length) * 2 * Math.PI;
                    const radius = 145;
                    const x = Math.cos(angle) * radius;
                    const y = Math.sin(angle) * radius;
                    const isSent = sentResumes.includes(company.id);

                    return (
                      <motion.div
                        key={company.id}
                        className="absolute top-1/2 left-1/2"
                        style={{
                          x: x - 40,
                          y: y - 20,
                        }}
                        initial={{ opacity: 0, scale: 0 }}
                        animate={{
                          opacity: isSent ? 1 : 0.3,
                          scale: isSent ? 1 : 0.8
                        }}
                        transition={{ duration: 0.5 }}
                      >
                        {/* Flying Resume Animation */}
                        {isSent && (
                          <motion.div
                            className="absolute -top-4 left-1/2"
                            initial={{
                              x: 0,
                              y: 0,
                              opacity: 0,
                              scale: 0.5
                            }}
                            animate={{
                              x: -40,
                              y: -10,
                              opacity: [0, 1, 1, 0],
                              scale: [0.5, 1, 1, 0.5]
                            }}
                            transition={{
                              duration: 1.5,
                              ease: "easeOut"
                            }}
                          >
                            <Send className="h-4 w-4 text-indigo-600" />
                          </motion.div>
                        )}

                        {/* Company Card */}
                        <div
                          className={`w-20 h-20 rounded-xl flex items-center justify-center text-white font-bold text-xs shadow-lg border-2 transition-all ${
                            isSent ? 'border-green-500' : 'border-transparent'
                          }`}
                          style={{ backgroundColor: company.color }}
                        >
                          {company.name}
                        </div>

                        {/* Checkmark for sent */}
                        {isSent && (
                          <motion.div
                            className="absolute -bottom-2 -right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center"
                            initial={{ scale: 0 }}
                            animate={{ scale: 1 }}
                            transition={{
                              type: "spring",
                              stiffness: 500,
                              damping: 25
                            }}
                          >
                            <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                            </svg>
                          </motion.div>
                        )}
                      </motion.div>
                    );
                  })}
                </AnimatePresence>
              </div>
            </div>
          </div>

          {/* Bottom Message */}
          <div className="mt-8 text-center">
            <div className="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-xl p-6">
              <p className="text-lg font-semibold text-[#1A1A1A] mb-2">
                Turn it on once. Get results forever.
              </p>
              <p className="text-[#4A4A4A]">
                AppliFlow Pro applies to new matching jobs every hour, so you never miss an opportunity
              </p>
            </div>

            {/* Get Pro Link */}
            <div className="mt-4">
              <a
                href="/subscribe"
                className="text-sm font-medium transition-all hover:opacity-80"
                style={{
                  background: 'linear-gradient(to right, #8B5CF6, #3B82F6)',
                  WebkitBackgroundClip: 'text',
                  WebkitTextFillColor: 'transparent',
                  backgroundClip: 'text'
                }}
              >
                Get AppliFlow Pro →
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
