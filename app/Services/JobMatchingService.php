<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\UserWork;
use App\Models\ParsedResume;
use App\Models\JobApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobMatchingService
{
    /**
     * Find relevant jobs for a user based on their resume and work experience.
     */
    public function findRelevantJobs(int $userId, int $limit = 50): Collection
    {
        $user = User::find($userId);
        if (!$user) {
            return collect();
        }

        // Get user's parsed resume and work experience
        $parsedResume = ParsedResume::where('user_id', $userId)->latest()->first();
        $workExperience = UserWork::where('user_id', $userId)->get();

        // Choose matching strategy based on available data
        if ($parsedResume && !empty($parsedResume->technical_skills)) {
            return $this->skillsBasedMatching($parsedResume, $workExperience, $limit);
        } else {
            return $this->experienceBasedMatching($workExperience, $limit);
        }
    }

    /**
     * Skills-based matching when technical skills are available.
     */
    protected function skillsBasedMatching(ParsedResume $parsedResume, Collection $workExperience, int $limit): Collection
    {
        $userSkills = array_map('strtolower', $parsedResume->technical_skills ?? []);
        $jobTitles = $workExperience->pluck('job_title')->map('strtolower')->toArray();
        $experienceLevel = $this->determineExperienceLevel($parsedResume->years_of_experience ?? 0);

        // Get user's applied lead IDs to exclude them
        $appliedLeadIds = JobApplication::where('user_id', $parsedResume->user_id)
            ->pluck('lead_id')
            ->toArray();

        // Build query for skills-based matching with chunked processing
        $relevantJobs = collect();
        $chunkSize = 500; // Process 500 jobs at a time
        $processedCount = 0;
        $maxJobs = $limit * 10; // Process up to 10x the requested limit to ensure we find enough good matches

        Lead::select('*')
            ->selectRaw('
                (CASE
                    WHEN experience_level = ? THEN 20
                    WHEN experience_level IN (?, ?) THEN 15
                    ELSE 5
                END) as experience_score
            ', [$experienceLevel, $this->getAdjacentLevels($experienceLevel)[0], $this->getAdjacentLevels($experienceLevel)[1]])
            ->where('is_active', true)
            ->whereNotIn('id', $appliedLeadIds) // Exclude jobs user has already applied to
            ->orderBy('created_at', 'desc') // Process newest jobs first
            ->chunk($chunkSize, function ($jobs) use ($userSkills, $jobTitles, $parsedResume, &$relevantJobs, $limit, &$processedCount, $maxJobs) {
                foreach ($jobs as $job) {
                    $processedCount++;
                    
                    $skillScore = $this->calculateSkillScore($job, $userSkills, $parsedResume->user_id);
                    $titleScore = $this->calculateTitleScore($job, $jobTitles);
                    $categoryScore = $this->calculateCategoryScore($job, $jobTitles);
                    
                    $job->relevance_score = $skillScore + $titleScore + $categoryScore + $job->experience_score;
                    $job->skill_matches = $this->getMatchingSkills($job, $userSkills);
                    
                    // Only keep jobs with decent relevance scores
                    if ($job->relevance_score >= 15) {
                        $relevantJobs->push($job);
                    }
                    
                    // Stop if we have enough good matches or processed enough jobs
                    if ($relevantJobs->count() >= $limit * 3 || $processedCount >= $maxJobs) {
                        return false; // Break out of chunk processing
                    }
                }
            });

        $jobs = $relevantJobs
            ->sortByDesc('relevance_score')
            ->take($limit);

        Log::info("Skills-based matching found {$jobs->count()} relevant jobs", [
            'user_skills_count' => count($userSkills),
            'top_score' => $jobs->first()?->relevance_score ?? 0,
        ]);

        return $jobs;
    }

    /**
     * Experience-based matching when no skills are available.
     */
    protected function experienceBasedMatching(Collection $workExperience, int $limit): Collection
    {
        if ($workExperience->isEmpty()) {
            return collect();
        }

        // Extract keywords from work experience
        $jobTitles = $workExperience->pluck('job_title')->map('strtolower')->toArray();
        $keywords = $this->extractKeywordsFromExperience($workExperience);
        $estimatedExperience = $this->estimateYearsOfExperience($workExperience);
        $experienceLevel = $this->determineExperienceLevel($estimatedExperience);

        // Get user's applied lead IDs to exclude them
        $userId = $workExperience->first()->user_id ?? null;
        $appliedLeadIds = [];
        if ($userId) {
            $appliedLeadIds = JobApplication::where('user_id', $userId)
                ->pluck('lead_id')
                ->toArray();
        }

        // Experience-based matching with chunked processing
        $relevantJobs = collect();
        $chunkSize = 500; // Process 500 jobs at a time
        $processedCount = 0;
        $maxJobs = $limit * 10; // Process up to 10x the requested limit to ensure we find enough good matches

        Lead::select('*')
            ->selectRaw('
                (CASE
                    WHEN experience_level = ? THEN 20
                    WHEN experience_level IN (?, ?) THEN 15
                    ELSE 5
                END) as experience_score
            ', [$experienceLevel, $this->getAdjacentLevels($experienceLevel)[0], $this->getAdjacentLevels($experienceLevel)[1]])
            ->where('is_active', true)
            ->whereNotIn('id', $appliedLeadIds) // Exclude jobs user has already applied to
            ->orderBy('created_at', 'desc') // Process newest jobs first
            ->chunk($chunkSize, function ($jobs) use ($keywords, $jobTitles, &$relevantJobs, $limit, &$processedCount, $maxJobs) {
                foreach ($jobs as $job) {
                    $processedCount++;
                    
                    $keywordScore = $this->calculateKeywordScore($job, $keywords);
                    $titleScore = $this->calculateTitleScore($job, $jobTitles);
                    $categoryScore = $this->calculateCategoryScore($job, $jobTitles);
                    
                    $job->relevance_score = $keywordScore + $titleScore + $categoryScore + $job->experience_score;
                    $job->keyword_matches = $this->getMatchingKeywords($job, $keywords);
                    
                    // Only keep jobs with decent relevance scores
                    if ($job->relevance_score >= 20) {
                        $relevantJobs->push($job);
                    }
                    
                    // Stop if we have enough good matches or processed enough jobs
                    if ($relevantJobs->count() >= $limit * 3 || $processedCount >= $maxJobs) {
                        return false; // Break out of chunk processing
                    }
                }
            });

        $jobs = $relevantJobs
            ->sortByDesc('relevance_score')
            ->take($limit);

        Log::info("Experience-based matching found {$jobs->count()} relevant jobs", [
            'keywords_count' => count($keywords),
            'estimated_experience' => $estimatedExperience,
            'top_score' => $jobs->first()?->relevance_score ?? 0,
        ]);

        return $jobs;
    }

    /**
     * Calculate skill match score against job description and requirements.
     */
    protected function calculateSkillScore(Lead $job, array $userSkills, ?int $userId = null): float
    {
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? '') . ' ' . ($job->technical_tools ?? ''));
        $matches = 0;
        $matchedSkills = [];
        $coreSkillMatches = 0;
        $coreSkillsMissing = 0;

        // Define core skills that should be heavily weighted
        $coreSkills = $this->identifyCoreSkills($userSkills, $userId);

        foreach ($userSkills as $skill) {
            $isCore = in_array($skill, $coreSkills);
            $skillFound = false;

            // Direct match
            if (strpos($jobText, $skill) !== false) {
                $matches++;
                $matchedSkills[] = $skill;
                $skillFound = true;
                if ($isCore) $coreSkillMatches++;
                continue;
            }

            // Fuzzy matching for common variations
            $variations = $this->getSkillVariations($skill);
            foreach ($variations as $variation) {
                if (strpos($jobText, $variation) !== false) {
                    $matches++;
                    $matchedSkills[] = $skill;
                    $skillFound = true;
                    if ($isCore) $coreSkillMatches++;
                    break;
                }
            }

            // Track missing core skills
            if ($isCore && !$skillFound) {
                $coreSkillsMissing++;
            }
        }

        // Enhanced scoring system
        $baseScore = $matches * 1.5; // Reduced base score to prioritize quality over quantity
        $coreSkillBonus = $coreSkillMatches * 18; // Increased core skill bonus
        $coreSkillPenalty = $coreSkillsMissing * -20; // Much stronger penalty for missing core skills
        
        // Dynamic primary skill bonus based on user's top skills
        $primarySkillBonus = $this->calculatePrimarySkillBonus($matchedSkills, $coreSkills);
        
        // Match percentage bonus (but less influential)
        $matchPercentage = count($userSkills) > 0 ? ($matches / count($userSkills)) : 0;
        $percentageBonus = $matchPercentage > 0.5 ? 15 : ($matchPercentage > 0.3 ? 8 : 0);

        return $baseScore + $coreSkillBonus + $coreSkillPenalty + $primarySkillBonus + $percentageBonus;
    }

    /**
     * Calculate job title similarity score.
     */
    protected function calculateTitleScore(Lead $job, array $userJobTitles): float
    {
        $jobTitle = strtolower($job->job_title);
        $coreTitle = strtolower($job->core_job_title ?? '');
        
        $score = 0;
        foreach ($userJobTitles as $userTitle) {
            // Exact match gets highest score
            if ($jobTitle === $userTitle || $coreTitle === $userTitle) {
                $score += 15;
            }
            // Partial match gets medium score
            elseif (strpos($jobTitle, $userTitle) !== false || strpos($userTitle, $jobTitle) !== false) {
                $score += 8;
            }
            // Common title words get lower score
            elseif ($this->hasCommonTitleWords($jobTitle, $userTitle)) {
                $score += 3;
            }
        }

        return min($score, 20); // Cap at 20 points
    }

    /**
     * Calculate category relevance score.
     */
    protected function calculateCategoryScore(Lead $job, array $userJobTitles): float
    {
        $category = strtolower($job->job_category ?? '');
        
        // Map user job titles to likely categories
        $relevantCategories = [];
        foreach ($userJobTitles as $title) {
            if (strpos($title, 'engineer') !== false || strpos($title, 'developer') !== false) {
                $relevantCategories[] = 'software development';
                $relevantCategories[] = 'engineering';
                $relevantCategories[] = 'information technology';
            }
        }

        foreach ($relevantCategories as $relevantCategory) {
            if (strpos($category, $relevantCategory) !== false) {
                return 10;
            }
        }

        return 0;
    }

    /**
     * Extract keywords from work experience descriptions and achievements.
     */
    protected function extractKeywordsFromExperience(Collection $workExperience): array
    {
        $allText = $workExperience->map(function ($work) {
            $text = $work->job_title . ' ' . ($work->description ?? '');
            if ($work->achievements && is_array($work->achievements)) {
                $text .= ' ' . implode(' ', $work->achievements);
            }
            return $text;
        })->implode(' ');

        // Extract technical terms and keywords
        $keywords = [];
        
        // Common tech patterns
        $patterns = [
            '/\b(php|python|java|javascript|react|vue|node|laravel|django|spring)\b/i',
            '/\b(mysql|postgresql|mongodb|redis|elasticsearch)\b/i',
            '/\b(aws|azure|docker|kubernetes|jenkins)\b/i',
            '/\b(api|microservices|rest|graphql|websocket)\b/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $allText, $matches);
            $keywords = array_merge($keywords, array_map('strtolower', $matches[0]));
        }

        return array_unique($keywords);
    }

    /**
     * Identify core skills that should be heavily weighted using dynamic analysis.
     */
    protected function identifyCoreSkills(array $userSkills, ?int $userId = null): array
    {
        $skillImportanceScores = $this->calculateSkillImportance($userSkills, $userId);
        
        // Sort skills by importance score (descending)
        arsort($skillImportanceScores);
        
        // Take top 3-5 most important skills as core skills
        $topSkills = array_slice(array_keys($skillImportanceScores), 0, 5);
        
        // Ensure we have at least the top 3 skills
        while (count($topSkills) < 3 && count($topSkills) < count($userSkills)) {
            $topSkills[] = $userSkills[count($topSkills)];
        }
        
        return array_map('strtolower', array_unique($topSkills));
    }

    /**
     * Calculate importance scores for skills based on multiple factors.
     */
    protected function calculateSkillImportance(array $userSkills, ?int $userId = null): array
    {
        $scores = [];
        
        // Initialize all skills with base scores
        foreach ($userSkills as $index => $skill) {
            $skillLower = strtolower($skill);
            
            // Stronger position-based scoring (first skills are MUCH more important)
            if ($index == 0) {
                $positionScore = 25; // First skill is extremely important
            } elseif ($index == 1) {
                $positionScore = 20; // Second skill is very important  
            } elseif ($index == 2) {
                $positionScore = 15; // Third skill is important
            } else {
                $positionScore = max(10 - $index, 1); // Others decrease normally
            }
            
            $scores[$skillLower] = $positionScore;
        }

        // If we have userId, analyze work experience for frequency
        if ($userId) {
            $workExperience = UserWork::where('user_id', $userId)->get();
            $experienceScores = $this->analyzeSkillFrequencyInExperience($userSkills, $workExperience);
            
            // Merge experience-based scores with position-based scores
            foreach ($experienceScores as $skill => $experienceScore) {
                $scores[$skill] = ($scores[$skill] ?? 0) + $experienceScore;
            }
        }

        return $scores;
    }

    /**
     * Analyze skill frequency and importance within work experience.
     */
    protected function analyzeSkillFrequencyInExperience(array $userSkills, $workExperience): array
    {
        $skillFrequency = [];
        $skillRecencyBonus = [];
        
        foreach ($workExperience as $work) {
            // Create combined text from job description and achievements
            $workText = strtolower(($work->description ?? '') . ' ' . 
                       ($work->job_title ?? '') . ' ');
            
            if ($work->achievements && is_array($work->achievements)) {
                $workText .= implode(' ', array_map('strtolower', $work->achievements));
            }
            
            // Calculate recency weight (more recent jobs are more important)
            $recencyWeight = $this->calculateRecencyWeight($work);
            
            // Count skill mentions in this job
            foreach ($userSkills as $skill) {
                $skillLower = strtolower($skill);
                $mentions = $this->countSkillMentions($skillLower, $workText);
                
                if ($mentions > 0) {
                    // Base frequency score
                    $skillFrequency[$skillLower] = ($skillFrequency[$skillLower] ?? 0) + $mentions;
                    
                    // Recency bonus (recent jobs matter more)
                    $skillRecencyBonus[$skillLower] = ($skillRecencyBonus[$skillLower] ?? 0) + 
                                                      ($mentions * $recencyWeight);
                }
            }
        }
        
        // Combine frequency and recency scores
        $combinedScores = [];
        foreach ($skillFrequency as $skill => $frequency) {
            $frequencyScore = min($frequency * 2, 10); // Cap at 10 points
            $recencyScore = min($skillRecencyBonus[$skill] ?? 0, 8); // Cap at 8 points
            
            $combinedScores[$skill] = $frequencyScore + $recencyScore;
        }
        
        return $combinedScores;
    }

    /**
     * Count how many times a skill is mentioned in text (including variations).
     */
    protected function countSkillMentions(string $skill, string $text): int
    {
        $count = 0;
        
        // Direct mention
        $count += substr_count($text, $skill);
        
        // Check variations
        $variations = $this->getSkillVariations($skill);
        foreach ($variations as $variation) {
            $count += substr_count($text, strtolower($variation));
        }
        
        return $count;
    }

    /**
     * Calculate recency weight based on job dates.
     */
    protected function calculateRecencyWeight($work): float
    {
        if (!$work->start_date) return 1.0;
        
        $startDate = new \DateTime($work->start_date);
        $now = new \DateTime();
        $yearsAgo = $startDate->diff($now)->y + ($startDate->diff($now)->m / 12);
        
        // More recent = higher weight
        if ($yearsAgo < 1) return 3.0;      // Current/very recent
        if ($yearsAgo < 2) return 2.5;      // Last 2 years
        if ($yearsAgo < 5) return 2.0;      // Last 5 years  
        return 1.0;                         // Older experience
    }

    /**
     * Calculate dynamic bonus for primary skills based on their importance rank.
     */
    protected function calculatePrimarySkillBonus(array $matchedSkills, array $coreSkills): float
    {
        $bonus = 0;
        
        foreach ($matchedSkills as $skill) {
            $skillLower = strtolower($skill);
            $corePosition = array_search($skillLower, $coreSkills);
            
            if ($corePosition !== false) {
                // Give higher bonus to more important core skills (earlier in array)
                switch ($corePosition) {
                    case 0: // Most important skill
                        $bonus += 25;
                        break;
                    case 1: // Second most important
                        $bonus += 20;
                        break;
                    case 2: // Third most important
                        $bonus += 15;
                        break;
                    default: // Other core skills
                        $bonus += 10;
                        break;
                }
            }
        }
        
        // Combo bonus: if multiple top skills match, give extra bonus
        $topThreeMatches = 0;
        for ($i = 0; $i < min(3, count($coreSkills)); $i++) {
            if (in_array($coreSkills[$i], array_map('strtolower', $matchedSkills))) {
                $topThreeMatches++;
            }
        }
        
        if ($topThreeMatches >= 2) {
            $bonus += 15; // Combo bonus for multiple top skills
        }
        
        return $bonus;
    }

    /**
     * Get skill variations for fuzzy matching.
     */
    protected function getSkillVariations(string $skill): array
    {
        $variations = [
            'react' => ['reactjs', 'react.js', 'react js'],
            'vue' => ['vuejs', 'vue.js', 'vue js'],
            'vue.js' => ['vue', 'vuejs'],
            'node' => ['nodejs', 'node.js'],
            'node.js' => ['nodejs', 'node js'],
            'javascript' => ['js', 'ecmascript'],
            'typescript' => ['ts'],
            'postgresql' => ['postgres', 'psql'],
            'mongodb' => ['mongo', 'mongo db'],
            'laravel' => ['laravel framework'],
            'php' => ['php7', 'php8'],
        ];

        return $variations[strtolower($skill)] ?? [];
    }

    /**
     * Determine experience level based on years.
     */
    protected function determineExperienceLevel(int $years): string
    {
        if ($years < 2) return 'Entry Level';
        if ($years < 5) return 'Mid Level';
        if ($years < 8) return 'Senior Level';
        return 'Executive Level';
    }

    /**
     * Get adjacent experience levels for fuzzy matching.
     */
    protected function getAdjacentLevels(string $level): array
    {
        $levels = ['Entry Level', 'Mid Level', 'Senior Level', 'Executive Level'];
        $index = array_search($level, $levels);
        
        return [
            $levels[$index - 1] ?? $level,
            $levels[$index + 1] ?? $level,
        ];
    }

    /**
     * Check if two job titles have common meaningful words.
     */
    protected function hasCommonTitleWords(string $title1, string $title2): bool
    {
        $commonWords = ['engineer', 'developer', 'programmer', 'architect', 'manager', 'lead', 'senior', 'principal'];
        
        foreach ($commonWords as $word) {
            if (strpos($title1, $word) !== false && strpos($title2, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate keyword match score for experience-based matching.
     */
    protected function calculateKeywordScore(Lead $job, array $keywords): float
    {
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? ''));
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (strpos($jobText, $keyword) !== false) {
                $matches++;
            }
        }

        return $matches * 2; // 2 points per keyword match
    }

    /**
     * Get matching skills for display purposes.
     */
    protected function getMatchingSkills(Lead $job, array $userSkills): array
    {
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? ''));
        $matches = [];

        foreach ($userSkills as $skill) {
            if (strpos($jobText, $skill) !== false) {
                $matches[] = $skill;
            }
        }

        return $matches;
    }

    /**
     * Get matching keywords for display purposes.
     */
    protected function getMatchingKeywords(Lead $job, array $keywords): array
    {
        $jobText = strtolower($job->description . ' ' . ($job->requirements_summary ?? ''));
        $matches = [];

        foreach ($keywords as $keyword) {
            if (strpos($jobText, $keyword) !== false) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    /**
     * Estimate years of experience from work history.
     */
    protected function estimateYearsOfExperience(Collection $workExperience): int
    {
        $totalYears = 0;
        
        foreach ($workExperience as $work) {
            $startDate = $work->start_date ? new \DateTime($work->start_date) : null;
            $endDate = $work->end_date ? new \DateTime($work->end_date) : new \DateTime();
            
            if ($startDate) {
                $interval = $startDate->diff($endDate);
                $totalYears += $interval->y;
                $totalYears += $interval->m / 12; // Add fractional years from months
            }
        }

        return (int) round($totalYears);
    }
}