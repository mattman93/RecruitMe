import { useEffect, useRef, useState } from 'react';

export function useScrollAnimation(threshold = 0.1) {
  const [isVisible, setIsVisible] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
        }
      },
      {
        threshold,
        rootMargin: '0px 0px -50px 0px'
      }
    );

    if (ref.current) {
      observer.observe(ref.current);
    }

    return () => {
      if (ref.current) {
        observer.unobserve(ref.current);
      }
    };
  }, [threshold]);

  return { ref, isVisible };
}

export function useParallax() {
  const [offsetY, setOffsetY] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      setOffsetY(window.pageYOffset);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });

    return () => {
      window.removeEventListener('scroll', handleScroll);
    };
  }, []);

  return offsetY;
}

export function useTypewriter(text: string, isVisible: boolean, speed = 100) {
  const [displayText, setDisplayText] = useState('');
  const [isComplete, setIsComplete] = useState(false);
  const [hasStarted, setHasStarted] = useState(false);

  useEffect(() => {
    if (!isVisible || hasStarted) return;

    setHasStarted(true);
    let i = 0;
    setDisplayText('');
    setIsComplete(false);

    const timer = setInterval(() => {
      if (i < text.length) {
        setDisplayText(text.slice(0, i + 1));
        i++;
      } else {
        setIsComplete(true);
        clearInterval(timer);
      }
    }, speed);

    return () => clearInterval(timer);
  }, [text, isVisible, speed, hasStarted]);

  return { displayText, isComplete };
}

export function useSteppedTypewriter(steps: Array<{title: string; description: string}>, isVisible: boolean, titleSpeed = 50, descSpeed = 30, stepDelay = 1000) {
  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [currentPhase, setCurrentPhase] = useState<'title' | 'description' | 'complete'>('title');
  const [displayTexts, setDisplayTexts] = useState<Array<{title: string; description: string}>>([]);
  const [hasStarted, setHasStarted] = useState(false);

  useEffect(() => {
    if (!isVisible || hasStarted) return;

    setHasStarted(true);
    setCurrentStepIndex(0);
    setCurrentPhase('title');
    setDisplayTexts(steps.map(() => ({ title: '', description: '' })));

    let timeouts: NodeJS.Timeout[] = [];

    const typeStep = (stepIndex: number) => {
      if (stepIndex >= steps.length) return;

      const step = steps[stepIndex];
      
      // Type title
      let titleCharIndex = 0;
      const typeTitle = () => {
        if (titleCharIndex <= step.title.length) {
          setDisplayTexts(prev => {
            const newTexts = [...prev];
            newTexts[stepIndex] = {
              ...newTexts[stepIndex],
              title: step.title.slice(0, titleCharIndex)
            };
            return newTexts;
          });
          titleCharIndex++;

          if (titleCharIndex <= step.title.length) {
            timeouts.push(setTimeout(typeTitle, titleSpeed));
          } else {
            // Title complete, start description after brief pause
            timeouts.push(setTimeout(() => {
              setCurrentPhase('description');
              let descCharIndex = 0;
              const typeDescription = () => {
                if (descCharIndex <= step.description.length) {
                  setDisplayTexts(prev => {
                    const newTexts = [...prev];
                    newTexts[stepIndex] = {
                      ...newTexts[stepIndex],
                      description: step.description.slice(0, descCharIndex)
                    };
                    return newTexts;
                  });
                  descCharIndex++;

                  if (descCharIndex <= step.description.length) {
                    timeouts.push(setTimeout(typeDescription, descSpeed));
                  } else {
                    // Description complete, move to next step
                    timeouts.push(setTimeout(() => {
                      setCurrentStepIndex(stepIndex + 1);
                      setCurrentPhase('title');
                      typeStep(stepIndex + 1);
                    }, stepDelay));
                  }
                }
              };
              typeDescription();
            }, 200));
          }
        }
      };

      setCurrentStepIndex(stepIndex);
      setCurrentPhase('title');
      typeTitle();
    };

    typeStep(0);

    return () => {
      timeouts.forEach(timeout => clearTimeout(timeout));
    };
  }, [steps, isVisible, titleSpeed, descSpeed, stepDelay, hasStarted]);

  return { displayTexts, currentStepIndex, currentPhase };
}