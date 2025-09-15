#!/bin/bash

# Setup external monitoring for AppliFlow scheduler
# This script adds a cron job to monitor the scheduler every 30 minutes

MONITOR_SCRIPT="/Users/mattcieslak/test-applyai/monitor-scheduler.sh"
CRON_JOB="*/30 * * * * $MONITOR_SCRIPT"

echo "Setting up AppliFlow scheduler monitoring..."

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "✅ Monitoring cron job already exists"
else
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Added monitoring cron job: runs every 30 minutes"
fi

echo "📋 Current crontab:"
crontab -l | grep "$MONITOR_SCRIPT" || echo "  (no monitoring jobs found)"

echo ""
echo "🔧 Manual commands:"
echo "  Test monitor:     $MONITOR_SCRIPT"
echo "  Remove monitoring: crontab -e  # then delete the line with monitor-scheduler.sh"
echo "  View logs:        tail -f /Users/mattcieslak/test-applyai/storage/logs/scheduler_monitor.log"