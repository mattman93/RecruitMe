<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to AppliFlow Beta</title>
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
        .logo {
            max-width: 200px;
            margin-bottom: 20px;
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
        .token-box {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: center;
        }
        .token-label {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .token-code {
            font-size: 24px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            word-break: break-all;
        }
        .instructions {
            background-color: #f9fafb;
            border-left: 4px solid #6366f1;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .instructions h3 {
            color: #1f2937;
            margin-top: 0;
            font-size: 18px;
        }
        .instructions ol {
            padding-left: 20px;
            margin: 15px 0;
        }
        .instructions li {
            margin-bottom: 10px;
            color: #555;
        }
        .cta-button {
            display: inline-block;
            background-color: #6366f1;
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/af-main.png') }}" alt="AppliFlow Logo" class="logo">
            <h1>Welcome to AppliFlow Beta! 🎉</h1>
        </div>

        <div class="intro">
            <p>Hi there,</p>

            <p>Thank you for joining the AppliFlow beta program! We're excited to have you as one of our early users.</p>

            <p>AppliFlow is your AI-powered job search copilot designed to help tech professionals find and apply to the best opportunities with ease. As a beta tester, you'll get early access to all our features and help shape the future of job applications.</p>
        </div>

        <div class="token-box">
            <div class="token-label">Your Beta Access Code</div>
            <div class="token-code">{{ $accessToken->token }}</div>
        </div>

        <div style="background-color: #FFF4E6; border-left: 4px solid #F59E0B; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #92400E; font-size: 14px;"><strong>⚠️ Important:</strong> Save this access code in a safe place! You'll need it each time you visit AppliFlow during our beta period.</p>
        </div>

        <div class="instructions">
            <h3>How to Get Started:</h3>
            <ol>
                <li>Visit <a href="https://appliflow.ai" style="color: #6366f1; text-decoration: none; font-weight: 600;">appliflow.ai</a></li>
                <li>Click on "Access System with Code"</li>
                <li>Enter your beta access code</li>
                <li>Start exploring and applying to your dream jobs!</li>
            </ol>
        </div>

        <div style="text-align: center;">
            <a href="https://appliflow.ai" class="cta-button" style="color: white !important; text-decoration: none;">Access AppliFlow Now</a>
        </div>

        <div class="intro">
            <p><strong>What you can do with AppliFlow:</strong></p>
            <ul>
                <li>Upload your resume and get personalized job recommendations</li>
                <li>Let AI automatically apply to jobs on your behalf</li>
                <li>Track all your applications in one dashboard</li>
                <li>Get matched with positions that fit your skills and preferences</li>
            </ul>
        </div>

        <div class="footer">
            <p><strong>Need help?</strong> Reply to this email and we'll be happy to assist you.</p>
            <p>Keep this email safe – you'll need your access code to activate your account.</p>
            <p style="margin-top: 15px;">© 2025 AppliFlow. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
