<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Auto-Apply Report</title>
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
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
        }
        h1 {
            color: #1f2937;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .subtitle {
            color: #6b7280;
            font-size: 14px;
        }
        .application-count {
            font-size: 48px;
            font-weight: bold;
            color: #10b981;
            margin: 20px 0;
        }
        .summary-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .summary-text {
            font-size: 16px;
            color: #065f46;
            font-weight: 500;
        }
        .application-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            background-color: #ffffff;
            border-left: 4px solid #10b981;
        }
        .job-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 5px 0;
        }
        .job-company {
            color: #059669;
            font-weight: 500;
            margin: 0 0 8px 0;
        }
        .job-details {
            font-size: 14px;
            color: #6b7280;
            margin: 5px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background-color: #d1fae5;
            color: #065f46;
        }
        .cta-button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Auto-Apply Report</h1>
            <p class="subtitle">
                @if($frequency === 'daily')
                    {{ now()->format('l, F j, Y') }}
                @else
                    Week of {{ now()->startOfWeek()->format('F j') }} - {{ now()->endOfWeek()->format('F j, Y') }}
                @endif
            </p>
        </div>

        <div class="summary-box">
            <div class="application-count">{{ $applications->count() }}</div>
            <div class="summary-text">
                Application{{ $applications->count() !== 1 ? 's' : '' }} Submitted
            </div>
        </div>

        <p style="text-align: center; color: #6b7280; font-size: 14px; margin: 20px 0;">
            Your applications were automatically sent to these companies while you focused on other things.
        </p>

        <div style="margin-top: 30px;">
            <h2 style="font-size: 20px; color: #1f2937; margin-bottom: 15px;">Applications Submitted</h2>

            @foreach($applications as $application)
                <div class="application-card">
                    <div class="job-title">{{ $application->lead->job_title }}</div>
                    <div class="job-company">{{ $application->lead->company }}</div>

                    <div class="job-details">
                        📍 {{ $application->lead->location ?? 'Location not specified' }}
                    </div>

                    @if($application->lead->yearly_min_compensation || $application->lead->yearly_max_compensation)
                        <div class="job-details" style="color: #059669; font-weight: 600;">
                            💰
                            @if($application->lead->yearly_min_compensation && $application->lead->yearly_max_compensation)
                                ${{ number_format($application->lead->yearly_min_compensation / 1000) }}k - ${{ number_format($application->lead->yearly_max_compensation / 1000) }}k
                            @elseif($application->lead->yearly_min_compensation)
                                ${{ number_format($application->lead->yearly_min_compensation / 1000) }}k+
                            @else
                                Up to ${{ number_format($application->lead->yearly_max_compensation / 1000) }}k
                            @endif
                        </div>
                    @endif

                    <div class="job-details" style="margin-top: 8px;">
                        <span class="status-badge">{{ ucfirst($application->status) }}</span>
                        <span style="margin-left: 10px; font-size: 12px;">
                            Sent {{ $application->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ config('app.url') }}/dashboard" class="cta-button">
                View All Applications
            </a>
        </div>

        <div class="footer">
            <p>
                You're receiving this email because you have auto-apply enabled with {{ $frequency }} digest notifications.
                <br>
                <a href="{{ config('app.url') }}/dashboard" style="color: #6366f1; text-decoration: none;">Manage your settings</a>
            </p>
        </div>
    </div>
</body>
</html>
