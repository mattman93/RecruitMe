# AppliFlow

https://appliflow.ai

**AI-Powered Job Application Automation Platform**

AppliFlow is a modern web application that streamlines the job search process by intelligently matching candidates with relevant opportunities and automating the application workflow. Built with Laravel and React, it leverages AI to parse resumes, analyze job postings, and submit personalized applications on behalf of users.

## Overview

AppliFlow transforms the traditional job application process from manual, time-consuming submissions into an automated, intelligent system. Users upload their resume, review AI-matched job opportunities, and let the platform handle the application process through personalized email outreach to hiring managers and recruiters.

### Key Features

- **AI Resume Parsing**: Extracts structured data from resumes using custom trained models
- **Intelligent Job Matching**: Analyzes job postings and matches them to user profiles based on skills, experience, and preferences
- **Automated Applications**: Sends personalized application emails with resume attachments via user's Gmail or AppliFlow email
- **Contact Discovery**: Identifies relevant hiring contacts (HR, recruiters) for each company
- **Application Tracking**: Dashboard for monitoring application status and responses
- **Token-Based System**: Flexible pricing with Stripe integration for subscriptions
- **OAuth Integration**: Google OAuth for seamless Gmail sending

## Architecture

### High-Level Overview

```
┌─────────────────┐
│   React SPA     │  (Frontend: Vite + React + TypeScript)
│   (Inertia.js)  │
└────────┬────────┘
         │
         │ HTTP/JSON API
         │
┌────────▼────────┐
│  Laravel API    │  (Backend: PHP 8.2 + Laravel 12)
│   + Services    │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌──▼──────┐
│ Queue │ │Database │
│System │ │(MySQL)  │
└───┬───┘ └─────────┘
    │
    │ Background Jobs
    │
┌───▼──────────────┐
│ Job Processing:  │
│ • Resume Parse   │
│ • Job Fetch      │
│ • Applications   │
│ • Email Send     │
└──────────────────┘
```

### Technology Stack

#### Backend
- **Framework**: Laravel 12
- **Language**: PHP 8.2+
- **Database**: MySQL/PostgreSQL
- **Authentication**: Laravel Sanctum
- **Rendering**: React/Typescript
- **Queue**: Laravel Queue with database driver
- **PDF Processing**: smalot/pdfparser

#### Frontend
- **Framework**: React 18
- **Language**: TypeScript
- **Build Tool**: Vite 6
- **UI Components**: Radix UI
- **Styling**: Tailwind CSS
- **Animations**: Motion (Framer Motion)
- **Charts**: Recharts
- **Forms**: React Hook Form

#### AI & Automation
- **AI Provider**: OpenAI (Generates value proposition in email body based on lead details/user experience) / Custom Models build with PyTorch and trained on publicly available data
- **Use Cases**:
  - Resume text extraction and parsing
  - Job requirement analysis
  - Application email generation
  - Contact email discovery
  - Value proposition generation

#### Third-Party Integrations
- **Email**: SendGrid, Gmail API
- **Payments**: Stripe
- **OAuth**: Google OAuth 2.0
- **Maps**: Google Maps API

#### Testing & Development
- **E2E Testing**: Playwright
- **Backend Testing**: Pest/PHPUnit
- **Code Quality**: Laravel Pint (PSR-12)
- **Development**: Laravel Sail, Docker

### Core Services

#### 1. ResumeParserService
Extracts structured information from PDF resumes:
- Personal contact information
- Work experience and employment history
- Skills and technologies
- Education and certifications

#### 2. JobMatchingService
Analyzes job postings and matches them to user profiles:
- Skill matching algorithms
- Experience level alignment
- Location and remote work preferences
- Salary range compatibility

#### 3. JobApplicationService
Manages the end-to-end application process:
- Contact email discovery using AI
- Personalized email generation
- Resume attachment handling
- Gmail API integration for user email sending
- SendGrid fallback for AppliFlow email
- Application status tracking

#### 4. PlaywrightAutomationService (Legacy)
Previously used for browser-based form filling; now replaced with email-based applications for better deliverability.

### Data Flow

1. **User Onboarding**
   - User uploads resume (PDF)
   - System parses resume using OpenAI
   - Structured data stored in database

2. **Job Ingestion**
   - Scheduled jobs fetch listings from data sources
   - Job data normalized and enriched
   - Company information extracted

3. **Matching**
   - Algorithm scores jobs against user profile
   - AI analyzes requirements vs. user experience
   - Ranked matches presented in dashboard

4. **Application**
   - User reviews and approves job matches
   - System discovers hiring contacts for company
   - AI generates personalized application email
   - Email sent via Gmail API (user's email) or SendGrid
   - Resume attached as PDF
   - Application tracked in database

5. **Monitoring**
   - Queue processes applications asynchronously
   - Status updates reflected in dashboard
   - Email notifications for important events

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 20+
- MySQL or PostgreSQL
- Docker (optional, for Laravel Sail)

### Installation

```bash
# Clone repository
git clone <repository-url>
cd appliflow

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Start development servers
composer run dev
```

### Running Tests

```bash
# Backend tests
composer test

# E2E tests
npx playwright test
```

## Environment Configuration

Key environment variables:

```env
# OpenAI
OPENAI_API_KEY=

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# SendGrid
SENDGRID_API_KEY=

# Stripe
STRIPE_KEY=
STRIPE_SECRET=

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=appliflow
```

## License

MIT
