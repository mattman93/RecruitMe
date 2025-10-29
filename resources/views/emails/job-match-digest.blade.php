<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Job Matches</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
        }
        h1 {
            color: #1f2937;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .match-count {
            font-size: 48px;
            font-weight: bold;
            color: #6366f1;
            margin: 20px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 6px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #6366f1;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .recommended-action {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .job-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            background-color: #ffffff;
            transition: box-shadow 0.2s;
        }
        .job-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .job-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 5px 0;
        }
        .job-company {
            color: #6366f1;
            font-weight: 500;
            margin: 0 0 8px 0;
        }
        .job-details {
            font-size: 14px;
            color: #6b7280;
            margin: 5px 0;
        }
        .job-salary {
            color: #059669;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .job-location {
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .icon {
            display: inline-block;
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }
        .relevance-badge {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        .cta-button {
            display: inline-block;
            background-color: #6366f1;
            color: #ffffff;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .footer a {
            color: #6366f1;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Job Matches</h1>
            <div class="match-count">{{ $matchCount }}</div>
            <p style="color: #6b7280; margin: 0;">New match{{ $matchCount > 1 ? 'es' : '' }} this {{ $user->match_email_frequency === 'daily' ? 'day' : 'week' }}</p>
        </div>

        @if($stats)
        <div class="stats-grid">
            @if($stats['avgSalary'])
            <div class="stat-item">
                <div class="stat-value">${{ number_format($stats['avgSalary']/1000, 0) }}K</div>
                <div class="stat-label">Avg Salary</div>
            </div>
            @endif
            <div class="stat-item">
                <div class="stat-value">{{ $stats['remotePercentage'] }}%</div>
                <div class="stat-label">Remote/Hybrid</div>
            </div>
        </div>
        @endif

        @if($recommendedAction)
        <div class="recommended-action">
            <strong>💡 Tip:</strong> {{ $recommendedAction }}
        </div>
        @endif

        <h2 style="color: #1f2937; margin-top: 30px;">Top Matches</h2>

        @foreach($topMatches as $match)
            @php
                $lead = $match->lead;
            @endphp
            <div class="job-card">
                <div class="job-title">{{ $lead->job_title }}</div>
                <div class="job-company">{{ $lead->company }}</div>

                <div class="job-details">
                    @if($lead->pay_range && $lead->pay_range !== 'Not specified')
                        <span class="job-salary">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            {{ $lead->pay_range }}
                        </span><br>
                    @endif
                    @if($lead->location)
                        <span class="job-location">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            {{ $lead->location }}
                        </span>
                        @if($lead->workplace_type)
                            <span> • {{ $lead->workplace_type }}</span>
                        @endif
                    @endif
                </div>

                <div class="relevance-badge">
                    {{ number_format($match->relevance_score, 0) }}% Match
                </div>
            </div>
        @endforeach

        @if($matchCount > 10)
        <p style="text-align: center; color: #6b7280; margin: 20px 0;">
            ...and {{ $matchCount - 10 }} more matches waiting for you!
        </p>
        @endif

        <center>
            <a href="{{ $viewAllUrl }}" class="cta-button">
                View All {{ $matchCount }} Matches →
            </a>
        </center>

        <div class="footer">
            <p>
                This email was sent to {{ $user->email }} because you have job match notifications enabled.<br>
                <a href="{{ url('/settings') }}">Manage email preferences</a> •
                <a href="{{ url('/dashboard') }}">Visit Dashboard</a>
            </p>
            <p style="margin-top: 15px;">
                AppliFlow - Your AI-Powered Job Application Assistant
            </p>
        </div>
    </div>
</body>
</html>
