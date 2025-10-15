import { useEffect, useRef } from "react";

export function LavaLampBackground() {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    const container = containerRef.current;
    if (!canvas || !container) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let isVisible = true;

    // Intersection Observer to pause when not visible
    const observer = new IntersectionObserver(
      (entries) => {
        isVisible = entries[0].isIntersecting;
        if (isVisible && !animationFrameId) {
          animate();
        }
      },
      { threshold: 0 }
    );
    observer.observe(container);

    // Debounced resize handler
    let resizeTimeout: number;
    const resizeCanvas = () => {
      canvas.width = canvas.offsetWidth;
      canvas.height = canvas.offsetHeight;
    };
    resizeCanvas();

    const debouncedResize = () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = window.setTimeout(resizeCanvas, 150);
    };
    window.addEventListener("resize", debouncedResize);

    // Wave layer configuration
    interface WaveLayer {
      amplitude: number;
      frequency: number;
      speed: number;
      offset: number;
      color: string;
      yPosition: number;
    }

    // Helper function to add randomness to a value
    const randomize = (base: number, variance: number) => {
      return base + (Math.random() - 0.5) * 2 * variance;
    };

    const waveLayers: WaveLayer[] = [
      {
        amplitude: randomize(60, 15),
        frequency: randomize(2, 0.2),
        speed: randomize(1.5, 0.6),
        offset: 0,
        color: "rgba(99, 102, 241, 0.5)",
        yPosition: 0.3,
      },
      {
        amplitude: randomize(80, 10),
        frequency: randomize(1.2, 0.5),
        speed: randomize(1.9, 0.9),
        offset: Math.PI / 2,
        color: "rgba(37, 99, 235, 0.5)",
        yPosition: 0.4,
      },
      {
        amplitude: randomize(70, 5),
        frequency: randomize(3, 0.3),
        speed: randomize(1.4, 0.7),
        offset: Math.PI,
        color: "rgba(139, 92, 246, 0.3)",
        yPosition: 0.5,
      },
    ];

    let time = 0;

    // Draw wave function
    const drawWave = (wave: WaveLayer, width: number, height: number) => {
      ctx.beginPath();
      ctx.moveTo(0, height);

      const baseY = (height * 0.8) * wave.yPosition;

      // Create smooth wave path
      for (let x = 0; x <= width; x += 15) {
        // Multiple sine waves for organic movement
        // Scale x to be between 0 and 2π for proper wave formation
        const normalizedX = (x / width) * Math.PI * 2;
        const y =
          baseY +
          Math.sin(normalizedX * wave.frequency - time * wave.speed + wave.offset) * wave.amplitude;


        ctx.lineTo(x, y);
      }

      // Complete the shape
      ctx.lineTo(width, height);
      ctx.lineTo(0, height);
      ctx.closePath();

      ctx.fillStyle = wave.color;
      ctx.fill();
    };

    // Animation loop with frame throttling
    let animationFrameId: number;
    let lastFrameTime = 0;
    const targetFPS = 30;
    const frameInterval = 1000 / targetFPS;

    const animate = (currentTime = 0) => {
      if (!canvas || !ctx || !isVisible) {
        animationFrameId = 0;
        return;
      }

      // Throttle to target FPS
      const elapsed = currentTime - lastFrameTime;
      if (elapsed < frameInterval) {
        animationFrameId = requestAnimationFrame(animate);
        return;
      }

      lastFrameTime = currentTime - (elapsed % frameInterval);

      // Clear canvas
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      // Draw all wave layers
      waveLayers.forEach((wave) => {
        drawWave(wave, canvas.width, canvas.height);
      });

      time += 0.008;
      animationFrameId = requestAnimationFrame(animate);
    };

    animate();

    // Cleanup
    return () => {
      observer.disconnect();
      window.removeEventListener("resize", debouncedResize);
      clearTimeout(resizeTimeout);
      cancelAnimationFrame(animationFrameId);
    };
  }, []);

  return (
    <div ref={containerRef} className="absolute inset-0 w-full h-full overflow-hidden">
      <canvas
        ref={canvasRef}
        className="absolute w-full h-full"
        style={{
          background: "linear-gradient(135deg, #6366f1 0%, #8b5cf6 25%, #a855f7 50%, #c084fc 75%, #2563eb 100%)",
          filter: "blur(15px)",
          transform: "translateZ(0) scale(1.1)",
          willChange: "transform",
          left: "-5%",
          top: "-5%",
          width: "110%",
          height: "110%",
        }}
      />
    </div>
  );
}
