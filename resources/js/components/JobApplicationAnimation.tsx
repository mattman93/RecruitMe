import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import buttonImage from './assets/05658c5e2b370efe8c275d14fcc38fe8f8ce321d.png';

export function JobApplicationAnimation() {
  const [animationPhase, setAnimationPhase] = useState<'waiting' | 'clicking' | 'loading' | 'complete'>('waiting');
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const runAnimationSequence = async () => {
      // Reset to initial state
      setAnimationPhase('waiting');
      setProgress(0);

      // Wait a moment, then show mouse approaching
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Mouse click animation
      setAnimationPhase('clicking');
      await new Promise(resolve => setTimeout(resolve, 800));
      
      // Start loading phase
      setAnimationPhase('loading');
      
      // Animate progress bar
      const progressInterval = setInterval(() => {
        setProgress(prev => {
          if (prev >= 100) {
            clearInterval(progressInterval);
            setAnimationPhase('complete');
            return 100;
          }
          return prev + 2;
        });
      }, 50);
      
      // After completion, wait a bit then restart
      await new Promise(resolve => setTimeout(resolve, 4000));
    };

    // Run initial animation
    runAnimationSequence();

    // Set up repeating animation every 8 seconds
    const interval = setInterval(() => {
      runAnimationSequence();
    }, 8000);

    return () => clearInterval(interval);
  }, []);

  const isWaiting = animationPhase === 'waiting';
  const isClicking = animationPhase === 'clicking';
  const isLoading = animationPhase === 'loading';
  const isComplete = animationPhase === 'complete';

  return (
    <div className="w-full max-w-md mx-auto pointer-events-none h-96 overflow-hidden"> {/* Fixed height with overflow hidden to prevent layout shifts */}
      {/* Header */}
      <div className="text-center mb-8">
        <h3 className="text-xl font-semibold text-[#1A1A1A] mb-2">Apply with Confidence</h3>
        <p className="text-sm text-[#7A7A7A]">One-click applications that work</p>
      </div>

      {/* Button Container */}
      <div className="relative mb-8">
        {/* The Button */}
        <motion.div
          className="relative"
          animate={{
            scale: isClicking ? 0.95 : 1,
          }}
          transition={{ duration: 0.1 }}
        >
          <img 
            src={buttonImage} 
            alt="Start Applying To Your Matched Jobs Button"
            className="w-full h-auto rounded-xl shadow-lg"
          />
        </motion.div>

        {/* Mouse Cursor */}
        <AnimatePresence>
          {(isWaiting || isClicking) && (
            <motion.div
              initial={{ 
                x: -50, 
                y: -30,
                opacity: 0,
                scale: 0.8
              }}
              animate={{ 
                x: isClicking ? 0 : -20,
                y: isClicking ? 0 : -15,
                opacity: 1,
                scale: isClicking ? 0.9 : 1
              }}
              exit={{ 
                opacity: 0,
                scale: 0.8
              }}
              transition={{ 
                duration: 0.8,
                ease: "easeOut"
              }}
              className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-10"
            >
              {/* Mouse Cursor SVG */}
              <svg 
                width="24" 
                height="32" 
                viewBox="0 0 24 32" 
                fill="none" 
                className="drop-shadow-lg"
              >
                <path 
                  d="M1 1L14.5 14.5L9 16L7 22L1 1Z" 
                  fill="white" 
                  stroke="black" 
                  strokeWidth="1"
                />
                <path 
                  d="M1 1L14.5 14.5L9 16L1 1Z" 
                  fill="white"
                />
              </svg>
              
              {/* Click effect */}
              {isClicking && (
                <motion.div
                  initial={{ scale: 0, opacity: 1 }}
                  animate={{ scale: 2, opacity: 0 }}
                  transition={{ duration: 0.6 }}
                  className="absolute inset-0 rounded-full border-2 border-[#2D5BFF]"
                />
              )}
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Progress Section */}
      <AnimatePresence>
        {(isLoading || isComplete) && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.5 }}
            className="space-y-4"
          >
            {/* Progress Bar Container */}
            <div className="w-full bg-[#E6E9ED] rounded-full h-3 overflow-hidden">
              <motion.div
                className="h-full rounded-full"
                style={{
                  background: 'linear-gradient(90deg, #2D5BFF 0%, #8B5CF6 50%, #A855F7 100%)',
                  width: `${progress}%`
                }}
                initial={{ width: '0%' }}
                animate={{ width: `${progress}%` }}
                transition={{ duration: 0.1, ease: "easeOut" }}
              />
            </div>

            {/* Loading Text */}
            <div className="text-center">
              <motion.p 
                className="text-sm text-[#7A7A7A]"
                animate={{
                  opacity: isComplete ? 0.7 : 1
                }}
              >
                {isComplete ? 'Jobs loaded successfully!' : 'Loading jobs in your queue'}
              </motion.p>
              
              {/* Progress Percentage */}
              <motion.p 
                className="text-xs text-[#2D5BFF] font-medium mt-1"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.5 }}
              >
                {progress}% complete
              </motion.p>
            </div>

            {/* Success State */}
            {isComplete && (
              <motion.div
                initial={{ opacity: 0, scale: 0.8 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.3, duration: 0.4 }}
                className="text-center"
              >
                <div className="inline-flex items-center gap-2 bg-[#F8FFF9] border border-[#28A745] rounded-lg px-4 py-2">
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ delay: 0.5 }}
                    className="w-5 h-5 bg-[#28A745] rounded-full flex items-center justify-center"
                  >
                    <span className="text-white text-xs">✓</span>
                  </motion.div>
                  <span className="text-sm text-[#28A745] font-medium">
                    Ready to apply to 4 matched jobs
                  </span>
                </div>
              </motion.div>
            )}
          </motion.div>
        )}
      </AnimatePresence>

      {/* Floating Elements During Loading */}
      {isLoading && (
        <div className="absolute inset-0 pointer-events-none">
          {[...Array(6)].map((_, i) => (
            <motion.div
              key={i}
              className="absolute w-1 h-1 bg-[#2D5BFF] rounded-full"
              style={{
                left: `${20 + (i * 12)}%`,
                top: `${60 + (i % 2) * 10}%`,
              }}
              animate={{
                opacity: [0, 1, 0],
                scale: [0.5, 1.2, 0.5],
                y: [0, -10, 0],
              }}
              transition={{
                duration: 2,
                repeat: Infinity,
                delay: i * 0.2,
              }}
            />
          ))}
        </div>
      )}
    </div>
  );
}