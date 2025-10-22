#!/usr/bin/env node

const { chromium } = require('playwright');

async function fetchJobs(url, headers, payload) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  try {
    const context = await browser.newContext({
      userAgent: headers['user-agent'] || headers['User-Agent'] || 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
    });

    const page = await context.newPage();

    // First navigate to the main hiring.cafe site to pass security checkpoint
    await page.goto('https://hiring.cafe', {
      waitUntil: 'networkidle',
      timeout: 60000
    });

    // Wait a bit to ensure security checkpoint passes
    await page.waitForTimeout(3000);

    // Now make the POST request via JavaScript in the page context
    // Only use essential headers to avoid detection
    const result = await page.evaluate(async ({ url, payload }) => {
      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload)
        });

        let data;
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
          data = await response.json();
        } else {
          const text = await response.text();
          data = { rawResponse: text };
        }

        return {
          status: response.status,
          statusText: response.statusText,
          data: data,
          contentType: contentType
        };
      } catch (error) {
        return {
          error: true,
          message: error.message,
          stack: error.stack
        };
      }
    }, { url, payload });

    // Output the result as JSON to stdout
    console.log(JSON.stringify(result));

    await browser.close();
    process.exit(0);

  } catch (error) {
    console.error(JSON.stringify({
      error: true,
      message: error.message,
      stack: error.stack
    }));

    await browser.close();
    process.exit(1);
  }
}

// Get arguments from command line
const args = process.argv.slice(2);

if (args.length < 3) {
  console.error(JSON.stringify({
    error: true,
    message: 'Usage: node fetch-hiring-cafe.js <url> <headers-json> <payload-json>'
  }));
  process.exit(1);
}

const url = args[0];
const headers = JSON.parse(args[1]);
const payload = JSON.parse(args[2]);

// Run the fetch
fetchJobs(url, headers, payload);
