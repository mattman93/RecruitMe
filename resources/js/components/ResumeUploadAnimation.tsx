import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import { Upload, FileText } from 'lucide-react';
import { useScrollAnimation } from './hooks/useScrollAnimation';

export function ResumeUploadAnimation() {
  const { ref, isVisible } = useScrollAnimation(0.3);
  const [animationPhase, setAnimationPhase] = useState<'waiting' | 'dragging' | 'dropped' | 'success'>('waiting');
  const [showResume, setShowResume] = useState(false);
  const [hasStarted, setHasStarted] = useState(false);

  useEffect(() => {
    if (!isVisible || hasStarted) return;

    setHasStarted(true);
    
    const runAnimationSequence = async () => {
      // Reset to initial state
      setAnimationPhase('waiting');
      setShowResume(false);

      // Wait a moment, then show resume
      await new Promise(resolve => setTimeout(resolve, 1000));
      setShowResume(true);
      
      // Start dragging animation
      await new Promise(resolve => setTimeout(resolve, 500));
      setAnimationPhase('dragging');
      
      // Drop animation
      await new Promise(resolve => setTimeout(resolve, 2000));
      setAnimationPhase('dropped');
      
      // Success state
      await new Promise(resolve => setTimeout(resolve, 800));
      setAnimationPhase('success');
      
      // Show success for a bit - then stay in success state
      await new Promise(resolve => setTimeout(resolve, 2000));
    };

    runAnimationSequence();
  }, [isVisible, hasStarted]);

  const isDragActive = animationPhase === 'dragging';
  const isDropped = animationPhase === 'dropped' || animationPhase === 'success';
  const isSuccess = animationPhase === 'success';

  return (
    <div ref={ref} className="relative w-full max-w-md mx-auto h-80 pointer-events-none"> {/* Fixed height container */}
      {/* Upload Area */}
      <motion.div
        className={`
          relative border-2 border-dashed rounded-2xl p-8 text-center transition-all duration-500
          ${isDragActive ? 'border-[#2D5BFF] bg-[#F5F8FF]' : 'border-[#E6E9ED] bg-white'}
          ${isSuccess ? 'border-[#28A745] bg-[#F8FFF9]' : ''}
        `}
        animate={{
          scale: isDragActive ? 1.02 : 1,
        }}
        transition={{ duration: 0.3 }}
      >
        {/* Upload Icon */}
        <motion.div
          className={`
            w-12 h-12 mx-auto mb-4 rounded-xl flex items-center justify-center transition-colors duration-300
            ${isDragActive ? 'bg-[#2D5BFF]' : 'bg-[#F7F8FA]'}
            ${isSuccess ? 'bg-[#28A745]' : ''}
          `}
          animate={{
            rotate: isDragActive ? [0, -5, 5, 0] : 0,
          }}
          transition={{ duration: 0.5, repeat: isDragActive ? Infinity : 0 }}
        >
          <motion.div
            animate={{
              scale: isSuccess ? [1, 1.2, 1] : 1,
            }}
            transition={{ duration: 0.6 }}
          >
            {isSuccess ? (
              <motion.div
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                className="text-white text-xl"
              >
                ✓
              </motion.div>
            ) : (
              <Upload 
                className={`w-6 h-6 transition-colors duration-300 ${
                  isDragActive ? 'text-white' : 'text-[#2D5BFF]'
                }`} 
              />
            )}
          </motion.div>
        </motion.div>

        {/* Main Text */}
        <motion.p 
          className="text-[#1A1A1A] mb-2 font-medium"
          animate={{
            color: isSuccess ? '#28A745' : '#1A1A1A'
          }}
        >
          {isSuccess 
            ? 'Resume uploaded successfully!' 
            : isDragActive 
              ? 'Drop your resume here' 
              : 'Drag your files to upload or '
          }
          {!isSuccess && !isDragActive && (
            <span className="text-[#2D5BFF] pointer-events-none">
              select files
            </span>
          )}
        </motion.p>

        {/* Supported formats */}
        {!isSuccess && (
          <p className="text-sm text-[#7A7A7A] mb-4">
            Supports PDF, DOC, DOCX, JPEG, PNG, GIF, WebP (max 10MB)
          </p>
        )}

        {/* Divider and Login link */}
        {!isSuccess && (
          <>
            <div className="relative my-6">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-[#E6E9ED]"></div>
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="px-2 bg-white text-[#7A7A7A]">or</span>
              </div>
            </div>

            <p className="text-sm text-[#7A7A7A]">
              Already have an account?{' '}
              <span className="text-[#2D5BFF] pointer-events-none">
                Login
              </span>
            </p>
          </>
        )}

        {/* Success message details */}
        {isSuccess && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            className="space-y-2"
          >
            <p className="text-sm text-[#28A745] font-medium">
              Resume_John_Doe.pdf
            </p>
            <p className="text-xs text-[#7A7A7A]">
              1.2 MB • Processed in 0.8s
            </p>
          </motion.div>
        )}
      </motion.div>

      {/* Animated Resume - Positioned absolutely within fixed container */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden"> {/* Overflow hidden container */}
        <AnimatePresence>
          {showResume && !isDropped && (
            <motion.div
              initial={{ 
                x: -100, 
                y: -50, 
                rotate: -15,
                scale: 0.8,
                opacity: 0
              }}
              animate={{ 
                x: animationPhase === 'dragging' ? 0 : -100,
                y: animationPhase === 'dragging' ? 0 : -50,
                rotate: animationPhase === 'dragging' ? -5 : -15,
                scale: animationPhase === 'dragging' ? 1 : 0.8,
                opacity: 1
              }}
              exit={{ 
                x: 20,
                y: 20,
                rotate: 0,
                scale: 0.8,
                opacity: 0
              }}
              transition={{ 
                duration: 1.5,
                ease: "easeInOut"
              }}
              className="absolute top-0 left-0 z-10"
              style={{
                filter: 'drop-shadow(0 4px 12px rgba(0,0,0,0.15))'
              }}
            >
              {/* Resume Document */}
              <div className="w-16 h-20 bg-white border border-[#E6E9ED] rounded-lg p-2 shadow-lg">
                <FileText className="w-4 h-4 text-[#2D5BFF] mb-1" />
                <div className="space-y-1">
                  <div className="h-0.5 bg-[#E6E9ED] rounded w-full"></div>
                  <div className="h-0.5 bg-[#E6E9ED] rounded w-3/4"></div>
                  <div className="h-0.5 bg-[#E6E9ED] rounded w-full"></div>
                  <div className="h-0.5 bg-[#E6E9ED] rounded w-2/3"></div>
                  <div className="h-0.5 bg-[#E6E9ED] rounded w-1/2"></div>
                </div>
              </div>
              
              {/* Filename */}
              <div className="text-xs text-[#1A1A1A] mt-1 text-center font-medium">
                Resume.pdf
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Drag indication dots */}
      {isDragActive && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="absolute inset-0 pointer-events-none"
        >
          {[...Array(8)].map((_, i) => (
            <motion.div
              key={i}
              className="absolute w-1 h-1 bg-[#2D5BFF] rounded-full"
              style={{
                left: `${20 + (i * 8)}%`,
                top: `${30 + (i % 2) * 20}%`,
              }}
              animate={{
                opacity: [0.3, 1, 0.3],
                scale: [0.8, 1.2, 0.8],
              }}
              transition={{
                duration: 1,
                repeat: Infinity,
                delay: i * 0.1,
              }}
            />
          ))}
        </motion.div>
      )}
    </div>
  );
}