<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Job Matches</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #6366f1;
            margin-bottom: 30px;
        }
        h1 {
            color: #6366f1;
            margin: 0;
            font-size: 28px;
        }
        .intro {
            font-size: 16px;
            margin-bottom: 25px;
            color: #555;
        }
        .job-card {
            background-color: #f9fafb;
            border-left: 4px solid #6366f1;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .job-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 8px 0;
        }
        .job-company {
            font-size: 16px;
            color: #6366f1;
            margin: 0 0 8px 0;
        }
        .job-details {
            font-size: 14px;
            color: #6b7280;
            margin: 5px 0;
        }
        .job-details span {
            margin-right: 15px;
        }
        .cta-button {
            display: inline-block;
            background-color: #6366f1;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            margin: 25px 0;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
            text-align: center;
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
            <h1>🎯 New Job Matches!</h1>
        </div>

        <div class="intro">
            Hi {{ $user->name }},<br><br>
            Great news! We found <strong>{{ $jobs->count() }} new job matches</strong> that align with your preferences. Here are some opportunities you might be interested in:
        </div>

        @foreach($jobs->take(5) as $job)
        <div class="job-card">
            <h2 class="job-title">{{ $job->job_title }}</h2>
            <p class="job-company">{{ $job->company }}</p>
            <div class="job-details">
                @if($job->location)
                    <span>📍 {{ $job->location }}</span>
                @endif
                @if($job->salary_min && $job->salary_max)
                    <span>💰 ${{ number_format($job->salary_min) }} - ${{ number_format($job->salary_max) }}</span>
                @endif
                @if($job->employment_type)
                    <span>⏰ {{ ucfirst($job->employment_type) }}</span>
                @endif
            </div>
        </div>
        @endforeach

        @if($jobs->count() > 5)
        <p style="text-align: center; color: #6b7280; font-size: 14px;">
            + {{ $jobs->count() - 5 }} more matches waiting for you
        </p>
        @endif

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/dashboard" class="cta-button">
                View All Matches →
            </a>
        </div>

        <div class="footer">
            <p>
                Want to adjust how often you receive these emails?<br>
                <a href="{{ config('app.url') }}/dashboard?tab=settings">Update your notification preferences</a>
            </p>
            <p style="margin-top: 15px;">
                You're receiving this email because you enabled new job match notifications in your ApplyFlow settings.
            </p>
        </div>
    </div>
</body>
</html>
