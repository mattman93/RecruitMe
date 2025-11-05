import { useState } from "react";
import { Slider } from "./ui/slider";
import { Clock, TrendingUp, Zap } from "lucide-react";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

export function ROICalculator() {
  const { ref, isVisible } = useScrollAnimation(0.3);
  const [timePerApp, setTimePerApp] = useState<number>(15); // minutes
  const [appsPerWeek, setAppsPerWeek] = useState<number>(5);

  // Calculate current time spent
  const hoursPerWeek = (timePerApp * appsPerWeek) / 60;

  // Calculate savings with AppliFlow
  // Standard: 3x faster (5 min per app), 3x more applications
  const standardTimePerApp = 5;
  const standardAppsPerWeek = appsPerWeek * 3;
  const standardHoursPerWeek = (standardTimePerApp * appsPerWeek) / 60; // Same number as before, but 3x faster

  // Pro: Fully automated (0 hours), 10x more applications
  const proAppsPerWeek = appsPerWeek * 7;
  const proHoursPerWeek = 0;

  // Time saved
  const standardTimeSaved = hoursPerWeek - standardHoursPerWeek;
  const proTimeSaved = hoursPerWeek - proHoursPerWeek;

  return (
    <section className={`w-full bg-gradient-to-b from-white to-gray-50 py-20 fade-in ${isVisible ? 'visible' : ''}`} ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        {/* Header */}
        <div className="text-center mb-12">
          <h2 className="text-[#1A1A1A] mb-4">
            How much time are you wasting on job applications?
          </h2>
          <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
            Calculate how much time AppliFlow can save you every week
          </p>
        </div>

        {/* Calculator Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8 md:p-12 max-w-4xl mx-auto border border-gray-200">
          {/* Input Sliders */}
          <div className="grid md:grid-cols-2 gap-8 mb-12">
            {/* Time per application slider */}
            <div>
              <div className="flex items-center justify-between mb-4">
                <label className="text-[#1A1A1A] font-semibold text-lg">
                  Time spent per application?
                </label>
                <span className="text-3xl font-bold text-indigo-600">
                  {timePerApp} min
                </span>
              </div>
              <Slider
                value={[timePerApp]}
                onValueChange={(value) => setTimePerApp(value[0])}
                min={5}
                max={30}
                step={1}
                className="w-full"
              />
              <div className="flex justify-between text-xs text-[#4A4A4A] mt-2">
                <span>5 min</span>
                <span>30 min</span>
              </div>
            </div>

            {/* Applications per week slider */}
            <div>
              <div className="flex items-center justify-between mb-4">
                <label className="text-[#1A1A1A] font-semibold text-lg">
                  Applications sent per week?
                </label>
                <span className="text-3xl font-bold text-indigo-600">
                  {appsPerWeek}
                </span>
              </div>
              <Slider
                value={[appsPerWeek]}
                onValueChange={(value) => setAppsPerWeek(value[0])}
                min={0}
                max={50}
                step={1}
                className="w-full"
              />
              <div className="flex justify-between text-xs text-[#4A4A4A] mt-2">
                <span>0</span>
                <span>50</span>
              </div>
            </div>
          </div>

          {/* Results - Current State */}
          <div className="bg-red-50 border-2 border-red-200 rounded-xl p-6 mb-6">
            <div className="flex items-center gap-3 mb-3">
              <Clock className="h-6 w-6 text-red-600" />
              <h3 className="text-xl font-bold text-red-900">
                Your Current Reality
              </h3>
            </div>
            <p className="text-3xl font-bold text-red-600">
              {hoursPerWeek.toFixed(1)} hours per week
            </p>
            <p className="text-[#4A4A4A] mt-2">
              That's {(hoursPerWeek * 4).toFixed(0)} hours per month wasted on manual applications
            </p>
          </div>

          {/* Results Grid - AppliFlow Plans */}
          <div className="grid md:grid-cols-2 gap-6 mb-8">
            {/* Standard Plan */}
            <div className="bg-blue-50 border-2 border-blue-200 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center gap-3 mb-3">
                <TrendingUp className="h-6 w-6 text-blue-600" />
                <h3 className="text-xl font-bold text-blue-900">
                  AppliFlow Standard
                </h3>
              </div>
              <div className="space-y-3">
                <div>
                  <p className="text-3xl font-bold text-blue-600">
                    {standardHoursPerWeek.toFixed(1)} hours/week
                  </p>
                  <p className="text-sm text-[#4A4A4A]">
                    Save <span className="font-bold text-blue-600">{standardTimeSaved.toFixed(1)} hours</span> per week
                  </p>
                </div>
                <div className="pt-3 border-t border-blue-200">
                  <p className="text-2xl font-bold text-blue-900">
                    {standardAppsPerWeek} applications
                  </p>
                  <p className="text-sm text-[#4A4A4A]">
                    <span className="font-bold text-blue-600">3x more</span> in less time
                  </p>
                </div>
              </div>
            </div>

            {/* Pro Plan */}
            <div className="bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-300 rounded-xl p-6 hover:shadow-xl transition-shadow relative overflow-hidden">
              {/* Best Value Badge */}
              <div className="absolute top-0 right-0 bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                BEST VALUE
              </div>

              <div className="flex items-center gap-3 mb-3">
                <Zap className="h-6 w-6 text-indigo-600" />
                <h3 className="text-xl font-bold text-indigo-900">
                  AppliFlow Pro
                </h3>
              </div>
              <div className="space-y-3">
                <div>
                  <p className="text-3xl font-bold text-indigo-600">
                    0 hours/week
                  </p>
                  <p className="text-sm text-[#4A4A4A]">
                    Save <span className="font-bold text-indigo-600">{proTimeSaved.toFixed(1)} hours</span> per week
                  </p>
                </div>
                <div className="pt-3 border-t border-indigo-200">
                  <p className="text-2xl font-bold text-indigo-900">
                    {proAppsPerWeek} applications
                  </p>
                  <p className="text-sm text-[#4A4A4A]">
                    <span className="font-bold text-indigo-600">7x more</span> with full automation
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Killer Closing Line */}
          <div className="text-center bg-gradient-to-r from-indigo-100 to-purple-100 rounded-xl p-8 border-2 border-indigo-200">
            <p className="text-2xl md:text-3xl font-bold mb-2 text-[#1A1A1A]">
              What would you do with {hoursPerWeek.toFixed(0)} extra hours this week?
            </p>
            <p className="text-lg text-[#4A4A4A]">
              Stop wasting time on applications. Let AppliFlow work for you.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
